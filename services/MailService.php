<?php
/**
 * 轻量 SMTP 邮件服务，无第三方依赖。
 */
class MailService {
    public static function isEnabled(): bool {
        return Setting::get('smtp_enabled', '0') === '1';
    }

    /**
     * 统一的邮件正文外壳（Fluent 极简排版，兼容主流邮件客户端）。
     * @param string $inner 已转义的 HTML 内容片段
     */
    public static function wrap(string $inner): string {
        $siteName = htmlspecialchars((string)Setting::get('site_name', '软件导航'), ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f4f5f7;font-family:system-ui,-apple-system,\'Segoe UI\',Roboto,\'PingFang SC\',\'Microsoft YaHei\',sans-serif;color:#18181b;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="padding:20px 28px;border-bottom:1px solid #e5e7eb;background:#eff6fc;">'
            . '<div style="font-size:16px;font-weight:700;color:#0067b8;">' . $siteName . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:26px 28px;font-size:14px;line-height:1.75;color:#4b5563;">'
            . $inner
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px;border-top:1px solid #e5e7eb;background:#fafbfc;font-size:12px;color:#6b7280;">'
            . '本邮件由 ' . $siteName . ' 系统自动发送，请勿直接回复。 &copy; ' . $year
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /**
     * 读 SMTP 配置。
     *
     * 必须区分「没这个键」与「键存在但是空串」：Setting::get() 用的是 ?? 兜底，
     * 后台保存过一次但某项留空，库里就是 ''，?? 不会回默认值 ——
     * 于是 smtp_port 变成 0、smtp_from_email 变成空，发信直接报「配置不完整」，
     * 而设置页看上去一切正常。这里统一把空串当「未设置」处理。
     */
    private static function config(string $key, string $default = ''): string {
        $value = trim((string) Setting::get($key, ''));
        return $value !== '' ? $value : $default;
    }

    public static function send(string $to, string $subject, string $html): array {
        if (!self::isEnabled()) {
            return ['success' => false, 'error' => 'SMTP 未启用'];
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => '收件邮箱无效'];
        }

        if (!function_exists('stream_socket_client')) {
            return ['success' => false, 'error' => '服务器禁用了 stream_socket_client（PHP disable_functions），无法直连 SMTP'];
        }

        $host = self::config('smtp_host');
        $port = (int) self::config('smtp_port', '587');
        $encryption = strtolower(self::config('smtp_encryption', 'tls'));
        $username = self::config('smtp_username');
        $password = self::config('smtp_password');
        $fromEmail = self::config('smtp_from_email', $username);
        $fromName = self::config('smtp_from_name', self::config('site_name', '软件导航'));

        $missing = [];
        if ($host === '') { $missing[] = 'SMTP 服务器地址'; }
        if ($port < 1 || $port > 65535) { $missing[] = '端口（应为 1-65535，常见 465/587）'; }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $missing[] = '发件邮箱（留空时取 SMTP 用户名，所以现在 SMTP 用户名也必须是完整邮箱地址）';
        }
        if ($missing !== []) {
            return ['success' => false, 'error' => 'SMTP 配置不完整，缺少：' . implode('、', $missing)];
        }

        if (!in_array($encryption, ['', 'none', 'tls', 'ssl'], true)) {
            return ['success' => false, 'error' => 'SMTP 加密方式无效（只能是 STARTTLS / SSL 隐式 TLS / 不加密）'];
        }

        if (($encryption === 'tls' || $encryption === 'ssl') && !extension_loaded('openssl')) {
            return ['success' => false, 'error' => 'PHP 未加载 openssl 扩展，无法建立加密 SMTP 连接'];
        }

        $remote = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0;
        $errstr = '';
        // SMTP 的用户名密码只是 base64 编码，未经验证的 TLS 通道可以被中间人取名后重放，
        // 所以隐式 TLS 与 STARTTLS 两条路都带上证书校验与 peer_name
        $sslOptions = HttpTls::streamContext()['ssl'] + ['peer_name' => $host];
        // 连接 8s + 读 12s：FPM 默认 max_execution_time=30s，原来的 15+15 会在超时前先被 PHP 掐断，
        // 浏览器拿到的是白屏/500 而不是一句能看的错误
        $socket = @stream_socket_client(
            $remote . ':' . $port,
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => $sslOptions])
        );

        if (!$socket) {
            self::logFail($host, $port, $encryption, $fromEmail, '连接', ($errstr ?: (string) $errno));
            return ['success' => false, 'error' => 'SMTP 连接失败：' . ($errstr ?: (string) $errno) . '（目标 ' . $host . ':' . $port . '）'];
        }

        stream_set_timeout($socket, 12);

        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO ' . self::localHostname(), [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                stream_context_set($socket, ['ssl' => $sslOptions]);
                // 只接受 TLS 1.2+，SMTP 没有降级保护协议，留 1.0/1.1 等于白装
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    $lastError = error_get_last();
                    throw new RuntimeException('无法建立 TLS 加密连接' . (isset($lastError['message']) ? '：' . $lastError['message'] : '')
                        . '（服务器 ' . $host . ' 可能只支持 SSL 隐式 TLS，请把加密方式改成 SSL 并配对应端口）');
                }
                self::command($socket, 'EHLO ' . self::localHostname(), [250]);
            }

            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                // 认证失败最常见的是「授权码/应用专用密码」被当成了登录密码，文案里点出来
                try {
                    self::command($socket, base64_encode($password), [235]);
                } catch (Throwable $authError) {
                    throw new RuntimeException($authError->getMessage()
                        . '（SMTP 登录失败：多数服务商要求填「授权码/应用专用密码」，不是网页登录密码）');
                }
            }

            self::command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $messageIdHost = preg_replace('/[^a-z0-9.-]/i', '', $host) ?: 'localhost';
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
                'To: <' . $to . '>',
                'Subject: ' . $encodedSubject,
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageIdHost . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64'
            ];

            $body = chunk_split(base64_encode($html), 76, "\r\n");
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $payload = preg_replace('/(?m)^\./', '..', $payload);
            fwrite($socket, $payload . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);

            fclose($socket);
            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            self::logFail($host, $port, $encryption, $fromEmail, '协议交互', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * SMTP 失败必须能在服务器日志里查到，否则运维只能靠猜。
     * 只记主机/端口/加密方式/发件地址与服务器原话 —— 用户名、密码、正文一概不落。
     */
    private static function logFail(string $host, int $port, string $encryption, string $from, string $stage, string $reason): void {
        error_log(sprintf(
            '[SMTP] 发送失败 stage=%s target=%s:%d encryption=%s from=%s 原因：%s',
            $stage,
            $host,
            $port,
            $encryption !== '' ? $encryption : 'none',
            $from,
            mb_substr(str_replace(["\r", "\n"], ' ', $reason), 0, 400)
        ));
    }

    private static function command($socket, string $command, array $expectedCodes): string {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expectedCodes);
    }

    private static function expect($socket, array $expectedCodes): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('SMTP 服务器未返回响应（等待超时或连接被对方切断，请检查端口与加密方式是否匹配，如 465 用 SSL、587 用 STARTTLS）');
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP 响应异常：' . trim($response));
        }

        return $response;
    }

    private static function localHostname(): string {
        $hostname = gethostname();
        return is_string($hostname) && $hostname !== '' ? $hostname : 'localhost';
    }
}
