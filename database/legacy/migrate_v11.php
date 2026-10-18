<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

require_once __DIR__ . '/../../config/config.php';
require_once CORE_PATH . '/Database.php';
$pdo = Database::pdo();
$columns = $pdo->query("PRAGMA table_info(reports)")->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($columns, 'name');
if (!in_array('review_reason', $names, true)) {
    $pdo->exec('ALTER TABLE reports ADD COLUMN review_reason TEXT');
}
echo "v11 migration complete (sqlite)\n";
