<?php
/**
 * 安全与行为回归测试入口。
 *
 *   php tests/run.php              全部用例
 *   php tests/run.php --no-net     跳过依赖外网的用例
 *   php tests/run.php --only=csrf,ssrf   只跑指定用例文件（cases/<name>_test.php）
 *
 * 全部用例都在临时副本上运行，绝不触碰项目里的 database/nav.sqlite。
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit; // 测试脚本只能命令行执行：它会复制项目并起服务器，经 HTTP 触发既危险又没意义
}

require __DIR__ . '/bootstrap.php';

$args = array_slice($argv, 1);
$noNet = in_array('--no-net', $args, true);
$only = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = explode(',', substr($arg, strlen('--only=')));
    }
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// 部分用例会在本进程里直接调用鉴权代码（内部有 session_start / regenerate）。
// CLI 下一旦有输出，这些调用就会告警，所以先把输出整体缓冲住，跑完再打印。
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$started = microtime(true);
Sandbox::$offline = $noNet;
Sandbox::start();
Sandbox::autoload();

echo "沙箱副本: " . Sandbox::$root . "\n";
echo "测试服务: " . Sandbox::$url . "\n";
echo "外网状态: " . (!Sandbox::$online ? '不可用（网络用例自动跳过）' : ($noNet ? '可用，但按要求跳过网络用例' : '可用')) . "\n";

$caseCount = 0; // 用例文件与 run.php 共享全局作用域，变量名要错开（曾被子用例里的 $run 覆盖过）
foreach (glob(__DIR__ . '/cases/*_test.php') as $file) {
    $name = basename($file, '_test.php');
    if ($only !== null && !in_array($name, $only, true)) {
        continue;
    }
    $caseCount++;
    try {
        require $file;
    } catch (Throwable $e) {
        ok('用例文件 ' . $name . ' 未抛异常', false);
        echo '  异常: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

$elapsed = round(microtime(true) - $started, 1);
echo "\n" . str_repeat('-', 52) . "\n";
if (Tally::$fail > 0) {
    echo "失败清单:\n";
    foreach (Tally::$failures as $f) {
        echo "  - {$f}\n";
    }
}
printf("合计: %d 通过 / %d 失败 / %d 跳过   用例文件 %d 个   耗时 %ss\n", Tally::$pass, Tally::$fail, Tally::$skip, $caseCount, $elapsed);

// 之前一直压着缓冲区，是为了让用会话相关 API 的用例不报「headers already sent」
while (ob_get_level() > 0) {
    ob_end_flush();
}

exit(Tally::$fail === 0 ? 0 : 1);
