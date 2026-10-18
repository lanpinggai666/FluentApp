<?php
/**
 * 蓝屏钙软件导航网 - 服务端外发请求的把关层
 *
 * 抓取目标（图标源、软件官网）来自用户提交，直接交给 curl 就等于把内网探测能力开放给
 * 匿名访问者。这里统一做三件事：只允许解析到公网 IPv4 的 http/https 地址、用
 * CURLOPT_RESOLVE 把校验过的 IP 钉死（消除「校验后再改 DNS」的重绑定窗口）、
 * 每一跳重定向都重新校验一次。
 */
class SafeHttp {
    private const MAX_REDIRECTS = 3;

    /**
     * @param array $options 额外的 curl 选项；由本类托管的选项会以本类取值为准。
     * @return array{ok: bool, status: int, body: string, content_type: string, headers: array<string,string>, errno: int, error: string, final_url: string}
     */
    public static function get(string $url, array $options = [], int $timeout = 8, int $connectTimeout = 3): array {
        $result = ['ok' => false, 'status' => 0, 'body' => '', 'content_type' => '', 'headers' => [], 'errno' => 0,
            'error' => '目标地址不可用', 'final_url' => trim($url)];
        $target = trim($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $ip = null;
            if (!Url::publiclyRoutable($target, $ip)) {
                $result['error'] = '目标不是可公开访问的 http/https 地址';
                return $result;
            }

            $parts = parse_url($target);
            $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
            $port = (int)($parts['port'] ?? (($parts['scheme'] ?? '') === 'http' ? 80 : 443));
            $location = '';
            $headers = [];

            $ch = curl_init($target);
            // 托管选项写在左边：array + 运算保留左侧取值，调用方无法覆盖这几项
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
                CURLOPT_HEADERFUNCTION => static function ($handle, $header) use (&$location, &$headers) {
                    if (preg_match('/^location:\s*(.+?)\s*$/i', $header, $m)) {
                        $location = $m[1];
                    }
                    // 顺手把响应头留给调用方：抓取层要按 Retry-After 决定等多久
                    if (preg_match('/^([!#$%&\'*+\-.^_`|~0-9A-Za-z]+):\s*(.*?)\s*$/u', $header, $hm) && $hm[2] !== '') {
                        $headers[strtolower($hm[1])] = $hm[2];
                    }
                    return strlen($header);
                },
            ] + HttpTls::curlOptions() + $options);

            $body = curl_exec($ch);
            $errno = (int) curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($status >= 300 && $status < 400 && $location !== '') {
                $next = Url::absolutize($target, $location);
                if ($next === null) {
                    break;
                }
                $target = $next;
                continue;
            }

            $result['status'] = $status;
            $result['content_type'] = $contentType;
            $result['headers'] = $headers;
            // 重定向循环走完后的 $target 才是真正取到内容的那个地址。调用方要拿它去解析页面里的
            // 相对路径 —— 用传进来的原地址会把 https://a.com 的图标算到已经跳走的旧域名上。
            $result['final_url'] = $target;
            $result['errno'] = $errno;
            $result['body'] = is_string($body) ? $body : '';
            $result['ok'] = $status >= 200 && $status < 300 && $result['body'] !== '';
            if (!$result['ok']) {
                $result['error'] = $errno !== 0 ? (string) curl_strerror($errno) : 'HTTP ' . $status;
            } else {
                $result['error'] = '';
            }
            return $result;
        }

        $result['error'] = '重定向次数超过上限或目标地址非法';
        return $result;
    }
}
