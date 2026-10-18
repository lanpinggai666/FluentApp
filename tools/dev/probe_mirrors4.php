<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 针对「下载加速」场景的真实可用性探测：使用 Range 只取前 2KB，
 * 判断代理是否真的把 GitHub 资源透传出来。
 */
function probe(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RANGE          => '0-2047',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    ]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $cd = defined('CURLINFO_CONTENT_TYPE') ? '' : '';
    $ms = round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);
    return compact('code', 'ct', 'cd', 'ms', 'final', 'err') + ['len' => strlen($body), 'body' => $body];
}

// 目标：一个很小的 GitHub Release 资产 + 一个 raw 文件
$targets = [
    'raw'      => 'https://raw.githubusercontent.com/microsoft/vscode/main/LICENSE.txt',
    'archive'  => 'https://github.com/microsoft/vscode/archive/refs/heads/main.zip',
];

$bases = [
    'gh-proxy.com'       => 'https://gh-proxy.com/',
    'gh-proxy.ygxz.in'   => 'https://gh-proxy.ygxz.in/',
    'ghproxy.cn'         => 'https://ghproxy.cn/',
    'gitproxy.click'     => 'https://gitproxy.click/',
    'gh-proxy.net'       => 'https://gh-proxy.net/',
    'gh.llkk.cc'         => 'https://gh.llkk.cc/',
    'ghfast.top'         => 'https://ghfast.top/',
    'ghproxy.net'        => 'https://ghproxy.net/',
];

foreach ($targets as $tname => $target) {
    echo "===== 目标: {$tname} ({$target})\n";
    $direct = probe($target);
    printf("  %-18s DIRECT code=%d %dms len=%d ct=%s err=%s\n", 'direct', $direct['code'], $direct['ms'], $direct['len'], $direct['ct'], $direct['err']);

    foreach ($bases as $name => $base) {
        $r = probe($base . $target);
        $ok = $r['code'] === 200 || $r['code'] === 206;
        printf("  %-18s code=%-4d %5dms len=%-6d ct=%-28s %s%s\n",
            $name, $r['code'], $r['ms'], $r['len'], $r['ct'],
            $ok ? 'OK ' : 'BAD', $r['err'] ? " ERR:{$r['err']}" : '');
    }
    echo "\n";
}
