<?php
/**
 * 镜像源「学格式」：类型由人选，映射规则必须能用真实链接自证
 */

group('53 学格式的判定');

$prefixSample = 'https://ghproxy.net/https://github.com/microsoft/vscode/releases/download/1.90.0/VSCode-x64.exe';
$prefixOriginal = 'https://github.com/microsoft/vscode/releases/download/1.90.0/VSCode-x64.exe';

$learned = GithubService::learnMirrorFromSample($prefixSample, 'download');
ok('前缀反代型学会：模板=' . ($learned['url_template'] ?? '-') . '，方式=' . ($learned['style'] ?? '-'),
    ($learned['success'] ?? false) === true && $learned['url_template'] === 'https://ghproxy.net/'
    && $learned['style'] === 'prefix' && $learned['original_url'] === $prefixOriginal);
ok('回环校验：用学到的模板能逐字还原贴入的链接', ($learned['mirror_url'] ?? '') === $prefixSample);

$keptType = GithubService::learnMirrorFromSample($prefixSample, 'web');
ok('类型不再被反猜：我选 web 就保持 web', ($keptType['type'] ?? '') === 'web' && ($keptType['type_guessed'] ?? true) === false);
ok('链接形态与所选类型不符时给出提示（不擅自改）', str_contains((string) ($keptType['hint'] ?? ''), '已按你选的保存'));
ok('不指定类型时才回落到形态猜测',
    GithubService::learnMirrorFromSample($prefixSample, '')['type_guessed'] === true
    && GithubService::learnMirrorFromSample($prefixSample, '')['type'] === 'download');

// 关键新增能力：没收录进域名清单的镜像站也要能学会（旧实现靠 hint 猜，猜不出就只能拼成前缀形态）
foreach ([
    '陌生换域名镜像' => 'https://mirror.brandnew.tld/microsoft/vscode/releases/download/1.90.0/a.exe',
    '已知换域名镜像' => 'https://kkgithub.com/microsoft/vscode/releases/download/1.90.0/a.exe',
] as $label => $sample) {
    $sampleOriginal = 'https://github.com' . (string) parse_url($sample, PHP_URL_PATH);
    $r = GithubService::learnMirrorFromSample($sample, 'download');
    $host = parse_url($sample, PHP_URL_HOST);
    ok($label . '：学成换域名，模板=https://' . $host . '/', ($r['success'] ?? false) === true
        && $r['url_template'] === 'https://' . $host . '/' && $r['style'] === 'replace'
        && GithubService::buildMirrorUrl($r['url_template'], $sampleOriginal, $r['style']) === $sample);
}

foreach ([
    'GitHub 自己的域名不是镜像' => ['https://raw.githubusercontent.com/twbs/bootstrap/main/README.md', ''],
    '参数型（无原链）' => ['https://gh.example.com/dl?url=abc123', ''],
    '参数型 + 补贴原链' => ['https://gh.example.com/dl?url=https%3A%2F%2Fgithub.com%2Fo%2Fr%2Freleases%2Fdownload%2Fv1%2Fa.zip',
        'https://github.com/o/r/releases/download/v1/a.zip'],
    '不是 http 的输入' => ['github.com/o/r/releases/download/v1/a.zip', ''],
] as $label => [$sample, $orig]) {
    $r = GithubService::learnMirrorFromSample($sample, 'download', $orig);
    ok($label . ' → 明确判失败而不是存个像样的模板', ($r['success'] ?? true) === false
        && !isset($r['url_template']) && mb_strlen((string) ($r['message'] ?? '')) > 8);
}
$wrongType = GithubService::learnMirrorFromSample($prefixSample, 'download-but-wrong');
ok('非法类型直接拒绝', ($wrongType['success'] ?? true) === false);

group('54 后台与 API 都按这套走');

$admin = Sandbox::adminJar();
[$code, $page] = Sandbox::http('/?r=admin/mirrors', [], [], $admin);
ok('镜像管理页含新的引导顺序（先选类型 → 贴真实链接）',
    $code === 200 && str_contains($page, '1. 加速定位') && str_contains($page, '2. 贴一条该镜像站上的真实链接')
    && str_contains($page, '解析并校验'));

$before = (int) Database::fetch("SELECT COUNT(*) AS c FROM github_mirrors WHERE url_template = 'https://learn-test.mirror.dev/'")['c'];
Sandbox::http('/?r=admin/mirrors', [
    'csrf_token' => (string) Sandbox::formToken($page),
    'action' => 'add', 'type' => 'download',
    'sample_url' => 'https://learn-test.mirror.dev/microsoft/vscode/releases/download/1.90.0/a.exe',
    'url_template' => '', 'name' => '',
], [], $admin);
$row = Database::fetch("SELECT * FROM github_mirrors WHERE url_template = 'https://learn-test.mirror.dev/'");
ok('只贴真实链接就能存下学到的模板与映射方式',
    (int) Database::fetch("SELECT COUNT(*) AS c FROM github_mirrors WHERE url_template = 'https://learn-test.mirror.dev/'")['c'] === $before + 1
    && is_array($row) && $row['url_style'] === 'replace'
    && $row['sample_original'] === 'https://github.com/microsoft/vscode/releases/download/1.90.0/a.exe');

[, $page2] = Sandbox::http('/?r=admin/mirrors', [], [], $admin);
$badTpl = Sandbox::http('/?r=admin/mirrors', [
    'csrf_token' => (string) Sandbox::formToken($page2),
    'action' => 'add', 'type' => 'download', 'name' => '拼错的',
    'url_template' => 'https://wrong.mirror.dev/',
    'sample_url' => 'https://learn-test.mirror.dev/microsoft/vscode/releases/download/1.90.0/a.exe',
    'sample_original' => 'https://github.com/microsoft/vscode/releases/download/1.90.0/a.exe',
], [], $admin);
$stillThere = (int) Database::fetch("SELECT COUNT(*) AS c FROM github_mirrors WHERE name = '拼错的'")['c'];
ok('模板与样例对不上时拒收，并把原因显示在页面上', $stillThere === 0);
// 横幅本身要能显示，并且要对回显内容做转义（这段文字里会带上用户贴的链接）
[, $bannerPage] = Sandbox::http('/?r=admin/mirrors&error=' . urlencode('模板与样例对不上：<img src=x onerror=alert(1)>'), [], [], $admin);
ok('错误提示回显到页面横幅且已转义',
    str_contains($bannerPage, '模板与样例对不上') && !str_contains($bannerPage, '<img src=x onerror'));

// API 侧同样一条规则
$key = ApiKey::issue('镜像学习探针', ['read', 'write'], 'test');
$auth = ['username' => 'admin', 'password' => 'admin123'];
$hdr = ['X-Api-Key: ' . $key['key'], 'Content-Type: application/json'];

$fail = json_decode(Sandbox::rawHttpBody(Sandbox::$url . '/index.php?r=apiadmin/mirrorSave', 'POST',
    json_encode($auth + ['name' => 'API 学的', 'sample_url' => 'https://gh.example.com/dl?url=x']), $hdr)[1], true);
ok('API：学不会返回 mirror_not_learned', ($fail['error']['code'] ?? '') === 'mirror_not_learned');

$okLearn = json_decode(Sandbox::rawHttpBody(Sandbox::$url . '/index.php?r=apiadmin/mirrorSave', 'POST',
    json_encode($auth + ['type' => 'download',
        'sample_url' => 'https://api-learn.mirror.dev/o/r/releases/download/v1/a.zip',
        'original_url' => 'https://github.com/o/r/releases/download/v1/a.zip']), $hdr)[1], true);
$apiRow = Database::fetch("SELECT * FROM github_mirrors WHERE url_template = 'https://api-learn.mirror.dev/'");
ok('API：给足两条链接就能学成并存下映射方式',
    ($okLearn['success'] ?? false) === true && is_array($apiRow) && $apiRow['url_style'] === 'replace'
    && $apiRow['type'] === 'download');

$mismatch = json_decode(Sandbox::rawHttpBody(Sandbox::$url . '/index.php?r=apiadmin/mirrorSave', 'POST',
    json_encode($auth + ['name' => '错模板', 'url_template' => 'https://nope.mirror.dev/',
        'sample_url' => 'https://api-learn.mirror.dev/o/r/releases/download/v1/a.zip',
        'original_url' => 'https://github.com/o/r/releases/download/v1/a.zip']), $hdr)[1], true);
ok('API：模板与样例不一致返回 mirror_mismatch', ($mismatch['error']['code'] ?? '') === 'mirror_mismatch');

// 老数据（url_style 空）不能被这次改动弄坏：仍按域名清单生成正确链接
$seedRow = Database::fetch("SELECT * FROM github_mirrors WHERE url_template LIKE '%ghproxy.net%' LIMIT 1");
if (!is_array($seedRow)) {
    Database::insert('github_mirrors', ['name' => 'GHProxy 老数据', 'url_template' => 'https://ghproxy.net/https://github.com/',
        'is_active' => 1, 'sort_order' => 1, 'type' => 'download', 'url_style' => '']);
    $seedRow = Database::fetch("SELECT * FROM github_mirrors WHERE url_style = '' LIMIT 1");
}
$builtLegacy = GithubService::buildMirrorUrl((string) $seedRow['url_template'], $prefixOriginal, (string) $seedRow['url_style']);
ok('老镜像行（未存映射方式）仍按域名清单拼出可用链接',
    str_contains($builtLegacy, 'ghproxy.net') && str_contains($builtLegacy, 'github.com/microsoft/vscode'));
