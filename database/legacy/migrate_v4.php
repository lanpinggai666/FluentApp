<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 蓝屏钙软件导航网 - v4 数据库迁移脚本
 * 为 softwares 表补充 install_type, supported_languages, os_requirements 字段
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
echo "==> 开始执行 v4 数据库迁移..." . PHP_EOL;

$cols = Database::fetchAll("PRAGMA table_info(softwares)");
$colNames = array_column($cols, 'name');

if (!in_array('install_type', $colNames)) {
    $pdo->exec("ALTER TABLE softwares ADD COLUMN install_type VARCHAR(100) DEFAULT NULL");
    echo "已为 softwares 增加 install_type 列" . PHP_EOL;
}
if (!in_array('supported_languages', $colNames)) {
    $pdo->exec("ALTER TABLE softwares ADD COLUMN supported_languages VARCHAR(100) DEFAULT NULL");
    echo "已为 softwares 增加 supported_languages 列" . PHP_EOL;
}
if (!in_array('os_requirements', $colNames)) {
    $pdo->exec("ALTER TABLE softwares ADD COLUMN os_requirements VARCHAR(150) DEFAULT NULL");
    echo "已为 softwares 增加 os_requirements 列" . PHP_EOL;
}

// 补充预设软件的默认元数据
$pdo->exec("UPDATE softwares SET install_type = '标准安装包 / 免安装便携版' WHERE install_type IS NULL OR install_type = ''");
$pdo->exec("UPDATE softwares SET supported_languages = '简体中文, 英语 (多语言支持)' WHERE supported_languages IS NULL OR supported_languages = ''");
$pdo->exec("UPDATE softwares SET os_requirements = 'Windows 10 版本 17763.0 或更高版本 / Windows 11' WHERE os_requirements IS NULL OR os_requirements = ''");

// 同步更新 MySQL schema_mysql.sql
$mysqlSchema = BASE_PATH . '/database/schema_mysql.sql';
if (file_exists($mysqlSchema)) {
    $sql = file_get_contents($mysqlSchema);
    if (!str_contains($sql, 'install_type')) {
        $sql = str_replace(
            "`tags` VARCHAR(500) DEFAULT NULL COMMENT '标签，英文逗号分隔',",
            "`tags` VARCHAR(500) DEFAULT NULL COMMENT '标签，英文逗号分隔',\n  `install_type` VARCHAR(100) DEFAULT '标准安装包 / 免安装便携版' COMMENT '安装包类型',\n  `supported_languages` VARCHAR(100) DEFAULT '简体中文, 英语' COMMENT '支持语言',\n  `os_requirements` VARCHAR(150) DEFAULT 'Windows 10 / Windows 11' COMMENT '系统要求',",
            $sql
        );
        file_put_contents($mysqlSchema, $sql);
    }
}

echo "==> v4 数据库迁移执行完毕！" . PHP_EOL;
