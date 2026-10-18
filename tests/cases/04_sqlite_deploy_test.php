<?php
/**
 * 数据层与部署面：SQLite 并发参数、调试开关、脚本与配置的 HTTP 可达性
 */

group('12 SQLite 运行时参数');

$pdo = Database::pdo();
$mode = strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn());
$busy = (int) $pdo->query('PRAGMA busy_timeout')->fetchColumn();
$sync = (int) $pdo->query('PRAGMA synchronous')->fetchColumn();
ok('journal_mode = wal（实际 ' . $mode . '）', $mode === 'wal');
ok('busy_timeout = 5000（实际 ' . $busy . '）', $busy === 5000);
ok('synchronous 已降低（实际 ' . $sync . '）', $sync <= 1);
ok('驱动识别为 sqlite', Database::getDriver() === 'sqlite');

// 读写并发不该抛 SQLITE_BUSY
$pdo->exec('CREATE TABLE IF NOT EXISTS busy_probe (id INTEGER PRIMARY KEY, v TEXT)');
$pdo->exec('DELETE FROM busy_probe');
$ok = true;
try {
    for ($i = 0; $i < 50; $i++) {
        Database::insert('busy_probe', ['v' => 'row-' . $i]);
        Database::fetchAll('SELECT COUNT(*) c FROM busy_probe');
    }
} catch (Throwable $e) {
    $ok = false;
    echo '  异常: ' . $e->getMessage() . "\n";
}
ok('50 轮写读交替无 SQLITE_BUSY', $ok);
$pdo->exec('DROP TABLE busy_probe');

group('13 调试开关');

$config = (string) file_get_contents(BASE_PATH . '/config/config.php');
ok('APP_DEBUG 不再硬编码为 true', !preg_match('/define\(\s*\'APP_DEBUG\'\s*,\s*true\s*\)/', $config));
// 服务器上不方便配环境变量的（宝塔、共享主机）走 config/.env；能配的容器仍以环境变量为准
ok('APP_DEBUG 由环境决定，不再只看 getenv', str_contains($config, "\$env('APP_DEBUG')") && str_contains($config, 'parse_ini_file'));
ok('.env 值与环境变量的优先级：环境变量胜出', str_contains($config, "getenv(\$key)") && str_contains($config, '$softnavEnv[$key]'));

$entry = (string) file_get_contents(BASE_PATH . '/public/index.php');
ok('入口按 APP_DEBUG 决定 display_errors', str_contains($entry, "ini_set('display_errors', APP_DEBUG"));
ok('入口不再无条件开 display_errors', !preg_match('/ini_set\(\s*\'display_errors\'\s*,\s*\'1\'\s*\)\s*;/', $entry));
ok('Router 在生产分支记录错误日志', str_contains((string) file_get_contents(BASE_PATH . '/core/Router.php'), 'error_log('));

$probe = <<<'PHP'
<?php
define('APP_DEBUG', false);
define('APP_ENV', 'production');
$_GET['r'] = '';
ob_start();
require __DIR__ . '/public/index.php';
$html = ob_get_clean();
fwrite(STDOUT, json_encode(['display_errors' => ini_get('display_errors'), 'bytes' => strlen($html), 'trace' => substr_count($html, '系统错误')]));
PHP;
$probeFile = Sandbox::$root . '/prod_mode_probe.php';
file_put_contents($probeFile, $probe);
$json = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(Sandbox::path($probeFile)) . ' 2>&1');
@unlink($probeFile);
$probeResult = json_decode($json, true);
ok('模拟生产模式：display_errors 关闭且页面正常渲染',
    is_array($probeResult) && $probeResult['display_errors'] === '0' && $probeResult['bytes'] > 1000 && $probeResult['trace'] === 0);

group('14 部署暴露面');

$scriptDirs = ['/database/*.php', '/database/legacy/*.php', '/tools/dev/*.php', '/deploy/*.php'];
$guarded = 0;
$unguarded = [];
foreach ($scriptDirs as $pattern) {
    foreach ((array) glob(BASE_PATH . $pattern) as $file) {
        // version.php 只 return 一个整数：经 HTTP 执行不输出任何内容，也没有副作用
        if (basename((string) $file) === 'version.php') {
            continue;
        }
        $guarded++;
        $src = (string) file_get_contents($file);
        if (!str_contains($src, 'PHP_SAPI') || !str_contains($src, 'http_response_code(404)')) {
            $unguarded[] = str_replace(BASE_PATH . '/', '', (string) $file);
        }
    }
}
ok("运维脚本 {$guarded} 个全部只允许命令行执行", $guarded >= 20 && $unguarded === []);
if ($unguarded) {
    echo '  缺少守卫: ' . implode(', ', $unguarded) . "\n";
}

foreach (['/database/legacy/migrate_v2.php', '/database/legacy/migrate_v12.php', '/tools/dev/inspect_state.php',
    '/tools/dev/test_inspect.php', '/tools/dev/probe_mirrors.php'] as $path) {
    [$code] = Sandbox::http($path);
    ok('正确部署（web 根=public）下 ' . basename($path) . ' 不可达（实际 ' . $code . '）', $code >= 400);
}
foreach (['/database/nav.sqlite', '/database/seed.sql', '/config/config.php', '/database/schema_mysql.sql', '/README.md'] as $path) {
    [$code] = Sandbox::http($path);
    ok('正确部署下拿不到 ' . basename($path) . '（实际 ' . $code . '）', $code >= 400);
}

// 误配部署（web 根=项目根）：脚本必须被 CLI 守卫挡住
$badRoot = Sandbox::startMisconfiguredServer();
foreach (['/database/legacy/migrate_v2.php', '/tools/dev/inspect_state.php', '/tools/dev/test_inspect.php'] as $path) {
    [$code, $body] = Sandbox::rawHttp($badRoot . $path);
    ok('误配部署下 ' . basename($path) . ' 被守卫拦截（' . $code . '，正文 ' . strlen($body) . "B）", $code === 404 && $body === '');
}
ok('误配部署下站点自身仍能工作', Sandbox::rawHttp($badRoot . '/')[0] === 200);
echo "  注意：误配部署时 database/nav.sqlite 等静态文件仍会被服务器直接送出，"
    . "守卫只挡 PHP 脚本；部署必须把 web 根指向 public/（.htaccess 仅对 Apache 生效）。\n";

ok('database/.htaccess 作为 Apache 误配兜底存在', is_file(BASE_PATH . '/database/.htaccess')
    && str_contains((string) file_get_contents(BASE_PATH . '/database/.htaccess'), 'Require all denied'));
ok('config/.htaccess 作为 Apache 误配兜底存在', is_file(BASE_PATH . '/config/.htaccess')
    && str_contains((string) file_get_contents(BASE_PATH . '/config/.htaccess'), 'Require all denied'));
