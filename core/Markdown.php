<?php
/**
 * 蓝屏钙软件导航网 - 轻量 Markdown 渲染器
 *
 * 设计原则：先整体转义 HTML，再按块级/行内规则还原为安全标签，
 * 任何用户输入都无法注入脚本。所有链接仅放行 http/https/mailto 协议。
 * 与前端开源编辑器 EasyMDE (MIT) 共用同一套语义。
 */
class Markdown {
    /** 渲染 Markdown 为安全 HTML。 */
    public static function render(?string $markdown): string {
        $markdown = (string)$markdown;
        if (trim($markdown) === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $lines = explode("\n", $text);
        $html = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // 1. 围栏代码块
            if (preg_match('/^```\s*([A-Za-z0-9_+#.-]*)\s*$/', $trimmed, $m)) {
                $lang = $m[1] !== '' ? ' class="language-' . self::escapeAttr($m[1]) . '"' : '';
                $buffer = [];
                $i++;
                for (; $i < $count; $i++) {
                    if (preg_match('/^```\s*$/', trim($lines[$i]))) {
                        break;
                    }
                    $buffer[] = $lines[$i];
                }
                $html[] = '<pre class="md-pre"><code' . $lang . '>' . implode("\n", $buffer) . '</code></pre>';
                continue;
            }

            // 2. 空行
            if ($trimmed === '') {
                continue;
            }

            // 3. 分隔线
            if (preg_match('/^(\*\s*){3,}$|^(-\s*){3,}$|^(_\s*){3,}$/', $trimmed)) {
                $html[] = '<hr class="md-hr">';
                continue;
            }

            // 4. 标题
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
                $level = strlen($m[1]);
                $html[] = '<h' . $level . ' class="md-h md-h' . $level . '">' . self::inline($m[2]) . '</h' . $level . '>';
                continue;
            }

            // 5. 表格
            if ($i + 1 < $count && preg_match('/^\s*\|?[\s:-]*\|[\s:|-]*$/', $lines[$i + 1])
                && str_contains($trimmed, '|')) {
                $header = self::splitTableRow($trimmed);
                $aligns = self::splitTableRow($lines[$i + 1]);
                $i += 2;
                $rows = [];
                while ($i < $count && str_contains(trim($lines[$i]), '|') && trim($lines[$i]) !== '') {
                    $rows[] = self::splitTableRow(trim($lines[$i]));
                    $i++;
                }
                $i--;

                $html[] = '<div class="md-table-wrap"><table class="md-table"><thead><tr>';
                foreach ($header as $index => $cell) {
                    $html[] = '<th' . self::alignAttr($aligns, $index) . '>' . self::inline($cell) . '</th>';
                }
                $html[] = '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $html[] = '<tr>';
                    foreach ($header as $index => $_) {
                        $html[] = '<td' . self::alignAttr($aligns, $index) . '>' . self::inline($row[$index] ?? '') . '</td>';
                    }
                    $html[] = '</tr>';
                }
                $html[] = '</tbody></table></div>';
                continue;
            }

            // 6. 引用块
            if (str_starts_with($trimmed, '&gt;')) {
                $buffer = [];
                while ($i < $count && str_starts_with(trim($lines[$i]), '&gt;')) {
                    $buffer[] = ltrim(substr(trim($lines[$i]), 4));
                    $i++;
                }
                $i--;
                $html[] = '<blockquote class="md-quote">' . self::render(implode("\n", $buffer)) . '</blockquote>';
                continue;
            }

            // 7. 无序 / 有序列表
            if (preg_match('/^[*+-]\s+(.*)$/', $trimmed, $m)) {
                $items = [];
                while ($i < $count) {
                    $current = trim($lines[$i]);
                    if (preg_match('/^[*+-]\s+(.*)$/', $current, $item)) {
                        $items[] = '<li>' . self::inline($item[1]) . '</li>';
                        $i++;
                        continue;
                    }
                    // 列表项的续行（缩进）
                    if ($current !== '' && str_starts_with($lines[$i], '  ') && !empty($items)) {
                        $last = array_pop($items);
                        $items[] = substr($last, 0, -5) . ' ' . self::inline(trim($current)) . '</li>';
                        $i++;
                        continue;
                    }
                    break;
                }
                $i--;
                $html[] = '<ul class="md-list">' . implode('', $items) . '</ul>';
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/', $trimmed, $m)) {
                $items = [];
                while ($i < $count) {
                    $current = trim($lines[$i]);
                    if (preg_match('/^\d+[.)]\s+(.*)$/', $current, $item)) {
                        $items[] = '<li>' . self::inline($item[1]) . '</li>';
                        $i++;
                        continue;
                    }
                    if ($current !== '' && str_starts_with($lines[$i], '  ') && !empty($items)) {
                        $last = array_pop($items);
                        $items[] = substr($last, 0, -5) . ' ' . self::inline(trim($current)) . '</li>';
                        $i++;
                        continue;
                    }
                    break;
                }
                $i--;
                $html[] = '<ol class="md-list md-ol">' . implode('', $items) . '</ol>';
                continue;
            }

            // 8. 段落（连续非空行合并）
            $buffer = [$line];
            $i++;
            while ($i < $count) {
                $next = trim($lines[$i]);
                if ($next === ''
                    || preg_match('/^#{1,6}\s+/', $next)
                    || preg_match('/^```/', $next)
                    || str_starts_with($next, '&gt;')
                    || preg_match('/^[*+-]\s+/', $next)
                    || preg_match('/^\d+[.)]\s+/', $next)) {
                    break;
                }
                $buffer[] = $lines[$i];
                $i++;
            }
            $i--;
            $html[] = '<p class="md-p">' . nl2br(self::inline(implode("\n", $buffer)), false) . '</p>';
        }

        return implode("\n", $html);
    }

    /** 提取首个有意义的正文段落，忽略标题、列表和代码块。 */
    public static function firstParagraph(?string $markdown, int $limit = 160): string {
        // 不能用 /\R/ 且不带宽恕：PCRE 在非 UTF-8 模式下把字节 0x85 当 NEL 换行符，
        // 而「具」「备」等汉字的 UTF-8 编码里就含 0x85 —— 会在字符中间把字符串劈开，产出乱码。
        $lines = preg_split('/\r\n|\r|\n/', str_replace(["\r\n", "\r"], "\n", (string)$markdown)) ?: [];
        $buffer = [];
        $inFence = false;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '```')) { $inFence = !$inFence; continue; }
            if ($inFence || $trimmed === '' || preg_match('/^#{1,6}\s+/', $trimmed) || preg_match('/^([-*+]\s+|\d+[.)]\s+)/', $trimmed)) {
                if ($buffer) break;
                continue;
            }
            $buffer[] = $trimmed;
        }
        return self::toPlainText(implode(' ', $buffer), $limit);
    }

    /**
     * 「一句话简介」的唯一算法：取软件说明的首句。
     *
     * 之所以不让用户单独填：单独填的那句一定会和正文说辞漂移，
     * 列表页与详情页各讲一套。现在 summary 是 description_md 的派生列，改正文即改简介。
     */
    public static function summaryFrom(?string $markdown, int $limit = 300): string {
        $first = self::firstParagraph($markdown, $limit);
        if ($first === '') {
            return '';
        }
        // 中文标点后即断句；英文只在「句点 + 空白」处断，这样 v1.2、e.g. 这类不会被误切
        if (preg_match('/^(.{1,150}?[。！？!?])/u', $first, $m) === 1) {
            return trim($m[1]);
        }
        if (preg_match('/^([^.!?\s][^.!?]{1,148}?\.)\s/u', $first, $m) === 1) {
            return trim($m[1]);
        }
        return $first;
    }

    /** 生成用于 SEO / 列表摘要的纯文本。 */
    public static function toPlainText(?string $markdown, int $limit = 160): string {
        $text = (string)$markdown;
        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[#>*_~\-]+/', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . '…';
        }
        return $text;
    }

    /** 行内语法渲染（输入已完成 HTML 转义）。 */
    private static function inline(string $text): string {
        $placeholders = [];

        // 行内代码优先占位，避免内部符号被二次解析
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$placeholders) {
            $key = "\x00CODE" . count($placeholders) . "\x00";
            $placeholders[$key] = '<code class="md-code">' . $m[1] . '</code>';
            return $key;
        }, $text) ?? $text;

        // 图片
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', function ($m) use (&$placeholders) {
            $url = self::safeUrl($m[2]);
            if ($url === '') {
                return $m[1];
            }
            $key = "\x00IMG" . count($placeholders) . "\x00";
            $placeholders[$key] = '<img class="md-img" src="' . $url . '" alt="'
                . self::escapeAttr($m[1]) . '"' . (isset($m[3]) ? ' title="' . self::escapeAttr($m[3]) . '"' : '')
                . ' loading="lazy" referrerpolicy="no-referrer">';
            return $key;
        }, $text) ?? $text;

        // 链接
        $text = preg_replace_callback('/\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', function ($m) use (&$placeholders) {
            $url = self::safeUrl($m[2]);
            if ($url === '') {
                return $m[1];
            }
            $external = !str_starts_with($url, 'mailto:');
            $key = "\x00LNK" . count($placeholders) . "\x00";
            $placeholders[$key] = '<a class="md-link" href="' . $url . '"'
                . (isset($m[3]) ? ' title="' . self::escapeAttr($m[3]) . '"' : '')
                . ($external ? ' target="_blank" rel="noopener noreferrer nofollow"' : '')
                . '>' . $m[1] . '</a>';
            return $key;
        }, $text) ?? $text;

        // 删除线
        $text = preg_replace('/~~(?=\S)([\s\S]*?\S)~~/', '<del class="md-del">$1</del>', $text) ?? $text;
        // 粗体
        $text = preg_replace('/\*\*(?=\S)([\s\S]*?\S)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__(?=\S)([\s\S]*?\S)__/', '<strong>$1</strong>', $text) ?? $text;
        // 斜体
        $text = preg_replace('/(?<![A-Za-z0-9*])\*(?=\S)([^*\n]*?\S)\*(?![A-Za-z0-9*])/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![A-Za-z0-9_])_(?=\S)([^_\n]*?\S)_(?![A-Za-z0-9_])/', '<em>$1</em>', $text) ?? $text;
        // 高亮
        $text = preg_replace('/==(?=\S)([\s\S]*?\S)==/', '<mark class="md-mark">$1</mark>', $text) ?? $text;

        return strtr($text, $placeholders);
    }

    /**
     * 仅放行安全协议的链接，不安全时返回空串。
     * GithubService 的 README 改写也走这条规则，两处输出必须共用同一份协议白名单。
     */
    public static function safeUrl(string $url): string {
        $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
        // 去掉可能被利用的控制字符
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? $url;

        if (preg_match('#^(https?://|mailto:)#i', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    private static function escapeAttr(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function splitTableRow(string $row): array {
        $row = trim($row);
        $row = preg_replace('/^\|/', '', $row) ?? $row;
        $row = preg_replace('/\|$/', '', $row) ?? $row;
        return array_map('trim', explode('|', $row));
    }

    private static function alignAttr(array $aligns, int $index): string {
        $align = $aligns[$index] ?? '';
        if (preg_match('/^:.*:$/', $align)) {
            return ' style="text-align:center"';
        }
        if (preg_match('/:$/', $align)) {
            return ' style="text-align:right"';
        }
        return '';
    }
}
