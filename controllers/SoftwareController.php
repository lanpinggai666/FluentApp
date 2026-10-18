<?php
/**
 * 蓝屏钙软件导航网 - 软件详情与交互控制器
 */

class SoftwareController {
    public function view(): void {
        $slug = $_GET['slug'] ?? '';
        $id = (int) ($_GET['id'] ?? 0);

        $software = null;
        if (!empty($slug)) {
            $software = Software::findBySlug($slug);
        } elseif ($id > 0) {
            $software = Software::find($id);
        }

        if (!$software || $software['status'] !== 'published') {
            http_response_code(404);
            View::render('home.404', ['title' => '软件不存在或尚未发布'], true);
            return;
        }

        // 浏览量按会话去重，且写失败不应影响详情页渲染（只读库 / 并发锁）
        Software::registerView((int)$software['id']);

        // 获取同类推荐软件
        $related = Software::getRelated((int)$software['category_id'], (int)$software['id'], 4);

        // 获取已审核评价
        $comments = Comment::getBySoftware((int)$software['id']);

        // 获取第三方相关资源
        $resources = SoftwareResource::getBySoftwareId((int)$software['id']);

        // 获取已激活的 GitHub 镜像列表
        $mirrors = GithubMirror::getActive();

        // 解析其它获取渠道
        $otherUrls = [];
        if (!empty($software['other_urls'])) {
            $decoded = json_decode($software['other_urls'], true);
            if (is_array($decoded)) {
                $otherUrls = $decoded;
            }
        }

        // AI 标记解析
        $aiFields = [];
        if (!empty($software['ai_generated_fields'])) {
            $aiFields = json_decode($software['ai_generated_fields'], true) ?: [];
        }
        // AI 整理资料时联网看过哪些页面 —— 摆出来让人能逐条核对，链接在输出侧重新校验
        $aiSources = AiService::normalizeSources($software['ai_sources'] ?? null);

        // 检查官网或项目是否指向 GitHub，若是则提取 README.md
        $readmeHtml = null;
        $isOfficialGithub = false;
        $targetGithub = $software['github_url'] ?? '';

        if (!empty($software['official_url']) && str_contains($software['official_url'], 'github.com/')) {
            $isOfficialGithub = true;
            $targetGithub = $software['official_url'];
            $readmeHtml = GithubService::getReadme($software['official_url']);
        } elseif (!empty($targetGithub)) {
            $readmeHtml = GithubService::getReadme($targetGithub);
        }

        $title = $software['name'] . ' 官网与下载渠道 - ' . Setting::get('site_name', '蓝屏钙软件导航');
        $siteDesc = View::excerpt($software['special_notes'] ?? '', 120) ?: Setting::get('site_description');

        $channels = [];
        $seenUrls = [];
        if (isset($software['other_urls'])) {
            $decoded = is_array($software['other_urls']) ? $software['other_urls'] : json_decode((string)$software['other_urls'], true);
            foreach (is_array($decoded) ? $decoded : [] as $channel) {
                if (!is_array($channel)) {
                    continue;
                }
                $url = trim((string)($channel['url'] ?? ''));
                if ($url === '' || !Url::isSafe($url)) {
                    continue;
                }
                $key = strtolower(rtrim($url, '/'));
                if (isset($seenUrls[$key])) {
                    continue;
                }
                $seenUrls[$key] = true;
                $channels[] = [
                    'type' => trim((string)($channel['type'] ?? '第三方下载站')) ?: '第三方下载站',
                    'name' => trim((string)($channel['name'] ?? '')) ?: trim((string)($channel['type'] ?? '下载渠道')),
                    'url' => $url,
                ];
            }
        }

        $otherUrls = $channels;

        $managerUser = !empty($software['owner_user_id']) ? User::findById((int)$software['owner_user_id']) : null;

        $currentUser = Auth::currentUser();
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $hasUserCommented = Comment::hasUserCommented(
            (int)$software['id'],
            $currentUser ? (int)$currentUser['id'] : null,
            $clientIp
        );
        $ratingDist = Comment::getRatingDistribution((int)$software['id']);

        View::render('software.detail', [
            'title' => $title,
            'siteDesc' => $siteDesc,
            'canonical' => Url::detail((string)$software['slug'], true),
            'software' => $software,
            'managerUser' => $managerUser,
            'related' => $related,
            'comments' => $comments,
            'ratingDist' => $ratingDist,
            'hasUserCommented' => $hasUserCommented,
            'mirrors' => $mirrors,
            'resources' => $resources,
            'otherUrls' => $otherUrls,
            'categories' => Category::getAll(),
            'aiFields' => $aiFields,
            'aiSources' => $aiSources,
            'readmeHtml' => $readmeHtml,
            'isOfficialGithub' => $isOfficialGithub,
            'captchaHtml' => CaptchaService::renderHtml(),
            'correctionCaptchaHtml' => CaptchaService::renderHtml('correction'),
            'reportCaptchaHtml' => CaptchaService::renderHtml('report'),
            'resourceCaptchaHtml' => CaptchaService::renderHtml('resource'),
        ]);
    }

    public function comment(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=home'));
            exit;
        }

        $softwareId = (int) ($_POST['software_id'] ?? 0);
        $software = Software::find($softwareId);
        if (!$software) {
            View::json(['success' => false, 'message' => '软件不存在']);
            return;
        }

        // 登录要求与权力限制检查
        $currentUser = Auth::currentUser();
        if (Setting::get('require_login_comment', '0') === '1' && !$currentUser) {
            View::json(['success' => false, 'message' => '本站已设置必须登录后才能发表评价，请先登录']);
            return;
        }
        if ($currentUser && User::isRestricted((int)$currentUser['id'], 'comment')) {
            View::json(['success' => false, 'message' => '您的账号已被限制发表评价权限']);
            return;
        }

        // 限制同一人只能发表一条评价
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (Comment::hasUserCommented($softwareId, $currentUser ? (int)$currentUser['id'] : null, $clientIp)) {
            $msg = $currentUser ? '您已对该软件发表过评价，暂不支持重复发表' : '您所在的 IP 已对该软件发表过评价，请登录账号或避免重复提交';
            View::json(['success' => false, 'message' => $msg]);
            return;
        }

        // 人机验证
        if (!CaptchaService::verify($_POST)) {
            View::json(['success' => false, 'message' => '人机安全验证失败，请重试']);
            return;
        }

        $nickname = $currentUser ? $currentUser['username'] : trim($_POST['nickname'] ?? '访客');
        $title = trim($_POST['title'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 5);
        $content = trim($_POST['content'] ?? '');

        if (empty($content)) {
            View::json(['success' => false, 'message' => '请输入评价内容']);
            return;
        }

        if (mb_strlen($content) > 500) {
            View::json(['success' => false, 'message' => '评价内容请在500字以内']);
            return;
        }

        // 敏感词屏蔽与检查
        if (FilterService::containsBanned($content) || FilterService::containsBanned($title) || FilterService::containsBanned($nickname)) {
            $content = FilterService::filter($content);
            $title = FilterService::filter($title);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $commentId = Comment::add($softwareId, $nickname, $rating, $content, $ip, $title, $currentUser ? (int)$currentUser['id'] : null);

        View::json([
            'success' => true,
            'message' => '评价发表成功',
            'comment' => [
                'id' => $commentId,
                'nickname' => htmlspecialchars($nickname),
                'title' => htmlspecialchars($title),
                'rating' => $rating,
                'content' => htmlspecialchars($content),
                'created_at' => date('Y-m-d H:i')
            ]
        ]);
    }

    public function voteComment(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => '请求方式非法']);
            return;
        }

        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $type = ($_POST['type'] ?? 'like') === 'dislike' ? 'dislike' : 'like';

        if ($commentId <= 0) {
            View::json(['success' => false, 'message' => '参数错误']);
            return;
        }

        $result = Comment::vote($commentId, $type);
        View::json([
            'success' => true,
            'likes' => (int)($result['likes'] ?? 0),
            'dislikes' => (int)($result['dislikes'] ?? 0)
        ]);
    }

    public function report(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => '请求方式非法']);
            return;
        }

        $softwareId = (int) ($_POST['software_id'] ?? 0);
        $software = Software::find($softwareId);
        if (!$software) {
            View::json(['success' => false, 'message' => '目标软件不存在']);
            return;
        }

        $currentUser = Auth::currentUser();
        if (Setting::get('require_login_report', '0') === '1' && !$currentUser) {
            View::json(['success' => false, 'message' => '本站已设置必须登录后才能提交举报，请先登录']);
            return;
        }
        if ($currentUser && User::isRestricted((int)$currentUser['id'], 'report')) {
            View::json(['success' => false, 'message' => '您的账号已被限制举报权限']);
            return;
        }

        // 人机验证（举报弹窗独立实例）
        if (!CaptchaService::verify($_POST, 'report')) {
            View::json(['success' => false, 'message' => '人机安全验证失败，请重试']);
            return;
        }

        $reason = trim((string)($_POST['reason'] ?? '其他原因'));
        $details = trim((string)($_POST['details'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ($currentUser['email'] ?? '')));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $resourceId = (int)($_POST['resource_id'] ?? 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            View::json(['success' => false, 'message' => '请填写有效的联系邮箱']);
            return;
        }
        if (mb_strlen($reason) > 50 || mb_strlen($details) > 500 || mb_strlen($contact) > 100) {
            View::json(['success' => false, 'message' => '举报内容过长']);
            return;
        }
        if (FilterService::containsBanned($reason . $details . $contact)) {
            View::json(['success' => false, 'message' => '举报内容包含敏感违禁词']);
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 若针对具体资源举报，立即将该资源置为 reported 状态临时下架屏蔽
        $reportedType = ($resourceId > 0 || $reason === '相关资源违规') ? 'resource' : 'software';
        if ($resourceId > 0) {
            Database::update('software_resources', ['status' => 'reported'], 'id = :rid', ['rid' => $resourceId]);
        }

        $reportId = Report::add(
            $softwareId,
            $reason,
            $details,
            $email,
            $contact,
            $ip,
            $resourceId ?: null,
            $reportedType,
            $currentUser ? (int)$currentUser['id'] : null
        );

        if ($reportId <= 0) {
            View::json(['success' => false, 'message' => '举报保存失败，请稍后重试']);
            return;
        }

        // 站内信通知该软件管理者
        if (!empty($software['owner_user_id'])) {
            Notification::send(
                (int)$software['owner_user_id'],
                "软件「{$software['name']}」收到违规举报",
                "有访客提交了违规举报（理由：{$reason}），请前往个人中心「违规举报处理」进行核实与处理。",
                'report',
                '?r=user/reports'
            );
        }

        View::json([
            'success' => true,
            'message' => '举报反馈已收到，感谢您的监督！'
        ]);
    }

    public function addTag(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => '请求方式非法']);
            return;
        }

        $softwareId = (int) ($_POST['software_id'] ?? 0);
        $tag = trim($_POST['tag'] ?? '');

        $currentUser = Auth::currentUser();
        if ($currentUser && User::isRestricted((int)$currentUser['id'], 'correction')) {
            View::json(['success' => false, 'message' => '您的账号已被限制修改资料权限']);
            return;
        }

        if (empty($tag)) {
            View::json(['success' => false, 'message' => '请输入标签名称']);
            return;
        }

        if (mb_strlen($tag) > 15) {
            View::json(['success' => false, 'message' => '标签长度不能超过 15 个字']);
            return;
        }

        if (FilterService::containsBanned($tag)) {
            View::json(['success' => false, 'message' => '标签包含敏感违禁词']);
            return;
        }

        $allTags = Software::addTag($softwareId, $tag);
        View::json([
            'success' => true,
            'message' => '标签添加成功',
            'tags' => $allTags
        ]);
    }

    public function submitCorrection(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => '请求方式非法']);
            return;
        }

        $softwareId = (int)($_POST['software_id'] ?? 0);
        $software = Software::find($softwareId);
        if (!$software || ($software['status'] ?? '') !== 'published') {
            View::json(['success' => false, 'message' => '目标软件不存在或尚未发布']);
            return;
        }

        $currentUser = Auth::currentUser();
        if (Setting::get('require_login_correction', '0') === '1' && !$currentUser) {
            View::json(['success' => false, 'message' => '本站已设置必须登录后才能提交软件信息修改，请先登录']);
            return;
        }
        if ($currentUser && User::isRestricted((int)$currentUser['id'], 'correction')) {
            View::json(['success' => false, 'message' => '您的账号已被限制修改资料权限']);
            return;
        }

        if (!CaptchaService::verify($_POST, 'correction')) {
            View::json(['success' => false, 'message' => '人机安全验证失败，请重试']);
            return;
        }

        $submitterEmail = trim((string)($_POST['submitter_email'] ?? ($currentUser['email'] ?? '')));
        if (!filter_var($submitterEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($submitterEmail) > 254) {
            View::json(['success' => false, 'message' => '请填写有效的联系邮箱']);
            return;
        }

        $limits = [
            'name' => 100,
            'icon_url' => 500,
            'official_url' => 500,
            'github_url' => 500,
            'special_notes' => 200,
            'description_md' => 10000,
            'developer' => 100,
            'publisher' => 100,
            'license' => 100,
            'tags' => 500,
            'screenshot_url' => 500,
            'contact' => 200,
        ];
        $data = [
            'software_id' => $softwareId,
            'user_id' => $currentUser ? (int)$currentUser['id'] : null,
            'submitter_email' => $submitterEmail,
        ];
        $hasContent = false;

        foreach ($limits as $field => $maxLength) {
            // 「这个字段压根没提交」（勾选开源后 GitHub 输入框被禁用就是这种情况）与
            // 「提交了空串」不是一回事：前者沿用库里现值，后者才是真的要清空
            if (!array_key_exists($field, $_POST)) {
                $data[$field] = trim((string)($software[$field] ?? ''));
                continue;
            }
            $value = trim((string)$_POST[$field]);
            if (mb_strlen($value) > $maxLength) {
                View::json(['success' => false, 'message' => "{$field} 内容过长"]);
                return;
            }
            if ($value !== '') {
                $hasContent = true;
                if (FilterService::containsBanned($value)) {
                    View::json(['success' => false, 'message' => '提交内容包含敏感违禁词']);
                    return;
                }
            }
            $data[$field] = $value;
        }

        foreach (['icon_url', 'official_url', 'github_url', 'screenshot_url'] as $urlField) {
            if (($data[$urlField] ?? '') !== '' && !Url::isSafe($data[$urlField])) {
                View::json(['success' => false, 'message' => '网址必须是有效的 HTTP 或 HTTPS 地址']);
                return;
            }
        }

        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0 || !Category::find($categoryId)) {
            View::json(['success' => false, 'message' => '请选择有效的软件分类']);
            return;
        }
        $data['category_id'] = $categoryId;
        $data['price_type'] = in_array($_POST['price_type'] ?? '', ['free', 'freemium', 'paid'], true)
            ? $_POST['price_type']
            : 'free';
        $data['is_open_source'] = !empty($_POST['is_open_source']) ? 1 : 0;
        $hasContent = true;

        $channels = [];
        $channelTypes = (array)($_POST['channel_type'] ?? []);
        $channelNames = (array)($_POST['channel_name'] ?? []);
        $channelUrls = (array)($_POST['channel_url'] ?? []);
        foreach ($channelUrls as $index => $rawUrl) {
            $url = trim((string)$rawUrl);
            if ($url === '') {
                continue;
            }
            if (!Url::isSafe($url)) {
                View::json(['success' => false, 'message' => '下载渠道包含无效网址']);
                return;
            }
            $type = trim((string)($channelTypes[$index] ?? '第三方下载'));
            $name = trim((string)($channelNames[$index] ?? '下载渠道'));
            if (mb_strlen($type) > 50 || mb_strlen($name) > 100
                || FilterService::containsBanned($type) || FilterService::containsBanned($name)) {
                View::json(['success' => false, 'message' => '下载渠道类型或名称不符合要求']);
                return;
            }
            $channels[] = [
                'type' => $type ?: '第三方下载',
                'name' => $name ?: ($type ?: '下载渠道'),
                'url' => $url
            ];
            $hasContent = true;
        }

        if (!$hasContent) {
            View::json(['success' => false, 'message' => '请至少填写一项补充信息']);
            return;
        }

        $data['download_channels'] = $channels
            ? json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $data['submitter_ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        try {
            $correctionId = SoftwareCorrection::create($data);
        } catch (Throwable $e) {
            View::json(['success' => false, 'message' => APP_DEBUG ? '补充信息保存失败：' . $e->getMessage() : '补充信息保存失败，请稍后重试']);
            return;
        }
        if ($correctionId <= 0) {
            View::json(['success' => false, 'message' => '补充信息保存失败，请稍后重试']);
            return;
        }
        View::json([
            'success' => true,
            'message' => '补充信息已提交审核，审核通过后才会更新软件资料'
        ]);
    }

    public function editCorrection(): void {
        $token = trim((string)($_GET['token'] ?? ''));
        $correction = SoftwareCorrection::findByEditToken($token);

        if (!$correction) {
            http_response_code(404);
            View::render('home.404', ['title' => '修改链接无效或已过期']);
            return;
        }

        $channels = [];
        if (!empty($correction['download_channels'])) {
            $decoded = json_decode((string)$correction['download_channels'], true);
            $channels = is_array($decoded) ? SoftwareCorrection::normalizeForDisplay($decoded) : [];
        }

        View::render('software.edit_correction', [
            'title' => '修改软件信息 - ' . Setting::get('site_name', '蓝屏钙软件导航'),
            'correction' => $correction,
            'token' => $token,
            'channels' => $channels,
            'categories' => Category::getAll(),
            'captchaHtml' => CaptchaService::renderHtml(),
            'error' => $_GET['error'] ?? null,
        ]);
    }

    public function resubmitCorrection(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=home'));
            exit;
        }

        $token = trim((string)($_POST['token'] ?? ''));
        $correction = SoftwareCorrection::findByEditToken($token);
        if (!$correction) {
            http_response_code(404);
            View::render('home.404', ['title' => '修改链接无效或已过期']);
            return;
        }

        if (!CaptchaService::verify($_POST)) {
            header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('人机安全验证失败，请重试')));
            exit;
        }

        $email = trim((string)($_POST['submitter_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('请填写有效的联系邮箱')));
            exit;
        }

        $limits = [
            'name' => 100, 'icon_url' => 500, 'official_url' => 500, 'github_url' => 500,
                        'developer' => 100, 'publisher' => 100, 'tags' => 500, 'screenshot_url' => 500,
            'contact' => 200,
        ];
        $data = [
            'submitter_email' => $email,
            'name' => trim((string)($_POST['name'] ?? '')),
            'icon_url' => trim((string)($_POST['icon_url'] ?? '')),
            'official_url' => trim((string)($_POST['official_url'] ?? '')),
            // 与 submitCorrection 同规则：字段没提交（勾选开源后输入框被禁用）就沿用这条修正申请里已有的地址
            'github_url' => array_key_exists('github_url', $_POST)
                ? trim((string)$_POST['github_url'])
                : trim((string)($correction['github_url'] ?? '')),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'special_notes' => trim((string)($_POST['special_notes'] ?? '')),
            'price_type' => in_array($_POST['price_type'] ?? '', ['free', 'freemium', 'paid'], true) ? $_POST['price_type'] : 'free',
            'is_open_source' => !empty($_POST['is_open_source']) ? 1 : 0,
            'license' => trim((string)($_POST['license'] ?? '')),
            'developer' => trim((string)($_POST['developer'] ?? '')),
            'publisher' => trim((string)($_POST['publisher'] ?? '')),
            'tags' => trim((string)($_POST['tags'] ?? '')),
            'screenshot_url' => trim((string)($_POST['screenshot_url'] ?? '')),
            'contact' => trim((string)($_POST['contact'] ?? '')),
        ];

        foreach ($data as $field => $value) {
            if (is_string($value) && isset($limits[$field]) && mb_strlen($value) > $limits[$field]) {
                header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('提交内容超过允许长度')));
                exit;
            }
        }

        foreach (['icon_url', 'official_url', 'github_url', 'screenshot_url'] as $field) {
            if (($data[$field] ?? '') !== '' && !Url::isSafe($data[$field])) {
                header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('网址必须是有效的 HTTP 或 HTTPS 地址')));
                exit;
            }
        }

        if ($data['category_id'] <= 0 || !Category::find($data['category_id'])) {
            header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('请选择有效的软件分类')));
            exit;
        }

        $channels = [];
        $types = (array)($_POST['channel_type'] ?? []);
        $names = (array)($_POST['channel_name'] ?? []);
        foreach ((array)($_POST['channel_url'] ?? []) as $index => $rawUrl) {
            $url = trim((string)$rawUrl);
            if ($url === '') {
                continue;
            }
            if (!Url::isSafe($url)) {
                header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('下载渠道包含无效网址')));
                exit;
            }
            $type = trim((string)($types[$index] ?? '第三方下载'));
            $name = trim((string)($names[$index] ?? ''));
            $channels[] = [
                'type' => $type !== '' ? $type : '第三方下载',
                'name' => $name !== '' ? $name : ($type !== '' ? $type : '下载渠道'),
                'url' => $url,
            ];
        }
        $data['download_channels'] = json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($data['name'] === '' || mb_strlen($data['name']) > 100 || $data['official_url'] === '') {
            header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('请填写软件名称和官网')));
            exit;
        }
        if (FilterService::containsBanned($data['name']) || FilterService::containsBanned($data['special_notes']) || FilterService::containsBanned($data['tags'])) {
            header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('提交内容包含敏感词，请修改后再提交')));
            exit;
        }

        if (!SoftwareCorrection::resubmitWithToken($token, $data)) {
            header('Location: ' . Url::target('?r=software/editCorrection&token=' . rawurlencode($token) . '&error=' . urlencode('重新提交失败，链接可能已失效')));
            exit;
        }

        View::render('software.edit_correction', [
            'title' => '重新提交成功',
            'success' => true,
            'correction' => $correction,
            'token' => '',
            'channels' => [],
            'categories' => Category::getAll(),
            'captchaHtml' => '',
        ]);
    }

    public function addResource(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => '请求方式非法']);
            return;
        }

        $softwareId = (int) ($_POST['software_id'] ?? 0);
        $software = Software::find($softwareId);
        if (!$software) {
            View::json(['success' => false, 'message' => '目标软件不存在']);
            return;
        }

        $currentUser = Auth::currentUser();
        if (Setting::get('require_login_submit_resource', '0') === '1' && !$currentUser) {
            View::json(['success' => false, 'message' => '本站已设置必须登录后才能提交相关资源，请先登录']);
            return;
        }
        if ($currentUser && User::isRestricted((int)$currentUser['id'], 'submit_resource')) {
            View::json(['success' => false, 'message' => '您的账号已被限制提交相关资源权限']);
            return;
        }

        // 资源是「匿名也能往软件详情页挂一条外链」的写入口，以前只有敏感词和协议前缀两道，
        // 人机验证与限流都没挂上 —— 脚本可以无限刷钓鱼链接。这里补齐。
        $rateIdentity = $currentUser ? 'user-' . (int)$currentUser['id'] : RateLimiter::clientIp();
        $rate = RateLimiter::consume('submit-resource', $rateIdentity, $currentUser ? 30 : 6, 3600);
        if (!$rate['allowed']) {
            View::json([
                'success' => false,
                'message' => '提交过于频繁，请 ' . max(1, (int)ceil($rate['retry_after'] / 60)) . ' 分钟后再试'
            ]);
            return;
        }

        // 算术题是一次性消费的，所以失败时顺手回一张新题：不然用户算错一次就得刷新整页
        if (!CaptchaService::verify($_POST, 'resource')) {
            View::json([
                'success' => false,
                'message' => '人机安全验证失败，请按新题目重新计算',
                'captchaHtml' => CaptchaService::renderHtml('resource')
            ]);
            return;
        }

        $type = trim($_POST['type'] ?? 'video');
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $author = trim($_POST['author'] ?? ($currentUser['username'] ?? ''));
        $description = trim($_POST['description'] ?? '');

        if (empty($title) || empty($url)) {
            View::json(['success' => false, 'message' => '请填写资源标题和有效链接']);
            return;
        }

        // 与提交软件同一套 URL 规则：只认 http(s)、拒控制字符与引号尖括号，长度也在这里兜住
        if (!Url::isSafe($url)) {
            View::json(['success' => false, 'message' => '链接必须是有效的 HTTP 或 HTTPS 地址，且不能包含空格或引号']);
            return;
        }

        // 列宽是硬的（title 255 / author 64），MySQL 严格模式下超长直接报错，SQLite 却默默收下
        if (mb_strlen($title) > 255) {
            View::json(['success' => false, 'message' => '资源标题请控制在 255 字以内']);
            return;
        }
        if (mb_strlen($author) > 64) {
            $author = mb_substr($author, 0, 64);
        }
        if (mb_strlen($description) > 2000) {
            $description = mb_substr($description, 0, 2000);
        }

        if (FilterService::containsBanned($title) || FilterService::containsBanned($description)) {
            View::json(['success' => false, 'message' => '提交内容包含违规词汇']);
            return;
        }

        $id = SoftwareResource::create([
            'software_id' => $softwareId,
            'user_id' => $currentUser ? (int)$currentUser['id'] : null,
            'type' => in_array($type, ['video', 'article', 'config', 'patch']) ? $type : 'video',
            'title' => $title,
            'url' => $url,
            'author' => $author,
            'description' => $description,
            'submitter_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'status' => 'published'
        ]);

        View::json([
            'success' => true,
            'message' => '资源提交成功，已向全站展示！',
            'resource' => [
                'id' => $id,
                'type' => $type,
                'title' => htmlspecialchars($title),
                'url' => htmlspecialchars($url),
                'author' => htmlspecialchars($author),
                'description' => htmlspecialchars($description),
                'created_at' => date('Y-m-d')
            ]
        ]);
    }

    /**
     * 提交新软件收录路由桥接 (支持 ?r=software/submit)
     */
    public function submit(): void {
        $submitCtrl = new SubmitController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $submitCtrl->save();
        } else {
            $submitCtrl->index();
        }
    }
}
