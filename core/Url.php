<?php
/**
 * 蓝屏钙软件导航网 - 外链地址校验与输出净化
 *
 * 站内外链（官网、图标、下载渠道、GitHub）全部来自用户提交，既要在入库时挡掉
 * javascript:/data: 这类伪协议，也要在属性输出时兜底：历史脏数据与后台直改库
 * 都会绕过入库校验。
 */
class Url {
    public const MAX_LENGTH = 500;

    /** 仅接受可安全作为 href 使用的 http/https 绝对地址。 */
    public static function isSafe(?string $url): bool {
        $url = trim((string) $url);

        if ($url === '' || mb_strlen($url) > self::MAX_LENGTH) {
            return false;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        // 中文网盘/下载链接的路径与查询串常含非 ASCII 字符，先转成百分号编码再校验，
        // 否则 filter_var 会把这类合法地址一并拒掉
        $ascii = preg_replace_callback('/[^\x00-\x7F]/u', static fn(array $m): string => rawurlencode($m[0]), $url) ?? $url;
        if (!filter_var($ascii, FILTER_VALIDATE_URL)) {
            return false;
        }
        // 属性撑破与换行注入用的字符一律拒绝（引号、尖括号、反斜杠、反引号、空白）
        if (strpbrk($url, " \t\n\r<>\"'`\\") !== false) {
            return false;
        }
        return !preg_match('/[\x00-\x1F\x7F]/', $url);
    }

    /**
     * 输出为 href / src 属性值：非法或空地址回落到占位值，返回值已按属性上下文转义。
     */
    public static function href(?string $url, string $fallback = '#'): string {
        return self::isSafe($url) ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : $fallback;
    }

    /**
     * 服务端外发请求前的地址把关：只允许解析到公网 IPv4 的 http/https 地址。
     *
     * 内网黑名单正则（形如 /^(127\.|10\.|...)$/）永远挡不住 127.0.0.1 这类带端口的
     * 写法、十进制/十六进制 IP，以及「域名解析到内网」的情况，所以这里改成先解析再按
     * RFC 私网/保留段判定。解析出的 IP 由引用带回，调用方可用 CURLOPT_RESOLVE 钉死，
     * 免得校验通过后 DNS 又指向内网（重绑定）。
     *
     * 只接受 IPv4：图标源与官网的 IPv6-only 主机极少，而 gethostbynamel 无法为 IPv6
     * 字面量提供可钉定的地址，宁可不发这请求。
     */
    public static function publiclyRoutable(?string $url, ?string &$ip = null): bool {
        $ip = null;
        if (!self::isSafe($url)) {
            return false;
        }

        $parts = parse_url($url);
        $host = strtolower(trim((string)($parts['host'] ?? ''), "[]"));
        if ($host === '') {
            return false;
        }

        $port = (int)($parts['port'] ?? ((($parts['scheme'] ?? 'https') === 'http') ? 80 : 443));
        if (!in_array($port, [80, 443], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (!self::isPublicIp($host)) {
                return false;
            }
            $ip = $host;
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        $resolved = gethostbynamel($host);
        if (!is_array($resolved) || $resolved === []) {
            return false;
        }
        foreach ($resolved as $candidate) {
            if (!self::isPublicIp($candidate)) {
                return false;
            }
        }
        $ip = $resolved[0];
        return true;
    }

    /** 把响应里的 Location 头解析成绝对地址，供逐跳重定向复检使用。 */
    public static function absolutize(string $baseUrl, string $ref): ?string {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }
        $origin = $scheme . '://' . $host . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');

        if ($ref[0] === '/') {
            return $origin . $ref;
        }
        $dir = preg_replace('#/[^/]*$#', '/', (string)($parts['path'] ?? '/')) ?? '/';
        return $origin . $dir . $ref;
    }

    /**
     * 伪静态开关，默认开。
     * 只要 Web 服务器把不存在的路径交给 index.php（nginx 的 try_files、Apache 的 .htaccess 都是这个形状）就能用；
     * 关掉则退回 ?r= 形态，两种地址始终都可达。
     */
    public static function rewriteOn(): bool
    {
        return Setting::get('url_rewrite', '1') === '1';
    }

    /** 站内根路径：根部署是 '/'，子目录部署是 '/nav/'（与 siteBase() 同一套推断） */
    public static function basePath(): string
    {
        $configured = trim((string) Setting::get('site_base_url', ''));
        if ($configured !== '' && preg_match('#^https?://[^/]+(/.*)?$#i', $configured, $m)) {
            // 站点配在域名根上时 $m[1] 为空或 '/'，前缀必须还是单个 '/'：
            // 拼出 '//software/x' 会被浏览器当成协议相对地址，把 software 当域名解析走
            $path = trim((string) ($m[1] ?? ''), '/');
            return $path === '' ? '/' : '/' . $path . '/';
        }
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
            $script = str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']);
            if (substr($script, -4) === '.php') {
                // Windows 上 dirname('/index.php') 返回的是 '\'，不先换成 '/' 就会拼出 '\/assets/...'
                // 这种「看着像绝对其实是相对」的地址，浏览器解析错位（本机预览时后台直接没样式）
                $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
                return ($dir === '' || $dir === '/') ? '/' : $dir . '/';
            }
        }
        return '/';
    }

    /**
     * 站内地址的唯一生成口：$route 形如 admin/users、submit、software/view。
     * 伪静态开启 → <根>/admin/users?status=pending；关闭 → <根>/index.php?r=admin/users&status=pending。
     */
    public static function route(string $route, array $query = [], bool $absolute = false): string
    {
        $route = trim(str_replace('\\', '/', $route), '/');
        if ($route === 'home' || $route === 'home/index') {
            $route = '';
        }
        // slug 允许中文：伪静态形态要逐段编码；查询串形态的 r= 值留给 PHP 解析，段内不编码斜杠
        $pathRoute = $route === '' ? '' : implode('/', array_map('rawurlencode', explode('/', $route)));
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode((string) $key) . '[]=' . rawurlencode((string) $item);
                }
                continue;
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        $prefix = $absolute ? rtrim(self::siteBase(), '/') . '/' : self::basePath();
        if (self::rewriteOn()) {
            return $prefix . $pathRoute . ($pairs === [] ? '' : '?' . implode('&', $pairs));
        }
        $tail = $route === '' ? '' : 'r=' . $route;
        if ($pairs !== []) {
            $tail = $tail === '' ? implode('&', $pairs) : $tail . '&' . implode('&', $pairs);
        }
        return $prefix . 'index.php' . ($tail === '' ? '' : '?' . $tail);
    }

    /**
     * 分类页地址：伪静态走 /category/<slug>（Router 的别名）；
     * 非伪静态只能是 home + cat 参数，因为 ?r= 形态里没有 category 这条路由。
     *
     * 参数声明成 ?string：这些值全部来自 LEFT JOIN 出来的行（分类被删、软件下架等），
     * 非空声明会让 PHP 抛 TypeError，而生产关闭了错误回显，用户看到的就是 nginx 的 500。
     */
    public static function category(?string $slug, array $query = [], bool $absolute = false): string
    {
        $slug = trim((string) $slug);
        if (!self::rewriteOn() || $slug === '') {
            return self::route('home', array_merge(['cat' => $slug], $query), $absolute);
        }
        return self::route('category/' . $slug, $query, $absolute);
    }

    /** 软件详情页地址：伪静态 /software/<slug>，否则 ?r=software/view&slug=<slug>；同上，允许 null */
    public static function detail(?string $slug, bool $absolute = false): string
    {
        $slug = trim((string) $slug);
        if (!self::rewriteOn() || $slug === '') {
            return self::route('software/view', ['slug' => $slug], $absolute);
        }
        return self::route('software/' . $slug, [], $absolute);
    }

    /**
     * 把老式 ?r=控制器/动作&参数 的地址串换成当前形态。
     * 用在无法于源头拆分的场合（拼接出来的跳转目标）：值本身已经编码过，这里只搬运、不重复编码。
     */
    public static function target(string $link): string
    {
        $link = trim($link);
        if ($link === '' || preg_match('#^https?://#i', $link)) {
            return $link;
        }
        $fragment = '';
        if (($hash = strpos($link, '#')) !== false) {
            $fragment = substr($link, $hash);
            $link = substr($link, 0, $hash);
        }
        $body = null;
        if (str_starts_with($link, 'index.php?')) {
            $body = substr($link, strlen('index.php?'));
        } elseif (str_starts_with($link, '?')) {
            $body = substr($link, 1);
        }
        if ($body === null) {
            // 已经是路径形态（/software/x）或站外目标，原样返回
            return $link . $fragment;
        }

        $route = '';
        $pairs = [];
        foreach (explode('&', $body) as $piece) {
            if ($piece === '') {
                continue;
            }
            if ($route === '' && str_starts_with($piece, 'r=')) {
                $route = substr($piece, 2);
                continue;
            }
            $pairs[] = $piece;
        }
        if ($route === 'home' || $route === 'home/index') {
            $route = '';
        }
        $prefix = self::basePath();
        if (self::rewriteOn()) {
            return $prefix . ltrim($route, '/') . ($pairs === [] ? '' : '?' . implode('&', $pairs)) . $fragment;
        }
        $tail = $route === '' ? '' : 'r=' . $route;
        if ($pairs !== []) {
            $tail = $tail === '' ? implode('&', $pairs) : $tail . '&' . implode('&', $pairs);
        }
        return $prefix . 'index.php' . ($tail === '' ? '' : '?' . $tail) . $fragment;
    }

    /**
     * 站点绝对基址（不含尾部斜杠），供回调地址、邮件内链与 sitemap 共用。
     *
     * 优先后台配置的 site_base_url：生产环境填了它，这些地址就完全不依赖请求头。
     * 未配置时才按当前请求推断 —— scheme 只看 HTTPS（本站不跑在反向代理后面，
     * X-Forwarded-Proto 任何人都能伪造），host 限定为合法主机名，并保留子目录部署的路径。
     */
    public static function siteBase(): string {
        $configured = rtrim(trim((string) Setting::get('site_base_url', '')), '/');
        if ($configured !== ''
            && preg_match('#^https?://[^/?\#]+#i', $configured)
            && !preg_match('#^https?://[^/?]*@#i', $configured)) {
            return $configured;
        }

        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._\-\[\]]{1,190}(:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }

        $subDir = '';
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
            $dir = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
            $subDir = $dir === '/' ? '' : $dir;
        }

        return ($https ? 'https' : 'http') . '://' . $host . $subDir;
    }

    private static function isPublicIp(string $ip): bool {
        // NO_PRIV_RANGE 挡 10/8、172.16/12、192.168/16；NO_RES_RANGE 挡回环、链路本地
        // (169.254，含云元数据 169.254.169.254)、0.0.0.0/8、192.0.2/24 等保留段
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // 100.64.0.0/10 运营商级 NAT 不在 PHP 的保留段名单里，但公网永不路由
        $long = ip2long($ip);
        return $long === false ? false : ($long & 0xFFC00000) !== ip2long('100.64.0.0');
    }
}
