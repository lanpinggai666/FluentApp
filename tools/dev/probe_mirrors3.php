<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

function probe(string $url, int $maxBytes = 2000): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    ]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['code' => $code, 'ct' => $ct, 'body' => substr($body, 0, $maxBytes), 'len' => strlen($body)];
}

$targets = [
    'https://gh-proxy.com/https://github.com/microsoft/vscode',
    'https://gh-proxy.ygxz.in/https://github.com/microsoft/vscode',
    'https://github.com/microsoft/vscode',
];

foreach ($targets as $t) {
    $r = probe($t, 900);
    echo "### $t\ncode={$r['code']} ct={$r['ct']} len={$r['len']}\n";
    echo preg_replace('/\s+/u', ' ', strip_tags($r['body'])), "\n\n---\n";
}
