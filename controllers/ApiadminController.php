<?php
/**
 * 开放 API —— 后台管理能力（供 App / 桌面端调用）
 *
 * 入口：/api/v1/<action>（或 ?r=apiadmin/<action>）
 * 鉴权：请求头 X-Api-Key（后台【开放 API 密钥】里生成）+ body 里的 username / password。
 *      三件套缺一不可，见 core/ApiAuth。
 *
 * 约定：
 *   - 所有响应都是 JSON 信封 {success, data, meta} 或 {success:false, error:{code,message}}；
 *   - 读端点只需要 read 作用域，写端点需要 write 作用域且只接受 POST/PUT/PATCH/DELETE；
 *   - 写操作的校验与副作用全部复用网页后台那套 service，App 不会绕过任何规则；
 *   - 内部字段（提交者 IP、一次性修改令牌摘要、通知错误详情）一律不外传。
 */

class ApiadminController
{
    /** 任何会被后台网页用的敏感列，API 响应里一律剔除 */
    private const HIDDEN_COLUMNS = [
        'submitter_email', 'submitter_ip', 'edit_token_hash', 'edit_token_expires_at',
        'notification_error', 'key_hash', 'password_hash',
    ];

    public function ping(): void
    {
        ApiAuth::guard('GET /ping', 'read');
        $context = (array) Api::context();
        Api::ok([
            'site_name' => Setting::get('site_name', ''),
            'server_time' => date('c'),
            'schema_version' => (int) require BASE_PATH . '/database/version.php',
            'granted_scopes' => $context['scopes'] ?? [],
        ]);
    }

    public function overview(): void
    {
        ApiAuth::guard('GET /overview', 'read');
        $reportStats = Report::getStats();
        Api::ok([
            'software_total' => Software::count([]),
            'software_published' => Software::count(['status' => 'published']),
            'software_pending' => count(Software::getPendingList()),
            'category_total' => count(Category::getAll()),
            'comment_pending' => (int) Database::fetch("SELECT COUNT(*) AS c FROM comments WHERE status='pending'")['c'],
            'correction_pending' => SoftwareCorrection::countPending(),
            'report_pending' => (int) ($reportStats['pending'] ?? Report::countPending()),
            'resource_pending' => (int) Database::fetch("SELECT COUNT(*) AS c FROM software_resources WHERE status='pending'")['c'],
            'user_total' => User::countAll(),
            'api_keys_active' => ApiKey::activeCount(),
            'api_calls_total' => ApiLog::count(),
            'api_calls_denied' => ApiLog::count('denied'),
        ]);
    }

    public function softwares(): void
    {
        ApiAuth::guard('GET /softwares', 'read');
        $page = Api::page();
        $per = Api::per();
        $options = [
            'search' => Api::stringParam('q', '', 120),
            'category_id' => Api::intParam('category_id', 0) ?: null,
            'status' => Api::stringParam('status', '', 20) ?: null,
            'featured_only' => Api::boolParam('featured_only'),
            'limit' => $per,
            'offset' => ($page - 1) * $per,
        ];
        $rows = array_map([$this, 'publicRow'], Software::getList($options));
        Api::ok($rows, ['page' => $page, 'per_page' => $per, 'total' => Software::count($options)]);
    }

    public function software(): void
    {
        ApiAuth::guard('GET /software', 'read');
        $row = $this->locateSoftware();
        if ($row === null) {
            Api::fail('not_found', '软件不存在', 404);
        }
        $detail = $this->publicRow($row);
        $detail['channels'] = SoftwareCorrection::normalizeForDisplay($this->decodeChannels((string) ($row['other_urls'] ?? '')));
        $detail['comments'] = array_map([$this, 'publicRow'], Comment::getBySoftware((int) $row['id'], 100));
        $detail['rating_distribution'] = Comment::getRatingDistribution((int) $row['id']);
        $detail['resources'] = array_map([$this, 'publicRow'], SoftwareResource::getBySoftwareId((int) $row['id']));
        $detail['related'] = array_map([$this, 'publicRow'], Software::getRelated((int) $row['category_id'], (int) $row['id'], 6));
        $detail['github'] = GithubService::parseRepo((string) ($row['github_url'] ?? ''));
        Api::ok($detail);
    }

    public function pending(): void
    {
        ApiAuth::guard('GET /pending', 'read');
        Api::ok(array_map([$this, 'publicRow'], Software::getPendingList()));
    }

    /** 审核一篇提交：action = approve | approve_ai | reject（驳回必须给 review_note） */
    public function review(): void
    {
        ApiAuth::guard('POST /review', 'write');
        Api::requireWriteMethod();
        $result = ModerationService::reviewSoftware($this->requestFields([
            'id', 'action', 'review_note', 'category_id', 'special_notes', 'description_md',
        ]));
        if (!$result['ok']) {
            Api::fail($result['code'], $result['message'], $result['code'] === 'not_pending' || $result['code'] === 'already_handled' ? 409 : 422);
        }
        Api::ok($result['data'], ['message' => $result['message']]);
    }

    public function softwareSave(): void
    {
        ApiAuth::guard('POST /software/save', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        // 白名单里没有 github_release_keyword：那一列现在没有任何代码读取（只有 schema/seed 还留着），
        // 接了参数只会让 App 以为改了 Release 文件过滤生效。要恢复功能得先把它接回 GithubService。
        $result = SoftwareWriteService::save($this->requestFields([
            'name', 'slug', 'icon_url', 'official_url', 'github_url', 'category_id',
            'special_notes', 'description_md', 'status', 'is_featured', 'price_type', 'price_desc',
            'is_open_source', 'license', 'developer', 'publisher', 'tags', 'screenshot_url',
            'channel_url', 'channel_type', 'channel_name', 'other_urls',
        ]), $id);
        if (!$result['ok']) {
            Api::fail('invalid_software', $result['errors'][0] ?? '保存失败', 422, ['errors' => $result['errors']]);
        }
        Api::ok(['id' => $result['id'], 'created' => $id <= 0], ['message' => $id > 0 ? '已更新' : '已创建']);
    }

    public function softwareDelete(): void
    {
        ApiAuth::guard('POST /software/delete', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        $row = Software::find($id);
        if (!$row) {
            Api::fail('not_found', '软件不存在', 404);
        }
        // 删除是整条链路里唯一不可逆的动作，要求显式确认，避免 App 一次误触清掉收录
        if (!Api::boolParam('confirm')) {
            Api::fail('confirm_required', '删除软件需要同时提交 confirm=1（会连带删除其评论、举报与资源）', 428);
        }
        Software::delete($id);
        Api::ok(['id' => $id, 'name' => $row['name']], ['message' => '已删除']);
    }

    public function categories(): void
    {
        ApiAuth::guard('GET /categories', 'read');
        Api::ok(array_map([$this, 'publicRow'], Category::getAllWithCounts()));
    }

    public function categorySave(): void
    {
        ApiAuth::guard('POST /category/save', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        $name = Api::stringParam('name', '', 50);
        $slug = Api::stringParam('slug', '', 50) ?: $name;
        if ($name === '') {
            Api::fail('invalid_param', '分类名称不能为空', 422, ['field' => 'name']);
        }
        if ($id > 0) {
            Category::update($id, ['name' => $name, 'slug' => $slug,
                'icon' => Api::stringParam('icon', 'folder', 32) ?: 'folder',
                'sort_order' => Api::intParam('sort_order', 0)]);
            Api::ok(['id' => $id], ['message' => '已更新']);
        }
        $newId = Category::create($name, $slug, Api::stringParam('icon', 'folder', 32) ?: 'folder', Api::intParam('sort_order', 0));
        Api::ok(['id' => (int) $newId], ['message' => '已创建']);
    }

    public function categoryDelete(): void
    {
        ApiAuth::guard('POST /category/delete', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        if (!Category::find($id)) {
            Api::fail('not_found', '分类不存在', 404);
        }
        $used = (int) Database::fetch('SELECT COUNT(*) AS c FROM softwares WHERE category_id = :c', ['c' => $id])['c'];
        if ($used > 0) {
            Api::fail('category_in_use', '这个分类下还有 ' . $used . ' 个软件，请先迁移再删除', 409, ['software_count' => $used]);
        }
        Category::delete($id);
        Api::ok(['id' => $id], ['message' => '已删除']);
    }

    public function mirrors(): void
    {
        ApiAuth::guard('GET /mirrors', 'read');
        Api::ok(array_map([$this, 'publicRow'], GithubMirror::getAll()));
    }

    public function mirrorSave(): void
    {
        ApiAuth::guard('POST /mirror/save', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        $name = Api::stringParam('name', '', 64);
        $template = Api::stringParam('url_template', '', 500);
        $type = in_array(Api::stringParam('type', 'download', 10), ['download', 'web', 'both'], true)
            ? Api::stringParam('type', 'download', 10) : 'download';

        // 与网页后台同一套「学格式」流程：类型由调用方定，模板靠真实链接反推并自证
        $sample = Api::stringParam('sample_url', '', 500);
        $original = Api::stringParam('original_url', '', 500);
        $style = Api::stringParam('url_style', '', 12);
        if ($template === '' && $sample !== '') {
            $learned = GithubService::learnMirrorFromSample($sample, $type, $original);
            if (empty($learned['success'])) {
                Api::fail('mirror_not_learned', $learned['message'], 422, ['sample_url' => $sample]);
            }
            $template = (string) $learned['url_template'];
            $name = $name !== '' ? $name : (string) $learned['name'];
            $original = (string) $learned['original_url'];
            $style = (string) $learned['style'];
        }
        if ($name === '' || $template === '') {
            Api::fail('invalid_mirror', '镜像名称与 URL 模板都不能为空（可只给 sample_url 让服务端学）', 422);
        }
        if (!Url::isSafe($template) && !preg_match('#^https?://#i', $template)) {
            Api::fail('invalid_mirror', 'URL 模板必须是 http/https 地址', 422, ['field' => 'url_template']);
        }
        // 给了样例就复核：模板映射出来的必须就是那条真实链接
        if ($sample !== '' && $original !== '') {
            $built = GithubService::buildMirrorUrl($template, $original, $style);
            if ($built !== $sample) {
                Api::fail('mirror_mismatch', '模板与样例对不上：映射得到 ' . $built . '，你贴的是 ' . $sample, 422,
                    ['built' => $built, 'sample' => $sample]);
            }
        }

        if ($id > 0) {
            GithubMirror::update($id, [
                'name' => $name,
                'url_template' => $template,
                'type' => $type,
                'sample_url' => $sample !== '' ? $sample : null,
                'sample_original' => $original !== '' ? $original : null,
                'url_style' => $style,
                'is_active' => Api::param('is_active', true) ? 1 : 0,
                'sort_order' => $this->mirrorOrder(),
            ]);
            Api::ok(['id' => $id, 'url_template' => $template, 'url_style' => $style], ['message' => '已更新']);
        }
        $newId = GithubMirror::add($name, $template, $this->mirrorOrder(), $type,
            $sample !== '' ? $sample : null, $style, $original !== '' ? $original : null);
        Api::ok(['id' => (int) $newId], ['message' => '已添加']);
    }

    public function mirrorDelete(): void
    {
        ApiAuth::guard('POST /mirror/delete', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        if ($id <= 0 || GithubMirror::delete($id) <= 0) {
            Api::fail('not_found', '镜像源不存在或未删除', 404);
        }
        Api::ok(['id' => $id], ['message' => '已删除']);
    }

    public function notices(): void
    {
        ApiAuth::guard('GET /notices', 'read');
        Api::ok(array_map([$this, 'publicRow'], Notice::getAll()));
    }

    public function noticeSave(): void
    {
        ApiAuth::guard('POST /notice/save', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        $title = Api::stringParam('title', '', 255);
        $content = Api::stringParam('content', '', 60000);
        if ($title === '' || $content === '') {
            Api::fail('invalid_notice', '公告标题与内容都不能为空', 422);
        }
        $fields = ['title' => $title, 'content' => $content,
            'is_active' => Api::boolParam('is_active', true) ? 1 : 0,
            'sort_order' => Api::intParam('sort_order', 0)];
        if ($id > 0) {
            Notice::update($id, $fields);
            Api::ok(['id' => $id], ['message' => '已更新']);
        }
        $newId = Notice::add($title, $content, $fields['is_active'], $fields['sort_order']);
        Api::ok(['id' => (int) $newId], ['message' => '已创建']);
    }

    public function noticeDelete(): void
    {
        ApiAuth::guard('POST /notice/delete', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        if ($id <= 0 || Notice::delete($id) <= 0) {
            Api::fail('not_found', '公告不存在或未删除', 404);
        }
        Api::ok(['id' => $id], ['message' => '已删除']);
    }

    public function comments(): void
    {
        ApiAuth::guard('GET /comments', 'read');
        $page = Api::page();
        $per = Api::per();
        $rows = array_map([$this, 'publicRow'], Comment::getAllAdmin($per, ($page - 1) * $per));
        $total = (int) Database::fetch('SELECT COUNT(*) AS c FROM comments')['c'];
        Api::ok($rows, ['page' => $page, 'per_page' => $per, 'total' => $total]);
    }

    public function commentDelete(): void
    {
        ApiAuth::guard('POST /comment/delete', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        if ($id <= 0 || Comment::delete($id) <= 0) {
            Api::fail('not_found', '评论不存在或未删除', 404);
        }
        Api::ok(['id' => $id], ['message' => '已删除']);
    }

    public function reports(): void
    {
        ApiAuth::guard('GET /reports', 'read');
        $status = Api::stringParam('status', '', 20) ?: null;
        $type = Api::stringParam('type', '', 20) ?: null;
        Api::ok(array_map([$this, 'publicRow'], Report::getAllAdmin($status, $type)), ['stats' => Report::getStats()]);
    }

    public function reportUpdate(): void
    {
        ApiAuth::guard('POST /report/update', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        $status = Api::stringParam('status', '', 20);
        if (!Report::find($id)) {
            Api::fail('not_found', '举报不存在', 404);
        }
        if (!in_array($status, ['pending', 'reviewing', 'resolved', 'rejected'], true)) {
            Api::fail('invalid_status', '举报状态只能是 pending/reviewing/resolved/rejected', 422, ['field' => 'status']);
        }
        Report::updateStatus($id, $status, Api::stringParam('review_reason', '', 500) ?: null);
        Api::ok(['id' => $id, 'status' => $status], ['message' => '已更新']);
    }

    public function corrections(): void
    {
        ApiAuth::guard('GET /corrections', 'read');
        Api::ok(array_map([$this, 'publicRow'], SoftwareCorrection::getPending()));
    }

    public function correctionReview(): void
    {
        ApiAuth::guard('POST /correction/review', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        $action = Api::stringParam('action', '', 10);
        $note = Api::stringParam('review_note', '', 500);
        if (!in_array($action, ['approve', 'reject'], true)) {
            Api::fail('invalid_param', 'action 只能是 approve 或 reject', 422, ['field' => 'action']);
        }
        if ($action === 'reject' && $note === '') {
            Api::fail('review_note_required', '驳回修正申请时必须填写理由', 422);
        }
        $done = $action === 'approve' ? SoftwareCorrection::approve($id, $note ?: null) : SoftwareCorrection::reject($id, $note ?: null);
        if (!$done) {
            Api::fail('not_found', '修正申请不存在或已被处理', 409);
        }
        Api::ok(['id' => $id, 'action' => $action], ['message' => $action === 'approve' ? '已通过' : '已驳回']);
    }

    public function resources(): void
    {
        ApiAuth::guard('GET /resources', 'read');
        $softwareId = Api::intParam('software_id', 0);
        $status = Api::stringParam('status', 'pending', 20);
        if ($softwareId > 0) {
            $rows = SoftwareResource::getBySoftwareId($softwareId, $status ?: 'published');
        } else {
            $rows = Database::fetchAll('SELECT * FROM software_resources WHERE status = :s ORDER BY id DESC LIMIT 200',
                ['s' => $status ?: 'pending']);
        }
        Api::ok(array_map([$this, 'publicRow'], $rows));
    }

    public function users(): void
    {
        ApiAuth::guard('GET /users', 'read');
        $page = Api::page();
        $per = Api::per();
        $search = Api::stringParam('q', '', 120);
        $rows = array_map([$this, 'publicRow'], User::getAllAdmin($per, ($page - 1) * $per, $search));
        Api::ok($rows, ['page' => $page, 'per_page' => $per, 'total' => User::countAll($search)]);
    }

    public function userPunish(): void
    {
        ApiAuth::guard('POST /user/punish', 'write');
        Api::requireWriteMethod();
        $result = UserAdminService::punish([
            'user_id' => Api::intParam('user_id', 0),
            'permanent' => Api::boolParam('permanent'),
            'banned_until' => Api::stringParam('banned_until', '', 64),
            'ban_reason' => Api::stringParam('ban_reason', '', 500),
            'restricted_permissions' => Api::param('restricted_permissions', []),
        ]);
        $this->renderUserResult($result);
    }

    public function userUnpunish(): void
    {
        ApiAuth::guard('POST /user/unpunish', 'write');
        Api::requireWriteMethod();
        $result = UserAdminService::unpunish(Api::intParam('user_id', 0));
        $this->renderUserResult($result);
    }

    public function userBadgeSave(): void
    {
        ApiAuth::guard('POST /user/badge/save', 'write');
        Api::requireWriteMethod();
        // 数组与后台表单那种逗号字符串都收，让 App 不必关心两边表单差异
        $badges = Api::param('badges', '');
        $result = UserAdminService::setBadges(Api::intParam('user_id', 0), $badges === null ? '' : $badges);
        $this->renderUserResult($result);
    }

    /** UserAdminService 的返回 → JSON 信封；错误码到 HTTP 状态的映射集中在一处 */
    private function renderUserResult(array $result): void
    {
        if (!empty($result['ok'])) {
            Api::ok($result['data'], ['message' => $result['message']]);
        }
        $status = match ((string) ($result['code'] ?? '')) {
            'not_found' => 404,
            'admin_target', 'self_target' => 403,
            default => 422,
        };
        Api::fail((string) ($result['code'] ?? 'invalid_user_action'), (string) ($result['message'] ?? '操作失败'), $status);
    }

    public function words(): void
    {
        ApiAuth::guard('GET /words', 'read');
        Api::ok(Database::fetchAll('SELECT id, word, replacement, created_at FROM sensitive_words ORDER BY id DESC LIMIT 500'));
    }

    public function wordSave(): void
    {
        ApiAuth::guard('POST /word/save', 'write');
        Api::requireWriteMethod();
        $words = Api::param('words', null);
        if (!is_array($words)) {
            $words = $words === null ? [Api::stringParam('word', '', 64)] : [(string) $words];
        }
        $added = FilterService::addWords($words, Api::stringParam('replacement', '***', 16) ?: '***');
        Api::ok(['added' => $added], ['message' => '新增 ' . (int) $added . ' 条（重复的会被跳过）']);
    }

    public function wordDelete(): void
    {
        ApiAuth::guard('POST /word/delete', 'write');
        Api::requireWriteMethod();
        $id = Api::intParam('id', 0);
        if (!Database::fetch('SELECT id FROM sensitive_words WHERE id = :id', ['id' => $id])) {
            Api::fail('not_found', '敏感词不存在', 404);
        }
        FilterService::deleteWord($id);
        Api::ok(['id' => $id], ['message' => '已删除']);
    }

    /** 设置读取：密钥类字段永远只报告“是否已设置”，值绝不出库 */
    public function settings(): void
    {
        ApiAuth::guard('GET /settings', 'read');
        $all = Setting::loadAll();
        $out = [];
        foreach ($this->writableKeys() as $key) {
            if (!array_key_exists($key, $all)) {
                continue;
            }
            if (in_array($key, $this->writeOnlyKeys(), true)) {
                $out[$key] = ($all[$key] === null || $all[$key] === '') ? '' : '●●●已设置●●●';
                $out[$key . '_set'] = $all[$key] !== null && $all[$key] !== '';
                continue;
            }
            $out[$key] = $all[$key];
        }
        Api::ok($out, ['note' => '凭据类字段（SMTP 密码、AI Key、验证码私钥、OAuth secret）不返回明文']);
    }

    public function settingsSave(): void
    {
        ApiAuth::guard('POST /settings/save', 'write');
        Api::requireWriteMethod();
        $input = Api::input();
        $saved = [];
        $skipped = [];
        foreach ($this->writableKeys() as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = trim((string) $input[$key]);
            // 与网页后台同一条规则：凭据字段留空表示“沿用原值”，避免一次不完整提交把密钥清了
            if ($value === '' && in_array($key, $this->writeOnlyKeys(), true)) {
                $skipped[] = $key;
                continue;
            }
            if ($key === 'site_base_url' && $value !== '' && !preg_match('#^https?://[^\s\'"]+$#i', $value)) {
                Api::fail('invalid_base_url', 'site_base_url 必须是完整的 http(s) 地址', 422, ['field' => 'site_base_url']);
            }
            Setting::set($key, $value);
            $saved[] = $key;
        }
        if ($saved === [] && $skipped === []) {
            Api::fail('nothing_to_save', '没有提交任何可保存的设置项', 422, ['allowed' => $this->writableKeys()]);
        }
        Api::ok(['saved' => $saved, 'skipped_empty_secrets' => $skipped], ['message' => '已保存 ' . count($saved) . ' 项']);
    }

    /** 最近的 API 调用记录，App 端可以自己看“谁在用这把密钥做了什么” */
    public function logs(): void
    {
        ApiAuth::guard('GET /logs', 'read');
        $context = (array) Api::context();
        Api::ok(ApiLog::recent(Api::intParam('limit', 50), isset($context['key_id']) ? (int) $context['key_id'] : null));
    }

    /**
     * 当场为某条软件跑一次 AI 补全并写库 —— 与后台「重新生成」按钮、审核时的当场补全是同一份实现。
     * 默认只补仍空着的字段；overwrite=true 先清掉上次 AI 生成的文本字段再整段重写。
     * 这是同步外呼，最长约 ai_total_timeout 秒，调用方要把自己的读超时设得比它大。
     */
    public function aiGenerate(): void
    {
        ApiAuth::guard('POST /aiGenerate', 'write');
        Api::requireWriteMethod();
        $input = Api::input();
        $id = (int) ($input['software_id'] ?? 0);
        if ($id <= 0) {
            Api::fail('invalid_param', 'software_id 必须是正整数', 422, ['field' => 'software_id']);
            return;
        }
        $overwrite = in_array(strtolower((string) ($input['overwrite'] ?? '')), ['1', 'true', 'yes', 'on'], true);

        $res = AiService::completeSoftware($id, 'manual_retry', $overwrite);
        if (!$res['ok']) {
            Api::fail('ai_generate_failed', (string) ($res['error'] ?: 'AI 补全失败'), 502, ['software_id' => $id]);
            return;
        }
        $note = AiService::describeRun($res);
        Api::ok([
            'software_id' => $id,
            'source' => $res['source'],
            'provider' => $res['provider'],
            'attempts' => $res['attempts'],
            'filled' => $res['filled'],
            'cleared' => $res['cleared'],
            'note' => $note,
        ], ['message' => $note]);
    }

    /**
     * AI 可用性自检。「资料全是占位内容」通常不是模型的问题，而是开关没开、线路没密钥、
     * 或服务器出不去 —— 这个端点一次把这几件事说清楚，不用两个后台页面来回翻。
     */
    public function aiStatus(): void
    {
        ApiAuth::guard('GET /aiStatus', 'read');
        $providers = AiService::getProviders();
        $usable = array_values(array_filter(
            $providers,
            static fn(array $p): bool => !empty($p['enabled']) && trim((string) ($p['api_key'] ?? '')) !== ''
        ));
        $stats = AiLog::summary(7);
        $errors = [];
        foreach (AiLog::recent(15) as $row) {
            if (($row['result'] ?? '') === 'error') {
                $errors[] = mb_substr((string) ($row['detail'] ?? ''), 0, 160);
            }
        }
        Api::ok([
            'ai_enabled' => Setting::get('ai_enabled', '1') !== '0',
            'providers_total' => count($providers),
            // 0 = 一条都发不出去，结果必然全是本地兜底
            'providers_usable' => count($usable),
            'provider_names' => array_map(static fn(array $p): string => (string) ($p['name'] ?? ''), $usable),
            'captcha_provider' => CaptchaService::getProvider(),
            'tool_use' => Setting::get('ai_tool_use', '1') !== '0',
            'web_search' => Setting::get('ai_web_search_enabled', '1') !== '0',
            'search_backend' => WebSearch::activeBackend(),
            'total_budget_seconds' => max(10, min(120, (int) Setting::get('ai_total_timeout', '20'))),
            'per_provider_retries' => max(1, min(5, (int) Setting::get('ai_retries', '3'))),
            'calls_7d' => (int) ($stats['calls'] ?? 0),
            'failures_7d' => (int) ($stats['failures'] ?? 0),
            'last_errors' => array_slice($errors, 0, 3),
        ]);
    }

    /** Router 找不到对应动作时走这里，保证 API 的错误也是 JSON */
    public function notFound(string $action = ''): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'unknown_endpoint',
            'message' => '没有这个 API 端点：' . $action, 'hint' => '见 API.md 的端点清单']],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** 镜像排序权重：sort_order 优先，兼容 App 端习惯写的 priority */
    private function mirrorOrder(): int
    {
        $order = Api::intParam('sort_order', -1);
        return $order >= 0 ? $order : Api::intParam('priority', 10);
    }

    /** 与 AdminController::settings 白名单一致，两处都要改时记得同步 */
    private function writableKeys(): array
    {
        return [
            'site_name', 'site_subtitle', 'site_description', 'site_keywords',
            'site_logo', 'site_favicon', 'site_footer',
            'ai_provider', 'ai_api_base', 'ai_api_key', 'ai_model', 'ai_enabled', 'ai_web_search_enabled',
            // AI 补全的节流与联网参数：与后台【系统与AI设置】同一批键，取值范围在读取处再夹一次
            'ai_retries', 'ai_total_timeout', 'ai_tool_use', 'ai_tool_rounds', 'ai_page_chars',
            'ai_respect_robots', 'ai_courtesy_delay',
            'search_tavily_key', 'search_brave_key', 'search_serper_key', 'search_firecrawl_key',
            'captcha_provider', 'captcha_site_key', 'captcha_secret_key',
            'google_ads_enabled', 'google_ads_client', 'google_ads_slot', 'google_ads_code',
            'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
            'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
            'site_base_url',
            // 与后台同一份：测试邮件与审核通知的兜底收件人
            'admin_email',
            'google_one_tap',
            'oauth_ms_client_id', 'oauth_ms_client_secret',
            'oauth_google_client_id', 'oauth_google_client_secret',
            'oauth_github_client_id', 'oauth_github_client_secret',
            'allowed_email_domains',
            'require_login_comment', 'require_login_submit_software',
            'require_login_submit_resource', 'require_login_correction', 'require_login_report',
        ];
    }

    private function writeOnlyKeys(): array
    {
        return ['ai_api_key', 'captcha_secret_key', 'smtp_password',
            'oauth_ms_client_secret', 'oauth_google_client_secret', 'oauth_github_client_secret',
            // 搜索后端 Key 与线路密钥同性质：留空=沿用原值，别让一次部分提交把 Key 清了
            'search_tavily_key', 'search_brave_key', 'search_serper_key', 'search_firecrawl_key'];
    }

    /** @param string[] $fields */
    private function requestFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $value = Api::param($field);
            if ($value !== null) {
                $out[$field] = $value;
            }
        }
        return $out;
    }

    private function locateSoftware(): ?array
    {
        $slug = Api::stringParam('slug', '', 120);
        if ($slug !== '') {
            return Software::findBySlug($slug);
        }
        $id = Api::intParam('id', 0);
        return $id > 0 ? Software::find($id) : null;
    }

    private function decodeChannels(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function publicRow(array $row): array
    {
        return Api::omit($row, self::HIDDEN_COLUMNS);
    }
}
