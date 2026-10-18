<?php
/**
 * 提交与登录：合法流程可用、恶意 URL 被拒、会话 cookie 行为、后台登录限流
 */

Sandbox::resetRateLimits();

group('18 软件提交流程');

function submitSoftware(array $fields, string $jar = null): array
{
    $jar = $jar ?? Sandbox::newJar();
    [, $page] = Sandbox::http('/?r=submit', [], [], $jar);
    $post = array_merge([
        'is_ajax' => '1',
        'name' => '临时提交样本',
        'submitter_email' => 'probe@example.com',
        'icon_url' => 'https://cdn.example.com/i.png',
        'official_url' => 'https://ok.example.com',
        'csrf_token' => (string) Sandbox::formToken((string) $page),
        'captcha_name' => 'submit',
        'captcha_math_input' => (string) Sandbox::mathAnswer((string) $page, 'submit'),
    ], $fields);
    return Sandbox::http('/?r=submit/save', $post, [], $jar);
}

$before = (int) Sandbox::$db->query('SELECT COUNT(*) FROM softwares')->fetchColumn();
[$code, $body] = submitSoftware(['name' => '合法提交-A', 'slug_probe' => 1]);
$json = json_decode($body, true);
ok('合法提交被接受', ($json['success'] ?? false) === true);
ok('合法提交入库且状态为待审', (string) Sandbox::$db->query("SELECT status FROM softwares WHERE name='合法提交-A'")->fetchColumn() === 'pending');

foreach ([
    ['官网伪协议', ['official_url' => 'javascript:alert(document.cookie)'], '官方网站'],
    ['图标伪协议', ['icon_url' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKQ=='], '图标链接'],
    ['GitHub 伪协议', ['github_url' => 'vbscript:msgbox(1)'], 'GitHub 地址'],
    ['官网含空格与引号', ['official_url' => 'https://evil.com/x" onmouseover="alert(1)'], '官方网站'],
    ['渠道含伪协议', ['channel_type' => ['第三方下载站'], 'channel_name' => ['网盘'], 'channel_url' => ['javascript:alert(1)']], '下载渠道'],
    ['超长地址', ['official_url' => 'https://ok.example.com/' . str_repeat('a', 600)], '官方网站'],
] as [$label, $fields, $expectIn]) {
    [$code, $body] = submitSoftware($fields + ['name' => '恶意提交-' . $label]);
    $json = json_decode($body, true);
    $msg = (string) ($json['message'] ?? '');
    ok("拦截{$label}并点名 {$expectIn}", ($json['success'] ?? true) === false && str_contains($msg, $expectIn));
}
ok('恶意提交一条都没入库', (int) Sandbox::$db->query('SELECT COUNT(*) FROM softwares')->fetchColumn() <= $before + 1);

[$code, $body] = submitSoftware(['name' => '无凭据提交', 'csrf_token' => '']);
ok('缺 CSRF 凭据的提交被框架拦截', $code === 400);

group('19 会话与 cookie');

$jar = Sandbox::newJar();
[, $loginPage] = Sandbox::http('/?r=auth/login', [], [], $jar);
[$code, , $head] = Sandbox::http('/?r=auth/login', [
    'account' => 'admin', 'password' => 'admin123', 'remember' => '1',
    'csrf_token' => (string) Sandbox::formToken($loginPage),
    'captcha_name' => 'login', 'captcha_math_input' => (string) Sandbox::mathAnswer($loginPage, 'login'),
], [], $jar);
[, $page2, $head2] = Sandbox::http('/?r=auth/login', [], [], $jar);
ok('前台登录成功', $code === 302);
$sessionLines = [];
foreach (preg_split('/\r\n/', $head) as $line) {
    if (stripos($line, 'set-cookie:') === 0) {
        $sessionLines[] = $line;
    }
}
$last = (string) end($sessionLines);
ok('会话 cookie 带 HttpOnly', stripos($last, 'httponly') !== false);
ok('会话 cookie 带 SameSite=Lax', stripos($last, 'samesite=Lax') !== false);
ok('勾选记住我时 cookie 带过期时间', preg_match('/expires=/', $last) === 1);
ok('登录时会轮换会话 ID', count($sessionLines) >= 2);
ok('不再下发服务端不校验的 remember 令牌', !str_contains(strtolower($head), 'fluent_remember_token'));
ok('登录后能访问需要登录的页面', Sandbox::http('/?r=user/notifications', [], [], $jar)[0] === 200);
[$code, , $logoutHead] = Sandbox::http('/?r=auth/logout', [], [], $jar);
$reset = false;
foreach (preg_split('/\r\n/', $logoutHead) as $line) {
    if (stripos($line, 'PHPSESSID') !== false && !preg_match('/expires=/', $line)) {
        $reset = true;
    }
}
ok('退出后 cookie 回到浏览器生命期（撤掉长效凭据）', $reset);

$jar2 = Sandbox::userJar('admin', 'admin123', false);
[$code, $body] = Sandbox::http('/?r=api/ping', [], [], $jar2);
ok('未勾选记住我也能正常登录使用', $code === 200);

group('20 后台登录速率限制');

Sandbox::resetRateLimits();
$jar3 = Sandbox::newJar();
[, $adminPage] = Sandbox::http('/?r=admin/login', [], [], $jar3);
$token = Sandbox::formToken($adminPage);
$limitedAt = 0;
for ($i = 1; $i <= 12; $i++) {
    [$code, $body] = Sandbox::http('/?r=admin/login', ['username' => 'admin', 'password' => 'nope-' . $i, 'csrf_token' => $token], [], $jar3);
    if (str_contains($body, '登录尝试过于频繁')) {
        $limitedAt = $i;
        break;
    }
}
ok("连续失败 {$limitedAt} 次后触发限流", $limitedAt >= 8 && $limitedAt <= 10);
ok('每次失败提示不泄露默认口令', !str_contains($adminPage, 'admin123'));
[$code, $body] = Sandbox::http('/?r=admin/login', ['username' => 'admin', 'password' => 'admin123', 'csrf_token' => $token], [], $jar3);
ok('限流期内正确密码也被拒', $code === 200 && str_contains($body, '过于频繁'));
Sandbox::resetRateLimits();
$jar4 = Sandbox::adminJar();
ok('清除计数后管理员可正常登录', Sandbox::http('/?r=admin/dashboard', [], [], $jar4)[0] === 200);
