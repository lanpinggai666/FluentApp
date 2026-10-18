<?php
/**
 * 开放 API 的调用日志。
 *
 * 刻意不记录请求参数：口令、密钥、邮箱都在参数里，落库等于把凭据抄了一份给读到日志的人。
 * 只记端点、方法、结果与来源 IP，够用来回溯「密钥泄露后谁在什么时间动了什么」。
 */

class ApiLog
{
    public static function write(?int $keyId, ?string $prefix, string $endpoint, string $method,
        int $status, string $result, string $detail = ''): void
    {
        try {
            Database::insert('api_logs', [
                'api_key_id' => $keyId,
                'key_prefix' => $prefix !== null ? substr($prefix, 0, 16) : null,
                'endpoint' => substr($endpoint, 0, 120),
                'method' => strtoupper(substr($method, 0, 10)),
                'http_status' => $status,
                'result' => $result,
                'detail' => $detail !== '' ? mb_substr($detail, 0, 255) : null,
                'ip' => RateLimiter::clientIp(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            // 顺手做一次低概率清理，省得日志无限增长；不开 cron 也不会撑爆库
            if (random_int(1, 50) === 1) {
                self::prune(30);
            }
        } catch (Throwable $e) {
            // 日志写失败绝不能拖垮一次正常调用
            error_log('[SoftNav] api log failed: ' . $e->getMessage());
        }
    }

    /** @return array<int,array> */
    public static function recent(int $limit = 50, ?int $keyId = null): array
    {
        $limit = max(1, min(200, $limit));
        if ($keyId !== null) {
            return Database::fetchAll('SELECT * FROM api_logs WHERE api_key_id = :k ORDER BY id DESC LIMIT ' . $limit,
                ['k' => $keyId]);
        }
        return Database::fetchAll('SELECT * FROM api_logs ORDER BY id DESC LIMIT ' . $limit);
    }

    public static function prune(int $keepDays = 30): int
    {
        $bound = date('Y-m-d H:i:s', time() - $keepDays * 86400);
        return Database::delete('api_logs', 'created_at < :b', ['b' => $bound]);
    }

    public static function count(?string $result = null): int
    {
        if ($result === null) {
            return (int) Database::fetch('SELECT COUNT(*) AS c FROM api_logs')['c'];
        }
        return (int) Database::fetch('SELECT COUNT(*) AS c FROM api_logs WHERE result = :r', ['r' => $result])['c'];
    }
}
