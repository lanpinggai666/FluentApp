<?php
/**
 * 蓝屏钙软件导航网 - GitHub 仓库与 Release 直链解析服务
 */

class GithubService {
    public static function parseRepo(string $url): ?array {
        $pattern = '~github\.com/([^/]+)/([^/?#]+)~i';
        if (preg_match($pattern, $url, $matches)) {
            $owner = $matches[1];
            $repo = preg_replace('/\.git$/i', '', $matches[2]);
            return [
                'owner' => $owner,
                'repo' => $repo,
                'full' => "{$owner}/{$repo}"
            ];
        }
        return null;
    }

    public static function getLatestRelease(string $githubUrl): ?array {
        $repoInfo = self::parseRepo($githubUrl);
        if (!$repoInfo) {
            return null;
        }

        $cacheDir = sys_get_temp_dir() . '/softnav_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir . '/gh_' . md5($repoInfo['full']) . '.json';

        // 缓存 15 分钟，避免 GitHub API Rate Limit (60次/小时)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 900)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!empty($data)) {
                return self::processReleaseAssets($data);
            }
        }

        $apiUrl = "https://api.github.com/repos/{$repoInfo['owner']}/{$repoInfo['repo']}/releases/latest";
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SoftwareNav/1.0',
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json']
        ] + HttpTls::curlOptions());

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $data = json_decode($res, true);
            if (is_array($data)) {
                file_put_contents($cacheFile, $res);
                return self::processReleaseAssets($data);
            }
        }

        return null;
    }

    private static function processReleaseAssets(array $releaseData): array {
        $assets = $releaseData['assets'] ?? [];
        $tagName = $releaseData['tag_name'] ?? 'latest';
        $publishedAt = substr($releaseData['published_at'] ?? '', 0, 10);
        $htmlUrl = $releaseData['html_url'] ?? '';

        $formattedAssets = [];
        $categorized = [
            'x64' => [],
            'x86' => [],
            'arm64' => [],
            'portable' => [],
            'other' => []
        ];

        foreach ($assets as $asset) {
            $name = $asset['name'];
            $lname = strtolower($name);
            $item = [
                'name' => $name,
                'download_url' => $asset['browser_download_url'] ?? '',
                'size_bytes' => $asset['size'] ?? 0,
                'size_formatted' => isset($asset['size']) ? self::formatBytes($asset['size']) : '未知大小',
                'download_count' => $asset['download_count'] ?? 0,
            ];
            $formattedAssets[] = $item;

            $isExe = str_ends_with($lname, '.exe') || str_ends_with($lname, '.msi');
            $isZip = str_ends_with($lname, '.zip') || str_ends_with($lname, '.7z');

            if (str_contains($lname, 'arm64') || str_contains($lname, 'aarch64')) {
                $categorized['arm64'][] = $item;
            } elseif (str_contains($lname, 'x64') || str_contains($lname, 'win64') || str_contains($lname, 'amd64') || str_contains($lname, '64-bit') || str_contains($lname, 'win-x64')) {
                if ($isExe) {
                    $categorized['x64'][] = $item;
                } elseif ($isZip) {
                    $categorized['portable'][] = $item;
                } else {
                    $categorized['x64'][] = $item;
                }
            } elseif (str_contains($lname, 'x86') || str_contains($lname, 'win32') || str_contains($lname, 'ia32') || str_contains($lname, '32-bit') || str_contains($lname, 'win-x86')) {
                $categorized['x86'][] = $item;
            } elseif (str_contains($lname, 'portable')) {
                $categorized['portable'][] = $item;
            } elseif ($isExe) {
                // 默认 exe 归入 x64
                $categorized['x64'][] = $item;
            } else {
                $categorized['other'][] = $item;
            }
        }

        // 主推资产
        $primaryAsset = $categorized['x64'][0] ?? ($formattedAssets[0] ?? null);

        return [
            'tag' => $tagName,
            'published_at' => $publishedAt,
            'release_url' => $htmlUrl,
            'asset_name' => $primaryAsset['name'] ?? null,
            'download_url' => $primaryAsset['download_url'] ?? null,
            'size_bytes' => $primaryAsset['size_bytes'] ?? 0,
            'size_formatted' => $primaryAsset['size_formatted'] ?? '未知大小',
            'download_count' => $primaryAsset['download_count'] ?? 0,
            'categories' => $categorized,
            'all_assets' => $formattedAssets,
            'total_assets' => count($formattedAssets)
        ];
    }

    public static function getReadme(string $githubUrl): ?string {
        $repoInfo = self::parseRepo($githubUrl);
        if (!$repoInfo) {
            return null;
        }

        $cacheDir = sys_get_temp_dir() . '/softnav_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir . '/readme_' . md5($repoInfo['full']) . '.html';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            return file_get_contents($cacheFile);
        }

        $rawPath = "https://raw.githubusercontent.com/{$repoInfo['owner']}/{$repoInfo['repo']}/HEAD/README.md";

        // 依次尝试各激活镜像：README 属于体积较小的 raw 资源，
        // 部分反代虽不支持仓库网页浏览但能稳定代理 raw 文件。
        $candidates = [''];
        foreach (GithubMirror::getActive() as $mirror) {
            $candidates[] = (string)($mirror['url_template'] ?? '');
        }

        foreach (array_unique(array_filter($candidates, 'strlen')) as $template) {
            // 映射方式要让 mirrorStyle 判：以前这里读了一个从没赋值的 $mirrorStyles，
            // 结果永远是空串，前缀型反代拼出来的 README 地址是错的
            $url = $template === ''
                ? $rawPath
                : self::buildMirrorUrl($template, $rawPath, self::mirrorStyle($template));

            // 超时原来是 2 秒：raw.githubusercontent 冷启动常在 2–4 秒，一超时就整份 README 不显示。
            // 只对连接层失败重试一次；拿到了 HTTP 响应（含 404/403）就不再重，重了也是同一个结果。
            for ($try = 1; $try <= 2; $try++) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SoftwareNav/1.0',
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_FOLLOWLOCATION => true
                ] + HttpTls::curlOptions());
                $res = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($httpCode === 200 && !empty($res)) {
                    $html = self::simpleMarkdownToHtml($res, (string) $repoInfo['full']);
                    file_put_contents($cacheFile, $html);
                    return $html;
                }
                if ($httpCode !== 0 || $curlErr === '') {
                    break;
                }
                usleep(300000);
            }
        }

        return null;
    }

    /**
     * 把 README 里的仓库内相对路径解析到仓库地址上。
     *
     * GitHub 文档大量使用相对路径（截图、徽章、指南链接），原样输出会被浏览器按
     * 本站目录解析成 404，被协议白名单挡掉后又会变成死链。
     */
    private static function resolveRepoPath(string $url, ?string $repoFull, bool $isImage): string {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($repoFull === null || $repoFull === '' || $url === '' || $url[0] === '#') {
            return $url;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return $url;
        }
        // 协议相对地址（//host/x.png）：补上 https 就完事，绝不能当仓库内相对路径拼到 raw 域名后面
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $path = preg_replace('#^(\./)+#', '', ltrim($url, '/')) ?? '';
        // 含 .. 的相对路径不猜位置，交回后面的协议白名单处理
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return $url;
        }

        return $isImage
            ? 'https://raw.githubusercontent.com/' . $repoFull . '/HEAD/' . $path
            : 'https://github.com/' . $repoFull . '/blob/HEAD/' . $path;
    }

    /**
     * README 里允许保留的标签。
     * GitHub 上的 README 大量用 HTML 排版（<p align="center"> 徽章墙、<img width=...> 截图），
     * 以前一律 htmlspecialchars 转义，结果就是「详情页没有图片」—— 现在改成白名单净化后保留。
     */
    private const README_ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'a', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'small', 'sub', 'sup',
        'code', 'pre', 'kbd', 'samp', 'var', 'blockquote', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'hr', 'details', 'summary', 'figure', 'figcaption', 'section', 'article', 'center',
    ];

    /** 脚本类容器连内容一起删（SVG 里能挂事件与外链，README 用不上，一律不要） */
    private const README_DROP_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'form', 'input', 'button',
        'link', 'meta', 'base', 'svg', 'math', 'head', 'title', 'noscript', 'template', 'applet', 'audio', 'video', 'source',
    ];

    /** 属性白名单：'*' 对所有标签生效，其余按标签叠加。事件属性（on*）天然不在表内 = 全删 */
    private const README_ALLOWED_ATTRS = [
        '*' => ['align', 'valign', 'title', 'width', 'height', 'dir', 'lang', 'colspan', 'rowspan', 'span', 'start', 'reversed', 'summary', 'scope', 'nowrap', 'border', 'cellpadding', 'cellspacing'],
        'a' => ['href', 'name', 'target', 'rel'],
        'img' => ['src', 'alt'],
        'td' => ['bgcolor', 'color'],
        'th' => ['bgcolor', 'color'],
    ];

    /** 摘出来的代码块/行内代码与尖括号目标暂存处用的哨兵字符（不含 NUL，DOM 与 XML 都能安全携带） */
    private const README_CODE_TOKEN = "\u{27E6}SOFTNAV-CODE-";
    private const README_RAW_TOKEN = "\u{27E6}SOFTNAV-RAW-";

    /**
     * 把 README 里的裸 HTML 按白名单洗一遍。
     * 净化器用 DOM 而不是正则：README 来自任意第三方仓库，正则漏一个写法就是本站的存储型 XSS，
     * 事件属性、script、SVG 这些必须结构性地拿掉。解析失败时整体转义（宁可不出图）。
     */
    private static function sanitizeReadmeHtml(string $html, ?string $repoFull): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="utf-8"?><div id="softnav-r">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        if (!$loaded) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }
        $root = (new DOMXPath($doc))->query('//div[@id="softnav-r"]')->item(0);
        if (!$root instanceof DOMElement) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $clean = static function (DOMNode $node) use (&$clean, $doc, $repoFull): void {
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $child = $node->childNodes->item($i);
                if ($child instanceof DOMComment) {
                    $node->removeChild($child);
                    continue;
                }
                if (!$child instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($child->nodeName);
                if (in_array($tag, self::README_DROP_TAGS, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $clean($child);
                if (!in_array($tag, self::README_ALLOWED_TAGS, true)) {
                    // 不认识就「剥壳留字」：文字保留，标签本身丢掉
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                self::cleanReadmeAttrs($child, $repoFull);
            }
        };
        $clean($root);

        $out = '';
        foreach ($root->childNodes as $kept) {
            $out .= (string) $doc->saveHTML($kept);
        }
        return $out;
    }

    /** 单元素的属性清洗：白名单外的全删，链接/图片地址重新解析并过协议白名单 */
    private static function cleanReadmeAttrs(DOMElement $el, ?string $repoFull): void
    {
        $tag = strtolower($el->nodeName);
        $allowed = array_merge(self::README_ALLOWED_ATTRS['*'], self::README_ALLOWED_ATTRS[$tag] ?? []);
        for ($i = $el->attributes->length - 1; $i >= 0; $i--) {
            $attr = $el->attributes->item($i);
            if ($attr === null) {
                continue;
            }
            $name = strtolower($attr->nodeName);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($name);
                continue;
            }
            if ($name === 'href' || $name === 'src') {
                $url = self::readmeUrl((string) $attr->nodeValue, $repoFull, $name === 'src');
                if ($url === '') {
                    $el->removeAttribute($name);
                } else {
                    $el->setAttribute($name, $url);
                }
            }
        }
        if ($tag === 'a' && $el->getAttribute('href') !== '') {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * 校验并补全 README 里的地址。
     * Markdown::safeUrl() 顺手做了 HTML 转义，而这里的值要交给 DOM 再序列化一次，
     * 所以只取它的「能不能用」结论，把转义再解回去，避免 &amp;amp; 这种二次编码。
     */
    private static function readmeUrl(string $url, ?string $repoFull, bool $isImage): string
    {
        $resolved = self::resolveRepoPath($url, $repoFull, $isImage);
        $safe = Markdown::safeUrl($resolved);
        return $safe === '' ? '' : html_entity_decode($safe, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 解析 markdown 链接/图片的圆括号部分：支持 `<a b.png>` 尖括号形态与 `url "标题"` 带标题形态。
     * @return array{0:string,1:string} [地址, 标题]
     */
    private static function splitLinkTarget(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['', ''];
        }
        if (preg_match('#^(?:<|&lt;)(.*?)(?:>|&gt;)[ \t]*((?:"[^"]*"|\'[^\']*\'|\([^)]*\)))?$#s', $raw, $m)) {
            // 尖括号形态存在的意义就是「地址里有空格」，这里百分号编码掉，别丢给白名单判死。
            // 存回来的条目在还原时被转义过，所以 <> 与 &lt;&gt; 两种形态都要认。
            return [str_replace(' ', '%20', trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))), ''];
        }
        if (preg_match('/^(\S+)\s+(?:"[^"]*"|\'[^\']*\'|\([^)]*\))$/s', $raw, $m)) {
            return [$m[1], ''];
        }
        return [$raw, ''];
    }

    private static function simpleMarkdownToHtml(string $md, ?string $repoFull = null): string {
        if (mb_strlen($md) > 60000) {
            $md = mb_substr($md, 0, 60000) . "\n\n*(文档过长已截断显示，更多内容请移步项目源码查看)*";
        }

        // 1) 代码块与行内代码先摘出来存好：它们是唯一允许出现尖括号、& 与各种符号的地方，
        //    不能参与后面的 HTML 净化与 markdown 解析
        $codeBank = [];
        $stash = static function (string $html) use (&$codeBank): string {
            $codeBank[] = $html;
            return self::README_CODE_TOKEN . (count($codeBank) - 1) . "\u{27E7}";
        };
        $text = preg_replace_callback('/```([a-zA-Z0-9_\-]+)?\n(.*?)```/s',
            static fn(array $m): string => $stash('<pre class="readme-pre"><code class="readme-code">'
                . htmlspecialchars(trim($m[2]), ENT_QUOTES, 'UTF-8') . '</code></pre>'), $md) ?? $md;
        $text = preg_replace_callback('/`([^`\n]+)`/',
            static fn(array $m): string => $stash('<code class="readme-inline-code">'
                . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>'), $text) ?? $text;

        // 1b) 尖括号形态的地址摘出来：`![s](<docs/my shot.png>)`、自动链接 `<https://x>`。
        //     不摘的话会被下一步的 HTML 解析器当成不认识的标签吃掉，图片就掉到兜底图。
        //     只认两种确定形态（链接圆括号里的 <…>、明确带协议的自动链接），
        //     其余一律交给净化器 —— 摘错会变成页面上的真标签，判错宁可保守。
        $rawBank = [];
        $stashRaw = static function (string $inner) use (&$rawBank): string {
            $rawBank[] = $inner;
            return self::README_RAW_TOKEN . (count($rawBank) - 1) . "\u{27E7}";
        };
        $text = preg_replace_callback(
            '/(!?\[[^\]\n]*\]\()[ \t]*<([^<>\n]*)>[ \t]*((?:"[^"\n]*"|\'[^\'\n]*\'))?[ \t]*\)/u',
            static fn(array $m): string => $m[1] . $stashRaw(trim($m[2])) . ($m[3] ?? '') . ')',
            $text
        ) ?? $text;
        $text = preg_replace_callback('/<((?:[a-zA-Z][a-zA-Z0-9+.-]*:\/\/|www\.)[^\s<>]*)>/',
            static fn(array $m): string => $stashRaw($m[1]), $text) ?? $text;

        // 2) 净化裸 HTML（图片、居中排版都靠它），之后才做 markdown 转换
        $text = self::sanitizeReadmeHtml($text, $repoFull);
        // 2b) 归还尖括号形态：转义后放回，这样上面万一判错也不会变成真标签
        foreach ($rawBank as $idx => $rawInner) {
            $text = str_replace(self::README_RAW_TOKEN . $idx . "\u{27E7}",
                '&lt;' . htmlspecialchars($rawInner, ENT_QUOTES, 'UTF-8') . '&gt;', $text);
        }

        // 3) markdown 语法
        $text = preg_replace('/^###### (.*?)$/m', '<h6 class="readme-h6">$1</h6>', $text) ?? $text;
        $text = preg_replace('/^##### (.*?)$/m', '<h5 class="readme-h5">$1</h5>', $text) ?? $text;
        $text = preg_replace('/^#### (.*?)$/m', '<h4 class="readme-h4">$1</h4>', $text) ?? $text;
        $text = preg_replace('/^### (.*?)$/m', '<h3 class="readme-h3">$1</h3>', $text) ?? $text;
        $text = preg_replace('/^## (.*?)$/m', '<h2 class="readme-h2">$1</h2>', $text) ?? $text;
        $text = preg_replace('/^# (.*?)$/m', '<h1 class="readme-h1">$1</h1>', $text) ?? $text;
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;

        // 图片与链接：地址必须过与站内 Markdown 同一份协议白名单，
        // 否则 README 里的 [x](javascript:...) 会原样落到 href 上
        $text = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/s', static function (array $m) use ($repoFull): string {
            [$target] = self::splitLinkTarget($m[2]);
            $src = self::readmeUrl($target, $repoFull, true);
            $alt = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            return '<img src="' . ($src !== '' ? htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                : rtrim(Url::basePath(), '/') . '/assets/images/default-app.svg')
                . '" alt="' . $alt . '" class="readme-img" loading="lazy" referrerpolicy="no-referrer">';
        }, $text) ?? $text;
        $text = preg_replace_callback('/(?<!!)\[(.*?)\]\((.*?)\)/s', static function (array $m) use ($repoFull): string {
            [$target] = self::splitLinkTarget($m[2]);
            $href = self::readmeUrl($target, $repoFull, false);
            return '<a href="' . ($href !== '' ? htmlspecialchars($href, ENT_QUOTES, 'UTF-8') : '#')
                . '" target="_blank" rel="noopener noreferrer nofollow" class="readme-link">' . $m[1] . '</a>';
        }, $text) ?? $text;

        // 自动链接 <https://…>：还原时是转义形态，这里补成真的链接（GitHub 也是这么渲染的）
        $text = preg_replace_callback('/&lt;((?:https?:\/\/|www\.)[^\s<>]+)&gt;/u', static function (array $m) use ($repoFull): string {
            $href = self::readmeUrl($m[1], $repoFull, false);
            if ($href === '') {
                return $m[0];
            }
            $label = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener noreferrer nofollow" class="readme-link">' . $label . '</a>';
        }, $text) ?? $text;

        $text = preg_replace('/^[*\-] (.*?)$/m', '<li class="readme-li">$1</li>', $text) ?? $text;
        $text = preg_replace('/^\d+[.)] (.*?)$/m', '<li class="readme-li">$1</li>', $text) ?? $text;

        // 4) 归还代码块（放在分段之前，<pre> 才不会被再套一层 <p>）
        foreach ($codeBank as $idx => $html) {
            $text = str_replace(self::README_CODE_TOKEN . $idx . "\u{27E7}", $html, $text);
        }

        // 5) 分段：本来就是块级标签的整块放行，其余包成段落
        $blockish = ['<h1', '<h2', '<h3', '<h4', '<h5', '<h6', '<pre', '<li', '<p', '<div', '<table',
            '<ul', '<ol', '<blockquote', '<details', '<hr', '<figure', '<section', '<center', '<img'];
        $paragraphs = explode("\n\n", $text);
        $out = [];
        foreach ($paragraphs as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $lower = strtolower(ltrim($p));
            $isBlock = false;
            foreach ($blockish as $open) {
                if (str_starts_with($lower, $open)) {
                    $isBlock = true;
                    break;
                }
            }
            $out[] = $isBlock ? $p : '<p class="readme-p">' . nl2br($p) . '</p>';
        }

        return implode("\n", $out);
    }

    /**
     * 需要「整段前缀」代理的 GitHub 官方域名。
     * 这些域名并非 github.com 的子域，域名替换型镜像无法直接接管，
     * 必须使用 <prefix>/https://host/path 的反代前缀形态。
     */
    public const PREFIX_PROXY_HOSTS = [
        'objects.githubusercontent.com',
        'raw.githubusercontent.com',
        'codeload.github.com',
        'release-assets.githubusercontent.com',
        'api.github.com',
        'gist.githubusercontent.com',
        'github-releases.githubusercontent.com',
        'user-images.githubusercontent.com',
    ];

    /** 已知「域名替换型」镜像：直接把 github.com 换成镜像域名即可。 */
    private const DOMAIN_REPLACEMENT_HINTS = [
        'boki.moe', 'moeyy.xyz', 'fastgit', 'kkgithub', 'ghproxy.cc',
    ];

    /**
     * 判定镜像模板属于哪种形态。
     * @return string 'direct' | 'replace' | 'prefix'
     */
    public static function mirrorStyle(string $template, string $declared = ''): string {
        // 学到的映射方式优先：镜像站的拼法只有它自己说得清，域名清单只是老数据的兜底
        if (in_array($declared, ['direct', 'replace', 'prefix'], true)) {
            return $declared;
        }
        $template = trim($template);
        if ($template === '' || strcasecmp($template, 'direct') === 0) {
            return 'direct';
        }
        if (preg_match('#^https?://github\.com/?$#i', $template)) {
            return 'direct';
        }
        if (preg_match('#^https?://github\.com/#i', $template)) {
            return 'prefix';
        }
        foreach (self::DOMAIN_REPLACEMENT_HINTS as $hint) {
            if (stripos($template, $hint) !== false) {
                return 'replace';
            }
        }
        // 模板中若包含一个完整的原始 URL（如 https://ghproxy.net/https://github.com/）
        // 说明用户填写的就是前缀反代形态
        if (preg_match('#^https?://[^/]+/(https?://|https%3A)#i', $template)) {
            return 'prefix';
        }
        return 'prefix';
    }

    /**
     * 构造镜像直链。
     * - direct  : 原样返回
     * - replace : 域名替换（github.com -> 镜像域名）
     * - prefix  : 反代前缀（<prefix>/<完整原链>）
     */
    public static function buildMirrorUrl(string $template, string $originalUrl, string $declared = ''): string {
        $template = trim($template);
        $originalUrl = trim($originalUrl);
        $style = self::mirrorStyle($template, $declared);

        if ($style === 'direct') {
            return self::normalizeGithubUrl($originalUrl);
        }

        $originalUrl = self::normalizeGithubUrl($originalUrl);
        $cleanTpl = rtrim($template, '/');

        if ($style === 'replace') {
            return preg_replace(
                '#^https?://(?:[\w.-]+\.)?github(?:usercontent)?\.com#i',
                $cleanTpl,
                $originalUrl,
                1
            ) ?: ($cleanTpl . '/' . ltrim($originalUrl, '/'));
        }

        // prefix：整条原链挂在前缀之后
        return $cleanTpl . '/' . $originalUrl;
    }

    /**
     * 归一化 GitHub 链接：补全协议、清除重复前缀。
     */
    public static function normalizeGithubUrl(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (!preg_match('#^https?://#i', $url)) {
            if (preg_match('#^(raw\.|objects\.)?(githubusercontent\.com|github\.com)/#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            } else {
                $url = 'https://github.com/' . ltrim($url, '/');
            }
        }

        // 彻底杜绝重复前缀，例如 https://github.com/https://github.com/...
        // 或 https://ghproxy.net/https://ghproxy.net/https://github.com/...
        $guard = 0;
        while ($guard++ < 8 && preg_match('#^https?://[^/]+/(https?://)#i', $url, $m)) {
            $url = $m[1] . substr($url, strlen($m[0]));
        }

        return $url;
    }

    /**
     * 先定类型，再用一条真实镜像链接「学会」这个镜像站的映射格式。
     *
     * 这条规则取代了旧的「自动识别」：
     *   1) 加速定位（download / web / both）由运维指定，系统不再从链接形态反猜 ——
     *      同一个镜像站常常既能代理下载又能代理网页，选哪个是运营决策，不是技术问题。
     *   2) 学到的规则必须自证：用推导出的模板把原始 GitHub 链接映射一遍，
     *      结果必须逐字符等于运维贴进来的那条。对不上就明说没学会，让运维补贴原始链接，
     *      而不是存一个看着像、实际拼错的模板进去（那样前台点每个版本都会 404）。
     *
     * @return array{success:bool,url_template?:string,original_url?:string,name?:string,host?:string,
     *               type?:string,type_guessed?:bool,verified?:bool,mirror_url?:string,
     *               type_label?:string,style?:string,hint?:string,message?:string}
     */
    public static function learnMirrorFromSample(string $sampleUrl, string $type = '', string $originalUrl = ''): array
    {
        $sampleUrl = trim($sampleUrl);
        if ($sampleUrl === '' || !preg_match('#^https?://#i', $sampleUrl)) {
            return ['success' => false, 'message' => '请粘贴一条以 http(s):// 开头的真实镜像链接'];
        }
        $type = strtolower(trim($type));
        if ($type !== '' && !in_array($type, ['download', 'web', 'both'], true)) {
            return ['success' => false, 'message' => '加速定位只能是 download / web / both'];
        }
        $originalUrl = trim($originalUrl);
        if ($originalUrl !== '' && !preg_match('#^https?://#i', $originalUrl)) {
            return ['success' => false, 'message' => '对应的原始链接也要是 http(s):// 地址'];
        }

        $candidates = self::mappingCandidates($sampleUrl, $originalUrl);
        if ($candidates === []) {
            return ['success' => false, 'message' => $originalUrl !== ''
                ? '这两个链接对不上：从「' . $originalUrl . '」到这条镜像链接之间找不到可用的映射规则。'
                    . '若这个镜像是把原链接编码进查询参数（形如 ?url=…）的形态，本站暂不支持，请改用它支持的换域名/前缀形式。'
                : '没能从这条链接里推出映射规则。它看起来不像是「原始 GitHub 链接挂在前缀后面」或「换域名」的形态 —— '
                    . '请在「对应的原始 GitHub 链接」里再贴一条 github.com 上的同一条资源地址，让系统学一次。'];
        }

        $learned = null;
        foreach ($candidates as [$template, $original]) {
            // 先按域名清单猜一次；猜不通就分别按「换域名」和「加前缀」两种拼法再试，
            // 哪个能逐字符还原出运维贴的这条链接，就学成哪个
            foreach (['', 'replace', 'prefix'] as $declared) {
                $roundTrip = self::buildMirrorUrl($template, $original, $declared);
                if ($roundTrip === $sampleUrl || $roundTrip === rtrim($sampleUrl, '/')) {
                    $learned = [
                        'template' => $template,
                        'original' => $original,
                        'style' => $declared !== '' ? $declared : self::mirrorStyle($template),
                        'roundtrip' => $roundTrip,
                    ];
                    break 2;
                }
            }
        }
        if ($learned === null) {
            $guess = $candidates[0];
            return ['success' => false,
                'url_template' => $guess[0], 'original_url' => $guess[1],
                'mirror_url' => self::buildMirrorUrl($guess[0], $guess[1]),
                'message' => '按「' . $guess[0] . '」推导出来的镜像地址和你贴的这条不一致，说明这个镜像站用的是另一种拼法。'
                    . '请补贴对应的原始 GitHub 链接再解析一次，或者手工填写模板。'];
        }

        $host = strtolower((string) (parse_url($sampleUrl, PHP_URL_HOST) ?: ''));
        $guessed = self::guessMirrorType($sampleUrl);
        $finalType = $type !== '' ? $type : $guessed;

        $hint = '';
        if ($type !== '' && $type !== $guessed) {
            $hint = '你选的是「' . self::typeLabel($type) . '」，而这条链接本身更像「' . self::typeLabel($guessed)
                . '」。已按你选的保存 —— 类型决定前端把它用在哪些场景，与链接形态无关。';
        }

        return [
            'success' => true,
            'url_template' => $learned['template'],
            'original_url' => $learned['original'],
            'mirror_url' => $learned['roundtrip'],
            'verified' => true,
            'style' => $learned['style'],
            'style_label' => $learned['style'] === 'replace' ? '换域名' : ($learned['style'] === 'prefix' ? '原链挂前缀' : '直连'),
            'name' => self::suggestMirrorName($host),
            'host' => $host,
            'type' => $finalType,
            'type_guessed' => $type === '',
            'type_label' => self::typeLabel($finalType),
            'description' => $finalType === 'download' ? '只用于 Release 安装包 / 源码包 / Raw 等文件下载'
                : ($finalType === 'web' ? '只用于仓库网页浏览加速' : '下载与网页浏览都可使用'),
            'hint' => $hint,
        ];
    }

    /** 候选映射：[模板, 原始 GitHub 链接]，按可信度排序，逐个拿去自证 */
    private static function mappingCandidates(string $sampleUrl, string $originalUrl): array
    {
        $ghHosts = 'github\.com|raw\.githubusercontent\.com|objects\.githubusercontent\.com|'
            . 'codeload\.github\.com|release-assets\.githubusercontent\.com|api\.github\.com|'
            . 'gist\.githubusercontent\.com|github-releases\.githubusercontent\.com|'
            . 'user-images\.githubusercontent\.com';
        $out = [];

        // 运维给了原始链接时，一切以它为准：先看镜像链接是否把原链整条包在里面，
        // 再退到「换域名/换前缀但路径相同」的公共后缀推导
        if ($originalUrl !== '') {
            $needle = strpos($sampleUrl, $originalUrl);
            if ($needle !== false && $needle > 0) {
                $out[] = [rtrim(substr($sampleUrl, 0, $needle), '/') . '/', $originalUrl];
            }
            $tail = self::commonSuffix($sampleUrl, $originalUrl);
            if ($tail !== '' && str_contains($tail, '/')) {
                $base = substr($sampleUrl, 0, strlen($sampleUrl) - strlen($tail));
                $from = substr($originalUrl, 0, strlen($originalUrl) - strlen($tail));
                if (preg_match('#^https?://(' . $ghHosts . ')$#i', $from) && preg_match('#^https?://#i', $base)) {
                    $out[] = [rtrim($base, '/') . '/', $originalUrl];
                }
            }
        }

        // 运维给了原始链接时一切以它为准，不再回退到「按形态猜」的候选：
        // 猜出来的候选对任何域名都能自证成功（原链是从样例本身拼的），会把「把原链接编码进查询参数」
        // 这类本站暂不支持的形式学成错误的换域名模板
        if ($originalUrl !== '') {
            return $out;
        }

        // 前缀反代型：https://ghproxy.net/https://github.com/owner/repo/releases/download/...
        if (preg_match('#^(https?://[^/]+)/(https?://(?:' . $ghHosts . ')(?:/.*)?)$#i', $sampleUrl, $m)) {
            $out[] = [rtrim($m[1], '/') . '/', $m[2]];
        }

        // 域名替换型：https://kkgithub.com/owner/repo/releases/download/...
        // 要求路径至少两段（owner/repo），否则 /dl?url=xxx 这种参数型会被误学成换域名
        $host = strtolower((string) (parse_url($sampleUrl, PHP_URL_HOST) ?: ''));
        $path = (string) (parse_url($sampleUrl, PHP_URL_PATH) ?: '');
        $query = (string) (parse_url($sampleUrl, PHP_URL_QUERY) ?: '');
        $suffix = $path . ($query !== '' ? '?' . $query : '');
        if ($host !== '' && !preg_match('#^(www\.)?(' . $ghHosts . ')$#i', $host)
            && substr_count(trim($path, '/'), '/') >= 1) {
            $scheme = (string) (parse_url($sampleUrl, PHP_URL_SCHEME) ?: 'https');
            $out[] = ["{$scheme}://{$host}/", 'https://github.com' . $suffix];
        }

        return $out;
    }

    /** 两条 URL 的最长公共后缀（至少要有路径分隔符，避免把整条链接当公共部分） */
    private static function commonSuffix(string $a, string $b): string
    {
        $max = min(strlen($a), strlen($b));
        for ($i = 0; $i < $max; $i++) {
            if ($a[strlen($a) - 1 - $i] !== $b[strlen($b) - 1 - $i]) {
                break;
            }
        }
        $tail = substr($a, strlen($a) - $i);
        return substr_count($tail, '/') >= 2 ? $tail : '';
    }

    /** 链接形态更像哪一类加速（只作为未指定类型时的兜底与建议提示） */
    private static function guessMirrorType(string $sampleUrl): string
    {
        return preg_match('#(releases/download|archive/refs|/raw/|/releases/latest|/releases/tag|\.exe|\.msi|\.zip|\.7z|\.rar|\.tar|\.gz|\.dmg|\.deb|\.apk|\.iso)#i', $sampleUrl)
            ? 'download' : 'both';
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'download' => '文件下载加速',
            'web' => '网页浏览加速',
            default => '全能加速（下载+网页）',
        };
    }

    private static function suggestMirrorName(string $host): string
    {
        $first = explode('.', $host)[0] ?? $host;
        return ucfirst($first) . " 加速 ({$host})";
    }

    /**
     * 服务端镜像可用性实测。
     *
     * 浏览器端 <img> 测速存在严重误判：部分反代对 favicon.ico 直接返回 403/404，
     * 但对真实的 Release 安装包 / raw 文件代理完全正常。因此测速必须以
     * 「真实可下载的 GitHub 资源」为探测目标，并由服务端发起带超时的请求。
     *
     * @param string $template 镜像模板（direct / 反代前缀 / 域名替换）
     * @param string $probeUrl  用于探测的真实 GitHub 资源地址
     * @return array{success:bool,time:int,code:int,message:string,url:string,kind:string}
     */
    public static function probeMirror(string $template, string $probeUrl = '', string $declared = ''): array {
        $template = trim($template);
        $style = self::mirrorStyle($template, $declared);

        // 探测目标：优先使用传入的真实资源；否则退化为仓库内一个稳定存在的小体积 raw 文件
        if (trim($probeUrl) === '') {
            $probeUrl = 'https://raw.githubusercontent.com/twbs/bootstrap/main/README.md';
        }

        $target = $style === 'direct'
            ? self::normalizeGithubUrl($probeUrl)
            : self::buildMirrorUrl($template, $probeUrl, $declared);

        $kind = self::probeKind($target);

        $ch = curl_init();
        $downloaded = 0;
        $contentType = '';
        curl_setopt_array($ch, [
            CURLOPT_URL => $target,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SoftwareNav/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Range: bytes=0-65535',
            ],
            // 累计 64KB 后返回 0 中止传输，避免大体积安装包打满内存
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$downloaded, &$contentType) {
                if ($contentType === '' && preg_match('/^content-type:\s*([^\r\n]+)/im', $data, $m)) {
                    $contentType = strtolower(trim($m[1]));
                }
                $chunk = strlen($data);
                $downloaded += $chunk;
                return $downloaded >= 65536 ? 0 : $chunk;
            },
        ] + HttpTls::curlOptions());

        $start = microtime(true);
        @curl_exec($ch);
        $elapsed = (int) round((microtime(true) - $start) * 1000);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $success = $code >= 200 && $code < 400 && $downloaded > 0;
        $message = '正常';

        if (!$success) {
            $message = $code > 0 ? "HTTP {$code}" : '连接失败：' . ($error ?: '超时或被拒绝');
        } elseif ($kind === 'download' && str_contains($contentType, 'text/html')) {
            // 反代把 404/拦截页包装成 200 HTML，必须识别出来，否则仍是误判
            $success = false;
            $message = '返回 HTML 错误页（代理不可用）';
        } elseif ($kind === 'download' && $contentType === '') {
            $success = false;
            $message = '未返回有效文件流';
        }

        return [
            'success' => $success,
            'time' => $elapsed,
            'code' => $code,
            'message' => $message,
            'url' => $target,
            'effective_url' => $effective,
            'kind' => $kind,
            'content_type' => $contentType,
            'bytes' => $downloaded,
        ];
    }

    /**
     * 推断探测地址更像「网页浏览」还是「文件下载」。
     */
    private static function probeKind(string $url): string {
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        if (str_contains($path, '/releases/download/')
            || str_contains($path, '/archive/')
            || preg_match('/\.(exe|msi|zip|7z|rar|tar|gz|dmg|deb|apk)(\?|$)/', $path)
        ) {
            return 'download';
        }
        return 'web';
    }

    private static function formatBytes(int $bytes, int $precision = 1): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
