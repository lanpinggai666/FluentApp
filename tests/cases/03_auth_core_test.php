<?php
/**
 * 鉴权核心：后门口令移除、引导口令边界、跳转白名单、速率限制、人机验证降级
 */

group('08 管理员口令');

// 现网副本里 admin 行的 hash 本就是引导口令，属合法校验路径
ok('admin / 引导口令可登录（走 password_verify）', Auth::login('admin', 'admin123'));
ok('admin / 错误口令被拒', !Auth::login('admin', 'definitely-wrong'));

$hash = Database::fetch('SELECT password_hash FROM users WHERE role = :r', ['r' => 'admin'])['password_hash'] ?? '';
ok('管理员 hash 不是明文', is_string($hash) && str_starts_with($hash, '$2y$'));

// 设了真实密码后，引导口令必须彻底失效（这正是被删掉的后门的行为）
Setting::set('admin_username', 'legacy_root_probe');
Setting::set('admin_password_hash', password_hash('a-real-secret', PASSWORD_DEFAULT));
ok('遗留路径：已设真实密码时引导口令失效', !Auth::login('legacy_root_probe', 'admin123'));
ok('遗留路径：已设真实密码时该密码可用', Auth::login('legacy_root_probe', 'a-real-secret'));

Setting::set('admin_username', 'fresh_root_probe');
Setting::set('admin_password_hash', '');
ok('全新安装（无 hash）引导口令仍可用，否则装机即锁死', Auth::login('fresh_root_probe', 'admin123'));
ok('引导口令只对配置的管理员账号有效', !Auth::login('whoever-not-admin', 'admin123'));

$originalAdminName = (string) Sandbox::setting('admin_username');
$originalAdminHash = (string) Sandbox::setting('admin_password_hash');
Setting::set('admin_username', 'renamed_root_probe');
Setting::set('admin_password_hash', password_hash('another-secret', PASSWORD_DEFAULT));
$crashed = null;
try {
    Auth::login('renamed_root_probe', 'another-secret');
} catch (Throwable $e) {
    $crashed = $e->getMessage();
}
ok('管理员改名后遗留登录不再因邮箱唯一键崩溃', $crashed === null);
ok('改名后确实建出了对应的管理员账号行',
    (int) Sandbox::$db->query("SELECT COUNT(*) FROM users WHERE username='renamed_root_probe'")->fetchColumn() === 1);

// 复原沙箱配置，避免影响后面的用例
Setting::set('admin_username', $originalAdminName);
Setting::set('admin_password_hash', $originalAdminHash);
Sandbox::$db->exec("DELETE FROM users WHERE username IN ('renamed_root_probe','fresh_root_probe','legacy_root_probe')");

$seed = (string) file_get_contents(BASE_PATH . '/database/seed.sql');
preg_match("/\('admin_password_hash',\s*'([^']+)'/", $seed, $m);
ok('seed 的默认 hash 确实对应引导口令', !empty($m[1]) && password_verify(Auth::BOOTSTRAP_PASSWORD, $m[1]));

foreach (['core/Auth.php' => 'models/User.php', 'controllers/AdminController.php' => '', 'views/admin/login.php' => ''] as $file => $_) {
    $src = (string) file_get_contents(BASE_PATH . '/' . $file);
    if ($file === 'core/Auth.php') {
        ok('引导口令只在 Auth 一处集中定义', substr_count($src, 'admin123') === 1 && str_contains($src, 'BOOTSTRAP_PASSWORD'));
    } else {
        ok(basename($file) . ' 中不再硬编码口令', !str_contains($src, 'admin123'));
    }
}

group('09 跳转白名单');

$_SERVER['HTTP_HOST'] = 'nav.example.com';
foreach ([
    ['?r=user/profile', '?r=user/profile', '站内 ?r= 保留'],
    ['/software/vscode', '/software/vscode', '站内路径保留'],
    ['https://nav.example.com/?r=home', '/?r=home', '本站绝对地址归一为相对'],
    ['http://evil.com/x', '?r=home', '外部地址被拒'],
    ['//evil.com', '?r=home', '协议相对被拒'],
    ['/\\evil.com', '?r=home', '反斜杠变体被拒'],
    ['javascript:alert(1)', '?r=home', '伪协议被拒'],
    ['data:text/html,x', '?r=home', 'data: 被拒'],
    ['../etc/passwd', '?r=home', '非站内前缀被拒'],
    ['', '?r=home', '空值回落'],
    ["/?r=home\r\nX-Evil: 1", '?r=home', '含控制字符整条放弃'],
] as [$in, $want, $label]) {
    ok($label, Auth::internalRedirect($in) === $want);
}
ok('可指定回落目标', Auth::internalRedirect('http://evil.com', '?r=admin/dashboard') === '?r=admin/dashboard');

group('10 速率限制');

$identity = 'probe-' . bin2hex(random_bytes(4));
for ($i = 1; $i <= 8; $i++) {
    $r = RateLimiter::consume('unit-test', $identity, 8, 60);
    ok("第 {$i} 次尝试放行", $r['allowed']);
}
$blocked = RateLimiter::consume('unit-test', $identity, 8, 60);
ok('第 9 次被拒', $blocked['allowed'] === false);
ok('被拒时给出 retry_after', $blocked['retry_after'] > 0 && $blocked['retry_after'] <= 60);
RateLimiter::clear('unit-test', $identity);
ok('clear 后重新放行', RateLimiter::consume('unit-test', $identity, 8, 60)['allowed']);
ok('不同身份互不影响', RateLimiter::consume('unit-test', $identity . '-other', 1, 60)['allowed']
    && !RateLimiter::consume('unit-test', $identity . '-other', 1, 60)['allowed']);
ok('clientIp 只认 REMOTE_ADDR', RateLimiter::clientIp() === (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown-ip'));
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
ok('clientIp 不受 X-Forwarded-For 影响', RateLimiter::clientIp() === '203.0.113.77');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.4';
ok('伪造 XFF 无法绕过限流身份', RateLimiter::clientIp() === '203.0.113.77');

group('11 人机验证与验证码');

Setting::set('captcha_provider', 'geetest');
ok('geetest 归一为 local（不再无条件放行）', CaptchaService::getProvider() === 'local');
ok('geetest 下伪造 lot_number 不再算通过', CaptchaService::verify(['lot_number' => 'x'], 'submit') === false);
Setting::set('captcha_provider', 'local');

$codeRow = ['email' => 'throttle-probe@example.com', 'code' => '123456', 'action' => 'reset_pwd',
            'expires_at' => date('Y-m-d H:i:s', time() + 600), 'is_used' => 0, 'created_at' => date('Y-m-d H:i:s')];
Database::insert('email_verification_codes', $codeRow);
$hits = [];
for ($i = 1; $i <= 9; $i++) {
    $hits[] = VerifyCode::verify($codeRow['email'], '000000', 'reset_pwd');
}
ok('验证码错误尝试全部被拒', !in_array(true, $hits, true));
$right = VerifyCode::verify($codeRow['email'], '123456', 'reset_pwd');
ok('超过尝试上限后即使答案正确也被拒', $right === false);

Database::update('email_verification_codes', ['is_used' => 0], 'email = :e AND code = :c', ['e' => $codeRow['email'], 'c' => '123456']);
$fresh = ['email' => 'throttle-fresh@example.com', 'code' => '654321', 'action' => 'register',
          'expires_at' => date('Y-m-d H:i:s', time() + 600), 'is_used' => 0, 'created_at' => date('Y-m-d H:i:s')];
Database::insert('email_verification_codes', $fresh);
ok('未超限的正常校验仍能通过', VerifyCode::verify($fresh['email'], '654321', 'register') === true);
ok('已消费的验证码不能复用', VerifyCode::verify($fresh['email'], '654321', 'register') === false);
