<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 蓝屏钙软件导航网 - v3 数据库迁移脚本
 * 1. 为 github_mirrors 增加 type (download/web/both) 与 sample_url
 * 2. 创建 software_resources 表 (视频教程、图文指南等第三方额外信息)
 * 3. 填充真实种子资源数据
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
echo "==> 开始执行 v3 数据库迁移..." . PHP_EOL;

// 1. github_mirrors 表结构升级
try {
    $cols = $pdo->query("PRAGMA table_info(github_mirrors)")->fetchAll(PDO::FETCH_ASSOC);
    $existing = array_column($cols, 'name');

    if (!in_array('type', $existing)) {
        $pdo->exec("ALTER TABLE github_mirrors ADD COLUMN type VARCHAR(20) DEFAULT 'download'");
        echo "已为 github_mirrors 增加 type 列" . PHP_EOL;
    }
    if (!in_array('sample_url', $existing)) {
        $pdo->exec("ALTER TABLE github_mirrors ADD COLUMN sample_url TEXT DEFAULT NULL");
        echo "已为 github_mirrors 增加 sample_url 列" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "github_mirrors 迁移提示: " . $e->getMessage() . PHP_EOL;
}

// 更新现有镜像类型并补全 sample_url
$mirrorsSetup = [
    ['name' => '官方 GitHub 直连', 'type' => 'both', 'url_template' => 'direct', 'sample_url' => 'https://github.com/microsoft/vscode'],
    ['name' => 'GHProxy 加速线路 1', 'type' => 'download', 'url_template' => 'https://ghproxy.net/', 'sample_url' => 'https://ghproxy.net/https://github.com/git-for-windows/git/releases/download/v2.55.0.windows.5/Git-2.55.0.5-64-bit.exe'],
    ['name' => 'GHProxy 加速线路 2', 'type' => 'download', 'url_template' => 'https://mirror.ghproxy.com/', 'sample_url' => 'https://mirror.ghproxy.com/https://github.com/git-for-windows/git/releases/download/v2.55.0.windows.5/Git-2.55.0.5-64-bit.exe'],
    ['name' => 'Boki 节点', 'type' => 'both', 'url_template' => 'https://github.boki.moe/', 'sample_url' => 'https://github.boki.moe/microsoft/vscode'],
    ['name' => 'Moeyy 节点', 'type' => 'both', 'url_template' => 'https://github.moeyy.xyz/', 'sample_url' => 'https://github.moeyy.xyz/microsoft/vscode'],
    ['name' => 'KKGitHub 镜像站', 'type' => 'web', 'url_template' => 'https://kkgithub.com/', 'sample_url' => 'https://kkgithub.com/microsoft/vscode']
];

foreach ($mirrorsSetup as $m) {
    // 检查是否存在
    $stmt = $pdo->prepare("SELECT id FROM github_mirrors WHERE url_template = ?");
    $stmt->execute([$m['url_template']]);
    $exist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exist) {
        $up = $pdo->prepare("UPDATE github_mirrors SET type = ?, sample_url = ? WHERE id = ?");
        $up->execute([$m['type'], $m['sample_url'], $exist['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO github_mirrors (name, url_template, is_active, sort_order, type, sample_url) VALUES (?, ?, 1, 10, ?, ?)");
        $ins->execute([$m['name'], $m['url_template'], $m['type'], $m['sample_url']]);
    }
}
echo "已更新 GitHub 镜像加速类型划分 (下载加速/网页加速/两者兼用)" . PHP_EOL;

// 2. 创建 software_resources 表
$pdo->exec("
CREATE TABLE IF NOT EXISTS software_resources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    software_id INTEGER NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'video',
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    author VARCHAR(64) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    submitter_ip VARCHAR(45) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'published',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_resources_software ON software_resources(software_id);
");
echo "已创建 software_resources 表及索引" . PHP_EOL;

// 3. 写入初始第三方资源（视频教程、权威指南）
$sampleResources = [
    // VS Code (id=1)
    [
        'software_id' => 1,
        'type' => 'video',
        'title' => 'VS Code 2026从入门到精通高效配置指南',
        'url' => 'https://www.bilibili.com/video/BV15741177te',
        'author' => 'Bilibili @前端达人',
        'description' => '涵盖快捷键、常用插件全景推荐与代码格式化自动调优。'
    ],
    [
        'software_id' => 1,
        'type' => 'article',
        'title' => 'VS Code 官方文档与中文学习社区',
        'url' => 'https://code.visualstudio.com/docs',
        'author' => '微软官方',
        'description' => '权威中文文档、架构解析与各语言官方扩展使用手册。'
    ],
    // Git (id=6)
    [
        'software_id' => 6,
        'type' => 'video',
        'title' => '十分钟彻底搞懂 Git 与 GitHub 工作流',
        'url' => 'https://www.bilibili.com/video/BV1FE411P7B3',
        'author' => 'Bilibili @极客时间',
        'description' => '动画可视化演示暂存区、分支合并、冲突解决与Rebase技巧。'
    ],
    [
        'software_id' => 6,
        'type' => 'article',
        'title' => '廖雪峰 Git 快速入门精炼教程',
        'url' => 'https://www.liaoxuefeng.com/wiki/896043488029600',
        'author' => '廖雪峰',
        'description' => '通俗易懂的经典中文教程，适合 Windows 用户快速上手。'
    ],
    // OBS Studio (id=5)
    [
        'software_id' => 5,
        'type' => 'video',
        'title' => 'OBS Studio 零基础直播推流与录屏教程',
        'url' => 'https://www.bilibili.com/video/BV1xK4y1P73x',
        'author' => 'Bilibili @数字音视频',
        'description' => '详解显卡硬编码、降噪滤镜、虚拟摄像头与高帧率参数配置。'
    ],
    // Snipaste (id=7)
    [
        'software_id' => 7,
        'type' => 'article',
        'title' => 'Snipaste 终极效率指南：贴图与取色高级玩法',
        'url' => 'https://sspai.com/post/35478',
        'author' => '少数派 sspai',
        'description' => '将截图贴在屏幕上对比参考、智能边缘吸附与命令行调用秘技。'
    ]
];

$stmtCheck = $pdo->prepare("SELECT COUNT(*) as c FROM software_resources WHERE url = ?");
$stmtInsert = $pdo->prepare("INSERT INTO software_resources (software_id, type, title, url, author, description, status) VALUES (?, ?, ?, ?, ?, ?, 'published')");

foreach ($sampleResources as $res) {
    $stmtCheck->execute([$res['url']]);
    if ((int)$stmtCheck->fetch(PDO::FETCH_ASSOC)['c'] === 0) {
        $stmtInsert->execute([
            $res['software_id'],
            $res['type'],
            $res['title'],
            $res['url'],
            $res['author'],
            $res['description']
        ]);
    }
}
echo "已导入初始社区第三方教程与资源数据" . PHP_EOL;

// 4. 同步更新 MySQL schema
$mysqlSchema = BASE_PATH . '/database/schema_mysql.sql';
if (file_exists($mysqlSchema)) {
    $sql = file_get_contents($mysqlSchema);
    if (!str_contains($sql, 'software_resources')) {
        $resourcesSql = "
-- ----------------------------
-- 第三方扩展资源与视频教程表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `software_resources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` INT UNSIGNED NOT NULL COMMENT '关联软件ID',
  `type` VARCHAR(32) NOT NULL DEFAULT 'video' COMMENT '类型: video/article/config/patch',
  `title` VARCHAR(255) NOT NULL COMMENT '资源标题',
  `url` TEXT NOT NULL COMMENT '资源外链URL',
  `author` VARCHAR(64) DEFAULT NULL COMMENT '作者或平台UP主',
  `description` TEXT DEFAULT NULL COMMENT '简要说明',
  `submitter_ip` VARCHAR(45) DEFAULT NULL,
  `status` ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_software_id` (`software_id`),
  CONSTRAINT `fk_resource_software` FOREIGN KEY (`software_id`) REFERENCES `softwares` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方扩展资源与教程表';
";
        file_put_contents($mysqlSchema, $sql . "\n" . $resourcesSql);
    }
}

echo "==> v3 数据库迁移完成！" . PHP_EOL;
