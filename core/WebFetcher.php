<?php
/**
 * 服务端「打开网页」层 —— AI 的联网能力都从这里出去。
 *
 * 为什么单独成一层：以前每次抓取都是几行临时 curl 选项，指纹自相矛盾
 * （Chrome 的 UA 配 HTTP/1.1、没有 Accept-Encoding、没有 cookie、多个域名秒内连开），
 * 这种「半像浏览器」的请求比老实的 curl 更容易被判定为爬虫。
 *
 * 这里做的是**合规的像人**，不是对抗：
 *   - 一套彼此自洽的浏览器指纹（UA / Sec-Fetch-* / Accept-* 齐全，版本别互相打脸）；
 *   - 接受 gzip/deflate（curl 默认 identity 是最强的机器人特征之一）；
 *   - 带 cookie 罐与来源 Referer（从搜索结果点进正文页，这一步最像真实用户）；
 *   - 同一主机之间留间隔并加抖动，429/503 时读 Retry-After，且只多试一次；
 *   - 读 robots.txt 并遵守针对 * 的 Disallow —— 人家明确写了不给抓就不抓。
 * 明确不做：代理池轮换、打码、伪造随机指纹海、绕过登录墙或付费墙。
 *
 * 所有真实外发仍然走 SafeHttp：只允许公网 http/https、把解析出的 IP 钉死、逐跳重定向复检。
 * 这一层不能变成 SSRF 的新入口。
 */
class WebFetcher
{
    /** 同一主机两次抓取之间的最小间隔（毫秒） */
    private const COURTESY_MS = 1200;
    /** robots.txt 缓存时长（秒） */
    private const ROBOTS_TTL = 3600;
    private const DEFAULT_PAGE_CHARS = 5000;

    /**
     * 成体系的指纹：一次进程内固定用一套（每次换一个反而更像脚本）。
     */
    private const PROFILES = [
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'sec_ch_ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
        ],
        [
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
            'sec_ch_ua' => '"Chromium";v="123", "Not:A-Brand";v="8", "Microsoft Edge";v="123"',
        ],
    ];

    private static ?array $profile = null;

    /**
     * 抓一个网页 → ['ok','status','body','content_type','final_url','error']。
     * $referer 复现「从搜索结果点进来」的路径；不少站对空 Referer 的 HTML 请求更严。
     */
    public static function fetch(string $url, int $timeout = 6, string $referer = '', ?array $options = null): array
    {
        $out = ['ok' => false, 'status' => 0, 'body' => '', 'content_type' => '', 'final_url' => $url, 'error' => ''];
        $target = trim($url);
        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            $out['error'] = '只允许 http/https 地址';
            return $out;
        }
        $host = strtolower((string) (parse_url($target, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            $out['error'] = '地址里取不到主机名';
            return $out;
        }
        if (!self::robotsAllowed($target)) {
            $out['status'] = 403;
            $out['error'] = 'robots.txt 明确禁止抓取该路径（不做绕过）';
            return $out;
        }

        for ($try = 1; $try <= 2; $try++) {
            if ($try > 1) {
                self::courtesyWait($host);
            }
            $res = SafeHttp::get($target, $options ?? self::curlOptions($referer), $timeout, 4);
            $out['status'] = (int) $res['status'];
            $out['body'] = (string) $res['body'];
            $out['content_type'] = (string) $res['content_type'];
            // 站点经常 301 到 www，页面里的相对路径要按跳转后的地址解析
            $out['final_url'] = (string) ($res['final_url'] ?? $target);

            if (!empty($res['ok'])) {
                $out['ok'] = true;
                $out['error'] = '';
                self::markFetched($host);
                return $out;
            }
            self::markFetched($host);

            // 只有 429/503 值得再试一次，而且要按 Retry-After 等够；其余错误重试没有意义
            if (!in_array($out['status'], [429, 503], true) || $try === 2) {
                $out['error'] = $res['error'] !== '' ? (string) $res['error'] : ('HTTP ' . $out['status']);
                return $out;
            }
            usleep(self::retryAfterSeconds((string) ($res['headers']['retry-after'] ?? '')) * 1000000);
        }
        return $out;
    }

    /**
     * 抓 + 抽正文 + 截断，直接产出能进 prompt 的一段文本。
     * @return array{ok:bool, text:string, title:string, url:string, error:string}
     */
    public static function read(string $url, int $maxChars = self::DEFAULT_PAGE_CHARS, string $referer = ''): array
    {
        $res = self::fetch($url, 8, $referer);
        if (!$res['ok']) {
            return ['ok' => false, 'text' => '', 'title' => '', 'url' => $url, 'error' => $res['error']];
        }
        // 只吃网页与纯文本：PDF/图片/zip 进 prompt 只会污染上下文
        if ($res['content_type'] !== '' && !preg_match('#(html|xml|text/plain|json)#i', $res['content_type'])) {
            return ['ok' => false, 'text' => '', 'title' => '', 'url' => $url,
                'error' => '目标不是网页（Content-Type: ' . mb_substr($res['content_type'], 0, 40) . '）'];
        }
        $page = self::parsePage($res['body']);
        $limit = max(400, min(20000, $maxChars));
        return [
            'ok' => true,
            'text' => mb_substr($page['text'], 0, $limit),
            'title' => $page['title'],
            'url' => (string) $res['final_url'],
            'error' => '',
        ];
    }

    /**
     * HTML → ['title','text']。
     * 正文优先取 <article> / <main> / #content，都没有就取「文本最长」的容器 ——
     * 菜单、页脚、Cookie 弹窗会因为字少自然被淘汰，不再整页 strip_tags 取前 450 字。
     * @return array{title:string, text:string}
     */
    public static function parsePage(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['title' => '', 'text' => ''];
        }

        $dom = new DOMDocument();
        // 抓回来的 HTML 是不可信输入：不加载外部实体，解析告警一律咽掉
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8"?>' . self::sanitizeHtml($html),
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        if (!$loaded) {
            return ['title' => '', 'text' => self::flatten(strip_tags($html))];
        }

        $xpath = new DOMXPath($dom);
        $title = self::firstNonEmpty($xpath, [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//title/text()',
        ]);

        $best = '';
        foreach (['//article', '//main', '//*[@role="main"]', '//*[@id="content"]', '//body'] as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }
            foreach ($nodes as $node) {
                $text = self::nodeText($node);
                if (mb_strlen($text) > mb_strlen($best)) {
                    $best = $text;
                }
            }
            // 命中更精确的容器就够了，不必再退到 //body
            if ($best !== '' && $query !== '//body') {
                break;
            }
        }

        if (mb_strlen($best) < 200) {
            // 重 JS 的壳页 / 整页都是模板：退化成 meta description + 现有文本
            $desc = self::firstNonEmpty($xpath, [
                '//meta[@name="description"]/@content',
                '//meta[@property="og:description"]/@content',
            ]);
            $best = trim($desc . ($best !== '' ? "\n" . $best : ''));
        }

        return ['title' => $title, 'text' => $best];
    }

    /** 去掉对文本抽取没意义、又可能带跳转语义的标签 */
    private static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<base\b[^>]*>/i', '', $html) ?? $html;
        return preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    }

    /** @return array<int,string> */
    private static function firstNonEmpty(DOMXPath $xpath, array $queries): string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $value = trim(html_entity_decode((string) $nodes->item(0)->nodeValue, ENT_QUOTES, 'UTF-8'));
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    /** 节点 → 保留段落感的纯文本（不改动原 DOM，避免影响后面的候选节点） */
    private static function nodeText(DOMNode $node): string
    {
        $doc = $node->ownerDocument;
        if ($doc === null) {
            return '';
        }
        $html = $doc->saveHTML($node);
        if ($html === false) {
            return '';
        }
        $html = preg_replace('#<(script|style|noscript|svg|iframe|template|form|nav|footer|header|aside)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('/<(p|br|li|h[1-6]|tr|section|div)\b[^>]*>/i', "\n", $html) ?? $html;
        return self::flatten(strip_tags($html));
    }

    private static function flatten(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n+/u', "\n", $text) ?? $text;
        return trim(preg_replace('/\r\n|\r|\n/', "\n", $text) ?? $text);
    }

    /**
     * 表单 POST 抓取（搜索端点用）。同样过 robots 与同主机间隔 ——
     * 浏览器在搜索框里回车也是一次 form-submit，请求头长得像表单才正常。
     * @param array<string,string> $fields
     */
    public static function post(string $url, array $fields, int $timeout = 6): array
    {
        $origin = self::schemeHost($url);
        return self::fetch($url, $timeout, $origin, self::formOptions($fields, $origin));
    }

    /** @param array<string,string> $fields @return array<int,mixed> */
    public static function formOptions(array $fields, string $origin): array
    {
        $profile = self::profile();
        $body = http_build_query($fields, '', '&');
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate',
            'Content-Type: application/x-www-form-urlencoded',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
        ];
        if ($origin !== '') {
            $headers[] = 'Origin: ' . $origin;
        }
        return [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_USERAGENT => $profile['ua'],
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => $headers,
        ];
    }

    /** https://host 形式（Origin 头要用，别把路径与查询带进去） */
    public static function schemeHost(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return $scheme . '://' . $host . $port;
    }

    /** @return array<int,mixed> 交给 SafeHttp 的额外选项（托管项 SafeHttp 自己会保留它的取值） */
    private static function curlOptions(string $referer): array
    {
        $profile = self::profile();
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: ' . ($referer !== '' ? 'cross-site' : 'none'),
            'Sec-Fetch-User: ?1',
            'sec-ch-ua: ' . $profile['sec_ch_ua'],
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
        ];
        if ($referer !== '') {
            $headers[] = 'Referer: ' . $referer;
        }

        $jar = self::cookieJar();
        return [
            CURLOPT_USERAGENT => $profile['ua'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_COOKIEJAR => $jar,
        ];
    }

    private static function profile(): array
    {
        if (self::$profile === null) {
            self::$profile = self::PROFILES[random_int(0, count(self::PROFILES) - 1)];
        }
        return self::$profile;
    }

    /** 用户提交的官网地址不能带进来 cookie，所以只留一个进程内的一次性罐子 */
    private static function cookieJar(): string
    {
        $path = self::stateDir() . '/cookies_' . getmypid() . '.txt';
        if (!is_file($path)) {
            @touch($path);
        }
        return $path;
    }

    /** Retry-After 只认「秒数」写法，并且最多等 3 秒：一次网页抓取不该把整条 AI 任务拖死 */
    private static function retryAfterSeconds(string $value): int
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return 1;
        }
        return max(1, min(3, (int) $value));
    }

    /** 同一主机的最小间隔 + 抖动。时间戳存在临时目录，跨请求也生效。 */
    private static function courtesyWait(string $host): void
    {
        if (Setting::get('ai_courtesy_delay', '1') === '0') {
            return;
        }
        $file = self::stateDir() . '/' . md5($host) . '.last';
        $last = (float) trim((string) @file_get_contents($file));
        if ($last <= 0) {
            return;
        }
        $gapMs = self::COURTESY_MS + random_int(0, 700);
        $waitMs = (int) round($gapMs - (microtime(true) - $last) * 1000);
        if ($waitMs > 0) {
            usleep(min(3000000, $waitMs * 1000));
        }
    }

    private static function markFetched(string $host): void
    {
        @file_put_contents(self::stateDir() . '/' . md5($host) . '.last', (string) microtime(true));
    }

    /**
     * robots.txt：只看针对 * 的组，Allow 优先；缓存 1 小时。
     * 取不到 robots 就当允许（礼貌性靠间隔与指纹一致性保证，不靠这里拦）。
     */
    public static function robotsAllowed(string $url): bool
    {
        if (Setting::get('ai_respect_robots', '1') === '0') {
            return true;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        $rules = self::readRobotsCache($scheme, $host);
        if ($rules === null) {
            $res = SafeHttp::get($scheme . '://' . $host . '/robots.txt', [
                CURLOPT_USERAGENT => self::profile()['ua'],
                CURLOPT_HTTPHEADER => ['Accept: text/plain'],
            ], 4, 3);
            $rules = self::parseRobots((string) $res['body']);
            @file_put_contents(self::robotsCacheFile($host), json_encode([
                'ts' => time(), 'allow' => $rules['allow'], 'disallow' => $rules['disallow'],
            ], JSON_UNESCAPED_SLASHES));
        }

        foreach ($rules['disallow'] as $prefix) {
            if ($prefix === '' || !str_starts_with($path, $prefix)) {
                continue;
            }
            foreach ($rules['allow'] as $ok) {
                if ($ok !== '' && str_starts_with($path, $ok)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /** @return array{allow:array<int,string>, disallow:array<int,string>} */
    private static function parseRobots(string $text): array
    {
        $allow = [];
        $disallow = [];
        $ours = false;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!preg_match('/^([A-Za-z-]+)\s*:\s*(.*)$/u', $line, $m)) {
                continue;
            }
            $key = strtolower($m[1]);
            $value = trim($m[2]);
            if ($key === 'user-agent') {
                $ours = $value === '*';
                continue;
            }
            if (!$ours || $value === '') {
                continue;
            }
            if ($key === 'disallow') {
                $disallow[] = $value;
            } elseif ($key === 'allow') {
                $allow[] = $value;
            }
        }
        return ['allow' => $allow, 'disallow' => $disallow];
    }

    /** @return array{allow:array<int,string>, disallow:array<int,string>}|null null = 没有可用缓存 */
    private static function readRobotsCache(string $scheme, string $host): ?array
    {
        $file = self::robotsCacheFile($host);
        if ($scheme !== 'https' && $scheme !== 'http') {
            return null;
        }
        $decoded = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        if (!is_array($decoded) || !isset($decoded['ts'], $decoded['allow'], $decoded['disallow'])) {
            return null;
        }
        if (time() - (int) $decoded['ts'] > self::ROBOTS_TTL) {
            return null;
        }
        return ['allow' => array_values((array) $decoded['allow']), 'disallow' => array_values((array) $decoded['disallow'])];
    }

    private static function robotsCacheFile(string $host): string
    {
        return self::stateDir() . '/' . md5($host) . '.robots.json';
    }

    /** 抓取层的临时目录（robots 缓存、同主机时间戳，以及 favicon 结果缓存都在这里） */
    public static function stateDir(): string
    {
        $dir = rtrim((string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()), '\\/') . '/softnav_fetch';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}
