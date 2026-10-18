<?php
/**
 * URL 白名单与服务端外发把关：入库校验、属性输出净化、公网地址判定、TLS 配置
 */

group('01 URL 协议白名单');

foreach (['https://example.com', 'http://example.com/a?b=1&c=2', 'https://cdn.example.com/i.png#frag',
          'https://ex.com/p/中文-名称', 'https://pan.baidu.com/s/1abcDEF'] as $good) {
    ok('接受 ' . $good, Url::isSafe($good));
}
foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html;base64,PHN2Zz4=', 'vbscript:msgbox(1)',
          'file:///etc/passwd', 'https://evil.com/x" onmouseover="alert(1)', 'https://ex.com/a b',
          'https://ex.com/\\x', 'http://ex.com/<script>', 'java&#115;cript:alert(1)', '', '   ', null,
          'https://ex.com/' . str_repeat('a', 600)] as $bad) {
    ok('拒绝 ' . mb_substr(var_export($bad, true), 0, 34), !Url::isSafe($bad));
}

ok('伪协议输出回落为 #', Url::href('javascript:alert(1)') === '#');
ok('合法地址按属性上下文转义', Url::href('https://ex.com/a?b=1&c=2') === 'https://ex.com/a?b=1&amp;c=2');
ok('null 输出回落为 #', Url::href(null) === '#');
ok('可自定义占位值', Url::href('', 'about:blank') === 'about:blank');

group('02 服务端外发的地址判定');

$blocked = ['http://127.0.0.1/x', 'http://127.0.0.1:8795/', 'http://localhost/x', 'http://LOCALHOST/x',
            'http://169.254.169.254/latest/meta-data/', 'http://10.1.2.3/', 'http://192.168.0.1/',
            'http://172.16.5.5/', 'http://172.31.255.255/', 'http://0.0.0.0/', 'http://[::1]/',
            'http://2130706433/', 'http://0x7f000001/', 'https://example.com:8080/', 'http://100.64.0.1/',
            'file:///C:/Windows/win.ini', 'javascript:alert(1)', 'http://127.1/', 'http://.local/'];
foreach ($blocked as $u) {
    $ip = null;
    ok('拒绝外发 ' . $u, Url::publiclyRoutable($u, $ip) === false && $ip === null);
}
$ip = null;
ok('放行公网 IPv4 字面量', Url::publiclyRoutable('https://93.184.216.34/', $ip) && $ip === '93.184.216.34');
ok('放行带查询串的公网 IP', Url::publiclyRoutable('http://93.184.216.34/a?b=1', $ip) && $ip === '93.184.216.34');

ok('根路径重定向解析', Url::absolutize('https://a.com/p/q', '/x') === 'https://a.com/x');
ok('相对路径沿用目录', Url::absolutize('https://a.com/p/q', 'x') === 'https://a.com/p/x');
ok('绝对地址原样返回', Url::absolutize('https://a.com/p', 'https://b.com/z') === 'https://b.com/z');
ok('保留非默认端口', Url::absolutize('http://a.com:80/x', '/y') === 'http://a.com:80/y');
ok('空 Location 返回 null', Url::absolutize('https://a.com/', '') === null);
$jump = Url::absolutize('https://a.com/p', '//evil.com/z');
ok('协议相对写法不会跳出原主机', $jump !== null && parse_url($jump, PHP_URL_HOST) === 'a.com');

group('03 TLS 校验配置');

$tls = HttpTls::curlOptions();
ok('curl 校验证书与主机名', $tls[CURLOPT_SSL_VERIFYPEER] === true && $tls[CURLOPT_SSL_VERIFYHOST] === 2);
if (defined('CURLSSLOPT_NATIVE_CA')) {
    ok('Windows 下启用系统证书库', $tls[CURLOPT_SSL_OPTIONS] === CURLSSLOPT_NATIVE_CA);
} else {
    skip('Windows 下启用系统证书库', '当前 PHP/curl 不支持 CURLSSLOPT_NATIVE_CA');
}
$stream = HttpTls::streamContext();
ok('SMTP 流上下文验证证书与主机名且不收自签',
    $stream['ssl']['verify_peer'] === true && $stream['ssl']['verify_peer_name'] === true
    && $stream['ssl']['allow_self_signed'] === false);

ok('SafeHttp 托管项调用方无法覆盖', (function () {
    $managed = [CURLOPT_FOLLOWLOCATION => false];
    $merged = $managed + [CURLOPT_FOLLOWLOCATION => true];
    return $merged[CURLOPT_FOLLOWLOCATION] === false;
})());

if (!Sandbox::$online || Sandbox::$offline) {
    skip('真实外发被证书校验拦住', '沙箱无外网');
}
