<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 一次性诊断脚本：探测 GitHub 加速节点可用性 + 输出 settings 表结构。
 */
define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/core/Database.php';

echo "=== settings columns ===\n";
foreach (Database::pdo()->query('PRAGMA table_info(settings)')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['name'] . ' | ' . $c['type'] . PHP_EOL;
}

echo "\n=== mirror probe ===\n";
$probes = [
    'ghproxy.net/https://github.com/'      => 'https://ghproxy.net/https://github.com/microsoft/vscode',
    'gh-proxy.com/https://github.com/'     => 'https://gh-proxy.com/https://github.com/microsoft/vscode',
    'mirror.ghproxy.com/'                  => 'https://mirror.ghproxy.com/https://github.com/microsoft/vscode',
    'github.boki.moe/'                     => 'https://github.boki.moe/microsoft/vscode',
    'github.moeyy.xyz/'                    => 'https://github.moeyy.xyz/microsoft/vscode',
    'kkgithub.com/'                        => 'https://kkgithub.com/microsoft/vscode',
    'ghfast.top/https://github.com/'       => 'https://ghfast.top/https://github.com/microsoft/vscode',
    'gh-proxy.net/https://github.com/'     => 'https://gh-proxy.net/https://github.com/microsoft/vscode',
    'gh.llkk.cc/https://github.com/'       => 'https://gh.llkk.cc/https://github.com/microsoft/vscode',
    'github.abskoop.workers.dev/'          => 'https://github.abskoop.workers.dev/microsoft/vscode',
    'raw.gitmirror.com/'                   => 'https://raw.gitmirror.com/microsoft/vscode/main/README.md',
    'hub.gitmirror.com/'                   => 'https://hub.gitmirror.com/https://github.com/microsoft/vscode',
    'gh.ddlc.top/https://github.com/'      => 'https://gh.ddlc.top/https://github.com/microsoft/vscode',
    'ghps.cc/https://github.com/'          => 'https://ghps.cc/https://github.com/microsoft/vscode',
    'gh.api.99988866.xyz/https://github.com/' => 'https://gh.api.99988866.xyz/https://github.com/microsoft/vscode',
    'gh-proxy.ygxz.in/https://github.com/' => 'https://gh-proxy.ygxz.in/https://github.com/microsoft/vscode',
    'wgetproxy.com/https://github.com/'    => 'https://wgetproxy.com/https://github.com/microsoft/vscode',
    'direct github.com'                    => 'https://github.com/microsoft/vscode',
];

foreach ($probes as $label => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);
    printf("%-45s code=%-4d %5dms len=%-8d %s%s\n",
        $label, $code, $time, strlen((string)$body), $err ? "ERR:$err " : '', $final !== $url ? "-> $final" : '');
}
