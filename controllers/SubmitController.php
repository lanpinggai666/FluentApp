<?php
/**
 * 蓝屏钙软件导航网 - 软件提交控制器
 */

class SubmitController {
    public function index(): void {
        $categories = Category::getAll();
        $captchaHtml = CaptchaService::renderHtml('submit');

        View::render('submit.index', [
            'title' => '提交收录软件 - ' . Setting::get('site_name', '蓝屏钙软件导航'),
            'categories' => $categories,
            'captchaHtml' => $captchaHtml,
            'success' => $_GET['success'] ?? null,
            'error' => $_GET['error'] ?? null
        ]);
    }

    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=submit'));
            exit;
        }

        // 访问门槛与权限校验
        $currentUser = Auth::currentUser();
        if (Setting::get('require_login_submit_software', '0') === '1' && !$currentUser) {
            $this->respondError('本站已设置必须登录后才能提交新软件收录，请先登录');
            return;
        }
        if ($currentUser && User::isRestricted((int)$currentUser['id'], 'submit_software')) {
            $this->respondError('您的账号已被限制提交新软件权限');
            return;
        }

        // 人机验证
        if (!CaptchaService::verify($_POST, 'submit')) {
            $this->respondError('人机安全验证失败，请重试');
            return;
        }

        // 必填项提取与校验
        $name = trim($_POST['name'] ?? '');
        $iconUrl = trim($_POST['icon_url'] ?? '');
        $officialUrl = trim($_POST['official_url'] ?? '');
        $submitterEmail = trim($_POST['submitter_email'] ?? ($currentUser['email'] ?? ''));

        if (empty($name)) {
            $this->respondError('软件名称为必填项');
            return;
        }
        if (empty($officialUrl)) {
            $this->respondError('官方网站为必填项');
            return;
        }
        if ($submitterEmail === '' || !filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
            $this->respondError('请填写有效的联系邮箱');
            return;
        }

        // 可选信息字段收集：special_notes 为一句话特别说明(纯文本)，description_md 为 Markdown 长说明
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $specialNotes = trim($_POST['special_notes'] ?? '');
        $descriptionMd = trim($_POST['description_md'] ?? '');
        if (mb_strlen($name) > 100 || mb_strlen($specialNotes) > 200 || mb_strlen($descriptionMd) > 10000 || mb_strlen($submitterEmail) > 254) {
            $this->respondError('提交内容超过允许长度');
            return;
        }
        $priceType = in_array($_POST['price_type'] ?? '', ['free', 'paid', 'freemium']) ? $_POST['price_type'] : 'free';
        
        // 收费方式与价格信息收集 (仅在非免费时记录)
        $priceMethod = trim($_POST['price_method'] ?? '');
        $priceAmount = trim($_POST['price_amount'] ?? '');
        $priceDesc = '';
        if ($priceType !== 'free') {
            $priceParts = array_filter([$priceMethod, $priceAmount]);
            $priceDesc = implode(' ', $priceParts);
        }

        // 源码与开源协议：若闭源则严格不收集协议
        $isOpenSource = isset($_POST['is_open_source']) ? (int)$_POST['is_open_source'] : 1;
        $license = ($isOpenSource === 1) ? trim($_POST['license'] ?? '开源协议') : null;

        $developer = trim($_POST['developer'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $installType = trim($_POST['install_type'] ?? '');
        $supportedLanguages = trim($_POST['supported_languages'] ?? '');
        $osRequirements = trim($_POST['os_requirements'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $githubUrl = trim($_POST['github_url'] ?? '');

        // 链接只接受 http/https 绝对地址：伪协议与含空格引号的值会直接落到前端 href 上
        foreach (['官方网站' => $officialUrl, '图标链接' => $iconUrl, 'GitHub 地址' => $githubUrl] as $fieldLabel => $linkValue) {
            if ($linkValue !== '' && !Url::isSafe($linkValue)) {
                $this->respondError($fieldLabel . '必须填写有效的 http/https 地址，且不能包含空格或引号');
                return;
            }
        }

        // 图标留空就抓官网自己声明的 favicon：让提交者去找 256×256 直链是最劝退的一步。
        // 限时 3 秒、按域名缓存，抓不到就用站内占位图 —— 不因此打回提交，也不让人白等。
        if ($iconUrl === '') {
            $iconUrl = Favicon::discover($officialUrl) ?: self::placeholderIcon();
        }

        // 处理下拉分类的其它获取渠道 (Github, Microsoft Store, 第三方下载站带下载方式名称)
        $otherUrls = [];
        $channelTypes = $_POST['channel_type'] ?? [];
        $channelNames = $_POST['channel_name'] ?? [];
        $channelUrls = $_POST['channel_url'] ?? [];

        if (is_array($channelTypes) && is_array($channelUrls)) {
            $seenChannels = [];
            for ($i = 0; $i < count($channelUrls); $i++) {
                $cType = trim($channelTypes[$i] ?? '第三方下载站');
                $cName = trim($channelNames[$i] ?? '');
                $cUrl = trim($channelUrls[$i] ?? '');

                if (!empty($cUrl)) {
                    if (!Url::isSafe($cUrl)) {
                        $this->respondError('下载渠道包含无效网址');
                        return;
                    }
                    $channelKey = strtolower(rtrim($cUrl, '/'));
                    if (isset($seenChannels[$channelKey])) {
                        continue;
                    }
                    $seenChannels[$channelKey] = true;
                    $otherUrls[] = [
                        'type' => $cType,
                        'name' => $cName ?: ($cType === '第三方下载站' ? '网盘下载' : $cType),
                        'url' => $cUrl
                    ];
                    // 若渠道类型为 Github 且未填 github_url，自动关联
                    if ($cType === 'Github' && empty($githubUrl)) {
                        $githubUrl = $cUrl;
                    }
                }
            }
        }

        // 规范化标签
        if (!empty($tags)) {
            $tagsArray = preg_split('/[\s,，]+/u', $tags, -1, PREG_SPLIT_NO_EMPTY);
            $tags = implode(',', array_slice(array_unique($tagsArray), 0, 8));
        }

        // 敏感词过滤
        if (
            FilterService::containsBanned($name) ||
            FilterService::containsBanned($tags) ||
            FilterService::containsBanned($specialNotes) ||
            FilterService::containsBanned($descriptionMd) ||
            FilterService::containsBanned($developer) ||
            FilterService::containsBanned($publisher)
        ) {
            $this->respondError('提交的内容包含敏感词，请修改后再提交');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $softwareData = [
            'name' => $name,
            'owner_user_id' => $currentUser ? (int)$currentUser['id'] : null,
            'icon_url' => $iconUrl,
            'official_url' => $officialUrl,
            'github_url' => $githubUrl ?: null,
            'other_urls' => !empty($otherUrls) ? json_encode($otherUrls, JSON_UNESCAPED_UNICODE) : null,
            // 没选分类就写 0（=「等 AI 判」）。列本身 NOT NULL DEFAULT 1，显式传 null 会直接违反约束；
            // 而 0 又正好是 AiService 认的「空分类」，审核时当场补全会把它换成真正的分类。
            'category_id' => $categoryId > 0 ? $categoryId : 0,
            'special_notes' => $specialNotes ?: null,
            'description_md' => $descriptionMd ?: null,
            'price_type' => $priceType,
            'price_desc' => $priceDesc ?: null,
            'is_open_source' => $isOpenSource,
            'license' => $license,
            'developer' => $developer ?: null,
            'publisher' => $publisher ?: null,
            'install_type' => $installType ?: null,
            'supported_languages' => $supportedLanguages ?: null,
            'os_requirements' => $osRequirements ?: null,
            'tags' => $tags ?: null,
            'submitter_email' => $submitterEmail,
            'status' => 'pending', // 默认进入待审核池
            'submitter_ip' => $ip,
        ];

        // 用户没填的那些详情页字段**不在这里补**：提交的人不该为 AI 等几十秒，
        // 也没必要在还没过审的资料上烧一次外呼。管理员点「AI 补全后发布」时当场生成。
        Software::create($softwareData);

        if ($this->isAjax()) {
            View::json([
                'success' => true,
                'message' => '软件提交成功！已进入待审核池，审核通过后资料会由 AI 补全，审核结果将发送至 ' . $submitterEmail . '。'
            ]);
            return;
        }

        header('Location: ' . Url::target('?r=submit&success=1'));
        exit;
    }

    /**
     * 驳回后的安全修改页。
     * 令牌为一次性 SHA-256 摘要校验，明文只存在于审核邮件中。
     */
    public function edit(): void {
        $token = trim((string)($_GET['token'] ?? ''));
        $software = Software::findByEditToken($token);

        if (!$software) {
            http_response_code(404);
            View::render('home.404', ['title' => '修改链接无效或已过期']);
            return;
        }

        $channels = [];
        if (!empty($software['other_urls'])) {
            $decoded = json_decode((string)$software['other_urls'], true);
            $channels = is_array($decoded) ? $decoded : [];
        }

        View::render('submit.edit', [
            'title' => '修改软件提交 - ' . Setting::get('site_name', '蓝屏钙软件导航'),
            'software' => $software,
            'token' => $token,
            'channels' => $channels,
            'categories' => Category::getAll(),
            'captchaHtml' => CaptchaService::renderHtml('submit'),
            'error' => $_GET['error'] ?? null,
            'success' => !empty($_GET['success']),
        ]);
    }

    /** 提交者修改后重新提交审核。 */
    public function resubmit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::target('?r=home'));
            exit;
        }

        $token = trim((string)($_POST['token'] ?? ''));
        $software = Software::findByEditToken($token);

        if (!$software) {
            http_response_code(404);
            View::render('home.404', ['title' => '修改链接无效或已过期']);
            return;
        }

        $back = '?r=submit/edit&token=' . rawurlencode($token);

        if (!CaptchaService::verify($_POST, 'submit')) {
            header('Location: ' . $back . '&error=' . urlencode('人机安全验证失败，请重试'));
            exit;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $officialUrl = trim((string)($_POST['official_url'] ?? ''));
        $iconUrl = trim((string)($_POST['icon_url'] ?? ''));
        $email = trim((string)($_POST['submitter_email'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            header('Location: ' . $back . '&error=' . urlencode('请填写有效的软件名称'));
            exit;
        }
        // 图标允许留空：留空就按新的官网重抓一次，抓不到则沿用原来的图标（别把已有图标抹掉）
        if (!Url::isSafe($officialUrl)) {
            header('Location: ' . $back . '&error=' . urlencode('官网地址必须是有效的 HTTP 或 HTTPS 地址'));
            exit;
        }
        if ($iconUrl !== '' && !Url::isSafe($iconUrl)) {
            header('Location: ' . $back . '&error=' . urlencode('图标地址必须是有效的 HTTP 或 HTTPS 地址'));
            exit;
        }
        if ($iconUrl === '') {
            $iconUrl = Favicon::discover($officialUrl)
                ?: (string) ($software['icon_url'] ?? '')
                ?: self::placeholderIcon();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            header('Location: ' . $back . '&error=' . urlencode('必须填写有效的联系邮箱'));
            exit;
        }

        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0 || !Category::find($categoryId)) {
            header('Location: ' . $back . '&error=' . urlencode('请选择有效的软件分类'));
            exit;
        }

        $specialNotes = trim((string)($_POST['special_notes'] ?? ''));
        $tags = $this->normalizeTags((string)($_POST['tags'] ?? ''));

        foreach ([$name, $tags, $specialNotes] as $value) {
            if (FilterService::containsBanned($value)) {
                header('Location: ' . $back . '&error=' . urlencode('提交的内容包含敏感词，请修改后重试'));
                exit;
            }
        }

        $isOpenSource = !empty($_POST['is_open_source']) ? 1 : 0;
        $priceType = in_array($_POST['price_type'] ?? '', ['free', 'paid', 'freemium'], true) ? $_POST['price_type'] : 'free';
        $priceMethod = trim((string)($_POST['price_method'] ?? ''));
        $priceAmount = trim((string)($_POST['price_amount'] ?? ''));
        $priceDesc = $priceType === 'free' ? null : (implode(' ', array_filter([$priceMethod, $priceAmount])) ?: null);

        $channels = $this->collectChannels();
        $otherUrls = !empty($channels) ? json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $updates = [
            'name' => $name,
            'official_url' => $officialUrl,
            'icon_url' => $iconUrl,
            'github_url' => $this->validUrlOrNull((string)($_POST['github_url'] ?? '')),
            'category_id' => $categoryId,
            'special_notes' => $specialNotes !== '' ? $specialNotes : null,
            'price_type' => $priceType,
            'price_desc' => $priceDesc,
            'is_open_source' => $isOpenSource,
            'license' => $isOpenSource === 1 ? (trim((string)($_POST['license'] ?? '')) ?: null) : null,
            'developer' => trim((string)($_POST['developer'] ?? '')) ?: null,
            'publisher' => trim((string)($_POST['publisher'] ?? '')) ?: null,
            'os_requirements' => trim((string)($_POST['os_requirements'] ?? '')) ?: null,
            'supported_languages' => trim((string)($_POST['supported_languages'] ?? '')) ?: null,
            'install_type' => trim((string)($_POST['install_type'] ?? '')) ?: null,
            'tags' => $tags !== '' ? $tags : null,
            'other_urls' => $otherUrls,
            'submitter_email' => $email,
        ];

        if ($updates['name'] !== ($software['name'] ?? '')) {
            $updates['slug'] = Software::generateSlug($updates['name']);
        }

        if (!Software::resubmitWithToken($token, $updates)) {
            header('Location: ' . $back . '&error=' . urlencode('重新提交失败，链接可能已被使用或过期'));
            exit;
        }

        // 这里不触发 AI：重新提交只是回到待审核池，资料补全等管理员审核通过时当场做。
        header('Location: ' . $back . '&success=1');
        exit;
    }

    private function collectChannels(): array {
        $types = (array)($_POST['channel_type'] ?? []);
        $names = (array)($_POST['channel_name'] ?? []);
        $urls = (array)($_POST['channel_url'] ?? []);

        $result = [];
        foreach ($urls as $index => $rawUrl) {
            $url = trim((string)$rawUrl);
            if ($url === '' || !Url::isSafe($url)) {
                continue;
            }
            $type = trim((string)($types[$index] ?? '第三方下载站')) ?: '第三方下载站';
            $name = trim((string)($names[$index] ?? ''));
            $result[] = [
                'type' => mb_substr($type, 0, 50),
                'name' => mb_substr($name !== '' ? $name : $type, 0, 100),
                'url' => mb_substr($url, 0, 500),
            ];
        }
        return $result;
    }

    private function normalizeTags(string $tags): string {
        $tags = trim($tags);
        if ($tags === '') {
            return '';
        }
        $parts = preg_split('/[\s,，]+/u', $tags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(',', array_slice(array_unique($parts), 0, 8));
    }

    private function validUrlOrNull(string $url): ?string {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        return Url::isSafe($url) ? $url : null;
    }

    /**
     * 站内占位图标的绝对地址。
     * icon_url 之后还要过 Url::isSafe（后台编辑、修正表单都按绝对 http 地址校验），
     * 所以这里不能存 /assets/... 这种根相对路径。
     */
    private static function placeholderIcon(): string
    {
        return rtrim(Url::siteBase(), '/') . '/assets/images/default-app.svg';
    }

    private function isAjax(): bool {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['is_ajax']);
    }

    private function respondError(string $msg): void {
        if ($this->isAjax()) {
            View::json(['success' => false, 'message' => $msg]);
            return;
        }
        header('Location: ' . Url::target('?r=submit&error=' . urlencode($msg)));
        exit;
    }
}
