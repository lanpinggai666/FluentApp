<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * v10 数据库迁移：举报邮箱与审核通知字段。
 */
define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';
$pdo = Database::pdo();
$driver = Database::getDriver();
$columns = [
    'email' => $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(254) NULL DEFAULT NULL',
    'notification_status' => $driver === 'sqlite' ? "TEXT NOT NULL DEFAULT 'pending'" : "VARCHAR(20) NOT NULL DEFAULT 'pending'",
    'notification_error' => $driver === 'sqlite' ? 'TEXT' : 'TEXT NULL',
    'notified_at' => $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL DEFAULT NULL',
];
foreach ($columns as $column => $definition) {
    if ($driver === 'sqlite') {
        $exists = false;
        foreach ($pdo->query('PRAGMA table_info(reports)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['name'] === $column) { $exists = true; break; }
        }
        if (!$exists) $pdo->exec("ALTER TABLE reports ADD COLUMN {$column} {$definition}");
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute(['reports', $column]);
        if ((int)$stmt->fetchColumn() === 0) $pdo->exec("ALTER TABLE `reports` ADD COLUMN `{$column}` {$definition}");
    }
}
echo "v10 migration complete ({$driver})\n";
