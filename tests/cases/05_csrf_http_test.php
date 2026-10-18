<?php
/**
 * CSRF 统一拦截：表单覆盖、请求头通道、跨会话令牌不可复用
 */

group('15 POST 表单的令牌覆盖');

$missing = [];
$totalForms = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $src = (string) file_get_contents($f->getPathname());
    $length = strlen($src);
    // 表单标签里现在会带 PHP 短标签（地址由 Url::route() 生成），不能把第一个 > 当成标签结束
    $cursor = 0;
    while (($start = strpos($src, '<form', $cursor)) !== false) {
        $scan = $start + 5;
        $tagEnd = false;
        while ($scan < $length) {
            $gt = strpos($src, '>', $scan);
            if ($gt === false) {
                break;
            }
            if ($src[$gt - 1] !== '?') {
                $tagEnd = $gt;
                break;
            }
            $scan = $gt + 1;
        }
        $cursor = $start + 5;
        if ($tagEnd === false) {
            continue;
        }
        $close = strpos($src, '</form>', $tagEnd);
        if ($close === false) {
            continue;
        }
        $tag = substr($src, $start, $tagEnd - $start + 1);
        $block = substr($src, $start, $close + 7 - $start);
        $cursor = $close + 7;
        if (!preg_match('/method\s*=\s*["\']?post/i', $tag)) {
            continue;
        }
        $totalForms++;
        if (!str_contains($block, 'csrf_token') && !str_contains($block, 'csrfField()')) {
            $missing[] = $f->getFilename();
        }
    }
}
ok("全部 {$totalForms} 个 POST 表单都带 CSRF 凭据", $totalForms > 30 && $missing === []);
if ($missing) {
    echo '  缺少: ' . implode(', ', array_unique($missing)) . "\n";
}
ok('控制器不再各自读 $_POST[csrf_token]', !str_contains(
    (string) file_get_contents(BASE_PATH . '/controllers/AdminController.php')
    . (string) file_get_contents(BASE_PATH . '/controllers/UserController.php'),
    "verifyCsrf(\$_POST['csrf_token']"
));

group('16 无凭据写请求被统一拦截');

foreach (['/?r=submit/save', '/?r=admin/settings', '/?r=admin/saveSoftware', '/?r=admin/deleteSoftware',
          '/?r=admin/mirrors', '/?r=admin/notices', '/?r=admin/comments', '/?r=admin/review',
          '/?r=user/editSoftware', '/?r=api/saveIconColor', '/?r=software/addResource'] as $path) {
    [$code, $body] = Sandbox::http($path, ['id' => 1, 'is_ajax' => '']);
    ok("无凭据 POST $path -> 400", $code === 400);
}
[, $ajaxBody] = Sandbox::http('/?r=api/saveIconColor', ['slug' => 'vscode', 'color' => '#112233'], ['X-Requested-With: XMLHttpRequest']);
$decoded = json_decode($ajaxBody, true);
ok('AJAX 被拒时回 JSON（前端可读）', is_array($decoded) && ($decoded['success'] ?? true) === false && !empty($decoded['error']));
ok('被拒时不改动数据', (string) Sandbox::$db->query("SELECT theme_color FROM softwares WHERE slug='vscode'")->fetchColumn() !== '#112233');

group('17 两条凭据通道与跨会话隔离');

$jar = Sandbox::newJar();
[, $home] = Sandbox::http('/', [], [], $jar);
$token = Sandbox::metaToken($home);
ok('页面 head 暴露 csrf-token meta', is_string($token) && strlen($token) >= 16);

[$code] = Sandbox::http('/?r=api/saveIconColor', ['slug' => 'vscode', 'color' => '#0a0a0a'], ['X-CSRF-Token: ' . $token], $jar);
ok('仅带头（JSON 体拿不到 $_POST）也能通过', $code === 200);
ok('带头的请求确实生效', (string) Sandbox::$db->query("SELECT theme_color FROM softwares WHERE slug='vscode'")->fetchColumn() === '#0a0a0a');

[$code, $page] = Sandbox::http('/?r=submit');
$otherToken = Sandbox::formToken((string) $page);
[$code2] = Sandbox::http('/?r=api/saveIconColor', ['slug' => 'vscode', 'color' => '#999999'], ['X-CSRF-Token: ' . $otherToken], $jar);
ok('别的会话的令牌不可复用', $code2 === 400);
ok('被复用的令牌未生效', (string) Sandbox::$db->query("SELECT theme_color FROM softwares WHERE slug='vscode'")->fetchColumn() === '#0a0a0a');

[$code] = Sandbox::http('/?r=api/ping');
ok('GET 请求不受影响', $code === 200);
[$code] = Sandbox::http('/');
ok('首页 GET 正常', $code === 200);
