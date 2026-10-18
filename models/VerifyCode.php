<?php
/**
 * 蓝屏钙软件导航网 - 邮箱验证码服务与模型
 */

class VerifyCode {
    public const ACTION_REGISTER = 'register';
    public const ACTION_RESET_PASSWORD = 'reset_pwd';

    /**
     * 发送验证码
     */
    public static function sendCode(string $email, string $action = self::ACTION_REGISTER): array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => '邮箱格式无效'];
        }

        // 1. 防刷检查：60秒内不得重复发送
        $recent = Database::fetch(
            "SELECT created_at FROM email_verification_codes 
             WHERE email = :email AND action = :action AND is_used = 0 
             ORDER BY id DESC LIMIT 1",
            ['email' => $email, 'action' => $action]
        );

        if ($recent) {
            $lastTime = strtotime($recent['created_at']);
            if (time() - $lastTime < 60) {
                $wait = 60 - (time() - $lastTime);
                return ['success' => false, 'error' => "请等待 {$wait} 秒后再试"];
            }
        }

        // 2. 作废旧的未用验证码
        Database::update('email_verification_codes', ['is_used' => 2], 'email = :email AND action = :action AND is_used = 0', [
            'email' => $email,
            'action' => $action
        ]);

        // 3. 生成 6 位随机数字
        $code = (string)random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15分钟有效期

        Database::insert('email_verification_codes', [
            'email' => $email,
            'code' => $code,
            'action' => $action,
            'expires_at' => $expiresAt,
            'is_used' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 4. 发送邮件
        $siteName = Setting::get('site_name', '蓝屏钙软件导航');
        $actionName = $action === self::ACTION_RESET_PASSWORD ? '重置登录密码' : '用户注册';
        $subject = "【{$siteName}】{$actionName}验证码";
        
        $body = MailService::wrap(
            "<h2 style='margin: 0 0 16px; font-size: 1.125rem;'>您好！</h2>"
            . "<p>您正在【{$siteName}】进行<strong>{$actionName}</strong>操作。您的邮箱安全验证码为：</p>"
            . "<div style='margin: 20px 0; padding: 14px 24px; background: #f0fdf4; border: 1px dashed #86efac; border-radius: 8px; font-size: 1.75rem; font-weight: 800; letter-spacing: 4px; color: #15803d; text-align: center; display: inline-block;'>"
            . htmlspecialchars($code)
            . "</div>"
            . "<p style='color: #666; font-size: 0.8125rem;'>验证码 15 分钟内有效。如非本人操作，请忽略此邮件。</p>"
        );

        $mailRes = MailService::send($email, $subject, $body);

        if (!$mailRes['success']) {
            // 如果 SMTP 未配置或发送失败，在开发/本地环境下可优雅提示或记录
            return [
                'success' => false,
                'error' => '邮件发送失败，请检查邮件配置：' . ($mailRes['error'] ?? '未知错误')
            ];
        }

        return ['success' => true, 'message' => '验证码已发送至您的邮箱，请注意查收'];
    }

    /**
     * 校验验证码
     */
    public static function verify(string $email, string $code, string $action = self::ACTION_REGISTER): bool {
        $email = strtolower(trim($email));
        $code = trim($code);

        if (empty($code) || strlen($code) !== 6) {
            return false;
        }

        // 6 位数字、15 分钟有效期，若不限尝试次数就是可以硬撞的
        $attemptLimit = RateLimiter::consume('verify-code', $email . '|' . $action, 8, 900);
        if (!$attemptLimit['allowed']) {
            return false;
        }

        $row = Database::fetch(
            "SELECT id, expires_at FROM email_verification_codes 
             WHERE email = :email AND code = :code AND action = :action AND is_used = 0 
             ORDER BY id DESC LIMIT 1",
            ['email' => $email, 'code' => $code, 'action' => $action]
        );

        if (!$row) {
            return false;
        }

        if (strtotime($row['expires_at']) < time()) {
            return false; // 已过期
        }

        // 标记已使用
        Database::update('email_verification_codes', ['is_used' => 1], 'id = :id', ['id' => $row['id']]);
        RateLimiter::clear('verify-code', $email . '|' . $action);
        return true;
    }
}
