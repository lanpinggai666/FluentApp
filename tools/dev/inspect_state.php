<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

echo "=== github_mirrors ===\n";
foreach (Database::fetchAll('SELECT * FROM github_mirrors') as $r) {
    echo $r['id'] . ' | ' . $r['name'] . ' | type=' . $r['type'] . ' | tpl=' . $r['url_template']
        . ' | active=' . ($r['is_active'] ?? '?') . ' | prio=' . ($r['priority'] ?? '?') . PHP_EOL;
}

echo "\n=== softwares ===\n";
foreach (Database::fetchAll('SELECT id,name,github_url,other_urls,summary,special_notes,submitter_email,status FROM softwares ORDER BY id LIMIT 20') as $r) {
    echo $r['id'] . ' | ' . $r['name'] . ' | gh=' . ($r['github_url'] ?? '-')
        . ' | other=' . substr((string)($r['other_urls'] ?? ''), 0, 180)
        . ' | email=' . ($r['submitter_email'] ?? '-')
        . ' | st=' . $r['status'] . PHP_EOL;
}

echo "\n=== corrections ===\n";
$rows = Database::fetchAll('SELECT id,software_id,status,submitter_email,notification_status,review_note FROM software_corrections ORDER BY id');
echo 'count=' . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo "\n=== settings (smtp/site) ===\n";
foreach (Database::fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp%' OR setting_key LIKE 'site_base%'") as $r) {
    echo $r['setting_key'] . ' = ' . $r['setting_value'] . PHP_EOL;
}

echo "\n=== softwares columns ===\n";
$cols = [];
foreach (Database::pdo()->query('PRAGMA table_info(softwares)')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cols[] = $c['name'];
}
echo implode(', ', $cols) . PHP_EOL;
