<?php
/**
 * 开放 API 的鉴权闸门。
 *
 * 每条 API 请求都要过四件事：密钥（后台生成、只存哈希）+ 管理员用户名 + 管理员口令 + 作用域。
 * 三件套少任何一项都是 401，不做「只带密钥也算通过」的宽松模式 —— 那等于把后台权限
 * 交给捡到一串字符串的任何人。
 */

class ApiAuth
{
    public static function guard(string $endpoint, string $scope = 'read'): void
    {
        Api::setContext(['endpoint' => $endpoint, 'key_id' => null, 'key_prefix' => null]);

        if (Setting::get('api_enabled', '1') !== '1') {
            Api::fail('api_disabled', '站点已关闭开放 API（后台【系统与AI设置】里的 api_enabled）', 503);
        }

        // 口令每次请求都要上传，明文传输等于把管理员账号送给路径上的任何观察者
        if (!self::transportSecure()) {
            Api::fail('insecure_transport', '必须通过 HTTPS 调用（当前是明文连接）。'
                . '内网调试可在后台设 api_allow_insecure=1，但请不要在正式站点上这么做。', 403);
        }

        $rawKey = self::presentedKey();
        if ($rawKey === '') {
            Api::fail('missing_key', '缺少请求头 X-Api-Key（或 Authorization: Bearer …）', 401);
        }

        $key = ApiKey::verify($rawKey);
        $prefix = substr($rawKey, 0, ApiKey::PREFIX_LENGTH);
        Api::setContext(['endpoint' => $endpoint, 'key_prefix' => $prefix,
            'key_id' => is_array($key) ? (int) $key['id'] : null]);

        if ($key === null) {
            // 区分「不存在」与「已吊销」，方便 App 端提示用户去后台重新生成
            $revoked = Database::fetch('SELECT id FROM api_keys WHERE key_prefix = :p AND revoked_at IS NOT NULL LIMIT 1',
                ['p' => $prefix]);
            Api::fail($revoked ? 'key_revoked' : 'key_invalid',
                $revoked ? '这把密钥已经在后台被吊销，请重新生成' : '密钥无效', 401);
        }

        // 限流放在作用域与凭据校验之前：配额判定只需要密钥前缀，
        // 让被拒的请求也一样消耗配额，探权限的人就讨不到便宜
        $limit = RateLimiter::consume($scope === 'write' ? 'api-write' : 'api-read', $prefix,
            $scope === 'write' ? Api::RATE_WRITE : Api::RATE_READ, 60);
        if (!$limit['allowed']) {
            Api::fail('rate_limited', '调用过于频繁，请 ' . $limit['retry_after'] . ' 秒后再试', 429,
                ['retry_after' => $limit['retry_after']]);
        }

        if (!ApiKey::hasScope($key, $scope)) {
            Api::fail('insufficient_scope', '这把密钥只有 ' . $key['scopes'] . ' 权限，调用不了需要 '
                . $scope . ' 的端点', 403, ['required' => $scope, 'granted' => explode(',', (string) $key['scopes'])]);
        }

        self::guardCredentials();

        ApiKey::touch((int) $key['id'], $key['last_used_at'] ?? null);
        Api::setContext(['endpoint' => $endpoint, 'key_id' => (int) $key['id'], 'key_prefix' => $prefix,
            'scopes' => explode(',', (string) $key['scopes']), 'key_name' => $key['name']]);
    }

    /** 凭据校验：先问限流器「这个 IP 是不是已经错太多次」，只有失败才计数 */
    private static function guardCredentials(): void
    {
        $ip = RateLimiter::clientIp();
        $peek = RateLimiter::peek('api-auth', $ip, Api::RATE_AUTH_FAIL, Api::AUTH_WINDOW);
        if (!$peek['allowed']) {
            Api::fail('auth_throttled', '管理员凭据连续错误太多次，请 ' . $peek['retry_after'] . ' 秒后再试', 429,
                ['retry_after' => $peek['retry_after']]);
        }

        $username = Api::stringParam('username', '', 190);
        if ($username === '') {
            $username = trim((string) ($_SERVER['HTTP_X_ADMIN_USERNAME'] ?? ''));
        }
        $password = (string) (Api::param('password', '') ?? '');
        if ($password === '') {
            $password = (string) ($_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '');
        }

        if ($username === '' || $password === '') {
            // 参数缺失不算「凭据错误」，不进失败计数，免得正常调试把自己锁住
            Api::fail('missing_credentials', '必须同时提交管理员用户名与口令（body 里的 username / password）', 401);
        }

        if (Auth::verifyAdminCredentials($username, $password) === null) {
            RateLimiter::consume('api-auth', $ip, Api::RATE_AUTH_FAIL, Api::AUTH_WINDOW);
            // 不在文案里区分「账号错」还是「密码错」，避免被用来枚举账号
            Api::fail('invalid_credentials', '管理员用户名或口令不正确', 401);
        }
        RateLimiter::clear('api-auth', $ip);
    }

    /**
     * 密钥只从请求头取。
     * 专门拒绝 query 里的 api_key：URL 会进 Nginx/Apache 访问日志、会进 Referer、也会被浏览器历史留下，
     * 让报错的人改投 header，比默默接受更安全。
     */
    private static function presentedKey(): string
    {
        if (isset($_GET['api_key']) || isset($_GET['key'])) {
            Api::fail('key_in_query', '请不要把密钥放在 URL 查询串里（会落进访问日志与 Referer），'
                . '改用请求头 X-Api-Key', 400);
        }
        $header = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
        if ($header !== '') {
            return $header;
        }
        $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')));
        if (stripos($auth, 'bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return '';
    }

    /** HTTPS 才算安全；127.0.0.1/localhost 上的本机调试放行；api_allow_insecure=1 显式豁免 */
    public static function transportSecure(): bool
    {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
        if ($https) {
            return true;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || str_starts_with($host, '127.')) {
            return true;
        }
        return Setting::get('api_allow_insecure', '0') === '1';
    }
}
