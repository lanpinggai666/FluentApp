<?php
/**
 * 蓝屏钙软件导航网 - 用户个人后台与软件管理中心控制器
 */

class UserController {
    private array $user;

    public function __construct() {
        Auth::start();
        // 公开页白名单要按解析后的路由比对：伪静态下 $_GET['r'] 是空的，
        // 拿它判断会把 /user/home 这种公开页当成需要登录
        $route = strtolower(trim(Router::currentRoute()));
        $publicRoutes = ['user/home', 'user/view', 'user/space'];
        if (!in_array($route, $publicRoutes, true)) {
            Auth::requireUser();
            $this->user = Auth::currentUser();
            if (!$this->user) {
                header('Location: ' . Url::target('?r=auth/login'));
                exit;
            }
        } else {
            $this->user = Auth::currentUser() ?: [];
        }
    }

    /**
     * 用户控制台总览
     */
    public function dashboard(): void {
        $uid = (int) $this->user['id'];
        $stats = User::getUserStats($uid);

        // 最近提交的软件 (最多5条)
        $recentSoftwares = Database::fetchAll(
            "SELECT * FROM softwares WHERE owner_user_id = :uid ORDER BY id DESC LIMIT 5",
            ['uid' => $uid]
        );

        // 最近收到的待处理软件举报
        $recentReports = Database::fetchAll(
            "SELECT r.*, s.name AS software_name, s.slug AS software_slug 
             FROM reports r 
             JOIN softwares s ON s.id = r.software_id 
             WHERE s.owner_user_id = :uid AND r.status = 'pending' 
             ORDER BY r.id DESC LIMIT 5",
            ['uid' => $uid]
        );

        // 最新未读站内信
        $recentNotifications = Notification::getByUserId($uid, 5, 0);

        View::render('user.dashboard', [
            'title' => '用户中心 - 蓝屏钙软件导航',
            'user' => $this->user,
            'stats' => $stats,
            'recentSoftwares' => $recentSoftwares,
            'recentReports' => $recentReports,
            'recentNotifications' => $recentNotifications
        ], false);
    }

    /**
     * 我的软件列表
     */
    public function softwares(): void {
        $uid = (int) $this->user['id'];
        $list = Database::fetchAll(
            "SELECT s.*, c.name AS category_name 
             FROM softwares s 
             LEFT JOIN categories c ON c.id = s.category_id 
             WHERE s.owner_user_id = :uid 
             ORDER BY s.id DESC",
            ['uid' => $uid]
        );

        View::render('user.softwares', [
            'title' => '我管理的软件 - 用户中心',
            'user' => $this->user,
            'softwares' => $list
        ], false);
    }

    /**
     * 软件管理者直接修改软件 (无需审核，直接生效)
     */
    public function editSoftware(): void {
        $uid = (int) $this->user['id'];
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        $software = Software::find($id);
        if (!$software) {
            http_response_code(404);
            View::render('home.404', ['title' => '软件不存在']);
            return;
        }

        // 仅软件主人或超级管理员可以修改
        $isOwner = ((int)($software['owner_user_id'] ?? 0) === $uid);
        $isAdmin = (Auth::check() || $this->user['role'] === 'admin');

        if (!$isOwner && !$isAdmin) {
            http_response_code(403);
            View::render('home.404', ['title' => '无权管理此软件']);
            return;
        }

        if (User::isRestricted($uid, 'correction')) {
            http_response_code(403);
            View::render('home.404', ['title' => '您的账号已被限制修改资料权限']);
            return;
        }

        $error = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $iconUrl = trim($_POST['icon_url'] ?? '');
            $officialUrl = trim($_POST['official_url'] ?? '');
            $githubUrl = trim($_POST['github_url'] ?? '');
            $specialNotes = trim($_POST['special_notes'] ?? '');
            $descriptionMd = trim($_POST['description_md'] ?? '');
            $priceType = in_array($_POST['price_type'] ?? '', ['free', 'freemium', 'paid'], true) ? $_POST['price_type'] : 'free';
            $isOpenSource = !empty($_POST['is_open_source']) ? 1 : 0;
            $license = trim($_POST['license'] ?? '');
            $developer = trim($_POST['developer'] ?? '');
            $publisher = trim($_POST['publisher'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            $screenshotUrl = trim($_POST['screenshot_url'] ?? '');

            // 链接一律只接受 http/https 绝对地址，伪协议值会直接落到前台 href
            $invalidLink = null;
            foreach (['官网链接' => $officialUrl, '图标链接' => $iconUrl, 'GitHub 地址' => $githubUrl, '截图地址' => $screenshotUrl] as $linkLabel => $linkValue) {
                if ($linkValue !== '' && !Url::isSafe($linkValue)) {
                    $invalidLink = $linkLabel;
                    break;
                }
            }
            if ($invalidLink === null) {
                foreach ((array) ($_POST['channel_url'] ?? []) as $channelValue) {
                    $channelValue = trim((string) $channelValue);
                    if ($channelValue !== '' && !Url::isSafe($channelValue)) {
                        $invalidLink = '下载渠道';
                        break;
                    }
                }
            }

            if (empty($name) || empty($officialUrl) || empty($categoryId)) {
                $error = '软件名称、官网链接与分类为必填项';
            } elseif ($invalidLink !== null) {
                $error = $invalidLink . '必须填写有效的 http/https 地址，且不能包含空格或引号';
            } elseif (
                FilterService::containsBanned($name) ||
                FilterService::containsBanned($specialNotes) ||
                FilterService::containsBanned($descriptionMd) ||
                FilterService::containsBanned($tags)
            ) {
                $error = '提交内容包含违禁敏感词，请修改后再保存';
            } else {
                // 下载渠道
                $channels = [];
                $types = (array)($_POST['channel_type'] ?? []);
                $cnames = (array)($_POST['channel_name'] ?? []);
                $urls = (array)($_POST['channel_url'] ?? []);
                foreach ($urls as $idx => $u) {
                    $u = trim((string)$u);
                    if ($u === '') continue;
                    $channels[] = [
                        'type' => trim((string)($types[$idx] ?? '第三方下载站')),
                        'name' => trim((string)($cnames[$idx] ?? '下载')),
                        'url' => $u
                    ];
                }

                $updateData = [
                    'name' => $name,
                    'category_id' => $categoryId,
                    'icon_url' => $iconUrl,
                    'official_url' => $officialUrl,
                    'github_url' => $githubUrl,
                    'special_notes' => $specialNotes,
                    'description_md' => $descriptionMd,
                    'price_type' => $priceType,
                    'is_open_source' => $isOpenSource,
                    'license' => $license,
                    'developer' => $developer,
                    'publisher' => $publisher,
                    'tags' => $tags,
                    'screenshot_url' => $screenshotUrl,
                    'other_urls' => !empty($channels) ? json_encode($channels, JSON_UNESCAPED_UNICODE) : null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                Software::update($id, $updateData);
                $software = Software::find($id);
                $success = '软件信息已直接修改并生效发布！';
            }
        }

        $channels = [];
        if (!empty($software['other_urls'])) {
            $dec = json_decode((string)$software['other_urls'], true);
            if (is_array($dec)) $channels = $dec;
        }

        View::render('user.edit_software', [
            'title' => '修改软件：' . View::e($software['name']),
            'user' => $this->user,
            'software' => $software,
            'categories' => Category::getAll(),
            'channels' => $channels,
            'error' => $error,
            'success' => $success
        ], false);
    }

    /**
     * 我的评价列表
     */
    public function comments(): void {
        $uid = (int) $this->user['id'];
        $list = Database::fetchAll(
            "SELECT cm.*, s.name AS software_name, s.slug AS software_slug 
             FROM comments cm 
             JOIN softwares s ON s.id = cm.software_id 
             WHERE cm.user_id = :uid 
             ORDER BY cm.id DESC",
            ['uid' => $uid]
        );

        View::render('user.comments', [
            'title' => '我的评价 - 用户中心',
            'user' => $this->user,
            'comments' => $list
        ], false);
    }

    /**
     * 我的软件收到的举报及处理
     */
    public function reports(): void {
        $uid = (int) $this->user['id'];
        $list = Database::fetchAll(
            "SELECT r.*, 
                    s.name AS software_name, s.slug AS software_slug, s.status AS software_status,
                    s.category_id, s.official_url, s.icon_url, s.github_url, s.developer, s.publisher,
                    s.tags, s.special_notes, s.description_md,
                    res.title AS resource_title, res.url AS resource_url
             FROM reports r 
             JOIN softwares s ON s.id = r.software_id 
             LEFT JOIN software_resources res ON res.id = r.resource_id
             WHERE s.owner_user_id = :uid 
             ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC",
            ['uid' => $uid]
        );
        $categories = Category::getAll();

        View::render('user.reports', [
            'title' => '软件举报处理 - 用户中心',
            'user' => $this->user,
            'reports' => $list,
            'categories' => $categories
        ], false);
    }

    /**
     * 软件管理者处理举报
     */
    public function handleReport(): void {
        $uid = (int) $this->user['id'];
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $action = trim($_POST['action'] ?? ''); // 'resolve_update', 'resolve_offline', 'resolve_delete_resource', 'resolve', 'reject', 'dismiss'
        $note = trim($_POST['note'] ?? ($_POST['review_reason'] ?? ''));

        $report = Report::find($reportId);
        if (!$report) {
            header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('举报不存在')));
            exit;
        }

        if ((int)$report['owner_user_id'] !== $uid && $this->user['role'] !== 'admin') {
            header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('无权处理此举报')));
            exit;
        }

        $softwareId = (int)$report['software_id'];
        $softwareName = $report['software_name'] ?? '软件';
        $resourceTitle = $report['resource_title'] ?: ('ID #' . ($report['resource_id'] ?? 0));
        $reporterUserId = (int)($report['user_id'] ?? 0);
        $email = trim((string)($report['email'] ?? ''));

        if ($action === 'resolve_update') {
            if (User::isRestricted($uid, 'correction')) {
                header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('您的账号已被限制修改资料权限')));
                exit;
            }
            $softwareData = [];
            $fields = ['name', 'category_id', 'official_url', 'icon_url', 'github_url', 'developer', 'tags', 'special_notes', 'description_md'];
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
                $checkStr = ($softwareData['name'] ?? '') . ($softwareData['tags'] ?? '') . ($softwareData['special_notes'] ?? '') . ($softwareData['description_md'] ?? '');
                if (FilterService::containsBanned($checkStr)) {
                    header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('修改内容包含敏感词')));
                    exit;
                }
                Database::update('softwares', $softwareData, 'id = :sid', ['sid' => $softwareId]);
            }
            $newStatus = 'resolved';
            $defaultNote = '软件管理者已核实并更正软件信息，处理完毕。感谢您的监督！';
            $reviewReason = $note !== '' ? $note : $defaultNote;
        } elseif ($action === 'resolve_offline') {
            Database::update('softwares', ['status' => 'offline'], 'id = :sid', ['sid' => $softwareId]);
            $newStatus = 'resolved';
            $defaultNote = '软件管理者已确认问题并将软件执行下架整改。';
            $reviewReason = $note !== '' ? $note : $defaultNote;
        } elseif ($action === 'resolve_delete_resource' || ($action === 'resolve' && !empty($report['resource_id']))) {
            if (!empty($report['resource_id'])) {
                Database::delete('software_resources', 'id = :rid', ['rid' => $report['resource_id']]);
            }
            $newStatus = 'resolved';
            $defaultNote = '软件管理者已核实违规并彻底删除了该扩展资源。';
            $reviewReason = $note !== '' ? $note : $defaultNote;
        } elseif ($action === 'resolve') {
            $newStatus = 'resolved';
            $defaultNote = '软件管理者已核实并处理完毕。';
            $reviewReason = $note !== '' ? $note : $defaultNote;
        } else {
            $newStatus = 'dismissed';
            $defaultNote = '软件管理者核实无违规或属误报。';
            $reviewReason = $note !== '' ? $note : $defaultNote;
            if (!empty($report['resource_id'])) {
                Database::update('software_resources', ['status' => 'published'], 'id = :rid', ['rid' => $report['resource_id']]);
            }
        }

        Database::update('reports', [
            'status' => $newStatus,
            'review_reason' => $reviewReason,
            'notified_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $reportId]);

        // 站内信通知举报人
        if ($reporterUserId > 0) {
            $title = ($newStatus === 'resolved') 
                ? "您对「{$softwareName}」的举报反馈已由软件管理者处理" 
                : "您对「{$softwareName}」的举报反馈处理结果";
            $content = ($newStatus === 'resolved')
                ? "软件管理者已核实并处理了您的举报反馈。处理说明：{$reviewReason}。感谢您的监督！"
                : "软件管理者已核实并驳回了您的举报反馈。驳回说明：{$reviewReason}。";
            Notification::send($reporterUserId, $title, $content, 'system');
        }

        // 邮件通知举报人
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && MailService::isEnabled()) {
            $siteName = Setting::get('site_name', '蓝屏钙软件导航');
            $label = ($newStatus === 'resolved') ? '已核实处理' : '已驳回';
            $body = '<p>您对「<strong>' . htmlspecialchars($softwareName, ENT_QUOTES, 'UTF-8') . '</strong>」提交的举报反馈，软件管理者已处理为：<strong>' . $label . '</strong>。</p>';
            $body .= '<p><strong>处理说明：</strong>' . nl2br(htmlspecialchars($reviewReason, ENT_QUOTES, 'UTF-8')) . '</p>';
            $mail = MailService::send($email, '[' . $siteName . '] 举报反馈处理结果', MailService::wrap($body));
            Report::updateNotification($reportId, !empty($mail['success']) ? 'sent' : 'failed', $mail['error'] ?? null);
        }

        header('Location: ' . Url::target('?r=user/reports&success=' . urlencode('举报已处理完毕')));
        exit;
    }

    /**
     * 删除已处理完毕的举报记录
     */
    public function deleteReport(): void {
        $uid = (int) $this->user['id'];
        $reportId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $report = Report::find($reportId);

        if (!$report) {
            header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('举报记录不存在')));
            exit;
        }

        if ((int)$report['owner_user_id'] !== $uid && ($this->user['role'] ?? '') !== 'admin') {
            header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('无权操作此举报记录')));
            exit;
        }

        if ($report['status'] === 'pending') {
            header('Location: ' . Url::target('?r=user/reports&error=' . urlencode('待处理的举报不能直接删除，请先核实处理或驳回')));
            exit;
        }

        Report::delete($reportId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            View::json(['success' => true]);
            return;
        }

        header('Location: ' . Url::target('?r=user/reports&success=' . urlencode('已成功删除该举报记录')));
        exit;
    }

    /**
     * 站内信列表
     */
    public function notifications(): void {
        $uid = (int) $this->user['id'];
        $list = Notification::getByUserId($uid, 100, 0);

        View::render('user.notifications', [
            'title' => '站内通知 - 用户中心',
            'user' => $this->user,
            'notifications' => $list
        ], false);
    }

    /**
     * 标为已读
     */
    public function readNotification(): void {
        $uid = (int) $this->user['id'];
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        Notification::markAsRead($id, $uid);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            View::json(['success' => true]);
            return;
        }
        header('Location: ' . Url::target('?r=user/notifications'));
        exit;
    }

    /**
     * 全部标为已读
     */
    public function readAllNotifications(): void {
        $uid = (int) $this->user['id'];
        Notification::markAllAsRead($uid);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            View::json(['success' => true]);
            return;
        }
        header('Location: ' . Url::target('?r=user/notifications'));
        exit;
    }

    /**
     * 删除单条站内信
     */
    public function deleteNotification(): void {
        $uid = (int) $this->user['id'];
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        Notification::delete($id, $uid);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            View::json(['success' => true]);
            return;
        }
        header('Location: ' . Url::target('?r=user/notifications'));
        exit;
    }

    /**
     * 清空所有已读站内信
     */
    public function deleteReadNotifications(): void {
        $uid = (int) $this->user['id'];
        Notification::deleteRead($uid);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            View::json(['success' => true]);
            return;
        }
        header('Location: ' . Url::target('?r=user/notifications'));
        exit;
    }

    /**
     * 账号与资料设置页
     */
    public function profile(): void {
        $uid = (int) $this->user['id'];
        $user = User::findById($uid);
        $oauthAccounts = User::getOAuthAccounts($uid);

        // 整理已绑定的平台映射
        $boundMap = [];
        foreach ($oauthAccounts as $oa) {
            $boundMap[strtolower($oa['provider'])] = $oa;
        }

        View::render('user.profile', [
            'title' => '账号与资料设置 - 蓝屏钙软件导航',
            'user' => $user,
            'boundMap' => $boundMap,
            'googleEnabled' => !empty(trim(Setting::get('oauth_google_client_id', ''))),
            'microsoftEnabled' => !empty(trim(Setting::get('oauth_ms_client_id', ''))),
            'captchaProfile' => CaptchaService::renderHtml('profile'),
            'captchaPassword' => CaptchaService::renderHtml('password'),
            'success' => $_GET['success'] ?? '',
            'error' => $_GET['error'] ?? ''
        ], false);
    }

    /**
     * 更新基本资料 (昵称、自定义图床头像)
     */
    public function updateProfile(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=user/profile'));
            exit;
        }

        if (!Auth::verifyRequestCsrf()) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('安全校验失败，请刷新重试')));
            exit;
        }

        if (!CaptchaService::verify($_POST, 'profile')) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('人机安全验证失败，请重新计算')));
            exit;
        }

        $uid = (int) $this->user['id'];
        $username = trim($_POST['username'] ?? '');
        $avatarUrl = trim($_POST['avatar_url'] ?? '');

        $res = User::updateProfile($uid, $username, $avatarUrl);
        if (!$res['success']) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode($res['error'])));
            exit;
        }

        header('Location: ' . Url::target('?r=user/profile&success=' . urlencode($res['message'])));
        exit;
    }

    /**
     * 更改登录密码
     */
    public function changePassword(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=user/profile'));
            exit;
        }

        if (!Auth::verifyRequestCsrf()) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('安全校验失败，请刷新重试')));
            exit;
        }

        if (!CaptchaService::verify($_POST, 'password')) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('人机安全验证失败，请重新计算')));
            exit;
        }

        $uid = (int) $this->user['id'];
        $oldPassword = $_POST['old_password'] ?? null;
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($newPassword !== $confirmPassword) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('两次输入的新密码不一致')));
            exit;
        }

        $res = User::changePassword($uid, $oldPassword, $newPassword);
        if (!$res['success']) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode($res['error'])));
            exit;
        }

        header('Location: ' . Url::target('?r=user/profile&success=' . urlencode($res['message'])));
        exit;
    }

    /**
     * 解绑第三方账号
     */
    public function unbindOAuth(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=user/profile'));
            exit;
        }

        if (!Auth::verifyRequestCsrf()) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('安全校验失败')));
            exit;
        }

        $uid = (int) $this->user['id'];
        $provider = trim($_POST['provider'] ?? '');

        $res = User::unbindOAuthAccount($uid, $provider);
        if (!$res['success']) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode($res['error'])));
            exit;
        }

        header('Location: ' . Url::target('?r=user/profile&success=' . urlencode($res['message'])));
        exit;
    }

    /**
     * 将第三方账号头像应用为当前头像
     */
    public function useOAuthAvatar(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=user/profile'));
            exit;
        }

        if (!Auth::verifyRequestCsrf()) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode('安全校验失败')));
            exit;
        }

        $uid = (int) $this->user['id'];
        $provider = trim($_POST['provider'] ?? '');

        $res = User::useOAuthAvatar($uid, $provider);
        if (!$res['success']) {
            header('Location: ' . Url::target('?r=user/profile&error=' . urlencode($res['error'])));
            exit;
        }

        header('Location: ' . Url::target('?r=user/profile&success=' . urlencode($res['message'])));
        exit;
    }

    /**
     * 公开用户个人主页
     */
    public function home(): void {
        $id = (int) ($_GET['id'] ?? 0);
        $username = trim($_GET['username'] ?? $_GET['u'] ?? '');

        $targetUser = null;
        if ($id > 0) {
            $targetUser = User::findById($id);
        } elseif ($username !== '') {
            $targetUser = User::findByUsername($username);
        } else {
            // 没给 id 也没给用户名（例如导航菜单里的「我的主页」）：登录者看自己，别让人 404
            $targetUser = Auth::currentUser();
        }

        if (!$targetUser) {
            http_response_code(404);
            View::render('home.404', ['title' => '用户不存在或已被注销 - 蓝屏钙软件导航'], true);
            return;
        }

        $targetUid = (int)$targetUser['id'];
        $stats = User::getUserStats($targetUid);
        $badges = User::getBadges($targetUser);

        // 获取该用户所管理维护的已发布软件
        $softwares = Database::fetchAll(
            "SELECT s.*, c.name AS category_name, c.slug AS category_slug 
             FROM softwares s 
             LEFT JOIN categories c ON c.id = s.category_id 
             WHERE s.owner_user_id = :uid AND s.status = 'published' 
             ORDER BY s.views_count DESC, s.id DESC",
            ['uid' => $targetUid]
        );

        // 统计管理的软件总浏览量
        $totalViews = 0;
        foreach ($softwares as $sw) {
            $totalViews += (int)($sw['views_count'] ?? 0);
        }

        // 获取该用户发表并审核通过的公开评价
        $comments = Database::fetchAll(
            "SELECT c.*, s.name AS software_name, s.slug AS software_slug, s.icon_url AS software_icon
             FROM comments c 
             JOIN softwares s ON s.id = c.software_id 
             WHERE c.user_id = :uid AND c.status = 'approved' AND s.status = 'published'
             ORDER BY c.id DESC LIMIT 30",
            ['uid' => $targetUid]
        );

        $currentUser = Auth::currentUser();
        $isSelf = ($currentUser && (int)$currentUser['id'] === $targetUid);

        // 脱敏处理敏感数据
        unset($targetUser['password_hash']);
        if (!$isSelf && ($currentUser['role'] ?? '') !== 'admin') {
            $email = $targetUser['email'] ?? '';
            if (strpos($email, '@') !== false) {
                [$u, $domain] = explode('@', $email, 2);
                $targetUser['email'] = substr($u, 0, 2) . '***@' . $domain;
            }
        }

        View::render('user.home', [
            'title' => $targetUser['username'] . ' 的个人主页 - 蓝屏钙软件导航',
            'targetUser' => $targetUser,
            'stats' => $stats,
            'badges' => $badges,
            'softwares' => $softwares,
            'totalViews' => $totalViews,
            'comments' => $comments,
            'isSelf' => $isSelf,
            'currentUser' => $currentUser
        ], true);
    }

    public function view(): void {
        $this->home();
    }

    public function space(): void {
        $this->home();
    }
}
