<?php
/**
 * 蓝屏钙软件导航网 - 轻量速率限制
 *
 * 登录爆破与验证码暴破此前完全没有闸门。这里不引入新表（两套 schema 尚未同步，
 * 建表失败反而会让鉴权形同虚设），改用带排他锁的本地文件做固定窗口计数。
 * 多机部署应替换为 Redis 或数据库计数，接口保持不变即可。
 */
class RateLimiter {
    /**
     * 消耗一次配额。
     *
     * @param string $action   场景标识，如 admin-login / user-login / verify-code
     * @param string $identity 调用者标识（IP 或账号），由调用方决定语义
     * @param int    $maxAttempts 窗口内允许的次数
     * @param int    $windowSeconds 窗口长度
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public static function consume(string $action, string $identity, int $maxAttempts, int $windowSeconds): array {
        $file = self::slotFile($action, $identity);
        $now = time();

        $handle = $file === null ? false : @fopen($file, 'c+b');
        if (!$handle) {
            // 计数存储不可用时不阻断正常登录，但要留下痕迹供排查
            error_log('[RateLimiter] 无法写入计数文件，本次跳过限流: ' . $action);
            return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
        }

        flock($handle, LOCK_EX);
        $payload = stream_get_contents($handle);
        $state = is_string($payload) && $payload !== '' ? json_decode($payload, true) : null;

        if (!is_array($state) || ($state['window'] ?? 0) + $windowSeconds <= $now) {
            $state = ['window' => $now, 'count' => 0];
        }

        if ($state['count'] >= $maxAttempts) {
            $retryAfter = ($state['window'] + $windowSeconds) - $now;
            flock($handle, LOCK_UN);
            fclose($handle);
            return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(1, $retryAfter)];
        }

        $state['count']++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return ['allowed' => true, 'remaining' => max(0, $maxAttempts - $state['count']), 'retry_after' => 0];
    }

    /**
     * 只读查询当前窗口的用量，不消耗配额。
     * 「失败才计数」的闸门需要先判断是否已经超限，用 consume() 判断会把正常请求也算进去。
     *
     * @return array{count:int, allowed:bool, retry_after:int}
     */
    public static function peek(string $action, string $identity, int $maxAttempts, int $windowSeconds): array {
        $file = self::slotFile($action, $identity);
        $now = time();
        if ($file === null || !is_file($file)) {
            return ['count' => 0, 'allowed' => true, 'retry_after' => 0];
        }

        $handle = @fopen($file, 'c+b');
        if (!$handle) {
            return ['count' => 0, 'allowed' => true, 'retry_after' => 0];
        }
        flock($handle, LOCK_SH);
        $payload = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        $state = is_string($payload) && $payload !== '' ? json_decode($payload, true) : null;
        if (!is_array($state) || ($state['window'] ?? 0) + $windowSeconds <= $now) {
            return ['count' => 0, 'allowed' => true, 'retry_after' => 0];
        }
        $count = (int) ($state['count'] ?? 0);
        return [
            'count' => $count,
            'allowed' => $count < $maxAttempts,
            'retry_after' => $count >= $maxAttempts ? max(1, ($state['window'] + $windowSeconds) - $now) : 0,
        ];
    }

    /** 操作成功后清零，避免同一出口 IP 的历史失败拖累正常用户。 */
    public static function clear(string $action, string $identity): void {
        $file = self::slotFile($action, $identity);
        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * 只认 REMOTE_ADDR。X-Forwarded-For 是客户端可伪造的头，拿它当身份等于没有限流；
     * 若站点部署在反向代理之后，请在代理层还原真实 IP（或让 REMOTE_ADDR 就是真实 IP）。
     */
    public static function clientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) && $ip !== '' ? $ip : 'unknown-ip';
    }

    private static function slotFile(string $action, string $identity): ?string {
        $dir = sys_get_temp_dir() . '/softnav_rate';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }
        return $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $action) . '-' . sha1($identity) . '.json';
    }
}
