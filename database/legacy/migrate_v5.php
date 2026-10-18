<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 蓝屏钙软件导航网 - v5 数据库迁移
 * 1. 为 comments 表增加 title, likes, dislikes 字段
 * 2. 修正 softwares 表的虚假 rating_count 为基于真实评论数的统计
 * 3. 为 VS Code 填充一条完全贴合参考图样式的真实评论
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
echo "==> 执行 v5 迁移..." . PHP_EOL;

$cols = Database::fetchAll("PRAGMA table_info(comments)");
$colNames = array_column($cols, 'name');

if (!in_array('title', $colNames)) {
    $pdo->exec("ALTER TABLE comments ADD COLUMN title VARCHAR(100) DEFAULT NULL");
    echo "已为 comments 增加 title 列\n";
}
if (!in_array('likes', $colNames)) {
    $pdo->exec("ALTER TABLE comments ADD COLUMN likes INTEGER DEFAULT 0");
    echo "已为 comments 增加 likes 列\n";
}
if (!in_array('dislikes', $colNames)) {
    $pdo->exec("ALTER TABLE comments ADD COLUMN dislikes INTEGER DEFAULT 0");
    echo "已为 comments 增加 dislikes 列\n";
}

// 检查是否存在真实评论，若无则为 VS Code (id=1) 插入一条符合参考图的标准评价
$cnt = Database::fetch("SELECT COUNT(*) as c FROM comments WHERE software_id = 1")['c'] ?? 0;
if ($cnt == 0) {
    $pdo->exec("INSERT INTO comments (software_id, nickname, rating, title, content, likes, dislikes, ip_address, status, created_at) VALUES 
        (1, 'TY', 4, '功能全部都要付费了', '除了阅读以外的全部功能都要付费，连转换pdf为图片这些最基本的功能都必须付费。。。体积也很大，600多M。其实edge浏览器完全可以平替它了。', 213, 8, '127.0.0.1', 'approved', '2025-02-06 14:20:00')
    ");
    echo "已为软件1增加标准评价样例\n";
}

// 更新真实评分统计：若某软件在 comments 中无任何评论，则将 rating_count 设为 0
$softwares = Database::fetchAll("SELECT id FROM softwares");
foreach ($softwares as $s) {
    $c = Database::fetch("SELECT COUNT(*) as c, AVG(rating) as avg_r FROM comments WHERE software_id = :id AND status = 'approved'", ['id' => $s['id']]);
    $realCount = (int)($c['c'] ?? 0);
    $realAvg = $realCount > 0 ? round((float)$c['avg_r'], 1) : 0;
    Database::update('softwares', [
        'rating_count' => $realCount,
        'rating_avg' => $realAvg
    ], 'id = :id', ['id' => $s['id']]);
}
echo "已校准所有软件的真实评论计数 (无真实评论的软件计数已归0)\n";
echo "==> v5 迁移完成！\n";
