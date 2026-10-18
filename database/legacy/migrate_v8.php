<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * v8 数据库迁移：提交邮箱、安全修改令牌和邮件通知状态。
 * 兼容 SQLite 与 MySQL，可重复执行。
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
$driver = Database::getDriver();
echo "==> 执行 v8 迁移 ({$driver})..." . PHP_EOL;

$tables = [
    'softwares' => [
        'submitter_email' => $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(254) NULL DEFAULT NULL',
    ],
    'software_corrections' => [
        'submitter_email' => $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(254) NULL DEFAULT NULL',
        'edit_token_hash' => $driver === 'sqlite' ? 'TEXT' : 'CHAR(64) NULL DEFAULT NULL',
        'edit_token_expires_at' => $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL DEFAULT NULL',
        'notification_status' => $driver === 'sqlite' ? "TEXT NOT NULL DEFAULT 'pending'" : "VARCHAR(20) NOT NULL DEFAULT 'pending'",
        'notification_error' => $driver === 'sqlite' ? 'TEXT' : 'TEXT NULL',
        'notified_at' => $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL DEFAULT NULL',
    ],
];

foreach ($tables as $table => $columns) {
    foreach ($columns as $column => $definition) {
        if ($driver === 'sqlite') {
            $existing = [];
            foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[$row['name']] = true;
            }
            if (!isset($existing[$column])) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                echo "    + 新增字段 {$table}.{$column}" . PHP_EOL;
            }
            continue;
        }

        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $check->execute([$table, $column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            echo "    + 新增字段 {$table}.{$column}" . PHP_EOL;
        }
    }
}

if ($driver === 'sqlite') {
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_corrections_edit_token ON software_corrections(edit_token_hash, edit_token_expires_at)');
} else {
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software_corrections' AND INDEX_NAME = 'idx_corrections_edit_token'"
    );
    $check->execute();
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `software_corrections` ADD KEY `idx_corrections_edit_token` (`edit_token_hash`, `edit_token_expires_at`)');
    }
}

echo "==> v8 迁移完成。" . PHP_EOL;
