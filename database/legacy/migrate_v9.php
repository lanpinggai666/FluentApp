<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 蓝屏钙软件导航网 - v9 数据库迁移
 *
 * 目标：
 *  1. 为「软件提交审核」补齐与补充信息审核同等的完整审核链路：
 *     审核备注、审核时间、安全修改令牌、邮件通知状态。
 *  2. 同步经验证可用的 GitHub 加速节点（清理已失效的旧节点）。
 *
 * 兼容 SQLite 与 MySQL，可重复执行。
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$pdo = Database::pdo();
$driver = Database::getDriver();
echo "==> 执行 v9 迁移 ({$driver})..." . PHP_EOL;

/* ---------------------------------------------------------------------------
 * 一、softwares 表补齐审核与通知字段
 * ------------------------------------------------------------------------- */
$columns = [
    'review_note'            => $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(500) NULL DEFAULT NULL',
    'reviewed_at'            => $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL DEFAULT NULL',
    'edit_token_hash'        => $driver === 'sqlite' ? 'TEXT' : 'CHAR(64) NULL DEFAULT NULL',
    'edit_token_expires_at'  => $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL DEFAULT NULL',
    'notification_status'    => $driver === 'sqlite' ? "TEXT NOT NULL DEFAULT 'pending'" : "VARCHAR(20) NOT NULL DEFAULT 'pending'",
    'notification_error'     => $driver === 'sqlite' ? 'TEXT' : 'TEXT NULL',
    'notified_at'            => $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL DEFAULT NULL',
];

foreach ($columns as $column => $definition) {
    if ($driver === 'sqlite') {
        $existing = [];
        foreach ($pdo->query('PRAGMA table_info(softwares)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['name']] = true;
        }
        if (!isset($existing[$column])) {
            $pdo->exec("ALTER TABLE softwares ADD COLUMN {$column} {$definition}");
            echo "    + 新增字段 softwares.{$column}" . PHP_EOL;
        }
        continue;
    }

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $check->execute(['softwares', $column]);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `softwares` ADD COLUMN `{$column}` {$definition}");
        echo "    + 新增字段 softwares.{$column}" . PHP_EOL;
    }
}

if ($driver === 'sqlite') {
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_softwares_edit_token ON softwares(edit_token_hash, edit_token_expires_at)');
} else {
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'softwares' AND INDEX_NAME = 'idx_softwares_edit_token'"
    );
    $check->execute();
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `softwares` ADD KEY `idx_softwares_edit_token` (`edit_token_hash`, `edit_token_expires_at`)');
        echo "    + 新增索引 idx_softwares_edit_token" . PHP_EOL;
    }
}

/* ---------------------------------------------------------------------------
 * 二、GitHub 加速节点：停用已失效线路，补充经验证可用线路
 * ------------------------------------------------------------------------- */
$verified = [
    ['name' => '官方 GitHub 直连', 'tpl' => 'direct',                        'type' => 'both',     'order' => 1],
    ['name' => 'GH-PROXY 加速线路', 'tpl' => 'https://gh-proxy.com/',       'type' => 'download', 'order' => 2],
    ['name' => 'GHFast 加速线路',   'tpl' => 'https://ghfast.top/',         'type' => 'download', 'order' => 3],
    ['name' => 'LLKK 加速线路',     'tpl' => 'https://gh.llkk.cc/',         'type' => 'download', 'order' => 4],
    ['name' => 'GHProxy 加速线路',  'tpl' => 'https://ghproxy.net/',        'type' => 'download', 'order' => 5],
    ['name' => 'Boki 镜像节点',     'tpl' => 'https://github.boki.moe/',    'type' => 'download', 'order' => 6],
];

// 已确认 TLS 握手失败 / 证书错误 / 服务下线，停用但保留记录以便回溯。
$retired = [
    'https://mirror.ghproxy.com/',
    'https://github.moeyy.xyz/',
    'https://kkgithub.com/',
];

$existingTemplates = [];
foreach (Database::fetchAll('SELECT id, url_template FROM github_mirrors') as $row) {
    $existingTemplates[rtrim((string)$row['url_template'], '/')] = (int)$row['id'];
}

foreach ($retired as $template) {
    $key = rtrim($template, '/');
    if (isset($existingTemplates[$key])) {
        Database::update('github_mirrors', ['is_active' => 0], 'id = :id', ['id' => $existingTemplates[$key]]);
        echo "    - 停用失效镜像 {$template}" . PHP_EOL;
    }
}

foreach ($verified as $mirror) {
    $key = rtrim($mirror['tpl'], '/');
    if ($mirror['tpl'] === 'direct') {
        // 直连节点始终保留且强制启用。
        if (isset($existingTemplates['direct'])) {
            Database::update('github_mirrors', [
                'is_active' => 1,
                'type' => $mirror['type'],
                'sort_order' => $mirror['order'],
            ], 'id = :id', ['id' => $existingTemplates['direct']]);
        }
        continue;
    }

    if (isset($existingTemplates[$key])) {
        Database::update('github_mirrors', [
            'name' => $mirror['name'],
            'is_active' => 1,
            'type' => $mirror['type'],
            'sort_order' => $mirror['order'],
        ], 'id = :id', ['id' => $existingTemplates[$key]]);
        continue;
    }

    Database::insert('github_mirrors', [
        'name' => $mirror['name'],
        'url_template' => $mirror['tpl'],
        'is_active' => 1,
        'sort_order' => $mirror['order'],
        'type' => $mirror['type'],
        'sample_url' => null,
    ]);
    echo "    + 新增镜像 {$mirror['name']} ({$mirror['tpl']})" . PHP_EOL;
}

/* ---------------------------------------------------------------------------
 * 三、历史数据兜底：已发布软件的通知状态归位，避免后台统计误报
 * ------------------------------------------------------------------------- */
if ($driver === 'sqlite') {
    $pdo->exec("UPDATE softwares SET notification_status = 'none' WHERE status = 'published'");
} else {
    $pdo->exec("UPDATE `softwares` SET `notification_status` = 'none' WHERE `status` = 'published'");
}
echo "    · 已发布软件通知状态归位" . PHP_EOL;

echo "==> v9 迁移完成。" . PHP_EOL;
