<?php
/**
 * 蓝屏钙软件导航网 - 用户与三方认证模型
 */

class User {
    // 默认允许注册的常用主流邮箱后缀列表 (防范临时/垃圾邮箱)
    public const DEFAULT_ALLOWED_DOMAINS = [
        'qq.com',
        'foxmail.com',
        '163.com',
        '126.com',
        'yeah.net',
        'sina.com',
        'sina.cn',
        'sohu.com',
        'aliyun.com',
        '139.com',
        '189.com',
        'wo.cn',
        'gmail.com',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'yahoo.com',
        'yahoo.com.cn',
        'proton.me',
        'protonmail.com',
    ];

    /**
     * 获取允许注册的邮箱域名列表
     */
    public static function getAllowedDomains(): array {
        $custom = Setting::get('allowed_email_domains', '');
        if (!empty($custom)) {
            $parts = array_filter(array_map('trim', explode(',', strtolower($custom))));
            if (!empty($parts)) {
                return array_unique(array_merge(self::DEFAULT_ALLOWED_DOMAINS, $parts));
            }
        }
        return self::DEFAULT_ALLOWED_DOMAINS;
    }

    /**
     * 校验邮箱后缀是否为允许的常用邮箱
     */
    public static function isAllowedEmail(string $email): bool {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];
        $allowed = self::getAllowedDomains();

        if (in_array($domain, $allowed, true)) {
            return true;
        }

        if (str_ends_with($domain, '.edu') || str_ends_with($domain, '.edu.cn')) {
            return true;
        }

        return false;
    }

    /**
     * 根据 ID 获取用户
     */
    public static function findById(int $id): ?array {
        return Database::fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
    }

    /**
     * 根据邮箱查找用户
     */
    public static function findByEmail(string $email): ?array {
        return Database::fetch("SELECT * FROM users WHERE LOWER(email) = LOWER(:email)", ['email' => trim($email)]);
    }

    /**
     * 根据昵称查找用户
     */
    public static function findByUsername(string $username): ?array {
        return Database::fetch("SELECT * FROM users WHERE LOWER(username) = LOWER(:u)", ['u' => trim($username)]);
    }

    /**
     * 根据账号 (邮箱或昵称) 查找用户
     */
    public static function findByAccount(string $account): ?array {
        $account = trim($account);
        if (empty($account)) return null;
        if (str_contains($account, '@')) {
            return self::findByEmail($account);
        }
        return self::findByUsername($account);
    }

    /**
     * 注册新用户
     * 昵称要求：纯字母或字母+数字 (3-20 位)，无敏感词
     */
    public static function register(string $username, string $email, string $password): array {
        $username = trim($username);
        $email = strtolower(trim($email));

        // 昵称规则：仅限纯英文字母或字母+数字，长度 3-20，不能为纯数字
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) || !preg_match('/[a-zA-Z]/', $username)) {
            return ['success' => false, 'error' => '昵称需为英文字母或字母与数字组合（3-20字符）'];
        }

        if (FilterService::containsBanned($username)) {
            return ['success' => false, 'error' => '昵称包含敏感词，请换一个'];
        }

        if (self::findByUsername($username)) {
            return ['success' => false, 'error' => '该昵称已被使用，请换一个'];
        }

        if (!self::isAllowedEmail($email)) {
            return ['success' => false, 'error' => '邮箱后缀不符合规范，请使用常见常用邮箱'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'error' => '密码长度不能少于 6 位'];
        }

        if (self::findByEmail($email)) {
            return ['success' => false, 'error' => '该邮箱已被注册，请直接登录'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = Database::insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash,
            'avatar_url' => '',
            'role' => 'user',
            'status' => 'active',
            'last_login_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $user = self::findById($userId);

        // 发送欢迎站内信
        Notification::send($userId, '欢迎加入蓝屏钙软件导航', '欢迎注册！您可以在个人中心查看您提交的软件、评价与最新通知。', 'system');

        return ['success' => true, 'user' => $user];
    }

    /**
     * 用户账号密码登录 (支持邮箱或昵称登录，支持封禁拦截)
     */
    public static function authenticate(string $account, string $password): array {
        $user = self::findByAccount($account);
        if (!$user) {
            $adminUser = Setting::get('admin_username', 'admin');
            $adminHash = Setting::get('admin_password_hash', '');
            if (strtolower($account) === strtolower($adminUser)) {
                // 与 Auth::login 同一策略：有 hash 只认 hash，没有 hash 才接受引导口令
                $valid = !$adminHash
                    ? ($password === Auth::BOOTSTRAP_PASSWORD)
                    : password_verify($password, $adminHash);
                if ($valid) {
                    $now = date('Y-m-d H:i:s');
                    // email 列有唯一约束：写死 admin@local.host 会在管理员改名后与既有行撞键，
                    // 把一次正常登录变成未捕获异常（500）
                    $email = strtolower($adminUser) . '@local.host';
                    if (self::findByEmail($email)) {
                        $email = strtolower($adminUser) . '-' . bin2hex(random_bytes(4)) . '@local.host';
                    }
                    $uid = Database::insert('users', [
                        'username' => $adminUser,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => 'admin',
                        'status' => 'active',
                        'badges' => json_encode(['超级管理员'], JSON_UNESCAPED_UNICODE),
                        'last_login_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                    $user = self::findById($uid);
                    return ['success' => true, 'user' => $user];
                }
            }
            return ['success' => false, 'error' => '账号或密码错误'];
        }

        // 检查封禁状态
        if (!empty($user['is_banned'])) {
            $reason = !empty($user['ban_reason']) ? '（原因：' . $user['ban_reason'] . '）' : '';
            return ['success' => false, 'error' => '您的账号已被永久封禁' . $reason];
        }

        if (!empty($user['banned_until']) && strtotime($user['banned_until']) > time()) {
            $reason = !empty($user['ban_reason']) ? '（原因：' . $user['ban_reason'] . '）' : '';
            return ['success' => false, 'error' => '账号已被封禁至 ' . $user['banned_until'] . $reason];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => '账号已被停用'];
        }

        if (empty($user['password_hash'])) {
            return ['success' => false, 'error' => '该账号使用第三方登录，请点击下方对应平台快捷登录'];
        }

        $isValid = password_verify($password, $user['password_hash']);

        if ($isValid) {
            Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
            $user['last_login_at'] = date('Y-m-d H:i:s');
            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'error' => '账号或密码错误'];
    }

    /**
     * 重置密码
     */
    public static function resetPassword(string $email, string $newPassword): array {
        $user = self::findByEmail($email);
        if (!$user) {
            return ['success' => false, 'error' => '未找到该邮箱对应的账号'];
        }
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => '新密码长度不能少于 6 位'];
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        Database::update('users', [
            'password_hash' => $hash,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $user['id']]);

        Notification::send($user['id'], '登录密码已重置', '您的账号密码于 ' . date('Y-m-d H:i:s') . ' 成功重置。如非本人操作请及时处理。', 'system');
        return ['success' => true, 'message' => '密码已成功重置，请使用新密码登录'];
    }

    /**
     * 第三方 OAuth 用户创建或关联登录
     */
    public static function findOrCreateFromOAuth(
        string $provider,
        string $providerUserId,
        string $email,
        string $name,
        string $avatar = '',
        bool $emailVerified = false
    ): array {
        $provider = strtolower(trim($provider));
        $providerUserId = trim($providerUserId);
        $email = strtolower(trim($email));
        $name = trim($name) ?: ($provider . '_user');

        // 1. 检查是否存在已绑定的第三方授权记录
        $oauthRecord = Database::fetch(
            "SELECT * FROM oauth_accounts WHERE provider = :p AND provider_user_id = :puid",
            ['p' => $provider, 'puid' => $providerUserId]
        );

        if ($oauthRecord) {
            $user = self::findById((int) $oauthRecord['user_id']);
            if ($user && $user['status'] === 'active') {
                if (!empty($user['is_banned']) || (!empty($user['banned_until']) && strtotime($user['banned_until']) > time())) {
                    return ['success' => false, 'error' => '该账号已被封禁'];
                }
                Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
                if (empty($user['avatar_url']) && !empty($avatar)) {
                    Database::update('users', ['avatar_url' => $avatar], 'id = :id', ['id' => $user['id']]);
                    $user['avatar_url'] = $avatar;
                }
                return ['success' => true, 'user' => $user];
            }
        }

        // 2. 同邮箱本站用户的自动关联。只有第三方明确声明「邮箱已验证」时才允许：
        //    否则攻击者只要在第三方平台上把账号邮箱设成受害者的本站注册邮箱，
        //    用该平台登录一次就被自动绑到了受害者的本站账号上。
        $user = null;
        if ($email !== '') {
            $sameEmailUser = self::findByEmail($email);
            if ($sameEmailUser && !$emailVerified) {
                return [
                    'success' => false,
                    'error' => '该邮箱已有本站账号，但 ' . $provider . ' 未向我们确认邮箱归属。请先用密码登录，再到个人中心绑定此第三方账号。',
                ];
            }
            $user = $emailVerified ? $sameEmailUser : null;
        }

        if (!$user) {
            // 生成合规合法的初始英文字母+数字用户名
            $safeName = preg_replace('/[^a-zA-Z0-9]/', '', $name);
            if (strlen($safeName) < 3) {
                $safeName = 'user' . substr(md5($providerUserId), 0, 8);
            }
            if (self::findByUsername($safeName)) {
                $safeName .= substr(md5(uniqid()), 0, 4);
            }

            $fallbackEmail = !empty($email) ? $email : ($provider . '_' . substr(md5($providerUserId), 0, 10) . '@oauth.local');
            $userId = Database::insert('users', [
                'username' => $safeName,
                'email' => $fallbackEmail,
                'password_hash' => null,
                'avatar_url' => $avatar,
                'role' => 'user',
                'status' => 'active',
                'last_login_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $user = self::findById($userId);
            Notification::send($userId, '欢迎加入蓝屏钙软件导航', '您已通过 ' . ucfirst($provider) . ' 快捷登录创建账号。', 'system');
        } else {
            Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
            if (empty($user['avatar_url']) && !empty($avatar)) {
                Database::update('users', ['avatar_url' => $avatar], 'id = :id', ['id' => $user['id']]);
                $user['avatar_url'] = $avatar;
            }
        }

        // 3. 记录或更新 OAuth 绑定
        if ($oauthRecord) {
            Database::update('oauth_accounts', [
                'user_id' => $user['id'],
                'provider_email' => $email,
                'provider_name' => $name,
                'provider_avatar' => $avatar,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $oauthRecord['id']]);
        } else {
            Database::insert('oauth_accounts', [
                'user_id' => $user['id'],
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'provider_email' => $email,
                'provider_name' => $name,
                'provider_avatar' => $avatar,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        return ['success' => true, 'user' => $user];
    }

    /**
     * 获取当前登录的用户信息
     */
    public static function current(): ?array {
        Auth::start();
        $userId = $_SESSION['frontend_user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        return self::findById((int) $userId);
    }

    /**
     * 获取用户绑定的第三方平台
     */
    public static function getOAuthAccounts(int $userId): array {
        return Database::fetchAll("SELECT * FROM oauth_accounts WHERE user_id = :uid", ['uid' => $userId]);
    }

    /**
     * 获取用户荣誉/身份标签数组
     */
    public static function getBadges(array $user): array {
        if (empty($user['badges'])) return [];
        $decoded = json_decode((string)$user['badges'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 设置用户荣誉标签
     */
    public static function setBadges(int $userId, array $badges): bool {
        $val = !empty($badges) ? json_encode(array_values(array_unique(array_filter($badges))), JSON_UNESCAPED_UNICODE) : null;
        $updated = Database::update('users', ['badges' => $val, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        Notification::send($userId, '用户标签已更新', '管理员已更新了您的身份标签：' . implode('、', $badges), 'badge');
        return $updated >= 0;
    }

    /**
     * 处罚用户 (封禁 / 限制权力)
     */
    public static function punish(int $userId, bool $permanent, ?string $bannedUntil, ?string $reason, array $restrictedPermissions): bool {
        $target = self::findById($userId);
        if (!$target || ($target['role'] ?? '') === 'admin') {
            return false;
        }

        $res = Database::update('users', [
            'is_banned' => $permanent ? 1 : 0,
            'banned_until' => (!$permanent && $bannedUntil) ? $bannedUntil : null,
            'ban_reason' => trim((string)$reason) ?: null,
            'restricted_permissions' => !empty($restrictedPermissions) ? json_encode(array_values($restrictedPermissions), JSON_UNESCAPED_UNICODE) : null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $userId]);

        $punishDesc = $permanent ? '您的账号已被永久封禁' : ($bannedUntil ? "您的账号已被封禁至 {$bannedUntil}" : '您的账号操作权力受到了部分限制');
        if ($reason) $punishDesc .= "。原因：{$reason}";
        Notification::send($userId, '账号处罚与限制通知', $punishDesc, 'punish');
        return $res >= 0;
    }

    /**
     * 解除处罚
     */
    public static function unpunish(int $userId): bool {
        $res = Database::update('users', [
            'is_banned' => 0,
            'banned_until' => null,
            'ban_reason' => null,
            'restricted_permissions' => null,
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $userId]);

        Notification::send($userId, '账号限制已解除', '管理员已解除对您账号的所有封禁与操作权力限制。', 'system');
        return $res >= 0;
    }

    /**
     * 检查当前用户某项操作是否受限
     */
    public static function isRestricted(int $userId, string $action): bool {
        $user = self::findById($userId);
        if (!$user) return true;
        if (!empty($user['is_banned'])) return true;
        if (!empty($user['banned_until']) && strtotime($user['banned_until']) > time()) return true;
        if (empty($user['restricted_permissions'])) return false;
        $perms = json_decode((string)$user['restricted_permissions'], true);
        return is_array($perms) && in_array($action, $perms, true);
    }

    /**
     * 获取用户个人后台统计数据
     */
    public static function getUserStats(int $userId): array {
        $softTotal = (int)(Database::fetch("SELECT COUNT(*) AS c FROM softwares WHERE owner_user_id = :uid", ['uid' => $userId])['c'] ?? 0);
        $softPub = (int)(Database::fetch("SELECT COUNT(*) AS c FROM softwares WHERE owner_user_id = :uid AND status = 'published'", ['uid' => $userId])['c'] ?? 0);
        $softPend = (int)(Database::fetch("SELECT COUNT(*) AS c FROM softwares WHERE owner_user_id = :uid AND status = 'pending'", ['uid' => $userId])['c'] ?? 0);
        $comments = (int)(Database::fetch("SELECT COUNT(*) AS c FROM comments WHERE user_id = :uid", ['uid' => $userId])['c'] ?? 0);
        $reportsTotal = (int)(Database::fetch(
            "SELECT COUNT(*) AS c FROM reports r JOIN softwares s ON s.id = r.software_id WHERE s.owner_user_id = :uid",
            ['uid' => $userId]
        )['c'] ?? 0);
        $reportsPending = (int)(Database::fetch(
            "SELECT COUNT(*) AS c FROM reports r JOIN softwares s ON s.id = r.software_id WHERE s.owner_user_id = :uid AND r.status = 'pending'",
            ['uid' => $userId]
        )['c'] ?? 0);
        $unreadMsg = Notification::getUnreadCount($userId);

        return [
            // 标准键名
            'softwares_total' => $softTotal,
            'softwares_published' => $softPub,
            'softwares_pending' => $softPend,
            'comments_count' => $comments,
            'reports_total' => $reportsTotal,
            'reports_pending' => $reportsPending,
            'unread_notifications' => $unreadMsg,

            // 兼容别名
            'submitted_softwares' => $softTotal,
            'published_softwares' => $softPub,
            'pending_softwares' => $softPend,
            'comments' => $comments,
            'received_reports' => $reportsPending
        ];
    }

    /**
     * 管理员获取用户列表
     */
    public static function getAllAdmin(int $limit = 50, int $offset = 0, string $search = ''): array {
        $params = [];
        $where = "1=1";
        if (!empty($search)) {
            $where .= " AND (username LIKE :q OR email LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return Database::fetchAll(
            "SELECT id, username, email, role, status, badges, is_banned, banned_until, ban_reason, restricted_permissions, last_login_at, created_at 
             FROM users 
             WHERE {$where} 
             ORDER BY id DESC LIMIT :limit OFFSET :offset",
            $params
        );
    }

    /**
     * 分页总数。筛选条件必须与 getAllAdmin 保持一致，否则页数与实际条目对不上。
     */
    public static function countAll(string $search = ''): int {
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (username LIKE :q OR email LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $row = Database::fetch("SELECT COUNT(*) AS c FROM users WHERE {$where}", $params);
        return (int) ($row['c'] ?? 0);
    }

    /**
     * 绑定第三方 OAuth 账号
     */
    public static function bindOAuthAccount(
        int $userId,
        string $provider,
        string $providerUserId,
        string $email,
        string $name,
        string $avatar = ''
    ): array {
        $provider = strtolower(trim($provider));
        $providerUserId = trim($providerUserId);
        $email = strtolower(trim($email));
        $name = trim($name) ?: ($provider . '_user');

        // 同一邮箱不能横跨两个本站身份：否则日后按邮箱自动关联时会分不清该落到哪个账号
        if ($email !== '') {
            $emailOwner = self::findByEmail($email);
            if ($emailOwner && (int) $emailOwner['id'] !== $userId) {
                return ['success' => false, 'error' => '该第三方邮箱已属于另一个本站账号，无法绑定'];
            }
        }

        // 检查该第三方凭据是否已被其他账号绑定
        $existing = Database::fetch(
            "SELECT * FROM oauth_accounts WHERE provider = :p AND provider_user_id = :puid",
            ['p' => $provider, 'puid' => $providerUserId]
        );

        if ($existing) {
            if ((int)$existing['user_id'] !== $userId) {
                return ['success' => false, 'error' => '该第三方账号已被其他用户绑定'];
            }
            // 属同一用户，更新信息
            Database::update('oauth_accounts', [
                'provider_email' => $email,
                'provider_name' => $name,
                'provider_avatar' => $avatar,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $existing['id']]);
            return ['success' => true, 'message' => '第三方账号信息已同步'];
        }

        // 检查该用户是否已绑定该平台的其他账号
        $userBound = Database::fetch(
            "SELECT * FROM oauth_accounts WHERE user_id = :uid AND provider = :p",
            ['uid' => $userId, 'p' => $provider]
        );
        if ($userBound) {
            Database::update('oauth_accounts', [
                'provider_user_id' => $providerUserId,
                'provider_email' => $email,
                'provider_name' => $name,
                'provider_avatar' => $avatar,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $userBound['id']]);
            return ['success' => true, 'message' => '第三方账号已重新关联绑定'];
        }

        Database::insert('oauth_accounts', [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_email' => $email,
            'provider_name' => $name,
            'provider_avatar' => $avatar,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Notification::send($userId, '第三方账号绑定成功', '您的账号已成功绑定 ' . ucfirst($provider) . ' 授权。', 'system');
        return ['success' => true, 'message' => '第三方账号绑定成功'];
    }

    /**
     * 解绑第三方 OAuth 账号
     */
    public static function unbindOAuthAccount(int $userId, string $provider): array {
        $provider = strtolower(trim($provider));
        $user = self::findById($userId);
        if (!$user) {
            return ['success' => false, 'error' => '用户不存在'];
        }

        // 检查安全凭据：如果未设置登录密码且只绑定了一个第三方平台，禁止解绑
        $boundList = self::getOAuthAccounts($userId);
        if (empty($user['password_hash']) && count($boundList) <= 1) {
            return ['success' => false, 'error' => '您尚未设置独立登录密码。若直接解绑将无法登录账号，请先在上方设置登录密码。'];
        }

        $res = Database::delete('oauth_accounts', 'user_id = :uid AND provider = :p', [
            'uid' => $userId,
            'p' => $provider
        ]);

        if ($res > 0) {
            Notification::send($userId, '第三方账号已解绑', '您的账号已解除与 ' . ucfirst($provider) . ' 的绑定关联。', 'system');
            return ['success' => true, 'message' => '已成功解绑 ' . ucfirst($provider) . ' 账号'];
        }

        return ['success' => false, 'error' => '未找到该平台的绑定记录'];
    }

    /**
     * 使用第三方账号头像替换当前用户头像
     */
    public static function useOAuthAvatar(int $userId, string $provider): array {
        $provider = strtolower(trim($provider));
        $record = Database::fetch(
            "SELECT * FROM oauth_accounts WHERE user_id = :uid AND provider = :p",
            ['uid' => $userId, 'p' => $provider]
        );

        if (!$record || empty($record['provider_avatar'])) {
            return ['success' => false, 'error' => '该第三方账号未提供或不存在有效头像'];
        }

        $avatarUrl = trim($record['provider_avatar']);
        Database::update('users', [
            'avatar_url' => $avatarUrl,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $userId]);

        Auth::start();
        $_SESSION['frontend_user_avatar'] = $avatarUrl;

        Notification::send($userId, '头像已更新', '您的账号头像已同步更新为 ' . ucfirst($provider) . ' 第三方头像。', 'system');
        return ['success' => true, 'avatar_url' => $avatarUrl, 'message' => '头像已更新为第三方账号头像'];
    }

    /**
     * 用户更新个人资料 (昵称与自定义图床头像)
     */
    public static function updateProfile(int $userId, string $username, string $avatarUrl): array {
        $username = trim($username);
        $avatarUrl = trim($avatarUrl);

        // 昵称为纯字母或字母+数字 (3-24位)
        if (!preg_match('/^[a-zA-Z0-9]{3,24}$/', $username)) {
            return ['success' => false, 'error' => '昵称只能由纯字母或字母与数字组合（3-24位）'];
        }

        // 检查违禁词
        if (Comment::containsSensitiveWord($username)) {
            return ['success' => false, 'error' => '昵称包含违禁词汇'];
        }

        // 检查唯一性
        $exist = self::findByUsername($username);
        if ($exist && (int)$exist['id'] !== $userId) {
            return ['success' => false, 'error' => '该昵称已被其他用户占用'];
        }

        // 头像验证 (允许留空，或必须为合法 http/https 链接)
        if (!empty($avatarUrl) && !Url::isSafe($avatarUrl)) {
            return ['success' => false, 'error' => '自定义头像必须为有效的 HTTP/HTTPS 图床图片直链'];
        }

        Database::update('users', [
            'username' => $username,
            'avatar_url' => $avatarUrl ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $userId]);

        Auth::start();
        $_SESSION['frontend_user_name'] = $username;
        $_SESSION['frontend_user_avatar'] = $avatarUrl;

        return ['success' => true, 'message' => '个人资料已成功保存'];
    }

    /**
     * 用户更改密码 (需要旧密码验证，第三方用户可直接设置初始密码)
     */
    public static function changePassword(int $userId, ?string $oldPassword, string $newPassword): array {
        $user = self::findById($userId);
        if (!$user) {
            return ['success' => false, 'error' => '用户不存在'];
        }

        // 如果用户已有密码，必须验证原密码
        if (!empty($user['password_hash'])) {
            if (empty($oldPassword) || !password_verify($oldPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => '原密码验证不正确'];
            }
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => '新密码长度至少需要 6 位字符'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        Database::update('users', [
            'password_hash' => $hash,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $userId]);

        Notification::send($userId, '登录密码已修改', '您的账号密码于 ' . date('Y-m-d H:i:s') . ' 成功更新。', 'system');
        return ['success' => true, 'message' => '登录密码已成功更新'];
    }
}
