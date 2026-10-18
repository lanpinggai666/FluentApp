<?php
/**
 * 网页安装向导：未装机时可用、装完即锁、失败不外泄细节、与 CLI 装出同一个库
 */

group('43 网页安装向导');

[$web, $webDir] = Sandbox::spawnBlank();
$baseUrl = $web;

$jar = Sandbox::newJar();
[$openCode, $formPage] = Sandbox::http('/install.php', [], [], $jar, $baseUrl);
ok('未装机时向导可打开（' . $openCode . '，' . strlen($formPage) . 'B）',
    $openCode === 200 && str_contains($formPage, '安装向导') && str_contains($formPage, 'name="csrf_token"'));
ok('向导页面禁止被搜索引擎收录', str_contains($formPage, 'noindex'));
ok('数据库口令字段不回显任何值', !preg_match('/name="db_pass"[^>]*value="[^"]+"/', $formPage));
ok('站点地址留空，向导不代替运维信任请求头',
    (bool) preg_match('/name="base_url"[^>]*value=""/', $formPage) && !str_contains($formPage, 'value="http://127.0.0.1'));

$token = (string) Sandbox::formToken($formPage);

/**
 * 提交一次装机表单。
 * 装机限流是 6 次/15 分钟，逐条校验用例都会消耗额度，所以每次提交前清一次计数；
 * 专门测限流的那条走 webSubmitRaw。
 */
function webSubmit(string $base, string $jar, string $token, array $fields): array
{
    Sandbox::resetRateLimits();
    return webSubmitRaw($base, $jar, $token, $fields);
}

function webSubmitRaw(string $base, string $jar, string $token, array $fields): array
{
    return Sandbox::http('/install.php', array_merge(['csrf_token' => $token], $fields), [], $jar, $base);
}

function webSqliteFields(string $base, array $override = []): array
{
    return array_merge([
        'driver' => 'sqlite', 'sqlite_path' => 'database/nav.sqlite',
        'site_name' => '网页装机的站', 'site_subtitle' => '', 'site_description' => '', 'site_keywords' => '',
        'base_url' => $base, 'admin_user' => 'webops', 'auto_password' => '1', 'seed' => '1',
    ], $override);
}

Sandbox::resetRateLimits();
[, $noCsrfBody] = Sandbox::http('/install.php', webSqliteFields($baseUrl), [], $jar, $baseUrl);
ok('缺 CSRF token 的提交被拒', str_contains($noCsrfBody, '安全令牌'));

[, $weakBody] = webSubmit($baseUrl, $jar, $token, webSqliteFields($baseUrl, [
    'auto_password' => '0', 'admin_pass' => 'short', 'admin_pass2' => 'short']));
ok('短口令被拒并给出可读原因', str_contains($weakBody, '至少 10 位'));

[, $defaultBody] = webSubmit($baseUrl, $jar, $token, webSqliteFields($baseUrl, [
    'auto_password' => '0', 'admin_pass' => 'admin123', 'admin_pass2' => 'admin123']));
ok('沿用种子里那个公开口令被拒', str_contains($defaultBody, '默认口令'));

[, $mismatchBody] = webSubmit($baseUrl, $jar, $token, webSqliteFields($baseUrl, [
    'auto_password' => '0', 'admin_pass' => 'Long-Enough-Pw-7', 'admin_pass2' => 'Different-Pw-77']));
ok('两次口令不一致被拒', str_contains($mismatchBody, '不一致'));

[, $badPathBody] = webSubmit($baseUrl, $jar, $token, webSqliteFields($baseUrl, ['sqlite_path' => 'public/nav.sqlite']));
ok('库文件想写进 public/ 时被拦', str_contains($badPathBody, 'public/'));

[, $badUserBody] = webSubmit($baseUrl, $jar, $token, webSqliteFields($baseUrl, ['admin_user' => '有中文']));
ok('管理员账号字符集受限', str_contains($badUserBody, '管理员账号'));
ok('校验失败时不会留下半个库', !is_file($webDir . '/database/nav.sqlite'));

[, $leakyBody] = webSubmit($baseUrl, $jar, $token, [
    'driver' => 'mysql', 'db_host' => '127.0.0.1', 'db_port' => '13306', 'db_name' => 'softnav_no_such',
    'db_user' => 'nobody', 'db_pass' => 'Sup3r-Secret-Pw', 'create_database' => '1',
    'site_name' => 'MySQL 失败路径', 'base_url' => $baseUrl, 'admin_user' => 'webops',
    'auto_password' => '1', 'seed' => '1']);
ok('MySQL 连不上时只回一句人话，不回堆栈也不回显密码',
    str_contains($leakyBody, '连接') && !preg_match('/Fatal error|Stack trace|Sup3r-Secret-Pw/i', $leakyBody));

// 运维自己加过的键必须保留：向导只改它负责的那几行
file_put_contents($webDir . '/config/.env', "# 我自己加的\nMY_OPS_FLAG=keep-me\nAPP_ENV=development\n");
[, $doneBody] = webSubmit($baseUrl, $jar, $token, webSqliteFields($baseUrl));
ok('提交后安装完成', str_contains($doneBody, '安装完成'));

$generated = preg_match('/class="pw">([^<]+)</', $doneBody, $mm) ? $mm[1] : '';
ok('随机口令展示一次且长度足够（' . strlen($generated) . ' 位）', strlen($generated) >= 16);
ok('成功页不泄漏数据库口令', !str_contains($doneBody, 'Sup3r-Secret-Pw'));

$envText = (string) @file_get_contents($webDir . '/config/.env');
ok('.env 写入驱动与路径，并保留原有注释和自定义键',
    str_contains($envText, 'DB_CONNECTION=sqlite') && str_contains($envText, 'MY_OPS_FLAG=keep-me')
    && str_contains($envText, '# 我自己加的') && substr_count($envText, 'APP_ENV=') === 1);
ok('.env 里 APP_ENV 被改成 production', str_contains($envText, 'APP_ENV=production'));
$envPath = preg_match('/^DB_SQLITE_PATH=(.+)$/m', $envText, $p) ? trim($p[1], " \"") : '';
// 短名/长名（LANPIN~1 与 lanpinggai666）在 Windows 上会同时出现，比较 realpath 才可靠
ok('.env 里 SQLite 路径被解析成实例内的绝对路径（实际 ' . $envPath . '）',
    $envPath !== '' && (bool) preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $envPath)
    && is_file($envPath)
    && realpath(dirname($envPath)) === realpath($webDir . '/database'));

$lockText = (string) @file_get_contents($webDir . '/config/install.lock');
ok('安装标记存在且带 PHP 拦截头', str_contains($lockText, '<?php exit; ?>')
    && str_contains($lockText, '"admin_username":"webops"'));
ok('库文件真的落在指定位置', is_file($webDir . '/database/nav.sqlite'));

group('44 装机后的站点与向导自锁');

[$homeCode, $homeBody] = Sandbox::http('/', [], [], Sandbox::newJar(), $baseUrl);
ok('前台首页可访问（' . $homeCode . '，' . strlen($homeBody) . 'B）',
    $homeCode === 200 && strlen($homeBody) > 5000 && !preg_match('/Fatal error|SQLSTATE|no such table/i', $homeBody));
[$detailCode, $detailBody] = Sandbox::http('/?r=software/view&slug=vscode', [], [], Sandbox::newJar(), $baseUrl);
ok('详情页可访问（种子数据到位）', $detailCode === 200 && strlen($detailBody) > 5000);

Sandbox::resetRateLimits();
$loginJar = Sandbox::newJar();
[, $loginPage] = Sandbox::http('/?r=admin/login', [], [], $loginJar, $baseUrl);
[$loginCode] = Sandbox::http('/?r=admin/login', [
    'username' => 'webops', 'password' => $generated, 'csrf_token' => (string) Sandbox::formToken($loginPage),
], [], $loginJar, $baseUrl);
[$dashCode, $dashBody] = Sandbox::http('/?r=admin/dashboard', [], [], $loginJar, $baseUrl);
ok('用生成的口令能进后台（' . $loginCode . '/' . $dashCode . '）',
    $loginCode === 302 && $dashCode === 200 && str_contains($dashBody, '退出登录'));

[$lockedCode, $lockedBody] = Sandbox::http('/install.php', [], [], Sandbox::newJar(), $baseUrl);
ok('装完后再访问向导返回 404', $lockedCode === 404);
ok('404 页面不再输出任何表单', !str_contains($lockedBody, 'name="csrf_token"') && !str_contains($lockedBody, '<form'));
[$lockedPost] = Sandbox::http('/install.php', webSqliteFields($baseUrl), [], Sandbox::newJar(), $baseUrl);
ok('绕过表单直接 POST 也一样被锁', $lockedPost === 404);

$check = cliRun($webDir . '/deploy/preflight.php', []);
ok('网页装好的站点体检 0 失败（退出码 ' . $check['code'] . '）',
    $check['code'] === 0 && str_contains($check['out'], '失败 0 项'));
ok('体检认得安装标记', str_contains($check['out'], '安装标记') || str_contains($check['out'], '结构版本与代码一致'));

group('45 未装机时体检要拦');

[$blankUrl, $blankDir] = Sandbox::spawnBlank();
$blankCheck = cliRun($blankDir . '/deploy/preflight.php', []);
ok('未装机时体检失败并指明怎么装（退出码 ' . $blankCheck['code'] . '）',
    $blankCheck['code'] === 1 && str_contains($blankCheck['out'], '装好之前别对外'));
ok('未装机时体检不去连库，免得顺手把库建出来', str_contains($blankCheck['out'], '跳过库内检查'));
ok('未装机时没有安装标记', !is_file($blankDir . '/config/install.lock'));

// 没装机时访问首页只会被引导去装，不会自己建库 —— 探测一轮之后状态仍然没变
$liveCheck = cliRun($blankDir . '/deploy/preflight.php', ['--url=' . $blankUrl]);
ok('带 --url 时能发现向导还开着', str_contains($liveCheck['out'], '✗ 安装向导已关闭'));
ok('探测过一轮后库文件依然不存在', !is_file($blankDir . '/database/nav.sqlite'));

group('46 安装专用限流');

$limJar = Sandbox::newJar();
Sandbox::resetRateLimits();
[, $limForm] = Sandbox::http('/install.php', [], [], $limJar, $blankUrl);
$limToken = (string) Sandbox::formToken($limForm);
$limitedBody = '';
for ($i = 0; $i < 8; $i++) {
    // 用一个必然校验失败的账号提交：限流在校验之前计数，而这些提交都不该真的把站装起来
    [, $limitedBody] = webSubmitRaw($blankUrl, $limJar, $limToken,
        webSqliteFields($blankUrl, ['admin_user' => '非法账号' . $i, 'site_name' => '限流测试' . $i]));
}
ok('装机接口单独限流，反复乱提交会被暂时挡住', str_contains($limitedBody, '过于频繁'));
if (!str_contains($limitedBody, '过于频繁')) {
    echo '    最后一次响应: ' . mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($limitedBody))), 0, 160) . "\n";
}
ok('限流生效后不再暴露校验细节', !str_contains($limitedBody, '安装完成'));
ok('被限流的这些提交没有把站装起来', !is_file($blankDir . '/config/install.lock'));
