<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 蓝屏钙软件导航网 - v7 数据库迁移
 * 新增并扩展软件补充信息审核表，兼容 SQLite 与 MySQL，可重复执行。
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
$driver = Database::getDriver();
echo "==> 执行 v7 迁移 ({$driver})..." . PHP_EOL;

$fields = [
    'name', 'icon_url', 'official_url', 'github_url', 'github_release_keyword',
    'category_id', 'summary', 'special_notes', 'price_type', 'is_open_source',
    'license', 'developer', 'publisher', 'tags', 'screenshot_url',
    'download_channels', 'contact'
];

if ($driver === 'sqlite') {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS software_corrections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  software_id INTEGER NOT NULL,
  name TEXT,
  icon_url TEXT,
  official_url TEXT,
  github_url TEXT,
  github_release_keyword TEXT,
  category_id INTEGER,
  summary TEXT,
  special_notes TEXT,
  price_type TEXT,
  is_open_source INTEGER,
  license TEXT,
  developer TEXT,
  publisher TEXT,
  tags TEXT,
  screenshot_url TEXT,
  download_channels TEXT,
  contact TEXT,
  submitter_ip TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  review_note TEXT,
  reviewed_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $existingColumns = [];
    foreach ($pdo->query("PRAGMA table_info(software_corrections)")->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $existingColumns[$column['name']] = true;
    }

    $columnTypes = [
        'name' => 'TEXT',
        'icon_url' => 'TEXT',
        'official_url' => 'TEXT',
        'github_url' => 'TEXT',
        'github_release_keyword' => 'TEXT',
        'category_id' => 'INTEGER',
        'summary' => 'TEXT',
        'special_notes' => 'TEXT',
        'price_type' => 'TEXT',
        'is_open_source' => 'INTEGER',
        'license' => 'TEXT',
        'developer' => 'TEXT',
        'publisher' => 'TEXT',
        'tags' => 'TEXT',
        'screenshot_url' => 'TEXT',
        'download_channels' => 'TEXT',
        'contact' => 'TEXT',
    ];

    foreach ($fields as $field) {
        if (!isset($existingColumns[$field])) {
            $pdo->exec("ALTER TABLE software_corrections ADD COLUMN {$field} {$columnTypes[$field]}");
            echo "    + 新增字段 software_corrections.{$field}" . PHP_EOL;
        }
    }

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_software_corrections_status ON software_corrections(status, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_software_corrections_software ON software_corrections(software_id, status)");
} else {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `software_corrections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NULL DEFAULT NULL,
  `icon_url` VARCHAR(500) NULL DEFAULT NULL,
  `official_url` VARCHAR(500) NULL DEFAULT NULL,
  `github_url` VARCHAR(500) NULL DEFAULT NULL,
  `github_release_keyword` VARCHAR(100) NULL DEFAULT NULL,
  `category_id` INT UNSIGNED NULL DEFAULT NULL,
  `summary` VARCHAR(500) NULL DEFAULT NULL,
  `special_notes` TEXT NULL,
  `price_type` ENUM('free', 'paid', 'freemium') NULL DEFAULT NULL,
  `is_open_source` TINYINT(1) NULL DEFAULT NULL,
  `license` VARCHAR(100) NULL DEFAULT NULL,
  `developer` VARCHAR(100) NULL DEFAULT NULL,
  `publisher` VARCHAR(100) NULL DEFAULT NULL,
  `tags` VARCHAR(500) NULL DEFAULT NULL,
  `screenshot_url` VARCHAR(500) NULL DEFAULT NULL,
  `download_channels` TEXT NULL,
  `contact` VARCHAR(200) NULL DEFAULT NULL,
  `submitter_ip` VARCHAR(45) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `review_note` VARCHAR(500) NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_software_status` (`software_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户提交的软件补充信息审核表'
SQL
    );

    $columnDefinitions = [
        'name' => "VARCHAR(100) NULL DEFAULT NULL",
        'icon_url' => "VARCHAR(500) NULL DEFAULT NULL",
        'official_url' => "VARCHAR(500) NULL DEFAULT NULL",
        'github_url' => "VARCHAR(500) NULL DEFAULT NULL",
        'github_release_keyword' => "VARCHAR(100) NULL DEFAULT NULL",
        'category_id' => "INT UNSIGNED NULL DEFAULT NULL",
        'summary' => "VARCHAR(500) NULL DEFAULT NULL",
        'special_notes' => "TEXT NULL",
        'price_type' => "ENUM('free', 'paid', 'freemium') NULL DEFAULT NULL",
        'is_open_source' => "TINYINT(1) NULL DEFAULT NULL",
        'license' => "VARCHAR(100) NULL DEFAULT NULL",
        'developer' => "VARCHAR(100) NULL DEFAULT NULL",
        'publisher' => "VARCHAR(100) NULL DEFAULT NULL",
        'tags' => "VARCHAR(500) NULL DEFAULT NULL",
        'screenshot_url' => "VARCHAR(500) NULL DEFAULT NULL",
        'download_channels' => "TEXT NULL",
        'contact' => "VARCHAR(200) NULL DEFAULT NULL",
    ];

    $checkColumn = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software_corrections' AND COLUMN_NAME = ?"
    );

    foreach ($fields as $field) {
        $checkColumn->execute([$field]);
        if ((int)$checkColumn->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `software_corrections` ADD COLUMN `{$field}` {$columnDefinitions[$field]}");
            echo "    + 新增字段 software_corrections.{$field}" . PHP_EOL;
        }
    }
}

echo "==> v7 迁移完成，software_corrections 表、完整资料字段及索引已就绪。" . PHP_EOL;
