<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 精确验证：Release 资产 (.exe) 经代理是否真的开始传输二进制内容。
 * 使用 WRITEFUNCTION 拿到 64KB 后立即中断，避免大文件占满内存。
 */
function probeAbortable(string $url): array {
    $received = 0;
    $firstChunk = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOPROGRESS      => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
        CURLOPT_WRITEFUNCTION   => function ($ch, $chunk) use (&$received, &$firstChunk) {
            if ($firstChunk === '') {
                $firstChunk = substr($chunk, 0, 300);
            }
            $received += strlen($chunk);
            return $received > 65536 ? 0 : strlen($chunk); // 返回 0 => 触发 CURLE_WRITE_ERROR 中断
        },
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $ms = round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'ct' => $ct, 'ms' => $ms, 'bytes' => $received, 'err' => $err, 'head' => $firstChunk];
}

// 目标：7-Zip 的 Release 资产
$asset = 'https://github.com/ip7z/7zip/releases/download/24.09/7z2409-x64.msi';
$bases = [
    'direct'          => '',
    'gh-proxy.com'    => 'https://gh-proxy.com/',
    'ghproxy.net'     => 'https://ghproxy.net/',
    'ghfast.top'      => 'https://ghfast.top/',
    'gh.llkk.cc'      => 'https://gh.llkk.cc/',
    'ghproxy.net/403' => 'https://ghproxy.net/',
    'github.boki.moe' => 'https://github.boki.moe/',
    'github.moeyy.xyz'=> 'https://github.moeyy.xyz/',
    'kkgithub.com'    => 'https://kkgithub.com/',
    'mirror.ghproxy.com' => 'https://mirror.ghproxy.com/',
];

foreach ($bases as $name => $base) {
    $url = $base . $asset;
    $r = probeAbortable($url);
    $binary = str_contains($r['ct'], 'octet-stream') || str_contains($r['ct'], 'msword')
        || str_contains($r['ct'], 'zip') || str_contains($r['ct'], 'x-msdownload');
    printf(
        "%-20s code=%-4d %5dms bytes=%-8d binary=%-3s ct=%-40s err=%s\n",
        $name, $r['code'], $r['ms'], $r['bytes'], $binary ? 'YES' : 'no', $r['ct'], $r['err'] ?: '-'
    );
}
