<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * v12 迁移：区分「特别说明」(一句话纯文本, special_notes) 与「软件说明」(Markdown 长文, description_md)。
 * 为 softwares 与 software_corrections 增加 description_md 字段，可重复执行。
 */
require_once __DIR__ . '/../../config/config.php';
require_once CORE_PATH . '/Database.php';
$pdo = Database::pdo();

$targets = ['softwares', 'software_corrections'];
foreach ($targets as $table) {
    $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'name');
    if (!in_array('description_md', $names, true)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN description_md TEXT");
        echo "已为 {$table} 增加 description_md 列\n";
    } else {
        echo "{$table}.description_md 已存在，跳过\n";
    }
}
echo "v12 migration complete (sqlite)\n";
