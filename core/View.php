<?php
/**
 * 蓝屏钙软件导航网 - 视图渲染引擎
 */

class View {
    public static function render(string $template, array $data = [], bool $useLayout = true): void {
        extract($data);

        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $template) . '.php';
        if (!file_exists($viewFile)) {
            throw new RuntimeException("视图模板不存在: {$template}");
        }

        if ($useLayout) {
            require VIEW_PATH . '/layout/header.php';
            require $viewFile;
            require VIEW_PATH . '/layout/footer.php';
        } else {
            require $viewFile;
        }
    }

    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function e(?string $str): string {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 渲染用户提交的 Markdown 为安全 HTML。
     * 所有内容已在 Markdown 内部完成转义，此处不可再次转义。
     */
    public static function markdown(?string $markdown): string {
        return Markdown::render($markdown);
    }

    /**
     * 由 Markdown 生成纯文本摘要，用于 SEO 描述与列表预览。
     */
    public static function excerpt(?string $markdown, int $limit = 160): string {
        return Markdown::toPlainText($markdown, $limit);
    }

    public static function firstParagraph(?string $markdown, int $limit = 160): string {
        return Markdown::firstParagraph($markdown, $limit);
    }

    public static function icon(string $name, string $className = 'fluent-icon'): string {
        return FluentIcon::render($name, $className);
    }

    /**
     * 内联事件属性里传参用。单引号包裹的属性中裸 ' 会撑破属性，所以必须连引号一起转义。
     */
    public static function jsArg($value): string {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * 后台允许填写 HTML 的字段（如页脚）输出前的收敛：限制标签集、丢弃事件属性与伪协议。
     *
     * 这不是完整的安全 HTML 解析器，只是把最常见的脚本注入面堵住；真正的信任边界仍然是
     * 后台鉴权与 CSRF 校验。
     */
    public static function sanitizeHtml(?string $html): string {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, '<a><br><span><div><p><small><strong><em><b><i><u><ul><ol><li><img><section><nav>');
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace_callback(
            '#\s(href|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i',
            static function (array $m): string {
                $value = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? ''));
                $probe = strtolower((string) preg_replace('/\s+/', '', html_entity_decode($value, ENT_QUOTES, 'UTF-8')));
                if (str_starts_with($probe, 'javascript:') || str_starts_with($probe, 'vbscript:') || str_starts_with($probe, 'data:')) {
                    return '';
                }
                return ' ' . $m[1] . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        ) ?? $html;

        return $html;
    }
}
