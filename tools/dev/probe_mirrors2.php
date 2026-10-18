<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit; // 迁移/诊断脚本仅限命令行执行，禁止经 HTTP 匿名触发
}

/**
 * 镜像深度校验：确认代理返回的是真实 GitHub 页面/资源，而非落地页或错误页。
 */
$repoPage = 'https://github.com/microsoft/vscode';
$assetUrl = 'https://github.com/microsoft/vscode/archive/refs/heads/main.zip';

$probes = [
    'gh-proxy.com'      => 'https://gh-proxy.com/',
    'gh-proxy.net'      => 'https://gh-proxy.net/',
    'gh-proxy.ygxz.in'  => 'https://gh-proxy.ygxz.in/',
    'gh.llkk.cc'        => 'https://gh.llkk.cc/',
    'ghfast.top'        => 'https://ghfast.top/',
    'ghproxy.net'       => 'https://ghproxy.net/',
    'ghproxy.cn'        => 'https://ghproxy.cn/',
    'ghp.ci'            => 'https://ghp.ci/',
    'gh-proxy.lixxing.top' => 'https://gh-proxy.lixxing.top/',
    'ghproxy.homeboyc.cn'  => 'https://ghproxy.homeboyc.cn/',
    'gitproxy.click'    => 'https://gitproxy.click/',
    'github.dpik.top'   => 'https://github.dpik.top/',
    'gh.7ed.cc'         => 'https://gh.7ed.cc/',
    'ghproxy.imciel.com' => 'https://ghproxy.imciel.com/',
    'gh.ddlc.top'       => 'https://gh.ddlc.top/',
    'ghproxy.cfd'       => 'https://ghproxy.cfd/',
    'hub.fgit.cf'        => 'https://hub.fgit.cf/',
    'github.site'       => 'https://github.site/',
    'ghproxy.cdn.itty'   => 'https://ghproxy.cdn.itty.workers.dev/',
];

function probe(string $url): array {
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
    $time = round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'ms' => $time, 'len' => strlen($body), 'final' => $final, 'err' => $err, 'body' => $body];
}

foreach ($probes as $name => $base) {
    $url = $base . $repoPage;
    $r = probe($url);
    $body = $r['body'];
    $isRepo = stripos($body, 'Visual Studio Code') !== false;
    $isErr  = stripos($body, 'Bad Gateway') !== false
        || stripos($body, 'Service Temporarily Unavailable') !== false
        || stripos($body, 'error_page') !== false;
    printf(
        "%-26s code=%-4d %5dms len=%-8d repo=%s err=%s final=%s\n",
        $name, $r['code'], $r['ms'], $r['len'],
        $isRepo ? 'YES' : 'no ',
        $isErr ? 'YES' : 'no',
        $r['final'] !== $url ? $r['final'] : '-'
    );
    if ($r['err']) echo "      ERR: {$r['err']}\n";
}
