<?php
/**
 * 蓝屏钙软件导航网 - 鉴权与安全模块
 */

class Auth {
    /** 仅在尚未设置管理员密码时（全新安装）生效的引导口令，设置过真实密码后即失效。 */
    const BOOTSTRAP_PASSWORD = 'admin123';

    /** 勾选「记住我」时会话 cookie 的生命期：30 天。 */
    private const REMEMBER_SESSION_SECONDS = 2592000;

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $o = self::cookieOptions();
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $o['path'],
                'domain' => $o['domain'],
                'secure' => $o['secure'],
                'httponly' => $o['httponly'],
                'samesite' => $o['samesite'],
            ]);
            session_start();
        }
    }

    /**
     * 会话 cookie 的统一参数（setcookie 的选项形态）。
     * SameSite=Lax 让跨站 POST 带不上会话 cookie（CSRF 的第一道防线），HttpOnly 防脚本窃取，
     * Secure 只在 HTTPS 下启用，否则本地 http 访问会直接丢 cookie。
     *
     * @return array<string, mixed>
     */
    private static function cookieOptions(int $expires = 0): array {
        return [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public static function check(): bool {
        self::start();
        if (!empty($_SESSION['admin_user'])) {
            return true;
        }
        if (!empty($_SESSION['frontend_user_role']) && $_SESSION['frontend_user_role'] === 'admin') {
            return true;
        }
        return false;
    }

    public static function user(): ?array {
        self::start();
        return $_SESSION['admin_user'] ?? null;
    }

    public static function login(string $username, string $password): bool {
        self::start();
        $verified = self::verifyAdminCredentials($username, $password);
        if ($verified === null) {
            return false;
        }

        if ($verified['kind'] === 'user' && is_array($verified['user'])) {
            self::loginUser($verified['user']);
            return true;
        }

        $_SESSION['admin_user'] = [
            'username' => $verified['username'],
            'logged_at' => time()
        ];
        session_regenerate_id(true);
        return true;
    }

    /**
     * 纯凭据校验，不建会话、不写 cookie。
     *
     * 网页后台与开放 API 走同一份规则，避免「API 那条路少做一项检查」这种漂移：
     * users 表里的管理员优先，其次是配置里那套 admin_username / admin_password_hash；
     * 配置了真实密码后，引导口令不再有任何特权。
     *
     * @return array{kind:string,username:string,user:?array}|null
     */
    public static function verifyAdminCredentials(string $username, string $password): ?array {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }
        $storedUser = Setting::get('admin_username', 'admin');

        $authRes = User::authenticate($username, $password);
        if (!empty($authRes['success'])) {
            $u = $authRes['user'];
            if ($u['role'] === 'admin' || strtolower((string) $u['username']) === strtolower((string) $storedUser)) {
                return ['kind' => 'user', 'username' => (string) $u['username'], 'user' => $u];
            }
        }

        if ($username !== $storedUser) {
            return null;
        }

        $storedHash = Setting::get('admin_password_hash', '');
        // 已设置过密码时只认 hash；引导口令不再有任何特权
        $valid = !$storedHash
            ? ($password === self::BOOTSTRAP_PASSWORD)
            : password_verify($password, $storedHash);

        return $valid ? ['kind' => 'settings', 'username' => $username, 'user' => null] : null;
    }

    public static function logout(): void {
        self::start();
        unset($_SESSION['admin_user']);
        self::logoutUser();
    }

    public static function requireAuth(): void {
        if (!self::check()) {
            header('Location: ' . Url::target('?r=auth/login&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '?r=admin/dashboard')));
            exit;
        }
    }

    /** 同一次请求里 checkUser() 与 currentUser() 都会被调多次，缓存掉那一次查询 */
    private static ?array $frontUser = null;
    private static bool $frontUserLoaded = false;

    /**
     * 前台登录用户。
     *
     * 只看会话标记是不够的：重装、清库、删号之后，浏览器里那个旧 PHPSESSID 还指向一个
     * 已经不存在的 id，页面会走进「已登录」分支却拿不到任何数据 —— 表现就是右上角头像和
     * 昵称/邮箱一片空白。这里以库里的真实行为准，查不到就当没登录。
     */
    public static function currentUser(): ?array {
        self::start();
        if (self::$frontUserLoaded) {
            return self::$frontUser;
        }
        self::$frontUserLoaded = true;
        $id = $_SESSION['frontend_user_id'] ?? null;
        $user = $id ? User::findById((int) $id) : null;
        if (!$user) {
            if ($id) {
                unset(
                    $_SESSION['frontend_user_id'],
                    $_SESSION['frontend_user_name'],
                    $_SESSION['frontend_user_email'],
                    $_SESSION['frontend_user_role'],
                    $_SESSION['frontend_user_avatar']
                );
            }
            self::$frontUser = null;
            return null;
        }
        self::$frontUser = $user;
        return $user;
    }

    public static function checkUser(): bool {
        return self::currentUser() !== null;
    }

    public static function loginUser(array $user, bool $remember = false): void {
        self::start();
        self::$frontUser = $user;
        self::$frontUserLoaded = true;
        $_SESSION['frontend_user_id'] = (int) $user['id'];
        $_SESSION['frontend_user_name'] = $user['username'];
        $_SESSION['frontend_user_email'] = $user['email'];
        $_SESSION['frontend_user_role'] = $user['role'] ?? 'user';
        $_SESSION['frontend_user_avatar'] = $user['avatar_url'] ?? '';

        // 如果用户是管理员角色，同步赋予后台管理会话权限
        $adminUser = Setting::get('admin_username', 'admin');
        if (($user['role'] ?? '') === 'admin' || strtolower($user['username']) === strtolower($adminUser)) {
            $_SESSION['admin_user'] = [
                'username' => $user['username'],
                'user_id' => $user['id'],
                'logged_at' => time()
            ];
        }

        // 换号再落盘：不轮换的话，攻击者预先埋下的会话 ID 会直接升级为已认证会话
        session_regenerate_id(true);

        if ($remember) {
            // 「记住我」就是延长这次会话 cookie 的生命期，不再另发一枚服务端从不校验的令牌
            setcookie(session_name(), session_id(), self::cookieOptions(time() + self::REMEMBER_SESSION_SECONDS));
        }
    }

    public static function logoutUser(): void {
        self::start();
        self::$frontUser = null;
        self::$frontUserLoaded = true;
        unset(
            $_SESSION['frontend_user_id'],
            $_SESSION['frontend_user_name'],
            $_SESSION['frontend_user_email'],
            $_SESSION['frontend_user_role'],
            $_SESSION['frontend_user_avatar'],
            $_SESSION['admin_user']
        );
        // 重新生成会话 ID 并让 cookie 回到浏览器生命期，撤掉「记住我」留下的长效凭据
        session_regenerate_id(true);
    }

    public static function requireUser(): void {
        if (!self::checkUser()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => '请先登录账号']);
                exit;
            }
            header('Location: ' . Url::target('?r=auth/login&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
            exit;
        }
    }

    public static function csrfToken(): string {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool {
        self::start();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /** 表单里直接铺一个隐藏域。 */
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** 请求携带的 token：表单字段优先，其次 AJAX 的 X-CSRF-Token 头。 */
    public static function tokenFromRequest(): ?string {
        $fromPost = $_POST['csrf_token'] ?? null;
        if (is_string($fromPost) && $fromPost !== '') {
            return $fromPost;
        }
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($header) && $header !== '' ? $header : null;
    }

    public static function verifyRequestCsrf(): bool {
        return self::verifyCsrf(self::tokenFromRequest());
    }

    /**
     * 把用户传来的跳转地址收敛为站内地址。
     *
     * 登录/注册/退出这些地方的 redirect 参数来自 GET/POST 甚至 Referer，直接塞进
     * Location 就是开放重定向（可用于骗取凭据与跳转钓鱼站）。这里只接受站内形态：
     * `?r=...` 或 `/path`，带协议、协议相对(`//evil.com`)、反斜杠变体一律回落到默认页。
     */
    public static function internalRedirect(?string $target, string $fallback = '?r=home'): string {
        $clean = trim((string) $target);

        if ($clean === '') {
            return $fallback;
        }
        // 带控制字符（换行等）的跳转参数本身就不可信，直接放弃而不是剥掉后照用
        if (preg_match('/[\x00-\x1F\x7F]/', $clean)) {
            return $fallback;
        }

        // 协议相对写法 //evil.com 与反斜杠变体 /\evil.com
        if (str_starts_with($clean, '//') || str_starts_with($clean, '/\\')) {
            return $fallback;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $clean)) {
            // 只接受指向本站自身的绝对地址（Referer 就属于这种），并归一为相对路径
            $parts = parse_url($clean);
            $host = strtolower((string)($parts['host'] ?? ''));
            $ownHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            if ($host === '' || $ownHost === '' || $host !== $ownHost) {
                return $fallback;
            }
            $path = (string)($parts['path'] ?? '');
            return $path . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '');
        }

        if ($clean[0] === '?' || $clean[0] === '/') {
            return $clean;
        }

        return $fallback;
    }
}

