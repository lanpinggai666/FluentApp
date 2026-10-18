<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 数据库扩展迁移脚本 (用户后台、权限标签、封禁、站内信、邮箱验证码、资源举报、管理者关联)
 */
define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();

echo "Running migrations..." . PHP_EOL;

// 1. 确保 users 表包含扩展字段
$userCols = array_column(Database::fetchAll("PRAGMA table_info(users)"), 'name');

if (!in_array('badges', $userCols, true)) {
    Database::query("ALTER TABLE users ADD COLUMN badges TEXT DEFAULT NULL");
    echo "Added users.badges" . PHP_EOL;
}
if (!in_array('is_banned', $userCols, true)) {
    Database::query("ALTER TABLE users ADD COLUMN is_banned INTEGER DEFAULT 0");
    echo "Added users.is_banned" . PHP_EOL;
}
if (!in_array('banned_until', $userCols, true)) {
    Database::query("ALTER TABLE users ADD COLUMN banned_until DATETIME DEFAULT NULL");
    echo "Added users.banned_until" . PHP_EOL;
}
if (!in_array('ban_reason', $userCols, true)) {
    Database::query("ALTER TABLE users ADD COLUMN ban_reason TEXT DEFAULT NULL");
    echo "Added users.ban_reason" . PHP_EOL;
}
if (!in_array('restricted_permissions', $userCols, true)) {
    Database::query("ALTER TABLE users ADD COLUMN restricted_permissions TEXT DEFAULT NULL");
    echo "Added users.restricted_permissions" . PHP_EOL;
}

// 2. 确保 softwares 表包含管理者字段 owner_user_id
$softCols = array_column(Database::fetchAll("PRAGMA table_info(softwares)"), 'name');
if (!in_array('owner_user_id', $softCols, true)) {
    Database::query("ALTER TABLE softwares ADD COLUMN owner_user_id INTEGER DEFAULT NULL");
    echo "Added softwares.owner_user_id" . PHP_EOL;
}

// 3. 确保 reports 表包含资源举报字段
$reportCols = array_column(Database::fetchAll("PRAGMA table_info(reports)"), 'name');
if (!in_array('resource_id', $reportCols, true)) {
    Database::query("ALTER TABLE reports ADD COLUMN resource_id INTEGER DEFAULT NULL");
    echo "Added reports.resource_id" . PHP_EOL;
}
if (!in_array('reported_type', $reportCols, true)) {
    Database::query("ALTER TABLE reports ADD COLUMN reported_type TEXT DEFAULT 'software'");
    echo "Added reports.reported_type" . PHP_EOL;
}
if (!in_array('user_id', $reportCols, true)) {
    Database::query("ALTER TABLE reports ADD COLUMN user_id INTEGER DEFAULT NULL");
    echo "Added reports.user_id" . PHP_EOL;
}

// 4. 确保 comments 表包含 user_id
$commentCols = array_column(Database::fetchAll("PRAGMA table_info(comments)"), 'name');
if (!in_array('user_id', $commentCols, true)) {
    Database::query("ALTER TABLE comments ADD COLUMN user_id INTEGER DEFAULT NULL");
    echo "Added comments.user_id" . PHP_EOL;
}

// 5. 确保 software_resources 表包含 user_id
$resCols = array_column(Database::fetchAll("PRAGMA table_info(software_resources)"), 'name');
if (!in_array('user_id', $resCols, true)) {
    Database::query("ALTER TABLE software_resources ADD COLUMN user_id INTEGER DEFAULT NULL");
    echo "Added software_resources.user_id" . PHP_EOL;
}

// 6. 确保 software_corrections 表包含 user_id
$corrCols = array_column(Database::fetchAll("PRAGMA table_info(software_corrections)"), 'name');
if (!in_array('user_id', $corrCols, true)) {
    Database::query("ALTER TABLE software_corrections ADD COLUMN user_id INTEGER DEFAULT NULL");
    echo "Added software_corrections.user_id" . PHP_EOL;
}

// 7. 创建站内信数据表 notifications
Database::query("CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'system',
    is_read TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
Database::query("CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read)");
echo "Table notifications checked/created." . PHP_EOL;

// 8. 创建邮箱验证码数据表 email_verification_codes
Database::query("CREATE TABLE IF NOT EXISTS email_verification_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    action VARCHAR(50) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
Database::query("CREATE INDEX IF NOT EXISTS idx_email_codes_lookup ON email_verification_codes(email, action, is_used)");
echo "Table email_verification_codes checked/created." . PHP_EOL;

echo "Migration finished successfully!" . PHP_EOL;
