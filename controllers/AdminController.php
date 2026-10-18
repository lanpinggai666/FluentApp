<?php
/**
 * 蓝屏钙软件导航网 - 后台综合管理控制器
 */

class AdminController {
    public function __construct() {
        Auth::start();
    }

    public function login(): void {
        if (Auth::check()) {
            header('Location: ' . Url::target('?r=admin/dashboard'));
            exit;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            $limit = RateLimiter::consume('admin-login', RateLimiter::clientIp(), 8, 900);
            if (!$limit['allowed']) {
                $error = '登录尝试过于频繁，请 ' . (int)ceil($limit['retry_after'] / 60) . ' 分钟后再试';
            } elseif (Auth::login($username, $password)) {
                RateLimiter::clear('admin-login', RateLimiter::clientIp());
                header('Location: ' . Url::target('?r=admin/dashboard'));
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        }

        View::render('admin.login', [
            'title' => '管理后台登录 - 蓝屏钙软件导航',
            'error' => $error
        ], false);
    }

    public function logout(): void {
        Auth::logout();
        header('Location: ' . Url::target('?r=admin/login'));
        exit;
    }

    public function dashboard(): void {
        Auth::requireAuth();

        // 统计数据
        $totalSoftwares = (int) (Database::fetch("SELECT COUNT(*) as c FROM softwares WHERE status = 'published'")['c'] ?? 0);
        $pendingCount = (int) (Database::fetch("SELECT COUNT(*) as c FROM softwares WHERE status = 'pending'")['c'] ?? 0);
        $totalViews = (int) (Database::fetch("SELECT SUM(views_count) as c FROM softwares")['c'] ?? 0);
        $totalComments = (int) (Database::fetch("SELECT COUNT(*) as c FROM comments")['c'] ?? 0);
        $pendingReports = Report::countPending();

        $recentPending = Software::getPendingList();
        $recentReports = Report::getAllAdmin('pending');

        View::render('admin.dashboard', [
            'title' => '控制台 - 蓝屏钙软件导航',
            'totalSoftwares' => $totalSoftwares,
            'pendingCount' => $pendingCount,
            'totalViews' => $totalViews,
            'totalComments' => $totalComments,
            'pendingReports' => $pendingReports,
            'recentPending' => array_slice($recentPending, 0, 5),
            'recentReports' => array_slice($recentReports, 0, 5),
        ], false);
    }

    public function pending(): void {
        Auth::requireAuth();
        $list = Software::getPendingList();
        $categories = Category::getAll();

        View::render('admin.pending', [
            'title' => '待审核软件 - 蓝屏钙软件导航',
            'list' => $list,
            'categories' => $categories,
            'success' => $_GET['success'] ?? null,
            'notice' => $_GET['notice'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ], false);
    }

    /**
     * 软件提交审核统一入口。
     * action = approve | approve_ai | reject
     * 驳回必须填写理由，并自动生成可供提交者修改原内容的安全链接。
     */
    public function review(): void {
        Auth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=admin/pending'));
            exit;
        }

        if (!Auth::verifyRequestCsrf()) {
            header('Location: ' . Url::target('?r=admin/pending&notice=' . urlencode('安全令牌无效，请刷新后重试')));
            exit;
        }

        // 审核本身的动作全在 ModerationService 里，和开放 API 是同一份实现
        // 「AI 补全后发布」这一步最长要等 ai_total_timeout 秒，同样先把会话锁放掉，
        // 让对话面板能边跑边轮询（ModerationService / MailService 都不写 $_SESSION）
        $wantsTrace = (string) ($_POST['ai_trace'] ?? '') !== ''
            && (string) ($_POST['action'] ?? '') === 'approve_ai';
        if ($wantsTrace && AiTrace::begin((string) $_POST['ai_trace']) !== '') {
            session_write_close();
        }

        $result = ModerationService::reviewSoftware($_POST);
        if ($wantsTrace) {
            AiTrace::finish(['note' => (string) ($result['message'] ?? ''), 'ok' => (bool) ($result['ok'] ?? false)]);
        }
        if (!$result['ok']) {
            if ($this->wantsJson()) {
                View::json(['success' => false, 'note' => (string) $result['message']]);
            }
            header('Location: ' . Url::target('?r=admin/pending&notice=' . urlencode($result['message'])));
            exit;
        }

        if ($this->wantsJson()) {
            View::json(['success' => true, 'note' => (string) $result['message']]);
        }

        header('Location: ' . Url::target('?r=admin/pending&success=' . urlencode($result['message'])));
        exit;
    }

    private function buildAbsoluteUrl(string $relativePath): string {
        // 站点基址统一走 Url::siteBase()：配了 site_base_url 就以它为准，
        // 邮件里的一次性修改链接才不会跟着 Host 头跑到别人的域上
        return Url::siteBase() . '/' . ltrim($relativePath, '/');
    }

    public function softwares(): void {
        Auth::requireAuth();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $list = Software::getAllAdmin($perPage, $offset);
        $total = (int) (Database::fetch("SELECT COUNT(*) as c FROM softwares")['c'] ?? 0);
        $totalPages = ceil($total / $perPage);
        $categories = Category::getAll();

        View::render('admin.softwares', [
            'title' => '全部软件管理 - 蓝屏钙软件导航',
            'list' => $list,
            'categories' => $categories,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'success' => $_GET['success'] ?? null,
        ], false);
    }

    public function saveSoftware(): void {
        Auth::requireAuth();
        $id = (int) ($_POST['id'] ?? 0);

        // 校验与组装都在 SoftwareWriteService 里，和开放 API 用的是同一套规则
        $result = SoftwareWriteService::save($_POST, $id);
        if (!$result['ok']) {
            header('Location: ' . Url::target('?r=admin/softwares&error=' . urlencode($result['errors'][0] ?? '保存失败')));
            exit;
        }

        header('Location: ' . Url::target('?r=admin/softwares&success=1'));
        exit;
    }

    public function deleteSoftware(): void {
        Auth::requireAuth();
        $id = (int) ($_POST['id'] ?? 0);
        Software::delete($id);
        header('Location: ' . Url::target('?r=admin/softwares&success=已删除'));
        exit;
    }

    public function settings(): void {
        Auth::requireAuth();
        $msg = null;
        $testMailResult = null;

        if (!empty($_GET['mail_notice'])) {
            $parts = explode('|', (string)$_GET['mail_notice'], 2);
            $testMailResult = [
                'type' => $parts[0] === 'success' ? 'success' : 'error',
                'text' => $parts[1] ?? ''
            ];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowedKeys = [
                'site_name', 'site_subtitle', 'site_description', 'site_keywords',
                'site_logo', 'site_favicon', 'site_footer',
                'ai_provider', 'ai_api_base', 'ai_api_key', 'ai_model', 'ai_enabled', 'ai_web_search_enabled',
                // 同线路重试次数与总时长预算；读取处一律再 clamp 一次，防手工往库里塞坏值
                'ai_retries', 'ai_total_timeout',
                // 工具调用与联网抓取；取值范围都在 AiService/WebFetcher 读取处再夹一次
                'ai_tool_use', 'ai_tool_rounds', 'ai_page_chars',
                'ai_respect_robots', 'ai_courtesy_delay',
                // 搜索后端 Key 与 AI 线路密钥同性质：只写不读
                'search_tavily_key', 'search_brave_key', 'search_serper_key', 'search_firecrawl_key',
                'captcha_provider', 'captcha_site_key', 'captcha_secret_key',
                'google_ads_enabled', 'google_ads_client', 'google_ads_slot', 'google_ads_code',
                'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
                'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
                'site_base_url',
                // 「发送测试邮件」留空收件人时兜底用；以前全项目没人写过这个键，兜底必然失败
                'admin_email',
                // 隐藏域写 0、复选框写 1，两个同名输入让「取消勾选」也能真的落库
                'url_rewrite',
                // Google One Tap 自动弹窗总闸（前端还有「每会话一次 / 用户关过」两道抑制）
                'google_one_tap',
                'oauth_ms_client_id', 'oauth_ms_client_secret',
                'oauth_google_client_id', 'oauth_google_client_secret',
                'oauth_github_client_id', 'oauth_github_client_secret',
                'allowed_email_domains',
                'require_login_comment', 'require_login_submit_software',
                'require_login_submit_resource', 'require_login_correction', 'require_login_report'
            ];

            // 只写不读的凭据字段：留空表示沿用原值，避免「清空表单」误删密钥，
            // 也配合视图不再把凭据回显到 HTML 里
            $writeOnlyKeys = [
                'ai_api_key', 'captcha_secret_key', 'smtp_password',
                'oauth_ms_client_secret', 'oauth_google_client_secret', 'oauth_github_client_secret',
                // 搜索后端密钥：不回显，留空=沿用库里原值（和 ai_api_key 一条规则）
                'search_tavily_key', 'search_brave_key', 'search_serper_key', 'search_firecrawl_key',
            ];

            foreach ($allowedKeys as $k) {
                if (!isset($_POST[$k])) {
                    continue;
                }
                $value = trim($_POST[$k]);
                if ($value === '' && in_array($k, $writeOnlyKeys, true)) {
                    continue;
                }
                Setting::set($k, $value);
            }

            // 处理多 AI 提供商配置保存与容灾优先级列表
            $providersToSave = null;
            if (isset($_POST['ai_providers_json']) && trim($_POST['ai_providers_json']) !== '') {
                $decoded = json_decode(trim($_POST['ai_providers_json']), true);
                if (is_array($decoded)) {
                    $providersToSave = $decoded;
                }
            } elseif (isset($_POST['ai_provider_name']) && is_array($_POST['ai_provider_name'])) {
                $names = $_POST['ai_provider_name'];
                $bases = $_POST['ai_provider_base'] ?? [];
                $keys = $_POST['ai_provider_key'] ?? [];
                $models = $_POST['ai_provider_model'] ?? [];
                $enableds = $_POST['ai_provider_enabled'] ?? [];
                $providersToSave = [];
                foreach ($names as $idx => $n) {
                    $providersToSave[] = [
                        'name' => trim((string)$n),
                        'api_base' => trim((string)($bases[$idx] ?? '')),
                        'api_key' => trim((string)($keys[$idx] ?? '')),
                        'model' => trim((string)($models[$idx] ?? '')),
                        'enabled' => !empty($enableds[$idx]) ? '1' : '0'
                    ];
                }
            }

            if ($providersToSave !== null) {
                // 视图不再回显 api_key，留空的一律沿用库里已有的密钥。
                // 按 name + api_base 匹配而不是按顺序，避免后台调整提供商优先级时把密钥串到别的线路
                $storedProviders = json_decode((string) Setting::get('ai_providers_json', ''), true);
                $storedKeys = [];
                if (is_array($storedProviders)) {
                    foreach ($storedProviders as $sp) {
                        if (!is_array($sp) || empty($sp['api_key'])) {
                            continue;
                        }
                        $storedKeys[trim((string)($sp['name'] ?? '')) . '|' . rtrim(trim((string)($sp['api_base'] ?? '')), '/')] = trim((string) $sp['api_key']);
                    }
                }
                $legacyKey = trim((string) Setting::get('ai_api_key', ''));

                $cleanProviders = [];
                foreach ($providersToSave as $p) {
                    $pName = trim((string)($p['name'] ?? ''));
                    $pBase = trim((string)($p['api_base'] ?? ''));
                    $pKey = trim((string)($p['api_key'] ?? ''));
                    if ($pKey === '') {
                        $pKey = $storedKeys[$pName . '|' . rtrim($pBase, '/')] ?? '';
                    }
                    $pModel = trim((string)($p['model'] ?? ''));
                    $pEnabled = (!empty($p['enabled']) && $p['enabled'] !== '0') ? '1' : '0';
                    if ($pName !== '' || $pBase !== '' || $pKey !== '') {
                        $cleanProviders[] = [
                            'name' => $pName ?: 'AI 提供商',
                            'api_base' => $pBase ?: 'https://api.openai.com/v1',
                            'api_key' => $pKey,
                            'model' => $pModel ?: 'gpt-4o-mini',
                            'enabled' => $pEnabled
                        ];
                    }
                }
                Setting::set('ai_providers_json', json_encode($cleanProviders, JSON_UNESCAPED_UNICODE));
                if (!empty($cleanProviders)) {
                    $first = $cleanProviders[0];
                    Setting::set('ai_provider', $first['name']);
                    Setting::set('ai_api_base', $first['api_base']);
                    if ($first['api_key'] !== '') {
                        Setting::set('ai_api_key', $first['api_key']);
                    } elseif ($legacyKey !== '' && count($cleanProviders) === 1) {
                        // 只有一个线路且没填新密钥时，保留旧的单提供商密钥而不是清空
                        Setting::set('ai_api_key', $legacyKey);
                    }
                    Setting::set('ai_model', $first['model']);
                }
            }

            // 处理未勾选的布尔开关（表单中有 has_access_settings 标志时）
            if (isset($_POST['has_access_settings'])) {
                $accessKeys = [
                    'require_login_comment', 'require_login_submit_software',
                    'require_login_submit_resource', 'require_login_correction', 'require_login_report'
                ];
                foreach ($accessKeys as $ak) {
                    Setting::set($ak, !empty($_POST[$ak]) ? '1' : '0');
                }
            }

            // 修改密码
            if (!empty($_POST['new_password'])) {
                $newHash = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
                Setting::set('admin_password_hash', $newHash);
            }

            if (!empty($_POST['admin_username'])) {
                Setting::set('admin_username', trim($_POST['admin_username']));
            }

            $msg = '系统设置已更新';
        }

        $settings = Setting::loadAll();

        View::render('admin.settings', [
            'title' => '系统配置 - 蓝屏钙软件导航',
            'settings' => $settings,
            'msg' => $msg,
            'csrfToken' => Auth::csrfToken(),
            'testMailResult' => $testMailResult,
            // AI 外呼的记录（线路/耗时/token/失败原因），运维在这里直接看，不用登服务器翻日志
            'aiLogs' => AiLog::recent(20),
            'aiStats' => AiLog::summary(7),
        ], false);
    }

    /**
     * 测试指定的 AI 提供商连通性与响应速度
     */
    public function testAiProvider(): void {
        Auth::requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyRequestCsrf()) {
            echo json_encode(['success' => false, 'message' => 'CSRF 校验失败或请求不合法'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $apiBase = trim((string)($_POST['api_base'] ?? ''));
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));

        if (empty($apiBase)) {
            echo json_encode(['success' => false, 'message' => 'API 接口基址不能为空'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'message' => 'API Key 密钥不能为空'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (empty($model)) {
            echo json_encode(['success' => false, 'message' => '模型名称不能为空'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = AiService::testProvider($apiBase, $apiKey, $model);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 发送测试邮件，验证 SMTP 配置是否可用。
     */
    public function testMail(): void {
        Auth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyRequestCsrf()) {
            View::render('home.404', ['title' => '无效请求'], false);
            return;
        }

        $to = trim((string)($_POST['test_mail_to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            // 「留空则发往管理员邮箱」以前是句假话：admin_email 全项目没有任何地方写入过，
            // 留空必然失败。现在按 管理员邮箱 → 发件邮箱 → SMTP 账号 顺序兜底
            foreach (['admin_email', 'smtp_from_email', 'smtp_username'] as $fallbackKey) {
                $candidate = trim((string) Setting::get($fallbackKey, ''));
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $to = $candidate;
                    break;
                }
            }
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWithSettingsNotice('error', '请填写测试收件邮箱；或先把「管理员邮箱 / 发件邮箱」填好，再留空让系统兜底');
            return;
        }

        if (!MailService::isEnabled()) {
            $this->redirectWithSettingsNotice('error', 'SMTP 邮件通知未启用，请先保存并开启');
            return;
        }

        $siteName = Setting::get('site_name', '软件导航');
        $body = MailService::wrap(
            '<p>恭喜，您的 SMTP 邮件服务配置正确！</p>'
            . '<p>本站为「' . htmlspecialchars((string)$siteName, ENT_QUOTES, 'UTF-8')
            . '」，审核通过与驳回通知将通过该邮箱发送。</p>'
            . '<p>发送时间：' . date('Y-m-d H:i:s') . '</p>'
        );

        $result = MailService::send($to, '[' . $siteName . '] SMTP 测试邮件', $body);
        $this->redirectWithSettingsNotice(
            !empty($result['success']) ? 'success' : 'error',
            !empty($result['success'])
                ? '测试邮件已发送至 ' . $to
                : '测试邮件发送失败：' . ($result['error'] ?? '未知错误')
        );
    }

    private function redirectWithSettingsNotice(string $type, string $text): void {
        header('Location: ' . Url::target('?r=admin/settings&mail_notice=' . rawurlencode($type . '|' . $text)));
        exit;
    }

    public function mirrors(): void {
        Auth::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'add';
            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                $tpl = trim($_POST['url_template'] ?? '');
                $type = in_array($_POST['type'] ?? '', ['download', 'web', 'both'], true) ? $_POST['type'] : 'download';
                $sample = trim($_POST['sample_url'] ?? '');
                $original = trim($_POST['sample_original'] ?? '');
                $style = trim((string) ($_POST['url_style'] ?? ''));

                // 只给了镜像链接、没给模板：让它去学。学不会就把原因原样回显 ——
                // 以前这里是静默「保存成功」但其实一行都没写，运维以为配好了
                if ($tpl === '' && $sample !== '') {
                    $learned = GithubService::learnMirrorFromSample($sample, $type, $original);
                    if (empty($learned['success'])) {
                        header('Location: ' . Url::target('?r=admin/mirrors&error=' . urlencode($learned['message'])));
                        exit;
                    }
                    $tpl = $learned['url_template'];
                    $name = $name !== '' ? $name : $learned['name'];
                    $original = $learned['original_url'];
                    $style = $learned['style'];
                }

                if ($name === '' || $tpl === '') {
                    header('Location: ' . Url::target('?r=admin/mirrors&error=' . urlencode('节点名称与加速前缀模板都不能为空')));
                    exit;
                }
                // 有样例就再验一次：模板必须能把原链逐字映射成那条真实链接
                if ($sample !== '' && $original !== '') {
                    $built = GithubService::buildMirrorUrl($tpl, $original, $style);
                    if ($built !== $sample) {
                        header('Location: ' . Url::target('?r=admin/mirrors&error=' . urlencode('模板与样例对不上：按此模板映射得到 ' . $built . '，而你贴的真实链接是 ' . $sample)));
                        exit;
                    }
                }

                GithubMirror::add($name, $tpl, (int) ($_POST['sort_order'] ?? 10), $type,
                    $sample !== '' ? $sample : null, $style, $original !== '' ? $original : null);
            } elseif ($action === 'delete') {
                GithubMirror::delete((int) ($_POST['id'] ?? 0));
            }
            header('Location: ' . Url::target('?r=admin/mirrors&success=1'));
            exit;
        }

        $mirrors = GithubMirror::getAll();
        View::render('admin.mirrors', [
            'title' => 'GitHub 镜像源管理 - 蓝屏钙软件导航',
            'mirrors' => $mirrors
        ], false);
    }

    /**
     * 结构升级（网页版）：容器里不方便进命令行时，在这里点一下就把 database/migrations/*.sql 补齐。
     *
     * 执行逻辑与 php database/migrate.php 完全同一份（core/Migrator.php），差别只在门槛：
     * 必须已登录后台 + 带 CSRF；要执行的语句只来自仓库里的 .sql 文件，页面上没有任何输入能拼进 SQL。
     */
    public function migrate(): void {
        Auth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::verifyRequestCsrf()) {
                header('Location: ' . Url::target('?r=admin/migrate&error=' . urlencode('安全令牌无效，请刷新页面重试')));
                exit;
            }
            $pdo = Database::pdo();
            Migrator::ensureTable($pdo);
            $action = trim((string) ($_POST['action'] ?? 'run'));

            if ($action === 'stamp') {
                $stamped = Migrator::stamp($pdo, trim((string) ($_POST['stamp_version'] ?? '')));
                header('Location: ' . Url::target($stamped['ok']
                    ? '?r=admin/migrate&success=' . urlencode((string) ($stamped['note'] ?? '已登记'))
                    : '?r=admin/migrate&error=' . urlencode((string) ($stamped['error'] ?? '登记失败'))));
                exit;
            }

            $skipped = [];
            $pending = Migrator::pending($pdo, $skipped);
            if ($pending === []) {
                $note = Migrator::syncLock($pdo, false);
                header('Location: ' . Url::target('?r=admin/migrate&success=' . urlencode('结构已是最新，无需变更'
                    . ($note !== null ? '（' . $note . '）' : ''))));
                exit;
            }

            $applied = [];
            $confirmed = !empty($_POST['confirm_destructive']);
            foreach ($pending as $version => $files) {
                $statements = Migrator::statements($files);
                $combined = implode("\n", $statements);
                $names = Migrator::label($statements);
                if (Migrator::isDestructive($combined) && !$confirmed) {
                    header('Location: ' . Url::target('?r=admin/migrate&error=' . urlencode(
                        "v{$version}（{$names}）含 DROP/TRUNCATE 一类不可回滚语句：请先完整备份数据库，再勾选页面上的确认框重新执行"
                        . ($applied === [] ? '' : '；已应用：v' . implode('、v', $applied)))));
                    exit;
                }
                $result = Migrator::apply($pdo, (int) $version, $names, $statements);
                if (!$result['ok']) {
                    header('Location: ' . Url::target('?r=admin/migrate&error=' . urlencode(
                        "v{$version} 执行失败：" . ($result['error'] ?? '未知错误')
                        . '（SQLite 会整体回滚；MySQL 的 DDL 隐式提交，可能已执行到一半，请先核对结构）'
                        . ($applied === [] ? '' : '；已成功：v' . implode('、v', $applied)))));
                    exit;
                }
                $applied[] = (string) $version;
            }

            $note = Migrator::syncLock($pdo, false);
            header('Location: ' . Url::target('?r=admin/migrate&success=' . urlencode(
                '已应用 v' . implode('、v', $applied) . ($note !== null ? '；' . $note : ''))));
            exit;
        }

        $pdo = Database::pdo();
        Migrator::ensureTable($pdo);
        $skipped = [];
        $pending = Migrator::pending($pdo, $skipped);
        $rows = [];
        foreach ($pending as $version => $files) {
            $statements = Migrator::statements($files);
            $combined = implode("\n", $statements);
            $rows[] = [
                'version' => (int) $version,
                'names' => Migrator::label($statements),
                'sql' => $combined,
                'destructive' => Migrator::isDestructive($combined),
            ];
        }

        View::render('admin.migrate', [
            'title' => '结构升级 - 蓝屏钙软件导航',
            'driver' => Database::getDriver(),
            'baseline' => Migrator::baseline(),
            'applied' => Migrator::readApplied($pdo),
            'pending' => $rows,
            'skipped' => $skipped,
            'success' => $_GET['success'] ?? null,
            'error' => $_GET['error'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ], false);
    }

    /**
     * 后台「用 AI 重新生成」：当场调用一次补全并写库，结果原样显示回来源页。
     * 默认只补仍然空着的字段；勾了覆盖则先按 ai_generated_fields 清掉上次 AI 写的文本字段再整段重生成。
     * 与审核通过时的当场补全是同一份实现（AiService::completeSoftware），不会出现两条路行为不一致。
     */
    public function aiRetry(): void {
        Auth::requireAuth();
        $back = in_array((string) ($_POST['back'] ?? ''), ['softwares', 'pending'], true)
            ? (string) $_POST['back'] : 'softwares';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=admin/' . $back));
            exit;
        }
        if (!Auth::verifyRequestCsrf()) {
            header('Location: ' . Url::target('?r=admin/' . $back . '&error=' . urlencode('安全令牌无效，请刷新页面重试')));
            exit;
        }

        $softwareId = (int) ($_POST['software_id'] ?? 0);
        $overwrite = !empty($_POST['overwrite_ai']);

        // 面板要「边跑边出字」，就必须让另一个请求能挤进来轮询：
        // PHP 默认把会话文件锁在整个请求上，POST 还在跑的这 20 秒里，同浏览器的
        // admin/aiTrace 会卡在 session_start() 上，面板一个字都不出 —— 和转圈圈没区别。
        // 补全这条链路不写 $_SESSION（AI 层也不碰 Auth），所以在调用前放掉锁是安全的；
        // 只有带了合法 run 号（= 前端确实会来轮询）才放，普通表单提交保持原样。
        $trace = AiTrace::begin((string) ($_POST['ai_trace'] ?? ''));
        if ($trace !== '') {
            session_write_close();
        }

        $result = AiService::completeSoftware($softwareId, 'manual_retry', $overwrite);

        $note = '软件 #' . $softwareId . '：' . AiService::describeRun($result);
        if ($overwrite && !empty($result['cleared'])) {
            $note .= '（已清掉 ' . count($result['cleared']) . ' 个上次 AI 生成的字段）';
        }
        if ($trace !== '') {
            AiTrace::finish([
                'note' => $note,
                'ok' => (bool) ($result['ok'] ?? false),
                'source' => (string) ($result['source'] ?? ''),
                'filled' => (int) count($result['filled'] ?? []),
            ]);
        }

        if ($this->wantsJson()) {
            View::json(['success' => (bool) ($result['ok'] ?? false), 'note' => $note,
                        'source' => (string) ($result['source'] ?? '')]);
        }

        header('Location: ' . Url::target('?r=admin/' . $back . '&' . ($result['ok'] ? 'success=' : 'error=') . urlencode($note)));
        exit;
    }

    /**
     * 对话面板的轮询口：把这次补全的事件流按增量吐给前端。
     * 只读、且 run 号是前端随机生成的 24 位十六进制，配合上面的管理员校验；
     * 事件本身不含提示词正文与任何密钥（见 core/AiTrace 的说明）。
     */
    public function aiTrace(): void {
        Auth::requireAuth();
        header('Cache-Control: no-store');

        $run = (string) ($_GET['run'] ?? '');
        $after = max(0, (int) ($_GET['after'] ?? 0));
        if (preg_match('/^[a-f0-9]{16,48}$/i', $run) !== 1) {
            View::json(['events' => [], 'next' => $after, 'done' => true, 'stale' => true], 400);
        }
        View::json(AiTrace::read($run, $after));
    }

    /** 前端用 fetch 发的请求要 JSON，普通表单提交仍然走 302 + 横幅（没 JS 也能用）。 */
    private function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public function notices(): void {
        Auth::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'add';
            if ($action === 'add') {
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if ($title && $content) {
                    Notice::add($title, $content);
                }
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                Notice::delete($id);
            }
            header('Location: ' . Url::target('?r=admin/notices&success=1'));
            exit;
        }

        $notices = Notice::getAll();
        View::render('admin.notices', [
            'title' => '公告管理 - 蓝屏钙软件导航',
            'notices' => $notices
        ], false);
    }

    public function comments(): void {
        Auth::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'delete_comment') {
                $id = (int) ($_POST['id'] ?? 0);
                Comment::delete($id);
            } elseif ($action === 'add_word') {
                $words = trim($_POST['words'] ?? $_POST['word'] ?? '');
                if ($words !== '') {
                    FilterService::addWords($words);
                }
            } elseif ($action === 'delete_word') {
                $id = (int) ($_POST['id'] ?? 0);
                FilterService::deleteWord($id);
            }
            header('Location: ' . Url::target('?r=admin/comments&success=1'));
            exit;
        }

        $comments = Comment::getAllAdmin(50, 0);
        $sensitiveWords = Database::fetchAll("SELECT * FROM sensitive_words ORDER BY id DESC");

        View::render('admin.comments', [
            'title' => '评价与敏感词管理 - 蓝屏钙软件导航',
            'comments' => $comments,
            'sensitiveWords' => $sensitiveWords
        ], false);
    }

    public function corrections(): void {
        Auth::requireAuth();

        View::render('admin.corrections', [
            'title' => '补充信息审核 - 蓝屏钙软件导航',
            'list' => SoftwareCorrection::getPending(),
            'success' => $_GET['success'] ?? null,
            'notice' => $_GET['notice'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ], false);
    }

    public function reviewCorrection(): void {
        Auth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=admin/corrections'));
            exit;
        }

        if (!Auth::verifyRequestCsrf()) {
            http_response_code(403);
            header('Location: ' . Url::target('?r=admin/corrections&notice=' . urlencode('安全令牌无效，请刷新后重试')));
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $reviewNote = trim((string)($_POST['review_note'] ?? ''));

        if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
            header('Location: ' . Url::target('?r=admin/corrections&notice=' . urlencode('审核参数无效')));
            exit;
        }

        if ($action === 'reject' && $reviewNote === '') {
            header('Location: ' . Url::target('?r=admin/corrections&notice=' . urlencode('驳回时必须填写驳回理由')));
            exit;
        }

        $correction = SoftwareCorrection::find($id);
        $ok = $action === 'approve'
            ? SoftwareCorrection::approve($id, $reviewNote)
            : SoftwareCorrection::reject($id, $reviewNote);

        if ($ok && $correction) {
            $email = trim((string)($correction['submitter_email'] ?? ''));
            $siteName = Setting::get('site_name', '软件导航');
            $softwareName = htmlspecialchars((string)($correction['software_name'] ?? '软件'), ENT_QUOTES, 'UTF-8');
            $subject = $action === 'approve' ? '补充信息审核通过' : '补充信息审核未通过';
            $body = '<p>您提交的「' . $softwareName . '」补充信息' . ($action === 'approve' ? '已审核通过。' : '未通过审核。') . '</p>';

            if ($reviewNote !== '') {
                $body .= '<p><strong>审核说明：</strong><br>' . nl2br(htmlspecialchars($reviewNote, ENT_QUOTES, 'UTF-8')) . '</p>';
            }

            $editRelativeUrl = null;
            if ($action === 'reject') {
                $token = SoftwareCorrection::createEditToken($id);
                if ($token) {
                    $editRelativeUrl = '?r=software/editCorrection&token=' . rawurlencode($token);
                    $editUrl = $this->buildAbsoluteUrl($editRelativeUrl);
                    $body .= '<p>您可以使用下方链接修改原提交内容并重新提交审核（链接有效期 7 天，仅可使用一次）：</p>';
                    $body .= '<p><a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">修改并重新提交</a></p>';
                }
            }

            // 站内信通知申请人
            $targetUserId = (int)($correction['user_id'] ?? 0);
            if ($targetUserId <= 0 && !empty($email)) {
                $matchedUser = User::findByEmail($email);
                if ($matchedUser) {
                    $targetUserId = (int)$matchedUser['id'];
                }
            }
            if ($targetUserId > 0) {
                if ($action === 'approve') {
                    $noteText = $reviewNote !== '' ? " 审核说明：{$reviewNote}。" : "。";
                    Notification::send(
                        $targetUserId,
                        "软件「{$correction['software_name']}」修改申请已审核通过",
                        "您提交的软件资料修改申请已审核通过并已更新至软件正式展示页面。{$noteText}",
                        'system',
                        !empty($correction['software_slug']) ? Url::detail($correction['software_slug']) : null
                    );
                } else {
                    $reasonText = $reviewNote !== '' ? " 驳回理由：{$reviewNote}。" : "。";
                    Notification::send(
                        $targetUserId,
                        "软件「{$correction['software_name']}」修改申请未通过审核",
                        "您提交的软件资料修改申请未通过审核{$reasonText}您可以点击链接修改提交内容并重新提交审核（有效期 7 天）。",
                        'system',
                        $editRelativeUrl
                    );
                }
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                SoftwareCorrection::updateNotification($id, 'skipped', '提交者未填写有效邮箱');
            } elseif (!MailService::isEnabled()) {
                SoftwareCorrection::updateNotification($id, 'skipped', 'SMTP 未启用');
            } else {
                $mailResult = MailService::send(
                    $email,
                    '[' . $siteName . '] ' . $subject,
                    MailService::wrap($body)
                );
                SoftwareCorrection::updateNotification(
                    $id,
                    !empty($mailResult['success']) ? 'sent' : 'failed',
                    $mailResult['error'] ?? null
                );
            }
        }

        $message = $action === 'approve' ? '补充信息已审核通过' : '补充信息已驳回';
        $queryKey = $ok ? 'success' : 'notice';
        if (!$ok) {
            $message = '记录不存在、已处理或关联软件无效';
        }

        header('Location: ' . Url::target('?r=admin/corrections&' . $queryKey . '=' . urlencode($message)));
        exit;
    }

    public function reports(): void {
        Auth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::verifyRequestCsrf()) {
                header('Location: ' . Url::target('?r=admin/reports&notice=' . urlencode('安全令牌无效，请刷新后重试')));
                exit;
            }

            $id = (int) ($_POST['id'] ?? 0);
            $action = trim($_POST['action'] ?? ($_POST['status'] ?? ''));
            $reason = trim((string)($_POST['review_reason'] ?? ''));

            $report = Report::find($id);
            if (!$report) {
                header('Location: ' . Url::target('?r=admin/reports&notice=' . urlencode('该举报记录不存在')));
                exit;
            }

            if ($action === 'delete') {
                if ($report['status'] === 'pending') {
                    header('Location: ' . Url::target('?r=admin/reports&notice=' . urlencode('待处理的举报不能直接删除，请先核实处理或驳回')));
                    exit;
                }
                Report::delete($id);
                header('Location: ' . Url::target('?r=admin/reports&success=' . urlencode('已成功删除该举报记录')));
                exit;
            }

            if ($report['status'] !== 'pending') {
                header('Location: ' . Url::target('?r=admin/reports&notice=' . urlencode('该举报已被处理')));
                exit;
            }

            $softwareId = (int)$report['software_id'];
            $resourceId = (int)($report['resource_id'] ?? 0);
            $softwareName = $report['software_name'] ?? '软件';
            $resourceTitle = $report['resource_title'] ?: ('ID #' . $resourceId);
            $ownerUserId = (int)($report['owner_user_id'] ?? 0);
            $reporterUserId = (int)($report['user_id'] ?? 0);
            $email = trim((string)($report['email'] ?? ''));

            $status = 'resolved';
            $successMsg = '举报已处理完毕';

            switch ($action) {
                case 'resolve_update':
                    // 修改软件信息并处理完毕举报
                    $softwareData = [];
                    $fields = ['name', 'category_id', 'official_url', 'icon_url', 'github_url', 'developer', 'publisher', 'tags', 'special_notes', 'description_md'];
                    foreach ($fields as $f) {
                        if (isset($_POST['software_' . $f])) {
                            $val = trim((string)$_POST['software_' . $f]);
                            if ($f === 'category_id') {
                                $softwareData[$f] = (int)$val ?: 1;
                            } else {
                                $softwareData[$f] = $val;
                            }
                        }
                    }
                    if (!empty($softwareData)) {
                        Database::update('softwares', $softwareData, 'id = :sid', ['sid' => $softwareId]);
                    }
                    $status = 'resolved';
                    $reason = $reason !== '' ? $reason : '管理员已核实并更正软件信息，处理完毕。';
                    $successMsg = '已更新软件资料并处理完毕举报';

                    // 站内信通知软件所有者
                    if ($ownerUserId > 0) {
                        Notification::send(
                            $ownerUserId,
                            "名下软件「{$softwareName}」已更正资料并处理举报",
                            "您的软件收到访客举报，管理员已核实并更新了软件相关信息。处理说明：{$reason}",
                            'system',
                            Url::detail((string)($report['software_slug'] ?? ''))
                        );
                    }
                    break;

                case 'resolve_offline':
                    // 确认软件违规：立即下架软件
                    Database::update('softwares', ['status' => 'offline'], 'id = :sid', ['sid' => $softwareId]);
                    $status = 'resolved';
                    $reason = $reason !== '' ? $reason : '经管理员核实确认违规，已对该软件执行下架处理。';
                    $successMsg = '已确认违规并将软件「' . $softwareName . '」下架';

                    // 站内信通知软件所有者
                    if ($ownerUserId > 0) {
                        Notification::send(
                            $ownerUserId,
                            "您的软件「{$softwareName}」因违规举报已下架",
                            "您的软件收到访客违规举报，管理员核实确认违规并执行了下架处理。处理说明：{$reason}。如需恢复，请修改合规后重新提交审核。",
                            'system'
                        );
                    }
                    break;

                case 'resolve_delete_resource':
                    // 确认资源违规：永久删除该资源
                    if ($resourceId > 0) {
                        Database::delete('software_resources', 'id = :rid', ['rid' => $resourceId]);
                    }
                    $status = 'resolved';
                    $reason = $reason !== '' ? $reason : '经核实该相关资源确属违规/失效，已彻底清理删除。';
                    $successMsg = '已确认违规并彻底删除该相关资源';

                    // 站内信通知软件所有者
                    if ($ownerUserId > 0) {
                        Notification::send(
                            $ownerUserId,
                            "名下软件「{$softwareName}」的扩展资源已被清理",
                            "您名下软件关联的扩展资源「{$resourceTitle}」经举报核实违规，管理员已将其彻底清理删除。处理说明：{$reason}",
                            'system'
                        );
                    }
                    break;

                case 'resolve_keep':
                case 'resolved':
                    // 核实已处理，但软件保持上架（例如修复链接或无须下架）
                    $status = 'resolved';
                    $reason = $reason !== '' ? $reason : '管理员已核实并完成相应处置与排查。';
                    $successMsg = '举报已标记为已核实处理';
                    break;

                case 'dismiss':
                case 'dismissed':
                case 'rejected':
                default:
                    // 驳回举报（误报或无违规）
                    $status = 'dismissed';
                    if ($reason === '') {
                        $reason = '经管理员核对未发现违规或属误报，予以驳回。';
                    }
                    // 若是相关资源举报，驳回时必须将临时下架的资源恢复上架
                    if ($resourceId > 0) {
                        Database::update('software_resources', ['status' => 'published'], 'id = :rid', ['rid' => $resourceId]);
                    }
                    $successMsg = '举报已驳回' . ($resourceId > 0 ? '（已恢复该资源正常展示）' : '');
                    break;
            }

            // 更新举报状态
            Report::updateStatus($id, $status, $reason);

            // 站内信通知举报人（若为登录用户）
            if ($reporterUserId > 0) {
                if ($status === 'resolved') {
                    Notification::send(
                        $reporterUserId,
                        "您对「{$softwareName}」的举报反馈已核实处理",
                        "您提交的违规举报反馈经管理员核实已完成处理。处理结果：已核实处理。说明：{$reason}。感谢您为维护平台生态做出的贡献！",
                        'system'
                    );
                } else {
                    Notification::send(
                        $reporterUserId,
                        "您对「{$softwareName}」的举报反馈处理结果",
                        "您提交的举报反馈经核实予以驳回。驳回说明：{$reason}。",
                        'system'
                    );
                }
            }

            // 邮件通知举报人（若留有合法邮箱且开启邮件服务）
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && MailService::isEnabled()) {
                $siteName = Setting::get('site_name', '蓝屏钙软件导航');
                $label = ($status === 'resolved') ? '已核实处理' : '已驳回忽略';
                $body = '<p>您对「<strong>' . htmlspecialchars($softwareName, ENT_QUOTES, 'UTF-8') . '</strong>」提交的举报反馈，管理员已处理为：<strong>' . $label . '</strong>。</p>';
                $body .= '<p><strong>处理说明：</strong>' . nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')) . '</p>';
                $mail = MailService::send($email, '[' . $siteName . '] 举报反馈处理结果', MailService::wrap($body));
                Report::updateNotification($id, !empty($mail['success']) ? 'sent' : 'failed', $mail['error'] ?? null);
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Report::updateNotification($id, 'skipped', '举报者未填写有效邮箱');
            } else {
                Report::updateNotification($id, 'skipped', 'SMTP 未启用');
            }

            header('Location: ' . Url::target('?r=admin/reports&success=' . urlencode($successMsg)));
            exit;
        }

        $currentStatus = $_GET['status'] ?? 'all';
        $currentType = $_GET['type'] ?? 'all';
        $reports = Report::getAllAdmin($currentStatus === 'all' ? null : $currentStatus, $currentType === 'all' ? null : $currentType);
        $stats = Report::getStats();
        $categories = Category::getAll();

        View::render('admin.reports', [
            'title' => '软件举报与反馈处理 - 蓝屏钙软件导航',
            'reports' => $reports,
            'stats' => $stats,
            'categories' => $categories,
            'currentStatus' => $currentStatus,
            'currentType' => $currentType,
            'success' => $_GET['success'] ?? null,
            'notice' => $_GET['notice'] ?? null,
            'csrfToken' => Auth::csrfToken()
        ], false);
    }

    public function users(): void {
        Auth::requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::verifyRequestCsrf()) {
                header('Location: ' . Url::target('?r=admin/users&error=' . urlencode('安全令牌无效')));
                exit;
            }

            $action = trim($_POST['action'] ?? '');
            $userId = (int)($_POST['user_id'] ?? 0);
            // 规则都在 UserAdminService 里，和开放 API 用的是同一套
            $operatorId = (int)($_SESSION['frontend_user_id'] ?? 0);

            if ($action === 'set_badges') {
                $result = UserAdminService::setBadges($userId, $_POST['badges'] ?? '');
            } elseif ($action === 'punish') {
                $result = UserAdminService::punish([
                    'user_id' => $userId,
                    'permanent' => !empty($_POST['permanent']),
                    'banned_until' => $_POST['banned_until'] ?? '',
                    'ban_reason' => $_POST['ban_reason'] ?? '',
                    'restricted_permissions' => $_POST['restricted_permissions'] ?? [],
                ], $operatorId);
            } elseif ($action === 'unpunish') {
                $result = UserAdminService::unpunish($userId);
            } else {
                $result = ['ok' => false, 'code' => 'unknown_action', 'message' => '未知操作', 'data' => []];
            }

            if (!$result['ok']) {
                header('Location: ' . Url::target('?r=admin/users&error=' . urlencode($result['message'])));
                exit;
            }
            header('Location: ' . Url::target('?r=admin/users&success=' . urlencode($result['message'])));
            exit;
        }

        $search = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $users = User::getAllAdmin($limit, $offset, $search);
        $total = User::countAll($search);

        View::render('admin.users', [
            'title' => '用户与权限管理 - 蓝屏钙软件导航',
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'search' => $search
        ], false);
    }

    /**
     * 开放 API 的密钥管理。
     *
     * 生成与吊销只允许在「已登录后台 + CSRF」这条路上做，绝不开放给 API 自己：
     * 否则一把泄露的密钥就能铸造新密钥，吊销也就失去意义。
     * 明文密钥只存在会话里，渲染一次即销毁，库里只有 SHA-256。
     */
    public function apiKeys(): void {
        Auth::requireAuth();
        $notice = trim((string) ($_GET['notice'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                $scopes = array_values(array_intersect(['read', 'write'], (array) ($_POST['scopes'] ?? ['read'])));
                $actor = (string) ($_SESSION['admin_user']['username'] ?? Setting::get('admin_username', 'admin'));
                try {
                    $issued = ApiKey::issue(trim((string) ($_POST['name'] ?? '')), $scopes === [] ? ['read'] : $scopes, $actor);
                    $_SESSION['api_key_once'] = ['name' => $issued['row']['name'], 'key' => $issued['key']];
                    $notice = '密钥已生成，下面这串明文只显示这一次，关掉页面就查不到';
                } catch (Throwable $e) {
                    $notice = $e->getMessage();
                }
            } elseif ($action === 'revoke') {
                $notice = ApiKey::revoke((int) ($_POST['id'] ?? 0))
                    ? '该密钥已吊销，用它发起的请求会立刻返回 401'
                    : '吊销失败：密钥不存在，或已经吊销过';
            } elseif ($action === 'switch') {
                $key = (string) ($_POST['switch'] ?? '');
                if ($key === 'api_enabled') {
                    Setting::set('api_enabled', ApiKey::trueish($_POST['value'] ?? '') ? '1' : '0');
                    $notice = Setting::get('api_enabled', '1') === '1' ? '开放 API 已启用' : '开放 API 已整体关闭，所有端点返回 503';
                } elseif ($key === 'api_allow_insecure') {
                    // 放宽传输要求是危险操作，只允许后台会话改，API 自己的设置端点不认这个键
                    Setting::set('api_allow_insecure', ApiKey::trueish($_POST['value'] ?? '') ? '1' : '0');
                    $notice = Setting::get('api_allow_insecure', '0') === '1'
                        ? '已允许明文 HTTP 调用 —— 管理员口令会随请求明文上网，只在临时内网调试时这么干'
                        : '已恢复 HTTPS 强制';
                } else {
                    $notice = '未知的开关项';
                }
            }
            header('Location: ' . Url::target('?r=admin/apiKeys&notice=' . urlencode($notice)));
            exit;
        }

        $once = $_SESSION['api_key_once'] ?? null;
        unset($_SESSION['api_key_once']);

        View::render('admin.api_keys', [
            'title' => '开放 API 密钥 - 蓝屏钙软件导航',
            'keys' => ApiKey::listAll(),
            'logs' => ApiLog::recent(20),
            'notice' => $notice,
            'once' => $once,
            'apiEnabled' => Setting::get('api_enabled', '1') === '1',
            'allowInsecure' => Setting::get('api_allow_insecure', '0') === '1',
        ], false);
    }
}
