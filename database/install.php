<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit; // 这里是命令行入口；网页装机走 public/install.php（带 CSRF、限流与装完自锁）
}

/**
 * 蓝屏钙软件导航 —— 命令行安装
 *
 * 用法（在项目根执行）：
 *   php database/install.php                        SQLite，随机生成管理员口令并只打印一次
 *   php database/install.php --admin=admin --password='你自己的口令'
 *   php database/install.php --db=mysql --create-database
 *   php database/install.php --base-url=https://example.com --no-seed
 *   php database/install.php --force                覆盖已有库重来（先备份！）
 *
 * 实际逻辑在 core/Installer.php —— 网页安装向导用的是同一个类，两条路建出来的库完全一致。
 * 只是升级结构请改用 php database/migrate.php，不要重跑这里。
 */

$config = require dirname(__DIR__) . '/config/config.php'; // 同时定义 BASE_PATH / CORE_PATH 等
require_once CORE_PATH . '/Installer.php';

$baseline = (int) require __DIR__ . '/version.php';
$options = parseOptions($argv);
$driver = $options['db'];
$sqlitePath = $options['sqlite-path'] !== '' ? $options['sqlite-path'] : (string) $config['db']['sqlite']['path'];
$mysql = (array) $config['db']['mysql'];
if ($options['db-name'] !== '') {
    $mysql['database'] = $options['db-name'];
}

echo "蓝屏钙软件导航 安装程序 · 结构基线 v{$baseline} · 驱动 {$driver}\n";
echo str_repeat('-', 60) . "\n";

$problems = Installer::problems($driver, $sqlitePath, $mysql);
if ($problems !== []) {
    foreach ($problems as $message) {
        echo "  ✗ {$message}\n";
    }
    echo "\n环境自检未通过，安装中止。补齐后重新执行即可，本脚本不会留下半个库。\n";
    exit(1);
}
echo "  ✓ 环境自检通过（PHP " . PHP_VERSION . "，扩展齐全，目录可写）\n";

$result = Installer::run([
    'driver' => $driver,
    'sqlitePath' => $sqlitePath,
    'mysql' => $mysql,
    'createDatabase' => $options['create-database'],
    'seed' => $options['seed'],
    'admin' => $options['admin'],
    'password' => $options['password'],
    'baseUrl' => $options['base-url'],
    'baseline' => $baseline,
    'reset' => $options['force'],
]);

foreach ($result['steps'] as $message) {
    echo "  ✓ {$message}\n";
}

if (!$result['ok']) {
    echo "  ✗ {$result['error']}\n";
    echo "    （没有对目标库做任何改动。确认要推倒重来请加 --force；只是升级结构请用 php database/migrate.php）\n";
    exit(1);
}

echo str_repeat('-', 60) . "\n";
echo "安装完成：{$result['tables']} 张表，软件 {$result['softwares']} 条，管理员账号 {$result['admin']['username']}\n";

// 与网页向导写同一份安装标记：体检靠它核对结构版本，向导靠它彻底关闭
$lock = Installer::writeLock([
    'installed_via' => 'cli',
    'schema_baseline' => $baseline,
    'driver' => $result['driver'],
    'admin_username' => $result['admin']['username'],
]);
echo $lock['ok'] ? "  ✓ 安装标记 config/install.lock 已写入（网页向导随之关闭）\n"
    : "  ! {$lock['error']}\n";
if ($result['admin']['generated']) {
    echo "\n管理员密码（只打印这一次，请立即保存到密码管理器）：\n  {$result['admin']['password']}\n";
}
echo "\n下一步：\n";
echo "  1. 把 web 根指向 public/（不要指向项目根），确认 database/ 与 config/ 不在可下载路径里\n";
echo "  2. 让 web 进程用户能写数据库所在目录：php deploy/preflight.php 会替你检查\n";
echo "  3. 后台登录一次，然后在【系统与AI设置】里补站点信息、SMTP、验证码与镜像源\n";
echo "  4. 上线前跑 php deploy/preflight.php，全绿再对外\n";
exit(0);

/** 只支持 --key=value 与几个开关，装一次的东西不值得做完整的参数解析器。 */
function parseOptions(array $argv): array
{
    $options = ['db' => 'sqlite', 'force' => false, 'seed' => true, 'create-database' => false,
        'admin' => '', 'password' => '', 'base-url' => '', 'sqlite-path' => '', 'db-name' => ''];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--force') {
            $options['force'] = true;
        } elseif ($arg === '--no-seed') {
            $options['seed'] = false;
        } elseif ($arg === '--create-database') {
            $options['create-database'] = true;
        } elseif (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
            if (!array_key_exists($m[1], $options)) {
                fwrite(STDERR, "不支持的参数: --{$m[1]}（可用 --db --admin --password --base-url --sqlite-path --db-name，以及开关 --force --no-seed --create-database）\n");
                exit(1);
            }
            $options[$m[1]] = $m[2];
        } else {
            fwrite(STDERR, "无法识别的参数: {$arg}\n");
            exit(1);
        }
    }
    if (!in_array($options['db'], ['sqlite', 'mysql'], true)) {
        fwrite(STDERR, "--db 只支持 sqlite 或 mysql（auto 是运行时兜底，装机必须指定一个）\n");
        exit(1);
    }
    if ($options['base-url'] !== '' && !preg_match('#^https?://[^\s]+$#', $options['base-url'])) {
        fwrite(STDERR, "--base-url 必须是带 http(s):// 的完整地址，且不能包含空格\n");
        exit(1);
    }
    return $options;
}
