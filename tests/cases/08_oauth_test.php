<?php
/**
 * OAuth 加固：state 一次性消费、回跳收敛、回调地址不受转发头影响、
 * 未验证邮箱不再自动关联本站账号、同邮箱不可跨账号绑定
 */

group('32 OAuth state 与回跳');

Setting::set('oauth_github_client_id', 'test-client-id');
Setting::set('oauth_github_client_secret', 'test-client-secret');
Setting::set('site_base_url', '');
$_SERVER['HTTP_HOST'] = 'nav.test.local';
$_SERVER['HTTPS'] = '';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
unset($_SERVER['HTTP_REFERER']);

$verifyState = new ReflectionMethod('OAuthService', 'verifyState');
$verifyState->setAccessible(true);

$url = OAuthService::getAuthorizeUrl('github', 'https://evil.example.com/steal');
ok('已配置时能生成授权跳转地址', is_string($url) && str_contains($url, 'github.com/login/oauth/authorize'));
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
$state = (string) ($q['state'] ?? '');
ok('state 随机且足够长', strlen($state) === 32 && ctype_xdigit($state));
$redirectUri = urldecode((string) ($q['redirect_uri'] ?? ''));
ok('回调地址不再被伪造的 X-Forwarded-Proto 改成 https（实际 ' . $redirectUri . '）',
    str_starts_with($redirectUri, 'http://nav.test.local/') && !str_starts_with($redirectUri, 'https://'));

$baseOf = function (): string { return Url::siteBase(); };
ok('未配置站点基址时按请求主机推断', $baseOf() === 'http://nav.test.local');
Setting::set('site_base_url', 'https://nav.example.com/');
ok('配置了 site_base_url 就以它为准（忽略 HTTP_HOST）', $baseOf() === 'https://nav.example.com');
$_SERVER['HTTP_HOST'] = 'evil.example.com';
ok('Host 头被污染也不影响已配置的回调基址', OAuthService::getCallbackUrl('github') === 'https://nav.example.com/?r=auth/oauth-callback&provider=github');
Setting::set('site_base_url', 'javascript:alert(1)');
ok('非法的 site_base_url 被忽略并回落请求推断', $baseOf() === 'http://evil.example.com');
Setting::set('site_base_url', '');
$_SERVER['HTTP_HOST'] = 'x" onload=alert(1)';
ok('畸形 Host 回落到 localhost 而不是拼进 URL', $baseOf() === 'http://localhost');
$_SERVER['HTTP_HOST'] = 'nav.test.local';

ok('state 首次校验通过', $verifyState->invoke(null, $state, 'github') === true);
ok('state 被一次性消费，第二次校验失败（防重放）', $verifyState->invoke(null, $state, 'github') === false);
ok('伪造 state 校验失败', $verifyState->invoke(null, str_repeat('0', 32), 'github') === false);
ok('provider 维度隔离：同值在别的 provider 下不通过', $verifyState->invoke(null, $state, 'microsoft') === false);

$after = OAuthService::getRedirectAfter('github');
ok('回跳目标被收敛为站内地址', $after === '?r=home');
$state2 = OAuthService::getAuthorizeUrl('github', '//evil.example.com');
parse_str((string) parse_url($state2, PHP_URL_QUERY), $q2);
$verifyState->invoke(null, (string) $q2['state'], 'github');
ok('协议相对回跳同样被收敛', OAuthService::getRedirectAfter('github') === '?r=home');
$_SERVER['HTTP_REFERER'] = 'https://nav.test.local/?r=user/profile';
$url3 = OAuthService::getAuthorizeUrl('github', null);
parse_str((string) parse_url($url3, PHP_URL_QUERY), $q3);
$verifyState->invoke(null, (string) $q3['state'], 'github');
ok('未显式给回跳时沿用 Referer 的站内路径', OAuthService::getRedirectAfter('github') === '/?r=user/profile');
ok('state 过期即失效', (function () use ($verifyState) {
    $u = OAuthService::getAuthorizeUrl('github', null);
    parse_str((string) parse_url($u, PHP_URL_QUERY), $q);
    $_SESSION['oauth_state_github']['expires'] = time() - 1;
    return $verifyState->invoke(null, (string) $q['state'], 'github') === false;
})());

group('33 邮箱验证与账号关联');

Sandbox::$db->exec("DELETE FROM oauth_accounts WHERE provider IN ('github','microsoft')");
Sandbox::$db->exec("DELETE FROM users WHERE email IN ('oauth-victim@example.test','oauth-new@example.test','oauth-unverified@example.test')");
$victim = Database::insert('users', [
    'username' => 'oauthvictim', 'email' => 'oauth-victim@example.test',
    'password_hash' => password_hash('secret', PASSWORD_DEFAULT), 'role' => 'user', 'status' => 'active',
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);

$unverified = User::findOrCreateFromOAuth('microsoft', 'ms-uid-1', 'oauth-victim@example.test', '攻击者', '', false);
ok('第三方未验证邮箱时不自动关联他人账号', ($unverified['success'] ?? true) === false);
ok('未关联时也不会顺手建出重复邮箱账号', (int) Sandbox::$db->query("SELECT COUNT(*) FROM users WHERE email='oauth-victim@example.test'")->fetchColumn() === 1);
ok("拒绝时给出可操作的提示", str_contains((string) ($unverified['error'] ?? ''), '个人中心'));

$verified = User::findOrCreateFromOAuth('github', 'gh-uid-1', 'oauth-victim@example.test', '本人', '', true);
ok('已验证邮箱可正常关联到本人账号', ($verified['success'] ?? false) === true && (int) ($verified['user']['id'] ?? 0) === $victim);

$fresh = User::findOrCreateFromOAuth('github', 'gh-uid-2', 'oauth-new@example.test', '新用户', '', false);
ok('邮箱未被占用时仍可正常注册登录', ($fresh['success'] ?? false) === true && (int) ($fresh['user']['id'] ?? 0) > 0);
ok('新账号与第三方身份都落库', (int) Sandbox::$db->query("SELECT COUNT(*) FROM oauth_accounts WHERE provider='github' AND provider_user_id='gh-uid-2'")->fetchColumn() === 1);

$bindForeign = User::bindOAuthAccount((int) $fresh['user']['id'], 'github', 'gh-uid-9', 'oauth-victim@example.test', '冒名');
ok('不允许把他人邮箱的第三方身份绑到自己账号上', ($bindForeign['success'] ?? true) === false);

$stolen = User::findOrCreateFromOAuth('github', 'gh-uid-9', 'oauth-unverified@example.test', '另一人', '', true);
ok('第三方身份不能把已有本站邮箱的人抢注走', (int) ($stolen['user']['id'] ?? 0) !== (int) $fresh['user']['id']);

$oauthSource = (string) file_get_contents(BASE_PATH . '/services/OAuthService.php');
ok('四个身份来源都显式声明 emailVerified', substr_count($oauthSource, "'emailVerified'") >= 4);
ok('回调地址计算不再信任任何转发头', !str_contains($oauthSource, 'HTTP_X_FORWARDED'));

group('34 站点基址统一（邮件内链与 sitemap 不再跟随 Host）');

Setting::set('site_base_url', 'https://nav.example.com');
[$code, $xml] = Sandbox::http('/sitemap.php');
ok('sitemap 使用配置的站点基址', $code === 200 && str_contains($xml, 'https://nav.example.com/?r=home'));
ok('sitemap 不再把请求 Host 写进 loc', !str_contains($xml, '://127.0.0.1'));

$hostUsers = [];
foreach (['core', 'models', 'services', 'controllers', 'public', 'views'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php' && preg_match_all('/HTTP_HOST|HTTP_X_FORWARDED/', (string) file_get_contents($f->getPathname()), $m)) {
            $hostUsers[] = str_replace('\\', '/', substr($f->getPathname(), strlen(BASE_PATH) + 1)) . ' (' . implode(',', array_unique($m[0])) . ')';
        }
    }
}
ok('读 Host 头的只有三处：Url::siteBase、站内跳转比对、ApiAuth 判断是否本机回环', count($hostUsers) === 3);
foreach ($hostUsers as $h) {
    echo '    ' . $h . "\n";
}
Setting::set('site_base_url', '');
