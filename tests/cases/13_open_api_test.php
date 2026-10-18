<?php
/**
 * 开放 API：装机向导 → 后台密钥页 → App 用「密钥+用户名+口令」调用后台能力
 *
 * 分两段：
 *   48 用一份「新装实例」跑完整运维路径（向导装机 → 后台生成密钥 → 密钥可用 → 明文只显示一次）
 *   49-51 直接对主沙箱实例调用端点，因为 ApiKey::issue() / Sandbox::setSetting() 操作的就是这份库，
 *      换实例断言会变成「改了 A 库、验了 B 库」的空测试。
 */

group('48 装机 → 密钥生成 → 密钥可用');

[$apiUrl, $apiDir] = Sandbox::spawnBlank();
$apiPw = 'Api-Test-Pw-9';

$jar = Sandbox::newJar();
// 上一轮用例的限流测试会把 web-install 计数打满，装机前先清一次，否则这里会拿到「过于频繁」
Sandbox::resetRateLimits();
[, $form] = Sandbox::http('/install.php', [], [], $jar, $apiUrl);
[, $done] = Sandbox::http('/install.php', [
    'csrf_token' => (string) Sandbox::formToken($form),
    'driver' => 'sqlite', 'sqlite_path' => 'database/nav.sqlite',
    'site_name' => 'API 测试站', 'base_url' => $apiUrl,
    'admin_user' => 'apiops', 'auto_password' => '0', 'admin_pass' => $apiPw, 'admin_pass2' => $apiPw,
    'seed' => '1',
], [], $jar, $apiUrl);
ok('实例已装好', str_contains($done, '安装完成'));

Sandbox::resetRateLimits();
$admin = Sandbox::newJar();
[, $loginPage] = Sandbox::http('/?r=admin/login', [], [], $admin, $apiUrl);
Sandbox::http('/?r=admin/login', ['username' => 'apiops', 'password' => $apiPw,
    'csrf_token' => (string) Sandbox::formToken($loginPage)], [], $admin, $apiUrl);
[, $keysPage] = Sandbox::http('/?r=admin/apiKeys', [], [], $admin, $apiUrl);
ok('后台密钥页可打开', str_contains($keysPage, '开放 API 密钥') && str_contains($keysPage, '生成密钥'));

Sandbox::http('/?r=admin/apiKeys', ['csrf_token' => (string) Sandbox::formToken($keysPage),
    'action' => 'create', 'name' => '安卓 App', 'scopes' => ['read', 'write']], [], $admin, $apiUrl);
[, $afterCreate] = Sandbox::http('/?r=admin/apiKeys', [], [], $admin, $apiUrl);
preg_match('/>(nav_[a-f0-9]{40})</', $afterCreate, $mk);
$uiKey = $mk[1] ?? '';
ok('在后台页面生成密钥并只显示一次（' . strlen($uiKey) . ' 位）', strlen($uiKey) === 44);
[, $secondLook] = Sandbox::http('/?r=admin/apiKeys', [], [], $admin, $apiUrl);
ok('刷新之后明文不再出现（会话里也销毁了）', !str_contains($secondLook, $uiKey));
ok('列表里只有识别前缀，没有密钥哈希',
    !str_contains($secondLook, 'key_hash') && str_contains($secondLook, substr($uiKey, 0, 12)));

$uiPing = apiCall($apiUrl, '/index.php?r=api/v1/ping', ['username' => 'apiops', 'password' => $apiPw], ['X-Api-Key: ' . $uiKey]);
ok('后台生成的密钥立刻可用', $uiPing[0] === 200 && (apiJson($uiPing)['success'] ?? false) === true);

/** 发一次 API 调用：JSON body + 自定义请求头，返回 [状态码, 响应体, 响应头] */
function apiCall(string $base, string $path, array $body = [], array $headers = [], string $method = 'GET'): array
{
    $payload = $body === [] ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
    if ($payload !== null && !in_array('Content-Type: application/json', $headers, true)) {
        $headers[] = 'Content-Type: application/json';
    }
    return Sandbox::rawHttpBody($base . $path, $method, $payload, $headers);
}

function apiJson(array $response): array
{
    $decoded = json_decode($response[1], true);
    return is_array($decoded) ? $decoded : ['_raw' => $response[1]];
}

function apiCode(array $response): string
{
    return (string) (apiJson($response)['error']['code'] ?? '');
}

function apiMessage(array $response): string
{
    return (string) (apiJson($response)['error']['message'] ?? '');
}

group('49 鉴权失败分支');

// 主沙箱就是「已装好且带默认口令」的那份库，凭据与 ApiKey 都在同一个库里，断言才有意义
$base = Sandbox::$url;
$key = ApiKey::issue('回归用例', ['read', 'write'], 'test');
$plainKey = $key['key'];
$auth = ['username' => 'admin', 'password' => 'admin123'];
$withKey = ['X-Api-Key: ' . $plainKey];
Sandbox::resetRateLimits();

$noKey = apiCall($base, '/index.php?r=api/v1/ping');
ok('不带密钥 → ' . apiCode($noKey) . '（HTTP ' . $noKey[0] . '）', $noKey[0] === 401 && apiCode($noKey) === 'missing_key');

$badKey = apiCall($base, '/index.php?r=api/v1/ping', $auth, ['X-Api-Key: nav_' . str_repeat('0', 40)]);
ok('错误密钥 → ' . apiCode($badKey), apiCode($badKey) === 'key_invalid');

$queryKey = apiCall($base, '/index.php?r=api/v1/ping&api_key=' . urlencode($plainKey));
ok('密钥塞进 URL 会被拒绝而不是接受 → ' . apiCode($queryKey), apiCode($queryKey) === 'key_in_query');

$badPw = apiCall($base, '/index.php?r=api/v1/ping', ['username' => 'admin', 'password' => 'Wrong-Pw-00000'], $withKey);
ok('密钥对但口令错 → ' . apiCode($badPw), $badPw[0] === 401 && apiCode($badPw) === 'invalid_credentials');

$noPw = apiCall($base, '/index.php?r=api/v1/ping', [], $withKey);
ok('只给密钥不给凭据 → ' . apiCode($noPw), $noPw[0] === 401 && apiCode($noPw) === 'missing_credentials');

$ping = apiCall($base, '/index.php?r=api/v1/ping', $auth, $withKey);
$pingJson = apiJson($ping);
ok('三件套齐全时通过（HTTP ' . $ping[0] . '）', $ping[0] === 200 && ($pingJson['success'] ?? false) === true
    && ($pingJson['data']['granted_scopes'] ?? []) === ['read', 'write']);
ok('响应是 JSON、禁止缓存、且不需要 CSRF token', str_contains($ping[2], 'application/json')
    && stripos($ping[2], 'no-store') !== false);
ok('凭据正确后失败计数被清零', RateLimiter::peek('api-auth', '127.0.0.1', Api::RATE_AUTH_FAIL, Api::AUTH_WINDOW)['count'] === 0);

group('50 只读端点');

foreach (['overview', 'softwares', 'categories', 'pending', 'comments', 'reports', 'corrections',
    'mirrors', 'notices', 'users', 'words', 'settings', 'logs', 'resources'] as $path) {
    $res = apiJson(apiCall($base, '/index.php?r=api/v1/' . $path, $auth, $withKey));
    ok('GET /' . $path . ' 返回 success', ($res['success'] ?? false) === true && isset($res['data']));
}

// 取库里真实存在的 slug，别硬编码示例数据（前面的用例可能已经改过这些行）
$anySlug = (string) Sandbox::$db->query("SELECT slug FROM softwares WHERE status='published' ORDER BY id LIMIT 1")->fetchColumn();
$one = apiCall($base, '/index.php?r=api/v1/software&slug=' . rawurlencode($anySlug), $auth, $withKey);
$oneJson = apiJson($one);
$oneData = $oneJson['data'] ?? [];
ok('软件详情带上渠道、评论、评分分布与资源（HTTP ' . $one[0] . '）',
    $one[0] === 200 && ($oneJson['success'] ?? false) === true
    && isset($oneData['channels'], $oneData['comments'], $oneData['rating_distribution'], $oneData['resources']));
if (!isset($oneData['channels'])) {
    echo '    响应开头: ' . mb_substr(str_replace("\n", ' ', $one[1]), 0, 200) . "\n";
}
$oneRaw = json_encode($oneJson, JSON_UNESCAPED_UNICODE);
ok('详情里不外泄一次性令牌摘要、提交者邮箱与 IP',
    !str_contains((string) $oneRaw, 'edit_token_hash') && !str_contains((string) $oneRaw, 'submitter_ip')
    && !str_contains((string) $oneRaw, 'submitter_email'));

$settingsJson = apiJson(apiCall($base, '/index.php?r=api/v1/settings', $auth, $withKey));
Sandbox::setSetting('ai_api_key', 'sk-real-secret-value');
$settingsJson2 = apiJson(apiCall($base, '/index.php?r=api/v1/settings', $auth, $withKey));
ok('AI 密钥只回占位符，不回明文', ($settingsJson2['success'] ?? false) === true
    && ($settingsJson2['data']['ai_api_key'] ?? '') === '●●●已设置●●●'
    && ($settingsJson2['data']['ai_api_key_set'] ?? false) === true);
ok('设置读取里找不到任何凭据明文', !str_contains(json_encode($settingsJson2, JSON_UNESCAPED_UNICODE), 'sk-real-secret-value')
    && !str_contains(json_encode($settingsJson2, JSON_UNESCAPED_UNICODE), 'admin_password_hash'));

$unknown = apiCall($base, '/index.php?r=api/v1/nopeNothingHere', $auth, $withKey);
ok('未知端点回 JSON 404 而不是 HTML 页面', $unknown[0] === 404 && apiCode($unknown) === 'unknown_endpoint'
    && str_contains($unknown[2], 'application/json'));

group('51 写端点与后台校验一致');

$readKey = ApiKey::issue('只读探针', ['read'], 'test')['key'];
$denied = apiCall($base, '/index.php?r=apiadmin/mirrorSave',
    $auth + ['name' => '不该存在', 'url_template' => 'https://example.com/'], ['X-Api-Key: ' . $readKey], 'POST');
ok('只读密钥调用写端点 → ' . apiCode($denied), $denied[0] === 403 && apiCode($denied) === 'insufficient_scope');

$saved = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'name' => '待审软件甲', 'official_url' => 'https://a.example.com', 'icon_url' => 'https://a.example.com/i.png',
    'status' => 'pending', 'category_id' => 1,
], $withKey, 'POST'));
ok('API 能创建待审提交（id=' . ($saved['data']['id'] ?? '?') . '）', ($saved['success'] ?? false) === true);
$pendingId = (int) ($saved['data']['id'] ?? 0);

$sneaky = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'name' => '坏链接软件', 'official_url' => 'javascript:alert(1)', 'icon_url' => 'https://b.example.com/i.png',
    'status' => 'pending', 'category_id' => 1,
], $withKey, 'POST'));
ok('API 挡得住 javascript: 官网链接（' . ($sneaky['error']['message'] ?? '?') . '）',
    ($sneaky['success'] ?? true) === false && str_contains((string) ($sneaky['error']['message'] ?? ''), 'http/https'));

$noNote = apiCall($base, '/index.php?r=apiadmin/review', $auth + ['id' => $pendingId, 'action' => 'reject'], $withKey, 'POST');
ok('驳回不写理由被拒 → ' . apiCode($noNote), apiCode($noNote) === 'review_note_required');

$approved = apiJson(apiCall($base, '/index.php?r=apiadmin/review',
    $auth + ['id' => $pendingId, 'action' => 'approve', 'category_id' => 2], $withKey, 'POST'));
ok('审核通过后状态变 published 且库里一致',
    ($approved['success'] ?? false) === true && ($approved['data']['status'] ?? '') === 'published'
    && (Software::find($pendingId)['status'] ?? '') === 'published');

$again = apiCall($base, '/index.php?r=apiadmin/review', $auth + ['id' => $pendingId, 'action' => 'approve'], $withKey, 'POST');
ok('重复审核被挡 → ' . apiCode($again) . '（HTTP ' . $again[0] . '）', $again[0] === 409 && apiCode($again) === 'not_pending');

$notice = apiJson(apiCall($base, '/index.php?r=apiadmin/noticeSave', $auth + ['title' => 'API 发的公告', 'content' => '正文'], $withKey, 'POST'));
ok('API 能发公告', ($notice['success'] ?? false) === true);
$noticeDelete = apiJson(apiCall($base, '/index.php?r=apiadmin/noticeDelete',
    $auth + ['id' => (int) ($notice['data']['id'] ?? 0)], $withKey, 'POST'));
ok('API 能删公告', ($noticeDelete['success'] ?? false) === true);

$getOnly = apiCall($base, '/index.php?r=apiadmin/noticeDelete', $auth + ['id' => 1], $withKey, 'GET');
ok('写端点不接受 GET（防预取与爬虫误触发）', $getOnly[0] === 405);

$noConfirm = apiCall($base, '/index.php?r=apiadmin/softwareDelete', $auth + ['id' => $pendingId], $withKey, 'POST');
ok('删除软件必须显式 confirm=1 → ' . apiCode($noConfirm), apiCode($noConfirm) === 'confirm_required');
$deleted = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareDelete',
    $auth + ['id' => $pendingId, 'confirm' => '1'], $withKey, 'POST'));
ok('带 confirm 才真删', ($deleted['success'] ?? false) === true && Software::find($pendingId) === null);

Sandbox::setSetting('smtp_password', 'SMTP-keep-me');
$emptySecret = apiJson(apiCall($base, '/index.php?r=apiadmin/settingsSave',
    $auth + ['site_name' => '改名的站', 'smtp_password' => ''], $withKey, 'POST'));
ok('留空的凭据字段不会被清空', ($emptySecret['success'] ?? false) === true && Sandbox::setting('smtp_password') === 'SMTP-keep-me');
ok('普通设置项照常保存', Sandbox::setting('site_name') === '改名的站');
$badBase = apiCall($base, '/index.php?r=apiadmin/settingsSave', $auth + ['site_base_url' => 'notaurl'], $withKey, 'POST');
ok('非法 site_base_url 被拒 → ' . apiCode($badBase), apiCode($badBase) === 'invalid_base_url');

group('52 吊销、总闸与限流');

ApiKey::revoke((int) $key['row']['id']);
$afterRevoke = apiCall($base, '/index.php?r=api/v1/ping', $auth, $withKey);
ok('后台吊销后立刻失效 → ' . apiCode($afterRevoke), $afterRevoke[0] === 401 && apiCode($afterRevoke) === 'key_revoked');

Sandbox::setSetting('api_enabled', '0');
$off = apiCall($base, '/index.php?r=api/v1/ping', $auth, ['X-Api-Key: ' . $readKey]);
ok('总闸关闭后所有端点 503 → ' . apiCode($off), $off[0] === 503 && apiCode($off) === 'api_disabled');
Sandbox::setSetting('api_enabled', '1');

$throttleKey = ApiKey::issue('写限流探针', ['read', 'write'], 'test')['key'];
for ($i = 0; $i < Api::RATE_WRITE; $i++) {
    RateLimiter::consume('api-write', substr($throttleKey, 0, ApiKey::PREFIX_LENGTH), Api::RATE_WRITE, 60);
}
$throttled = apiCall($base, '/index.php?r=apiadmin/noticeSave', $auth + ['title' => 'T', 'content' => 'C'],
    ['X-Api-Key: ' . $throttleKey, 'Content-Type: application/json'], 'POST');
ok('写请求超频被限流 → ' . apiCode($throttled), $throttled[0] === 429 && apiCode($throttled) === 'rate_limited');

$probeThrottled = apiCall($base, '/index.php?r=apiadmin/noticeSave', $auth, ['X-Api-Key: ' . $readKey]);
ok('被拒的调用同样消耗配额（探权限也占限流额度）', in_array(apiCode($probeThrottled), ['rate_limited', 'insufficient_scope'], true));

for ($i = 0; $i < Api::RATE_AUTH_FAIL; $i++) {
    RateLimiter::consume('api-auth', '127.0.0.1', Api::RATE_AUTH_FAIL, Api::AUTH_WINDOW);
}
$brute = apiCall($base, '/index.php?r=api/v1/ping', $auth, ['X-Api-Key: ' . $readKey]);
ok('凭据连续错太多次后先被闸门挡住 → ' . apiCode($brute), $brute[0] === 429 && apiCode($brute) === 'auth_throttled');
Sandbox::resetRateLimits();

$logsJson = apiJson(apiCall($base, '/index.php?r=api/v1/logs', $auth, ['X-Api-Key: ' . $readKey]));
$rows = $logsJson['data'] ?? [];
ok('端点只返回本密钥的日志（' . count($rows) . ' 条）', ($logsJson['success'] ?? false) === true && count($rows) >= 1);
ok('全局日志已经累计到 ' . ApiLog::count() . ' 条', ApiLog::count() > 15);
$logsRaw = (string) json_encode($logsJson, JSON_UNESCAPED_UNICODE);
ok('日志里没有把口令或密钥明文写进去', !str_contains($logsRaw, 'admin123') && !str_contains($logsRaw, $plainKey)
    && !str_contains($logsRaw, $readKey));
ok('库里只存密钥哈希，读库文件也拿不到明文密钥',
    !str_contains((string) file_get_contents(Sandbox::$root . '/database/nav.sqlite'), $plainKey));

group('55 软件详情：summary 派生 + publisher / slug 入库');

// 上一组把各限流计数都用过了，这里先清一次再跑
Sandbox::resetRateLimits();
$writeKey = ['X-Api-Key: ' . $throttleKey];

$created = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'name' => '详情写入测试', 'slug' => 'detail-write-test', 'category_id' => 1,
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
    'publisher' => '某某实验室', 'special_notes' => '特别说明初值',
    'description_md' => "## 标题\n\n这是一款便携截图工具，支持滚动截取。第二句不该进简介。",
], $writeKey, 'POST'));
$detailId = (int) ($created['data']['id'] ?? 0);
$row = Software::find($detailId);
ok('API 新建时 slug/publisher 入库、summary 由说明首句派生（' . ($created['error']['message'] ?? 'ok') . '）',
    ($created['success'] ?? false) === true && ($row['summary'] ?? '') === '这是一款便携截图工具，支持滚动截取。'
    && ($row['publisher'] ?? '') === '某某实验室' && ($row['slug'] ?? '') === 'detail-write-test');

$patched = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1,
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
    'description_md' => '改过的说明第一句。后面还有内容。',
], $writeKey, 'POST'));
$row = Software::find($detailId);
ok('改正文即改简介，publisher 不提交就保持原值（' . ($patched['error']['message'] ?? 'ok') . '）',
    ($patched['success'] ?? false) === true && ($row['description_md'] ?? '') === '改过的说明第一句。后面还有内容。'
    && ($row['summary'] ?? '') === '改过的说明第一句。' && ($row['publisher'] ?? '') === '某某实验室');

$ignored = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1, 'summary' => '调用方硬塞的简介',
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
    'description_md' => '以正文为准的一句话。',
], $writeKey, 'POST'));
$row = Software::find($detailId);
ok('请求里带 summary 也不会被采纳（派生列）→ ' . (string) ($row['summary'] ?? ''),
    ($ignored['success'] ?? false) === true && ($row['summary'] ?? '') === '以正文为准的一句话。');

$tooLong = apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1,
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
    'publisher' => str_repeat('超', 101),
], $writeKey, 'POST');
ok('超长 publisher 被拒且没写坏原记录 → ' . apiCode($tooLong),
    $tooLong[0] === 422 && str_contains(apiMessage($tooLong), '过长')
    && (Software::find($detailId)['publisher'] ?? '') === '某某实验室');

$dup = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'name' => '占位软件', 'slug' => 'taken-slug-001', 'category_id' => 1,
    'icon_url' => 'https://e.example.com/i.png', 'official_url' => 'https://e.example.com',
], $writeKey, 'POST'));
$conflict = apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1, 'slug' => 'taken-slug-001',
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
], $writeKey, 'POST');
ok('slug 撞车在写库前就被拦下（UNIQUE 不会抛裸异常）→ ' . apiCode($conflict),
    ($dup['success'] ?? false) === true && $conflict[0] === 422 && str_contains(apiMessage($conflict), '占用'));

$badSlug = apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1, 'slug' => 'Bad Slug!',
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
], $writeKey, 'POST');
ok('非法 slug 被拒 → ' . apiCode($badSlug) . '：' . apiMessage($badSlug), $badSlug[0] === 422);

// 后台网页表单压根没有这三个输入框以外的提交（不提交 summary），必须保持库里原值
$webBuild = SoftwareWriteService::build([
    'name' => '后台保存一次', 'icon_url' => 'https://f.example.com/i.png', 'official_url' => 'https://f.example.com',
]);
ok('后台表单不含这些字段时不会把它们洗成空',
    $webBuild['ok'] === true && !array_key_exists('summary', $webBuild['data'])
    && !array_key_exists('publisher', $webBuild['data']) && !array_key_exists('slug', $webBuild['data']));

$dead = apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1, 'github_release_keyword' => 'arm64',
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
], $writeKey, 'POST');
$deadJson = apiJson($dead);
$deadBuild = SoftwareWriteService::build([
    'name' => 'x', 'icon_url' => 'https://g.example.com/i.png', 'official_url' => 'https://g.example.com',
    'github_release_keyword' => 'arm64',
]);
// 白名单里没有它：请求照样成功，但这个参数一个字节都不会进库——不假装生效，也不误伤别的字段
ok('死字段 github_release_keyword 已移出白名单（请求成功但一个字节都不进库）',
    ($deadJson['success'] ?? false) === true && !array_key_exists('github_release_keyword', $deadBuild['data']));

// 「读出来直接改回去」：读端点必须把 softwareSave 能写的每一列都吐回来，且原样提交不产生漂移
$writable = ['name', 'slug', 'icon_url', 'official_url', 'github_url', 'category_id', 'summary',
    'special_notes', 'description_md', 'status', 'is_featured', 'price_type', 'price_desc',
    'is_open_source', 'license', 'developer', 'publisher', 'tags', 'screenshot_url'];

// 给这条软件挂上两个渠道，后面的渠道幂等断言才有东西可比（空渠道的 0→0 是自证空白）
apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $auth + [
    'id' => $detailId, 'name' => '详情写入测试', 'category_id' => 1,
    'icon_url' => 'https://d.example.com/i.png', 'official_url' => 'https://d.example.com',
    'channel_url' => ['https://pan.example.com/s/abc', 'https://dl.example.com/setup.exe'],
    'channel_type' => ['官方下载', '第三方下载站'],
], $writeKey, 'POST'));

$readJson = apiJson(apiCall($base, '/index.php?r=api/v1/software', $auth + ['id' => $detailId], $writeKey));
$read = (array) ($readJson['data'] ?? []);
$missing = array_values(array_diff($writable, array_keys($read)));
ok('读端点把 19 个可写字段全吐回来了' . ($missing === [] ? '' : '，缺：' . implode(',', $missing)),
    ($readJson['success'] ?? false) === true && $missing === [] && ($read['channels'] ?? null) !== null);

$before = Software::find($detailId);
$payload = $auth + ['id' => $detailId];
foreach ($writable as $field) {
    $payload[$field] = $read[$field] ?? '';
}
// 渠道用读回来的 channels[] 原样回传，验证 read→write 不需要 App 自己重抄结构
$payload['other_urls'] = $read['channels'];
$roundTrip = apiJson(apiCall($base, '/index.php?r=apiadmin/softwareSave', $payload, $writeKey, 'POST'));
$after = Software::find($detailId);
$drift = [];
foreach ($writable as $field) {
    if ((string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')) {
        $drift[] = $field . '(' . ($before[$field] ?? 'NULL') . '→' . ($after[$field] ?? 'NULL') . ')';
    }
}
ok('读回来原样提交回去是幂等的' . ($drift === [] ? '' : '，漂移：' . implode(' ', $drift)),
    ($roundTrip['success'] ?? false) === true && $drift === []);

$urlsOf = function (?string $raw): array {
    $list = json_decode((string) $raw, true);
    return is_array($list) ? array_values(array_map(static fn($c) => (string) ($c['url'] ?? ''), $list)) : [];
};
$chanBefore = $urlsOf($before['other_urls'] ?? null);
$chanAfter = $urlsOf($after['other_urls'] ?? null);
ok('渠道按读回来的 channels[] 回传后地址一条不少（' . count($chanBefore) . '→' . count($chanAfter) . '）',
    $chanBefore === $chanAfter);

Sandbox::resetRateLimits();
