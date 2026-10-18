<?php
/**
 * 开放 API 的统一响应层。
 *
 * 一个信封格式贯穿所有端点：成功 {success:true,data,meta}，失败 {success:false,error:{code,message,...}}。
 * 端点只管业务，状态码、安全响应头与调用日志都在这里收口 ——
 * 日志由 ok()/fail() 自动写，端点忘了记也不会出现「查不到谁动过」。
 */

class Api
{
    public const VERSION = 'v1';
    public const RATE_READ = 120;      // 每分钟每把密钥的读请求上限
    public const RATE_WRITE = 30;
    public const RATE_AUTH_FAIL = 10;  // 每 IP 在 10 分钟内允许的凭据错误次数
    public const AUTH_WINDOW = 600;

    private static ?array $context = null;
    private static bool $logged = false;

    public static function setContext(array $context): void
    {
        self::$context = $context + ['endpoint' => '', 'key_id' => null, 'key_prefix' => null];
    }

    public static function context(): ?array
    {
        return self::$context;
    }

    public static function setEndpoint(string $endpoint): void
    {
        self::$context['endpoint'] = $endpoint;
    }

    /** 统一的 JSON 出口：所有响应都不缓存，并显式声明不是 HTML（防被当成页面渲染） */
    public static function send(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            header('X-API-Version: ' . self::VERSION);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function ok(array $data = [], array $meta = []): void
    {
        self::log('ok', $meta['detail'] ?? '', 200);
        self::send(['success' => true, 'data' => $data, 'meta' => $meta + ['version' => self::VERSION]]);
    }

    /** 失败也要有稳定的 code，App 端才能按码数分支，而不是去匹配中文文案 */
    public static function fail(string $code, string $message, int $status = 400, array $extra = []): void
    {
        self::log($status >= 500 ? 'error' : 'denied', $message, $status);
        self::send(['success' => false, 'error' => ['code' => $code, 'message' => $message] + $extra], $status);
    }

    /** 参数来源：JSON 请求体 > 表单 > 查询串。JSON 优先，App 端直接发 body 最自然。 */
    public static function input(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $body = [];
        $raw = file_get_contents('php://input') ?: '';
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($raw !== '' && str_contains($type, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } elseif ($raw !== '' && $body === [] && empty($_POST)) {
            // 有些 HTTP 库默认发 form 但没带 Content-Type，这里兜一下，别让人卡在「参数缺失」上
            parse_str($raw, $parsed);
            $body = is_array($parsed) ? $parsed : [];
        }
        return $cached = $body + $_POST + $_GET;
    }

    public static function param(string $key, $default = null)
    {
        $input = self::input();
        return array_key_exists($key, $input) && $input[$key] !== null ? $input[$key] : $default;
    }

    public static function stringParam(string $key, string $default = '', int $maxLength = 5000): string
    {
        return mb_substr(trim((string) self::param($key, $default)), 0, $maxLength);
    }

    public static function intParam(string $key, int $default = 0): int
    {
        $value = self::param($key, $default);
        return is_array($value) ? $default : (int) $value;
    }

    public static function boolParam(string $key): bool
    {
        $value = self::param($key, false);
        if (is_bool($value)) {
            return $value;
        }
        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    /** 写端点只接受 POST/PUT，避免 GET 被浏览器预取、被爬虫误触发 */
    public static function requireWriteMethod(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            self::fail('method_not_allowed', '这个端点需要 POST/PUT/PATCH/DELETE，当前是 ' . $method, 405,
                ['allowed' => ['POST', 'PUT', 'PATCH', 'DELETE']]);
        }
    }

    public static function page(): int
    {
        return max(1, self::intParam('page', 1));
    }

    public static function per(int $default = 20, int $max = 100): int
    {
        $per = self::intParam('per_page', $default);
        return max(1, min($max, $per > 0 ? $per : $default));
    }

    /** 把敏感字段摘掉后再返回：设置的读取走这里，密钥类字段永远不出库 */
    public static function omit(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            unset($row[$field]);
        }
        return $row;
    }

    public static function logInputError(string $field): void
    {
        // 参数错误只告诉 App 哪个字段不对；具体值不回显，避免把用户提交的内容原样吐回日志
        self::fail('invalid_param', '缺少或非法的参数：' . $field, 422, ['field' => $field]);
    }

    private static function log(string $result, string $detail, int $status): void
    {
        if (self::$logged || self::$context === null) {
            return;
        }
        self::$logged = true;
        ApiLog::write(
            self::$context['key_id'] ?? null,
            self::$context['key_prefix'] ?? null,
            (string) (self::$context['endpoint'] ?? 'unknown'),
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $status,
            $result,
            $detail
        );
    }
}
