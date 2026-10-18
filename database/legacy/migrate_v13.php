<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * v13 迁移：补齐查询热点上缺失的索引，可重复执行。
 *
 * 这批索引对应的是审查中确认的高频过滤/关联列（归属人、评论人、举报状态与渠道外键等），
 * 之前只靠 slug 唯一键与几个组合索引撑。列本身在线上库里已经存在，所以这里不动列结构，
 * 新库请直接使用 schema_sqlite.sql / schema_mysql.sql。
 */
require_once __DIR__ . '/../../config/config.php';
require_once CORE_PATH . '/Database.php';

$pdo = Database::pdo();
$isSqlite = Database::getDriver() === 'sqlite';

$indexes = [
    'idx_softwares_owner' => 'ON softwares(owner_user_id, status)',
    'idx_softwares_category_only' => 'ON softwares(category_id)',
    'idx_comments_user' => 'ON comments(user_id)',
    'idx_reports_status' => 'ON reports(status, created_at)',
    'idx_reports_resource' => 'ON reports(resource_id)',
    'idx_reports_user' => 'ON reports(user_id)',
    'idx_reports_software_status' => 'ON reports(software_id, status)',
    'idx_resources_user' => 'ON software_resources(user_id, status)',
];

$existsSql = $isSqlite
    ? 'SELECT COUNT(*) FROM sqlite_master WHERE type = \'index\' AND name = :n'
    : 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name = :n';

$created = 0;
$skipped = 0;
foreach ($indexes as $name => $clause) {
    $stmt = $pdo->prepare($existsSql);
    $stmt->execute(['n' => $name]);
    if ((int) $stmt->fetchColumn() > 0) {
        $skipped++;
        echo "  跳过 {$name}（已存在）\n";
        continue;
    }
    $prefix = $isSqlite ? 'CREATE INDEX IF NOT EXISTS ' : 'CREATE INDEX ';
    $pdo->exec($prefix . $name . ' ' . $clause);
    $created++;
    echo "  建立 {$name} {$clause}\n";
}

echo "migrate_v13 完成：新建 {$created} 个索引，跳过 {$skipped} 个已存在索引。\n";
