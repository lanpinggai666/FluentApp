<?php
/**
 * 联网搜索后端 —— AI 的「先查再答」里那半句「查」。
 *
 * 现状问题（改造前）：只有两个免 Key 源，`api.duckduckgo.com` 的 Instant Answer 基本只覆盖
 * Wikipedia（商业软件几乎回空），另一个是正则抓 Bing 的 HTML —— 对方模板一改就集体失效。
 *
 * 现在按「稳定优先」排：
 *   1. 官方搜索 API（后台填了 Key 就优先用）：Tavily → Brave → Serper → Firecrawl。
 *      这类接口是**设计给程序调的**，不把你当爬虫，返回结构化结果，也最接近 AI 网页端那种「搜完能直接用」；
 *      Firecrawl 还会把结果页正文一起带回（返回体里带 markdown），命中它就不必再开一次网页；
 *   2. 免 Key 兜底：DuckDuckGo HTML 端点 → DDG Lite → Bing，用浏览器一致指纹抓 HTML，
 *      解析改走 DOMXPath（模板微调不至于全瞎），并解开 DDG 的 /l/?uddg= 跳转壳。
 * 结果统一清洗：解跳转、去重、同域名限 2 条、剔掉下载站与 SEO 农场（它们会把开发者、协议写错）。
 */
class WebSearch
{
    /** 低质量来源：装机站/下载站的文案会污染开发者、版本、协议这些事实字段 */
    private const NOISE_HOSTS = [
        'softonic.com', 'uptodown.com', 'filehippo.com', 'download.com', 'cnetdownload.com',
        'baixaki.com.br', 'malekal.com', 'techyart.com', 'snapshotlabs', 'apkpure.com',
        'apkcombo.com', 'moeville.com', 'getfrompdf.com', 'crack', 'keygen', 'portableapps-download',
    ];

    /** @return array<int,array{title:string,url:string,snippet:string,site:string,source:string}> */
    public static function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '' || Setting::get('ai_web_search_enabled', '1') === '0') {
            return [];
        }
        $limit = max(1, min(10, $limit));

        foreach (self::backends() as $name => $runner) {
            $items = $runner($query, $limit);
            $clean = self::clean($items, $limit);
            if ($clean !== []) {
                return $clean;
            }
        }
        return [];
    }

    /**
     * 后端顺序：有 Key 的官方 API 在前，然后是免 Key 的 HTML 抓取。
     * @return array<string,callable>
     */
    private static function backends(): array
    {
        $list = [];
        if (self::key('tavily') !== '') {
            $list['tavily'] = static fn(string $q, int $n): array => self::tavily($q, $n);
        }
        if (self::key('brave') !== '') {
            $list['brave'] = static fn(string $q, int $n): array => self::brave($q, $n);
        }
        if (self::key('serper') !== '') {
            $list['serper'] = static fn(string $q, int $n): array => self::serper($q, $n);
        }
        if (self::key('firecrawl') !== '') {
            $list['firecrawl'] = static fn(string $q, int $n): array => self::firecrawl($q, $n);
        }
        $list['duckduckgo_html'] = static fn(string $q, int $n): array => self::duckduckgo($q, $n, 'https://html.duckduckgo.com/html/');
        $list['duckduckgo_lite'] = static fn(string $q, int $n): array => self::duckduckgo($q, $n, 'https://lite.duckduckgo.com/lite/');
        $list['bing'] = static fn(string $q, int $n): array => self::bing($q, $n);
        return $list;
    }

    public static function key(string $backend): string
    {
        return trim((string) Setting::get('search_' . $backend . '_key', ''));
    }

    /** 这次真正会用的后端名（给日志和后台显示用，不发请求） */
    public static function activeBackend(): string
    {
        foreach (array_keys(self::backends()) as $name) {
            return (string) $name;
        }
        return 'none';
    }

    /** @return array<int,array<string,string>> */
    private static function tavily(string $query, int $limit): array
    {
        $payload = json_encode([
            'query' => $query,
            'max_results' => $limit,
            'search_depth' => 'basic',
            'include_answer' => false,
        ], JSON_UNESCAPED_UNICODE);
        $res = self::postJson('https://api.tavily.com/search', $payload, [
            'Authorization: Bearer ' . self::key('tavily'),
            'Content-Type: application/json',
        ]);
        $out = [];
        foreach ($res['results'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'snippet' => (string) ($row['content'] ?? ''),
                'source' => 'tavily',
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,string>> */
    private static function brave(string $query, int $limit): array
    {
        $url = 'https://api.search.brave.com/res/v1/web/search?q=' . urlencode($query)
            . '&count=' . max(1, min(20, $limit * 2)) . '&search_lang=zh-hans&text_decorations=false';
        $data = self::getJson($url, ['X-Subscription-Token: ' . self::key('brave'), 'Accept: application/json']);
        $out = [];
        foreach ($data['web']['hits'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'snippet' => (string) ($row['description'] ?? ''),
                'source' => 'brave',
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,string>> */
    private static function serper(string $query, int $limit): array
    {
        $payload = json_encode(['q' => $query, 'num' => max(1, min(20, $limit * 2))], JSON_UNESCAPED_UNICODE);
        $data = self::postJson('https://google.serper.dev/search', $payload, [
            'X-API-KEY: ' . self::key('serper'),
            'Content-Type: application/json',
        ]);
        $out = [];
        foreach ($data['organic'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['link'] ?? ''),
                'snippet' => (string) ($row['snippet'] ?? ''),
                'source' => 'serper',
            ];
        }
        return $out;
    }

    /**
     * Firecrawl 搜索（POST /v1/search）。
     * 与另外几家的差别是它会顺手把结果页正文抓回来（返回体里每条带 markdown），
     * 「搜完就有正文」，省掉再开一次网页 —— 这正是 AI 网页端那种体验的来源。
     * @return array<int,array<string,string>>
     */
    private static function firecrawl(string $query, int $limit): array
    {
        $payload = json_encode(['query' => $query, 'limit' => $limit], JSON_UNESCAPED_UNICODE);
        $res = self::postJson('https://api.firecrawl.dev/v1/search', $payload, [
            'Authorization: Bearer ' . self::key('firecrawl'),
            'Content-Type: application/json',
        ]);
        $rows = $res['data'] ?? $res['results'] ?? [];
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $snippet = self::plain((string) ($row['description'] ?? ''));
            if ($snippet === '') {
                // 抓过正文的返回才有 markdown：那是整页文档，只取前面一小段当摘要
                $snippet = mb_substr(self::plain((string) ($row['markdown'] ?? '')), 0, 600);
            }
            $out[] = [
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'snippet' => $snippet,
                'source' => 'firecrawl',
            ];
        }
        return $out;
    }

    /**
     * DuckDuckGo 的 HTML 端点（html 与 lite 两种模板）。
     * 链接是 /l/?uddg=<urlencoded> 的跳转壳，必须解开，否则抓到的永远是 duckduckgo.com。
     * @return array<int,array<string,string>>
     */
    private static function duckduckgo(string $query, int $limit, string $endpoint): array
    {
        $res = WebFetcher::post($endpoint, ['q' => $query, 'kl' => 'zh-cn'], 6);
        if (!$res['ok']) {
            return [];
        }
        $rows = self::nodesFromHtml((string) $res['body'], [
            '//a[contains(@class,"result__a")]',
            '//a[contains(@class,"result-link")]',
            '//div[@class="result results_links"]//h2/a',
        ]);
        $out = [];
        foreach ($rows as $row) {
            $url = self::unwrapDuckDuckGo($row['href']);
            if ($url === '') {
                continue;
            }
            $out[] = [
                'title' => $row['text'],
                'url' => $url,
                'snippet' => $row['snippet'] !== '' ? $row['snippet'] : $row['text'],
                'source' => 'duckduckgo',
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,string>> */
    private static function bing(string $query, int $limit): array
    {
        $url = 'https://www.bing.com/search?q=' . urlencode($query) . '&count=' . max(1, min(20, $limit * 2)) . '&setlang=zh-hans';
        $res = WebFetcher::fetch($url, 6, 'https://www.bing.com/');
        if (!$res['ok']) {
            return [];
        }
        $rows = self::nodesFromHtml((string) $res['body'], [
            '//li[contains(@class,"b_algo")]//h2/a',
            '//div[contains(@class,"b_algo")]//h2/a',
        ]);
        $out = [];
        foreach ($rows as $row) {
            $target = self::unwrapBing($row['href']);
            if ($target === '') {
                continue;
            }
            $out[] = ['title' => $row['text'], 'url' => $target, 'snippet' => $row['snippet'], 'source' => 'bing'];
        }
        return $out;
    }

    /**
     * 用 XPath 抓一批链接节点，附带各自的摘要文本（同一条结果里的 class 含 snippet 的元素，或父级段落）。
     * @param array<int,string> $queries
     * @return array<int,array{href:string,text:string,snippet:string}>
     */
    private static function nodesFromHtml(string $html, array $queries): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        if (!$loaded) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }
            $rows = [];
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $rows[] = [
                    'href' => $node->getAttribute('href'),
                    'text' => self::plain($node->textContent),
                    'snippet' => self::plain(self::snippetOf($dom, $node)),
                ];
            }
            if ($rows !== []) {
                return $rows;
            }
        }
        return [];
    }

    /** 摘要优先取结果容器里的 snippet 元素，取不到就退回链接的 title 属性 */
    private static function snippetOf(DOMDocument $dom, DOMElement $link): string
    {
        $parent = $link->parentNode;
        for ($up = 0; $up < 4 && $parent !== null; $up++) {
            foreach ($parent->getElementsByTagName('*') as $el) {
                $class = strtolower($el->getAttribute('class'));
                if (str_contains($class, 'snippet') || str_contains($class, 'b_caption')) {
                    $text = trim($el->textContent);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
            $parent = $parent->parentNode;
        }
        return $link->getAttribute('title');
    }

    private static function plain(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private static function unwrapDuckDuckGo(string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES, 'UTF-8'));
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            $href = 'https:' . $href;
        }
        if (preg_match('#[?&]uddg=([^&]+)#', $href, $m)) {
            return urldecode($m[1]);
        }
        return str_contains($href, 'duckduckgo.com') ? '' : $href;
    }

    private static function unwrapBing(string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES, 'UTF-8'));
        if ($href === '' || preg_match('#^https?://#i', $href) !== 1) {
            return '';
        }
        // Bing 会把结果包在 /url?= 跳转里
        if (str_contains($href, '/url?') && preg_match('#[?&]q?u?r?l?=([^&]+)#', $href, $m)) {
            $decoded = urldecode($m[1]);
            if (preg_match('#^https?://#i', $decoded) === 1) {
                return $decoded;
            }
        }
        return str_contains($href, 'bing.com') ? '' : $href;
    }

    /** @param array<int,string> $headers */
    private static function getJson(string $url, array $headers): array
    {
        $res = SafeHttp::get($url, [
            CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: application/json']),
            CURLOPT_ENCODING => 'gzip, deflate',
        ], 8, 4);
        if (!$res['ok']) {
            return [];
        }
        $decoded = json_decode((string) $res['body'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int,string> $headers */
    private static function postJson(string $url, string $payload, array $headers): array
    {
        $res = SafeHttp::get($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: application/json']),
            CURLOPT_ENCODING => 'gzip, deflate',
        ], 9, 4);
        if (!$res['ok']) {
            return [];
        }
        $decoded = json_decode((string) $res['body'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 清洗：只要公网 http/https、去重、同域名最多 2 条、剔掉下载站/破解站这类噪声源。
     * @param array<int,array<string,string>> $items
     * @return array<int,array{title:string,url:string,snippet:string,site:string,source:string}>
     */
    public static function clean(array $items, int $limit = 5): array
    {
        $out = [];
        $seen = [];
        $perHost = [];
        foreach ($items as $item) {
            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '' || preg_match('#^https?://#i', $url) !== 1 || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            if ($host === '' || self::isNoise($host)) {
                continue;
            }
            $normalized = strtolower((string) preg_replace('#/$#', '', rawurldecode(parse_url($url, PHP_URL_PATH) ?: '/')));
            $dedupeKey = $host . '|' . $normalized;
            if (isset($seen[$dedupeKey]) || ($perHost[$host] ?? 0) >= 2) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $perHost[$host] = ($perHost[$host] ?? 0) + 1;

            $out[] = [
                'title' => mb_substr(trim((string) ($item['title'] ?? '')), 0, 160),
                'url' => mb_substr($url, 0, 400),
                'snippet' => mb_substr(trim((string) ($item['snippet'] ?? '')), 0, 320),
                'site' => $host,
                'source' => (string) ($item['source'] ?? ''),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    private static function isNoise(string $host): bool
    {
        foreach (self::NOISE_HOSTS as $noise) {
            if ($noise !== '' && (str_contains($host, $noise) || str_ends_with($host, '.' . $noise))) {
                return true;
            }
        }
        return false;
    }
}
