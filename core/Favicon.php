<?php
/**
 * 从站点地址里找一个能当软件图标的图片。
 *
 * 提交表单原本要求上传者自己找图标直链，这一步最劝退，也最容易收到图床死链。
 * 官网地址是必填的，所以先替用户抓：首页 HTML 里的 <link rel="icon|apple-touch-icon">
 * 优先（这才是站点自己声明的图标），都没有再退回约定俗成的 /favicon.ico。
 *
 * 抓取仍然只从 WebFetcher 出去：robots.txt、同主机间隔、浏览器指纹这些约束
 * 不能因为「只是找个小图标」就绕开，否则这一层就成了绕过合规的新入口。
 */
class Favicon
{
    /** 一次提交的总预算（毫秒）：抓不到就算了，绝不能让提交按钮转三秒以上 */
    public const BUDGET_MS = 3000;

    /** 首页只需要 <head> 里那几行，读太多纯属浪费 */
    private const HEAD_CHARS = 60000;

    /** 成功缓存 / 失败缓存（秒）：同一个域名被反复提交时不再重复外发 */
    private const HIT_TTL = 86400 * 7;
    private const MISS_TTL = 3600;

    /**
     * @return string 可用的图标绝对地址，找不到返回 ''（调用方决定是报错还是留空）
     */
    public static function discover(string $siteUrl, int $budgetMs = self::BUDGET_MS): string
    {
        $site = self::normalizeSite($siteUrl);
        if ($site === '') {
            return '';
        }

        $host = strtolower((string) parse_url($site, PHP_URL_HOST));
        $cached = self::readCache($host);
        if ($cached !== null) {
            return $cached;
        }

        $deadline = microtime(true) + max(0.2, $budgetMs / 1000);
        $found = self::fromHomepage($site, $deadline);
        if ($found === '') {
            $found = self::fromDefaultPath($site, $deadline);
        }
        self::writeCache($host, $found);
        return $found;
    }

    /** 站点根地址：只保留 scheme://host:port，图标候选都相对它解析 */
    private static function normalizeSite(string $siteUrl): string
    {
        $url = trim($siteUrl);
        if (!Url::isSafe($url)) {
            return '';
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            return '';
        }
        $root = $scheme . '://' . $host . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . '/';
        // 服务端外发前的把关：挡内网与保留地址。这一层拿的是用户填的域名，
        // 不检查就等于让提交表单探测站点的内网。
        return Url::publiclyRoutable($root) ? $root : '';
    }

    /** 首页 HTML 里声明的 <link> 图标 */
    private static function fromHomepage(string $root, float $deadline): string
    {
        $res = WebFetcher::fetch($root, self::leftSeconds($deadline), $root);
        if (!$res['ok']) {
            return '';
        }
        $head = mb_substr((string) $res['body'], 0, self::HEAD_CHARS);
        if (!preg_match_all('/<link\b[^>]*>/i', $head, $tags)) {
            return '';
        }
        // 相对地址按「实际取到内容的那个页面」解析，官网 301 到 www 是常态
        $base = self::normalizeSite((string) $res['final_url']) ?: $root;

        $best = '';
        $bestScore = -1;
        foreach ($tags[0] as $tag) {
            $rel = strtolower(self::attr($tag, 'rel'));
            $href = self::attr($tag, 'href');
            if ($href === '' || $rel === '') {
                continue;
            }
            // apple-touch-icon / shortcut icon / icon 都算；mask-icon 是单色遮罩，不能当图标
            if (!preg_match('/(?:^|\s)(?:apple-touch-icon|shortcut icon|icon)(?:\s|$)/', $rel)
                || str_contains($rel, 'mask')) {
                continue;
            }
            $abs = Url::absolutize($base, html_entity_decode($href, ENT_QUOTES, 'UTF-8'));
            if ($abs === null || !Url::isSafe($abs)) {
                continue;
            }
            $score = self::score($rel, $tag, $abs);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $abs;
            }
        }
        return $best;
    }

    /** 约定地址 /favicon.ico：不少站点不写 link 标签但这里有图 */
    private static function fromDefaultPath(string $root, float $deadline): string
    {
        $res = WebFetcher::fetch($root . 'favicon.ico', self::leftSeconds($deadline), $root);
        if (!$res['ok'] || $res['status'] !== 200) {
            return '';
        }
        return preg_match('/(x-icon|image|octet-stream)/i', (string) $res['content_type'])
            ? $root . 'favicon.ico' : '';
    }

    /** 挑图标的优先级：明确声明尺寸的按边长取大的，apple-touch 优先于普通 icon */
    private static function score(string $rel, string $tag, string $url): int
    {
        $side = 0;
        foreach (preg_split('/[\s,]+/', strtolower(self::attr($tag, 'sizes'))) ?: [] as $size) {
            if (preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
                $side = max($side, min((int) $m[1], (int) $m[2]));
            }
        }
        $score = $side > 0 ? min(1000, $side) : 100;
        if (str_contains($rel, 'apple-touch')) {
            $score += 120;
        }
        if (str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?: ''), '.svg')) {
            $score += 60;
        }
        return $score;
    }

    /** 取标签属性：双引号、单引号、裸值三种写法都要认 */
    private static function attr(string $tag, string $name): string
    {
        if (!preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $tag, $m)) {
            return '';
        }
        foreach ([1, 2, 3] as $i) {
            if (($m[$i] ?? '') !== '') {
                return trim($m[$i]);
            }
        }
        return '';
    }

    /** 剩余预算换算成 curl 的秒数，至少 1 秒 —— 0 秒等于直接失败 */
    private static function leftSeconds(float $deadline): int
    {
        return max(1, (int) ceil($deadline - microtime(true)));
    }

    private static function cacheFile(string $host): string
    {
        return rtrim(WebFetcher::stateDir(), '/') . '/favicon_' . md5($host) . '.json';
    }

    /** @return string|null null = 没缓存过；'' = 缓存过的「就是没有」 */
    private static function readCache(string $host): ?string
    {
        $file = self::cacheFile($host);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['at'], $data['url'])) {
            return null;
        }
        $url = (string) $data['url'];
        $ttl = $url === '' ? self::MISS_TTL : self::HIT_TTL;
        return (time() - (int) $data['at'] < $ttl) ? $url : null;
    }

    private static function writeCache(string $host, string $url): void
    {
        @file_put_contents(self::cacheFile($host),
            json_encode(['at' => time(), 'url' => $url], JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
