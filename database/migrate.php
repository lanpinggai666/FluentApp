<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit; // 迁移能改表结构，绝不能被匿名 HTTP 请求触发（登录后的网页入口在 ?r=admin/migrate）
}

/**
 * 蓝屏钙软件导航 —— 数据库升级执行器（CLI 外壳）
 *
 *   php database/migrate.php --dry-run     打印当前版本、待执行迁移与语句，不碰库
 *   php database/migrate.php               按编号顺序补齐待执行迁移
 *   php database/migrate.php --yes         迁移里含 DROP/TRUNCATE 等破坏性语句时需要的确认开关
 *   php database/migrate.php --stamp=15    只登记版本号、不执行（用于人工对齐已知良好的库）
 *   php database/migrate.php --stamp=baseline
 *
 * 真正的执行逻辑在 core/Migrator.php —— 后台【结构升级】页走的是同一份代码，
 * 这样「PHP 在容器里、不方便进命令行」时可以改点网页按钮，两边不会漂出两套行为。
 *
 * 升级约定（以后每次改结构都照这个来）：
 *   1. 改 database/schema_sqlite.sql 与 schema_mysql.sql（新库直接是最终结构），两份必须同步；
 *   2. 把 database/version.php 里的基线号 +1；
 *   3. 在 database/migrations/ 放一个 <新编号>_<说明>.sql，内容只描述「从上一个版本到这一版」的变更
 *      （SQLite 版本里不要写列级 COMMENT，SQLite 不支持）；
 *   4. 跑 php tests/run.php，再用 php database/migrate.php --dry-run 看一遍将要执行的语句。
 *
 * 老库（v13 之前、没有 schema_migrations 表）先按 database/legacy/README.md 补齐，再 --stamp=baseline。
 */

$config = require __DIR__ . '/../config/config.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Installer.php';
require_once CORE_PATH . '/Migrator.php';

$baseline = Migrator::baseline();
$driver = Database::getDriver();
$pdo = Database::pdo();

$actionFlags = parseArgs($argv);
Migrator::ensureTable($pdo);

echo "数据库升级执行器 · 驱动 {$driver} · 代码基线 v{$baseline}\n";
echo str_repeat('-', 60) . "\n";

$applied = Migrator::readApplied($pdo);
$skipped = [];
$pending = Migrator::pending($pdo, $skipped);

foreach ($skipped as $badName) {
    echo "  ! 跳过不符合命名规则的文件: {$badName}（应为 <编号>_<说明>.sql，方言专属再加 .sqlite/.mysql）\n";
}

if ($actionFlags['stamp'] !== '') {
    $stamped = Migrator::stamp($pdo, $actionFlags['stamp']);
    if (!$stamped['ok']) {
        fwrite(STDERR, ($stamped['error'] ?? '--stamp 失败') . "\n");
        exit(1);
    }
    echo '  · ' . ($stamped['note'] ?? '已登记') . "\n";
    exit(0);
}

echo "已应用版本: " . ($applied === [] ? '（无）' : implode(', ', array_keys($applied))) . "\n";
echo "待执行迁移: " . ($pending === [] ? '（无）' : implode(', ', array_keys($pending))) . "\n";

if ($applied === [] && $pending === []) {
    echo "\n提示：库里没有任何迁移记录。若这是 database/install.php 装出来的新库，"
        . "应已有 v{$baseline} 记录；若不是，说明这个库不是通过安装程序建立的，"
        . "请先确认结构是否与 schema_*.sql 一致，再用 --stamp=baseline 登记基线。\n";
}

if ($pending === []) {
    $note = Migrator::syncLock($pdo, $actionFlags['dry-run']);
    if ($note !== null) {
        echo "  ·  {$note}\n";
    }
    echo "\n结构已是最新，无需变更。\n";
    exit(0);
}

$failed = false;
foreach ($pending as $version => $files) {
    $statements = Migrator::statements($files);
    $combined = implode("\n", $statements);
    $names = Migrator::label($statements);

    if ($actionFlags['dry-run']) {
        echo "\n[dry-run] v{$version} ← {$names}\n" . indent($combined) . "\n";
        continue;
    }
    if (!$actionFlags['yes'] && Migrator::isDestructive($combined)) {
        echo "\n✗ v{$version} 含 DROP/TRUNCATE 一类破坏性语句。这类变更不可回滚，"
            . "请先完整备份数据库（SQLite: 用 .backup 或连同 -wal 一起复制；MySQL: mysqldump），"
            . "确认无误后加 --yes 重跑。\n";
        $failed = true;
        break;
    }
    $result = Migrator::apply($pdo, (int) $version, $names, $statements);
    if (!$result['ok']) {
        fwrite(STDERR, "✗ 迁移 v{$version} 执行失败，已回滚（SQLite）或停在失败点（MySQL）: "
            . ($result['error'] ?? '未知错误') . "\n");
        $failed = true;
        break;
    }
    echo "  ✓ 已应用 v{$version}（{$names}）\n";
}

if (!$actionFlags['dry-run'] && !$failed) {
    $note = Migrator::syncLock($pdo, false);
    if ($note !== null) {
        echo "  ·  {$note}\n";
    }
    echo "\n升级完成。建议接着跑一次 php deploy/preflight.php 确认站点仍然健康。\n";
}
exit($failed ? 1 : 0);

function parseArgs(array $argv): array
{
    $out = ['dry-run' => false, 'yes' => false, 'stamp' => ''];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $out['dry-run'] = true;
        } elseif ($arg === '--yes') {
            $out['yes'] = true;
        } elseif (preg_match('/^--stamp=(.+)$/', $arg, $m)) {
            $out['stamp'] = $m[1];
        } else {
            fwrite(STDERR, "无法识别的参数: {$arg}\n");
            exit(1);
        }
    }
    return $out;
}

function indent(string $text): string
{
    return '    ' . str_replace("\n", "\n    ", rtrim($text));
}
