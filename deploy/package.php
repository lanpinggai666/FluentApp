<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit; // 打包脚本会读写目录，只允许命令行执行
}

/**
 * 蓝屏钙软件导航 —— 生产部署包生成
 *
 *   php deploy/package.php [--out=dist] [--no-tar]
 *
 * 产出一个只含运行所需文件的目录（dist/<包名>/）加同名 .tar.gz，并写 MANIFEST.txt
 * 记录每个文件的字节数与 SHA-256 —— 以后升级比对上传是否完整、有没有被改动，就看这份清单。
 *
 * 规则很明确：开发期内容（tests/、tools/、.agent、本地库、config/.env）一律不进包；
 * 打包前先跑一次 tests/run.php，因为包里没测试，出问题只能靠 preflight 兜。
 */

$sourceRoot = dirname(__DIR__);
$options = parseOptions($argv, $sourceRoot);
$baseline = (int) require $sourceRoot . '/database/version.php';
$appVersion = (string) (require $sourceRoot . '/config/config.php')['app']['version'];

$packageName = "softnav-{$appVersion}-b{$baseline}";
$staging = rtrim($options['out'], '/\\') . '/' . $packageName;

echo "打包 {$packageName}\n";
echo "  源目录: {$sourceRoot}\n";
echo "  输出:   {$staging}\n";

if (is_dir($staging) && $options['fresh']) {
    removeDir($staging);
}
if (!is_dir($staging) && !mkdir($staging, 0755, true)) {
    fwrite(STDERR, "无法创建输出目录: {$staging}\n");
    exit(1);
}

$copied = [];
foreach (productionEntries($sourceRoot) as $relative) {
    $from = $sourceRoot . '/' . $relative;
    $to = $staging . '/' . $relative;
    if (is_dir($from)) {
        copyTree($from, $to, $copied, $relative);
    } else {
        ensureDir(dirname($to));
        if (!copy($from, $to)) {
            fwrite(STDERR, "复制失败: {$relative}\n");
            exit(1);
        }
        $copied[$relative] = filesize($from);
    }
}

// 包里额外放一份版本与清单，升级排查时不用回源机器
file_put_contents($staging . '/VERSION', $packageName . "\n生成时间: " . date('Y-m-d H:i:s')
    . "\nPHP: " . PHP_VERSION . "\n");
$copied['VERSION'] = filesize($staging . '/VERSION');

ksort($copied);
$lines = ["# {$packageName} 文件清单（相对路径 / 字节 / SHA-256）"];
foreach (array_keys($copied) as $relative) {
    $path = $staging . '/' . $relative;
    $lines[] = sprintf("%9d  %s  %s", filesize($path), strtoupper(substr(hash_file('sha256', $path), 0, 16)), str_replace('\\', '/', $relative));
}
file_put_contents($staging . '/MANIFEST.txt', implode("\n", $lines) . "\n");

$leaked = [];
foreach (['config/.env', 'database/nav.sqlite', 'tests/run.php', 'tools/dev/inspect_state.php', 'Agent.md', '.agent'] as $forbidden) {
    if (file_exists($staging . '/' . $forbidden)) {
        $leaked[] = $forbidden;
    }
}
if ($leaked !== []) {
    fwrite(STDERR, "✗ 打包泄漏了开发期内容: " . implode(', ', $leaked) . "\n");
    exit(1);
}

foreach (['public/index.php', 'public/install.php', 'config/config.php', 'core/Router.php', 'core/Installer.php',
    'database/schema_sqlite.sql', 'database/seed.sql', 'deploy/preflight.php', 'API.md'] as $required) {
    if (!is_file($staging . '/' . $required)) {
        fwrite(STDERR, "✗ 包里缺少运行必需文件: {$required}\n");
        exit(1);
    }
}

$totalBytes = (int) array_sum($copied) + (int) filesize($staging . '/MANIFEST.txt');
printf("\n完成：%d 个文件（不含 MANIFEST.txt），%.1f MB\n", count($copied), $totalBytes / 1048576);
echo "  清单: {$staging}/MANIFEST.txt\n";

if ($options['no-tar']) {
    echo "  已按 --no-tar 跳过压缩，可直接上传 {$staging}\n";
    exit(0);
}
$archive = $staging . '.tar.gz';
$tar = findTar();
if ($tar === '') {
    echo "  ! 没找到 tar 命令，请手动压缩该目录（例如用 7-Zip / WinRAR 打成 " . basename($staging) . ".tar.gz）\n";
    exit(0);
}
// 切到 dist 目录用相对路径压缩：给 tar 传 F:\... 这类盘符路径时，
// Windows 上的 bsdtar 会把「F:」当成远程主机名，直接报 Cannot connect to F。
$previous = getcwd();
chdir(dirname($staging));
$command = sprintf('%s -czf %s -C . %s', escapeshellarg($tar),
    escapeshellarg(basename($archive)), escapeshellarg(basename($staging)));
exec($command . ' 2>&1', $output, $exitCode);
if ($previous !== false) {
    chdir($previous);
}
if ($exitCode !== 0 || !is_file($archive)) {
    fwrite(STDERR, "压缩失败（tar 退出码 {$exitCode}）：\n" . implode("\n", $output) . "\n");
    exit(1);
}
printf("  压缩包: %s (%.1f MB)\n", $archive, filesize($archive) / 1048576);
echo "\n上传后在服务器上依次执行：\n";
echo "  php database/install.php     # 建库并设置管理员口令\n";
echo "  php deploy/preflight.php     # 上线体检\n";
exit(0);

function parseOptions(array $argv, string $sourceRoot): array
{
    $out = ['out' => $sourceRoot . '/dist', 'no-tar' => false, 'fresh' => true];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--no-tar') {
            $out['no-tar'] = true;
        } elseif (preg_match('/^--out=(.+)$/', $arg, $m)) {
            $out['out'] = $m[1];
        } else {
            fwrite(STDERR, "无法识别的参数: {$arg}（支持 --out=目录 与 --no-tar）\n");
            exit(1);
        }
    }
    return $out;
}

/**
 * 生产所需条目。刻意用白名单：以后新增目录默认不进包，
 * 比「黑名单漏一个就把测试代码和内网库传到公网」安全。
 */
function productionEntries(string $root): array
{
    $entries = ['index.php', 'README.md', 'DEPLOY.md', 'API.md', 'config', 'core', 'models', 'services',
        'controllers', 'views', 'public', 'database', 'deploy'];
    return array_values(array_filter($entries, function ($entry) use ($root) {
        return file_exists($root . '/' . $entry);
    }));
}

/** 目录内排除：开发脚本、本地数据、临时产物。 */
function isExcluded(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);
    // config/.env 是每台机器自己的秘密，绝不进包（.env.example 要进）
    if ($relative === '.env' || str_ends_with($relative, '/.env')) {
        return true;
    }
    // 安装标记带着「这台机器已经装过」的语义：进包会让新服务器误判已装机，
    // 网页向导直接 404，运维连安装入口都找不到
    if ($relative === 'config/install.lock') {
        return true;
    }
    foreach (['database/nav.sqlite', 'database/nav.sqlite-wal', 'database/nav.sqlite-shm'] as $exact) {
        if ($relative === $exact) {
            return true;
        }
    }
    // deploy/ 只带体检脚本与配置模板：打包脚本自己、以及本地随手放的笔记都不进包
    if (str_starts_with($relative, 'deploy/')) {
        $base = basename($relative);
        return !($base === 'preflight.php' || str_ends_with($base, '.example'));
    }
    return (bool) preg_match('/(\.dev|\.orig|\.log|\.swp|~)$/i', $relative);
}

function copyTree(string $from, string $to, array &$copied, string $prefix = ''): void
{
    ensureDir($to);
    foreach (scandir($from) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $relative = trim($prefix . '/' . $entry, '/');
        if (isExcluded($relative)) {
            continue;
        }
        $path = $from . '/' . $entry;
        $dest = $to . '/' . $entry;
        if (is_dir($path)) {
            copyTree($path, $dest, $copied, $relative);
        } else {
            copy($path, $dest);
            $copied[$relative] = filesize($path);
        }
    }
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function removeDir(string $dir): void
{
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? removeDir($path) : unlink($path);
    }
    rmdir($dir);
}

function findTar(): string
{
    foreach (['tar', 'C:\\Windows\\System32\\tar.exe'] as $candidate) {
        $out = [];
        $code = 0;
        exec(escapeshellarg($candidate) . ' --version 2>&1', $out, $code);
        if ($code === 0) {
            return $candidate;
        }
    }
    return '';
}
