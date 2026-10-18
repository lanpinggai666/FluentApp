<?php
/**
 * SSRF 拦截、后台链路、凭据只写不读、脏数据渲染、路由完整性与全站页面扫描
 */

Sandbox::resetRateLimits();

group('21 图标代理的地址 SSRF');

foreach (['http://127.0.0.1:8860/', 'http://169.254.169.254/latest/meta-data/', 'http://10.0.0.5/',
          'http://192.168.1.1/', 'http://[::1]/', 'http://2130706433/', 'file:///C:/Windows/win.ini',
          'http://localhost:6379/', 'https://example.com:8443/x.png'] as $target) {
    [$code] = Sandbox::http('/?r=api/iconProxy&url=' . rawurlencode($target));
    ok("拒绝代理 {$target}（{$code}）", $code === 403);
}
[$code] = Sandbox::http('/?r=api/iconProxy');
ok('缺少 url 参数返回 400', $code === 400);

group('22 测速端点只对后台开放');

foreach (['/api/probeMirror&template=http://127.0.0.1:9999/', '/api/probeAllMirrors'] as $path) {
    [$code, $body] = Sandbox::http('/?r=' . $path);
    ok("匿名访问 $path 不触发外发", $code === 302 && stripos($body, 'success') === false);
}
$admin = Sandbox::adminJar();
[$code, $body] = Sandbox::http('/?r=api/probeAllMirrors', [], [], $admin);
ok('管理员访问测速端点可用', $code === 200 && str_contains($body, '"success"'));

group('23 脏数据渲染（直接写库绕过入库校验）');

Sandbox::$db->exec("UPDATE softwares SET official_url='javascript:alert(document.cookie)', icon_url='https://evil.example.com/x\" onmouseover=\"alert(1)', github_url=NULL WHERE slug='vscode'");
Sandbox::$db->exec("UPDATE softwares SET other_urls=" . Sandbox::$db->quote(json_encode([
    ['type' => '第三方下载站', 'name' => '网盘', 'url' => 'javascript:alert(1)'],
    ['type' => 'Github', 'name' => '合法渠道', 'url' => 'https://good.example.com/dl'],
], JSON_UNESCAPED_UNICODE)) . " WHERE slug='vscode'");
[$code, $detail] = Sandbox::http('/?r=software/view&slug=vscode');
ok('详情页仍正常渲染', $code === 200 && strlen($detail) > 10000);
ok('数据位 href 回落为 #', substr_count($detail, 'href="#"') >= 4);
ok('没有出现可执行的 javascript:alert href', !str_contains($detail, 'href="javascript:alert'));
ok('属性撑破被挡住', !str_contains($detail, 'onmouseover="alert(1)'));
ok('合法渠道链接保留', str_contains($detail, 'https://good.example.com/dl'));
ok('页面无 PHP 错误', !preg_match('/系统错误|Fatal error|Warning:|Deprecated:/', $detail));

Sandbox::setSetting('site_footer', '<script>alert(document.cookie)</script><a href="javascript:alert(1)">点我</a><img src="x" onerror="alert(2)"><a href="https://beian.miit.gov.cn" onclick="alert(3)">备案</a><span>© 页脚标记</span>');
[, $home] = Sandbox::http('/');
ok('页脚 script 标签被剥离', !str_contains($home, '<script>alert(document.cookie)'));
ok('页脚事件属性被丢弃', !str_contains($home, 'onerror="alert(2)"') && !str_contains($home, 'onclick="alert(3)"'));
ok('页脚伪协议链接被丢弃', !str_contains($home, 'href="javascript:alert(1)"'));
ok('页脚合法链接与文字保留', str_contains($home, 'href="https://beian.miit.gov.cn"') && str_contains($home, '© 页脚标记'));
Sandbox::setSetting('site_footer', '');

// 后台用户页：用户名、封禁理由、受限权限三处都投毒，属性逃逸必须失效
$now = date('Y-m-d H:i:s');
Sandbox::$db->prepare('INSERT INTO users (username,email,password_hash,avatar_url,role,status,ban_reason,restricted_permissions,created_at,updated_at,last_login_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute(["x'alert(4)'y", 'xss-probe@local.host', password_hash('p', PASSWORD_DEFAULT), '', 'user', 'active',
               "reason' onmouseover='alert(5)", json_encode(['submit_software"><img src=x onerror=alert(6)']), $now, $now, $now]);
[, $usersPage] = Sandbox::http('/?r=admin/users', [], [], $admin);
Sandbox::$db->exec("DELETE FROM users WHERE email='xss-probe@local.host'");
ok('后台用户页正常渲染', strlen($usersPage) > 3000);
ok('用户名原始单引号未泄漏到 HTML', !str_contains($usersPage, "x'alert(4)'y"));
ok('onclick 参数以 \\u0027 形态出现', str_contains($usersPage, '\u0027alert(4)\u0027'));
ok('封禁理由无法闭合属性', !str_contains($usersPage, "onmouseover='alert(5)"));
ok('受限权限以转义文本呈现（标签未真正闭合）', !str_contains($usersPage, '<img src=x onerror=alert(6)>'));
ok('受限权限内容仍然可读', str_contains($usersPage, 'submit_software'));
ok('后台用户页无 PHP 错误', !preg_match('/系统错误|Call to undefined/', $usersPage));

group('24 后台审核链路');

Sandbox::$db->exec("UPDATE softwares SET status='pending' WHERE slug='vscode'");
Sandbox::$db->exec("UPDATE softwares SET official_url='https://code.visualstudio.com', other_urls=NULL, icon_url='https://cdn.example.com/vscode.png' WHERE slug='vscode'");
$id = (int) Sandbox::$db->query("SELECT id FROM softwares WHERE slug='vscode'")->fetchColumn();
[, $pendingPage] = Sandbox::http('/?r=admin/pending', [], [], $admin);
$token = Sandbox::formToken($pendingPage);
[$code] = Sandbox::http('/?r=admin/review', ['id' => $id, 'action' => 'approve', 'csrf_token' => ''], [], $admin);
ok('无凭据的审核被拒', $code === 400);
ok('被拒的审核未改状态', (string) Sandbox::$db->query("SELECT status FROM softwares WHERE id=$id")->fetchColumn() === 'pending');
[$code] = Sandbox::http('/?r=admin/review', ['id' => $id, 'action' => 'approve', 'csrf_token' => $token], [], $admin);
ok('带凭据的审核通过', $code === 302);
ok('状态已发布', (string) Sandbox::$db->query("SELECT status FROM softwares WHERE id=$id")->fetchColumn() === 'published');
[$code] = Sandbox::http('/?r=admin/review', ['id' => $id, 'action' => 'nonsense', 'csrf_token' => $token], [], $admin);
ok('非法 action 被业务层拒绝而不是 500', $code === 302);

group('25 后台凭据只写不读');

$secrets = ['smtp_password' => 'SMTP-secret-X', 'captcha_secret_key' => 'CAPTCHA-secret-Y',
            'oauth_ms_client_secret' => 'MS-secret-Z', 'oauth_google_client_secret' => 'GG-secret-W',
            'oauth_github_client_secret' => 'GH-secret-V', 'ai_api_key' => 'sk-legacy-U'];
foreach ($secrets as $k => $v) {
    Sandbox::setSetting($k, $v);
}
[, $settingsPage] = Sandbox::http('/?r=admin/settings', [], [], $admin);
foreach ($secrets as $k => $v) {
    ok("设置页不出现 $k 的明文", !str_contains($settingsPage, $v));
}
ok('设置页给出「已设置」提示（' . substr_count($settingsPage, '已设置') . ' 处）', substr_count($settingsPage, '已设置') >= 5);
$token = Sandbox::formToken($settingsPage);
Sandbox::http('/?r=admin/settings', ['csrf_token' => $token, 'site_name' => '只写不读验证', 'smtp_password' => '', 'ai_api_key' => ''], [], $admin);
foreach ($secrets as $k => $v) {
    ok("留空提交后 $k 未被清空", Sandbox::setting($k) === $v);
}
ok('普通字段照常保存', Sandbox::setting('site_name') === '只写不读验证');
Sandbox::http('/?r=admin/settings', ['csrf_token' => $token, 'smtp_password' => 'SMTP-rotated'], [], $admin);
ok('填新值时确实覆盖', Sandbox::setting('smtp_password') === 'SMTP-rotated');

$providers = json_encode([
    ['name' => 'DeepSeek', 'api_base' => 'https://api.deepseek.com/v1', 'api_key' => 'sk-DEEP-1', 'model' => 'deepseek-chat', 'enabled' => '1'],
    ['name' => 'Qwen', 'api_base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'api_key' => 'sk-QWEN-2', 'model' => 'qwen-plus', 'enabled' => '1'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
Sandbox::setSetting('ai_providers_json', $providers);
// 多线路配置同样不能把密钥写进 HTML：隐藏域里的 JSON 必须是脱敏副本
[, $providersPage] = Sandbox::http('/?r=admin/settings', [], [], $admin);
foreach (['sk-DEEP-1', 'sk-QWEN-2'] as $secret) {
    ok("设置页不出现线路密钥明文 {$secret}", !str_contains($providersPage, $secret));
}
$token = Sandbox::formToken($providersPage);
$swapped = json_encode([
    ['name' => 'Qwen', 'api_base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'api_key' => '', 'model' => 'qwen-plus', 'enabled' => '1'],
    ['name' => 'DeepSeek', 'api_base' => 'https://api.deepseek.com/v1', 'api_key' => '', 'model' => 'deepseek-chat', 'enabled' => '1'],
    ['name' => 'Moonshot', 'api_base' => 'https://api.moonshot.cn/v1', 'api_key' => '', 'model' => 'moonshot', 'enabled' => '1'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
Sandbox::http('/?r=admin/settings', ['csrf_token' => $token, 'ai_providers_json' => $swapped], [], $admin);
$saved = json_decode((string) Sandbox::setting('ai_providers_json'), true);
$byName = [];
foreach ($saved as $p) {
    $byName[$p['name']] = $p['api_key'];
}
ok('调换优先级后密钥不会串号', ($byName['Qwen'] ?? '') === 'sk-QWEN-2' && ($byName['DeepSeek'] ?? '') === 'sk-DEEP-1');
ok('新增线路保持空密钥', ($byName['Moonshot'] ?? 'x') === '');

group('26 路由完整性');

$targets = [];
foreach ([BASE_PATH . '/views', BASE_PATH . '/public/assets/js'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && in_array($f->getExtension(), ['php', 'js'], true)) {
            // <code>/<pre> 里的是「给运维看的示例 URL」，不是页面导航，不该算进路由完整性检查
            $src = (string) preg_replace('#<(code|pre)\b[^>]*>.*?</\1>#is', '', (string) file_get_contents($f->getPathname()));
            preg_match_all('#[?&]r=([a-zA-Z0-9_\-]+/[a-zA-Z0-9_\-]+)#', $src, $m);
            foreach ($m[1] as $route) {
                $targets[$route][] = $f->getFilename();
            }
        }
    }
}
$dangling = [];
foreach ($targets as $route => $where) {
    [$controller, $action] = explode('/', $route, 2);
    $file = BASE_PATH . '/controllers/' . ucfirst($controller) . 'Controller.php';
    if (!is_file($file)) {
        $dangling[] = $route . ' (控制器缺失)';
        continue;
    }
    $method = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $action))));
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', (string) file_get_contents($file))) {
        $dangling[] = $route . ' (方法缺失，出现于 ' . implode(',', array_unique($where)) . ')';
    }
}
ok(count($targets) . ' 个站内路由目标全部可解析', $dangling === []);
foreach ($dangling as $d) {
    echo '  悬空: ' . $d . "\n";
}

group('27 全站页面扫描');

$jar = Sandbox::adminJar();
$anon = Sandbox::newJar();
// 已登录会话访问登录/注册页会被有意重定向，所以这几个用匿名会话扫
$paths = [
    '/' => $jar, '/?r=home' => $jar, '/?r=submit' => $jar, '/?r=software/view&slug=vscode' => $jar,
    '/category/1' => $jar, '/?r=user/home&username=admin' => $jar, '/?r=user/notifications' => $jar,
    '/?r=user/profile' => $jar, '/?r=auth/login' => $anon, '/?r=auth/register' => $anon,
    '/?r=admin/dashboard' => $jar, '/?r=admin/pending' => $jar, '/?r=admin/softwares' => $jar,
    '/?r=admin/users' => $jar, '/?r=admin/settings' => $jar, '/?r=admin/reports' => $jar,
    '/?r=admin/mirrors' => $jar, '/?r=admin/comments' => $jar, '/?r=admin/notices' => $jar,
    '/?r=admin/corrections' => $jar, '/sitemap.php' => $anon,
];
foreach ($paths as $path => $sessionJar) {
    [$code, $body] = Sandbox::http($path, [], [], $sessionJar);
    $err = (bool) preg_match('/系统错误|Fatal error|Call to undefined|is invalid|Deprecated:/', $body);
    ok(sprintf('%-32s -> %d%s', $path, $code, $err ? ' [含错误]' : ''), $code === 200 && !$err);
}

group('28 外网相关（证书与逐跳复检）');

if (!Sandbox::$online || Sandbox::$offline) {
    skip('公网图标可正常代理', '沙箱无外网');
    skip('自签证书主机不被代理', '沙箱无外网');
    skip('公网重定向到内网被逐跳复检拦下', '沙箱无外网');
} else {
    [$code, $body] = Sandbox::http('/?r=api/iconProxy&url=' . rawurlencode('https://www.gstatic.com/webp/gallery/1.jpg'));
    ok('公网图标可代理（' . strlen($body) . ' 字节）', $code === 200 && strlen($body) > 1000);
    [$code] = Sandbox::http('/?r=api/iconProxy&url=' . rawurlencode('https://self-signed.badssl.com/x.png'));
    ok('自签证书主机不再被代理', $code !== 200);
    foreach (['127.0.0.1:9999/', '169.254.169.254/', '10.0.0.5/'] as $inner) {
        [$code, $body] = Sandbox::http('/?r=api/iconProxy&url=' . rawurlencode('https://httpbin.org/redirect-to?url=' . urlencode('http://' . $inner) . '&status_code=302'));
        ok("公网重定向到 {$inner} 被逐跳复检拦下", $code !== 200 && $body === '');
    }
}
