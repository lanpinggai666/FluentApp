<?php
/**
 * 蓝屏钙软件导航网 - 第三方 OAuth2 认证服务 (Microsoft, Google, GitHub)
 */

class OAuthService {

    /**
     * 获取指定平台的授权重定向 URL
     */
    public static function getAuthorizeUrl(string $provider, ?string $redirectAfter = null): ?string {
        $provider = strtolower($provider);
        $state = self::generateState($provider, $redirectAfter);
        $callbackUri = self::getCallbackUrl($provider);

        switch ($provider) {
            case 'microsoft':
                $clientId = trim(Setting::get('oauth_ms_client_id', ''));
                if (empty($clientId)) return null;
                $params = [
                    'client_id' => $clientId,
                    'response_type' => 'code',
                    'redirect_uri' => $callbackUri,
                    'response_mode' => 'query',
                    'scope' => 'openid profile email User.Read',
                    'state' => $state
                ];
                return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($params);

            case 'google':
                $clientId = trim(Setting::get('oauth_google_client_id', ''));
                if (empty($clientId)) return null;
                $params = [
                    'client_id' => $clientId,
                    'redirect_uri' => $callbackUri,
                    'response_type' => 'code',
                    'scope' => 'openid email profile',
                    'access_type' => 'online',
                    'state' => $state
                ];
                return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

            case 'github':
                $clientId = trim(Setting::get('oauth_github_client_id', ''));
                if (empty($clientId)) return null;
                $params = [
                    'client_id' => $clientId,
                    'redirect_uri' => $callbackUri,
                    'scope' => 'read:user user:email',
                    'state' => $state
                ];
                return 'https://github.com/login/oauth/authorize?' . http_build_query($params);

            default:
                return null;
        }
    }

    /**
     * 处理 OAuth 回调，获取第三方用户信息
     */
    public static function handleCallback(string $provider, string $code, string $state): array {
        $provider = strtolower($provider);
        if (!self::verifyState($state, $provider)) {
            return ['success' => false, 'error' => '安全验证失败 (CSRF State 异常)，请重试'];
        }

        switch ($provider) {
            case 'microsoft':
                return self::handleMicrosoftCallback($code);
            case 'google':
                return self::handleGoogleCallback($code);
            case 'github':
                return self::handleGithubCallback($code);
            default:
                return ['success' => false, 'error' => '不支持的第三方登录提供商'];
        }
    }

    /**
     * 校验 Google GIS (Google Identity Services) JS 弹窗返回的 ID Token
     */
    public static function verifyGoogleIdToken(string $idToken): array {
        $clientId = trim(Setting::get('oauth_google_client_id', ''));
        if (empty($clientId)) {
            return ['success' => false, 'error' => 'Google 登录服务未在系统后台配置 Client ID'];
        }

        // 调用 Google 官方 tokeninfo 接口进行安全有效性校验
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $res = self::httpGet($url);
        if (!$res) {
            return ['success' => false, 'error' => '无法与 Google 认证服务器通信，请检查网络'];
        }

        $data = json_decode($res, true);
        if (empty($data) || empty($data['sub'])) {
            return ['success' => false, 'error' => 'Google 凭证无效或已过期'];
        }

        // 校验 aud 是否为本站注册的 Client ID
        if (!empty($data['aud']) && $data['aud'] !== $clientId) {
            return ['success' => false, 'error' => 'Google 凭证受众不匹配 (Audience Mismatch)'];
        }

        return [
            'success' => true,
            'user' => [
                'provider' => 'google',
                'id' => $data['sub'],
                'email' => $data['email'] ?? '',
                'emailVerified' => filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name' => $data['name'] ?? $data['given_name'] ?? 'Google 用户',
                'avatar' => $data['picture'] ?? ''
            ]
        ];
    }

    // ------------------------------------------------------------------------
    // 内部提供商处理细节
    // ------------------------------------------------------------------------

    private static function handleMicrosoftCallback(string $code): array {
        $clientId = trim(Setting::get('oauth_ms_client_id', ''));
        $clientSecret = trim(Setting::get('oauth_ms_client_secret', ''));
        $redirectUri = self::getCallbackUrl('microsoft');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'Microsoft OAuth 未在系统后台配置 Client ID 或 Secret'];
        }

        // 换取 Access Token
        $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri
        ];

        $tokenRes = self::httpPost($tokenUrl, $postData);
        $tokenJson = json_decode($tokenRes, true);

        if (empty($tokenJson['access_token'])) {
            $errDesc = $tokenJson['error_description'] ?? '获取 Microsoft Access Token 失败';
            return ['success' => false, 'error' => $errDesc];
        }

        $accessToken = $tokenJson['access_token'];

        // 获取用户信息
        $meRes = self::httpGet('https://graph.microsoft.com/v1.0/me', [
            'Authorization: Bearer ' . $accessToken
        ]);
        $me = json_decode($meRes, true);

        if (empty($me['id'])) {
            return ['success' => false, 'error' => '获取 Microsoft 用户信息失败'];
        }

        $email = $me['mail'] ?? $me['userPrincipalName'] ?? '';
        $name = $me['displayName'] ?? 'Microsoft 用户';

        return [
            'success' => true,
            'user' => [
                'provider' => 'microsoft',
                'id' => $me['id'],
                'email' => $email,
                // Microsoft Graph 的 /me 不返回邮箱验证状态，mail 与 userPrincipalName 都可能是未验证别名，
                // 因此一律按未验证处理：不做同邮箱自动关联，需密码登录后手动绑定
                'emailVerified' => false,
                'name' => $name,
                'avatar' => ''
            ]
        ];
    }

    private static function handleGoogleCallback(string $code): array {
        $clientId = trim(Setting::get('oauth_google_client_id', ''));
        $clientSecret = trim(Setting::get('oauth_google_client_secret', ''));
        $redirectUri = self::getCallbackUrl('google');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'Google OAuth 未在系统后台配置 Client ID 或 Secret'];
        }

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri
        ];

        $tokenRes = self::httpPost($tokenUrl, $postData);
        $tokenJson = json_decode($tokenRes, true);

        if (empty($tokenJson['access_token'])) {
            return ['success' => false, 'error' => $tokenJson['error_description'] ?? '获取 Google Access Token 失败'];
        }

        $userInfoRes = self::httpGet('https://openidconnect.googleapis.com/v1/userinfo', [
            'Authorization: Bearer ' . $tokenJson['access_token']
        ]);
        $info = json_decode($userInfoRes, true);

        if (empty($info['sub'])) {
            return ['success' => false, 'error' => '获取 Google 用户信息失败'];
        }

        return [
            'success' => true,
            'user' => [
                'provider' => 'google',
                'id' => $info['sub'],
                'email' => $info['email'] ?? '',
                'emailVerified' => !empty($info['email_verified']),
                'name' => $info['name'] ?? 'Google 用户',
                'avatar' => $info['picture'] ?? ''
            ]
        ];
    }

    private static function handleGithubCallback(string $code): array {
        $clientId = trim(Setting::get('oauth_github_client_id', ''));
        $clientSecret = trim(Setting::get('oauth_github_client_secret', ''));
        $redirectUri = self::getCallbackUrl('github');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'GitHub OAuth 未在系统后台配置 Client ID 或 Secret'];
        }

        $tokenUrl = 'https://github.com/login/oauth/access_token';
        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        ];

        $tokenRes = self::httpPost($tokenUrl, $postData, [
            'Accept: application/json',
            'User-Agent: BlueCalcium-Software-Nav'
        ]);
        $tokenJson = json_decode($tokenRes, true);

        if (empty($tokenJson['access_token'])) {
            return ['success' => false, 'error' => $tokenJson['error_description'] ?? '获取 GitHub Access Token 失败'];
        }

        $userRes = self::httpGet('https://api.github.com/user', [
            'Authorization: Bearer ' . $tokenJson['access_token'],
            'User-Agent: BlueCalcium-Software-Nav'
        ]);
        $userInfo = json_decode($userRes, true);

        if (empty($userInfo['id'])) {
            return ['success' => false, 'error' => '获取 GitHub 用户信息失败'];
        }

        $email = trim((string)($userInfo['email'] ?? ''));
        // /user 给出的邮箱不保证已验证，只有 /user/emails 里 verified=true 的地址才能用于自动关联本站账号
        $emailVerified = false;
        if ($email === '') {
            // 获取私有邮箱
            $emailsRes = self::httpGet('https://api.github.com/user/emails', [
                'Authorization: Bearer ' . $tokenJson['access_token'],
                'User-Agent: BlueCalcium-Software-Nav'
            ]);
            $emails = json_decode((string) $emailsRes, true);
            if (is_array($emails)) {
                $pick = null;
                foreach ([['primary', 'verified'], ['verified'], ['primary']] as $rule) {
                    foreach ($emails as $em) {
                        if (!is_array($em) || empty($em['email'])) {
                            continue;
                        }
                        $matched = true;
                        foreach ($rule as $flag) {
                            if (empty($em[$flag])) {
                                $matched = false;
                                break;
                            }
                        }
                        if ($matched) {
                            $pick = $em;
                            break;
                        }
                    }
                    if ($pick) {
                        break;
                    }
                }
                if ($pick) {
                    $email = trim((string) $pick['email']);
                    $emailVerified = !empty($pick['verified']);
                }
            }
        }

        return [
            'success' => true,
            'user' => [
                'provider' => 'github',
                'id' => (string) $userInfo['id'],
                'email' => $email,
                'emailVerified' => $emailVerified,
                'name' => $userInfo['name'] ?: $userInfo['login'],
                'avatar' => $userInfo['avatar_url'] ?? ''
            ]
        ];
    }

    // ------------------------------------------------------------------------
    // 状态令牌与工具函数
    // ------------------------------------------------------------------------

    /**
     * 本站 OAuth 回调地址。
     *
     * 优先使用后台显式配置的回调，其次 site_base_url —— 生产环境请把 site_base_url 填成
     * 真实站点地址，这样回调地址完全不依赖请求头。这里刻意不读 X-Forwarded-Proto：
     * 本站不跑在反向代理后面，那个头任何人都能伪造，只会让 https 站点生成出 http 回调。
     */
    public static function getCallbackUrl(string $provider): string {
        $custom = trim((string) Setting::get('oauth_' . strtolower($provider) . '_redirect_uri', ''));
        if ($custom !== '') {
            return $custom;
        }

        return Url::siteBase() . '/?r=auth/oauth-callback&provider=' . urlencode($provider);
    }

    private static function generateState(string $provider, ?string $redirectAfter = null): string {
        Auth::start();
        $token = bin2hex(random_bytes(16));
        unset($_SESSION['oauth_redirect_after']);
        $_SESSION['oauth_state_' . $provider] = [
            'token' => $token,
            'expires' => time() + 600,
            // 回跳目标先收敛成站内路径：这里来自 GET 参数与 Referer，原样用就是开放重定向
            'redirect' => Auth::internalRedirect($redirectAfter ?? ($_SERVER['HTTP_REFERER'] ?? null)),
        ];
        return $token;
    }

    /** 校验 state 并一次性消费：验过之后同一枚不能再被用来重放回调 */
    private static function verifyState(string $state, string $provider): bool {
        Auth::start();
        $key = 'oauth_state_' . $provider;
        $saved = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        if (!is_array($saved) || empty($saved['token']) || $state === '') {
            return false;
        }
        if (($saved['expires'] ?? 0) < time()) {
            return false;
        }
        if (!hash_equals((string) $saved['token'], $state)) {
            return false;
        }

        $_SESSION['oauth_redirect_after'] = (string) ($saved['redirect'] ?? '?r=home');
        return true;
    }

    public static function getRedirectAfter(string $provider): string {
        Auth::start();
        $url = (string) ($_SESSION['oauth_redirect_after'] ?? '?r=home');
        unset($_SESSION['oauth_redirect_after']);
        return Auth::internalRedirect($url);
    }

    private static function httpGet(string $url, array $headers = []): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt_array($ch, HttpTls::curlOptions());
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $res = curl_exec($ch);
            curl_close($ch);
            return $res !== false ? $res : null;
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => implode("\r\n", $headers)
            ]
        ];
        return @file_get_contents($url, false, stream_context_create($opts));
    }

    private static function httpPost(string $url, array $data, array $headers = []): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt_array($ch, HttpTls::curlOptions());
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $res = curl_exec($ch);
            curl_close($ch);
            return $res !== false ? $res : null;
        }

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n" . implode("\r\n", $headers),
                'content' => http_build_query($data),
                'timeout' => 15
            ]
        ];
        return @file_get_contents($url, false, stream_context_create($opts));
    }
}
