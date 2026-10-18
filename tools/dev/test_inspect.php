<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

$list = Database::fetchAll("SELECT id, name, slug, official_url, icon_url FROM softwares");
foreach ($list as $s) {
    echo $s['id'] . " | " . $s['name'] . " | " . $s['official_url'] . " | " . $s['icon_url'] . PHP_EOL;
}
