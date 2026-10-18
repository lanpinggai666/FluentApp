<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 蓝屏钙软件导航网 - v6 数据库迁移
 * 1. 为 softwares 表增加 theme_color 字段 (存放代表色 Hex，用于图标取色渐变)
 * 2. 为已有 10 款软件注入经典官方代表色
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
echo "==> 执行 v6 迁移..." . PHP_EOL;

$cols = Database::fetchAll("PRAGMA table_info(softwares)");
$colNames = array_column($cols, 'name');

if (!in_array('theme_color', $colNames)) {
    $pdo->exec("ALTER TABLE softwares ADD COLUMN theme_color VARCHAR(50) DEFAULT NULL");
    echo "已为 softwares 增加 theme_color 列\n";
}

$colorMap = [
    'Visual Studio Code' => '#0078d4', // 微软蓝
    '7-Zip' => '#3b82f6',              // 经典天蓝
    'PotPlayer' => '#f59e0b',          // 播放器琥珀金
    'Everything' => '#f97316',         // 搜索橙
    'OBS Studio' => '#6366f1',         // 录屏靛蓝
    'Git for Windows' => '#f05032',    // Git 鲜明珊瑚红
    'Snipaste' => '#0284c7',           // 截图青蓝
    'Rufus' => '#2563eb',              // 刻录宝蓝
    'Typora' => '#8b5cf6',             // 编辑器丁香紫
    'Geek Uninstaller' => '#10b981',   // 卸载翠绿
];

foreach ($colorMap as $name => $color) {
    Database::update('softwares', ['theme_color' => $color], 'name = :name', ['name' => $name]);
    echo "已为 {$name} 设置图标采样基色 {$color}\n";
}

echo "==> v6 迁移完成！\n";
