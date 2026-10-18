<?php
/**
 * 输出侧 XSS：站内 Markdown、GitHub README 渲染、属性/JSON 转义、后台自定义 HTML
 */

group('04 站内 Markdown 渲染');

$md = Markdown::render("# 标题\n\n正文 **加粗** 与 [链接](https://a.example.com) 和 `code`\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n> 引用");
ok('标题渲染', str_contains($md, '<h1'));
ok('链接保留安全协议', str_contains($md, 'href="https://a.example.com"'));
ok('表格渲染', str_contains($md, '<table'));
ok('引用渲染', str_contains($md, '<blockquote'));
foreach (['[x](javascript:alert(1))', '[x](JaVaScRiPt:alert(1))', '[x](data:text/html;base64,PHN2Zz4=)',
          '[x](vbscript:msgbox(1))', '[x](java&#115;cript:alert(1))', '[x](FILE:///etc/passwd)'] as $payload) {
    $out = Markdown::render($payload);
    $probe = strtolower($out);
    ok('Markdown 挡掉 ' . mb_substr($payload, 0, 26), !str_contains($probe, 'javascript:')
        && !str_contains($probe, 'vbscript:') && !str_contains($probe, 'data:text/html') && !str_contains($probe, 'file:'));
}
ok('原始 HTML 被转义', !str_contains(Markdown::render('<script>alert(1)</script>'), '<script>alert'));

group('05 GitHub README 渲染');

$renderReadme = function (string $md): string {
    $method = new ReflectionMethod('GithubService', 'simpleMarkdownToHtml');
    $method->setAccessible(true);
    return $method->invoke(null, $md);
};
$out = $renderReadme('[点我](javascript:alert(document.cookie))');
ok('README 链接不再带 javascript:', !stripos($out, 'javascript:'));
ok('README 恶意链接回落 #', str_contains($out, 'href="#"'));
$out = $renderReadme('![shot](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)');
ok('README 图片 data: 伪协议被挡', !str_contains($out, 'data:text/html'));
// 占位图必须是根绝对路径：详情页在 /software/<slug> 下，相对 assets/... 会被解析成 /software/assets/...
ok('README 恶意图片回落占位图', str_contains($out, 'src="/assets/images/default-app.svg"'));
$out = $renderReadme('[x](java&#115;cript:alert(1))');
ok('README 实体混淆伪协议被挡', !str_contains(strtolower($out), 'ipt:alert'));
$out = $renderReadme('[官网](https://example.com/dl?a=1&b=2)');
ok('README 正常链接保留', str_contains($out, 'href="https://example.com/dl?a=1&amp;b=2"'));
ok('README 样式类未被破坏', str_contains($out, 'class="readme-link"'));
$out = $renderReadme("<script>alert(1)</script>\n\n# 标题\n\n- 项目\n\n![截图](img/a.png)\n\n[文档](docs/start.md)");
ok('README 原始 HTML 被转义', !str_contains($out, '<script>alert'));
ok('README 标题与列表样式保留', str_contains($out, 'readme-h1') && str_contains($out, 'readme-li'));
ok('README 相对链接回落 #', str_contains($out, 'href="#"'));

group('06 属性与 JSON 输出');

$j = View::jsArg("x'alert(1)'y");
ok("jsArg 转义单引号", str_contains($j, '\u0027') && !str_contains($j, "'"));
ok('jsArg 阻止提前闭合 script', !str_contains(View::jsArg('</script><b>'), '</script>'));
ok('jsArg 数组正常序列化', View::jsArg(['a', 'b']) === '["a","b"]');

$s = View::sanitizeHtml('<script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">x</a><b onclick=\'alert(4)\'>粗体</b>');
ok('sanitizeHtml 剥离 script', !str_contains($s, '<script'));
ok('sanitizeHtml 丢弃无引号事件属性', !str_contains($s, 'onerror') && !str_contains($s, 'onclick'));
ok('sanitizeHtml 丢弃伪协议属性', !stripos($s, 'javascript:'));
ok('sanitizeHtml 保留合法标签文字', str_contains($s, '<b>粗体</b>'));
$entity = View::sanitizeHtml('<a href="java&#115;cript:alert(1)">x</a>');
ok('sanitizeHtml 挡住实体混淆伪协议', !stripos($entity, 'ipt:alert'));
$kept = View::sanitizeHtml('<span>© 2026</span> · <a href="https://beian.miit.gov.cn">备案</a>');
ok('sanitizeHtml 完整保留正常 HTML', $kept === '<span>© 2026</span> · <a href="https://beian.miit.gov.cn">备案</a>');
ok('excerpt 返回纯文本', !str_contains(Markdown::toPlainText("# 标题\n\n`代码` 与 [链接](https://a.com)"), '<'));

group('07 防退化静态检查');

$sources = [
    'public/assets/js/app.js' => 'public/assets/js/app.js',
];
$app = (string) file_get_contents(BASE_PATH . '/public/assets/js/app.js');
ok('搜索下拉转义 icon_url', str_contains($app, 'escapeHtml(item.icon_url)'));
ok('搜索下拉转义 url', str_contains($app, 'escapeHtml(item.url)'));

$footer = (string) file_get_contents(BASE_PATH . '/views/layout/footer.php');
ok('页脚自定义 HTML 走 sanitizeHtml', str_contains($footer, 'View::sanitizeHtml($customFooter)'));

$header = (string) file_get_contents(BASE_PATH . '/views/layout/header.php');
ok('头部不再原样 echo 未定义的 extraHead', !str_contains($header, '$extraHead'));

$views = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $views[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
    }
}
$rawUrlHref = [];
foreach ($views as $file => $src) {
    if (preg_match_all('/href="<\?= (?!Url::href|View::e|`\?r=|\$detailUrl)[^>]*(_url|resource_url|StoreUrl|\[.url.\])[^>]*\?>"/', $src, $m)) {
        $rawUrlHref[] = basename($file) . ':' . implode(',', $m[0]);
    }
}
ok('视图里不存在未净化的 URL 型 href', $rawUrlHref === []);
if ($rawUrlHref !== []) {
    echo "  发现: " . implode('; ', $rawUrlHref) . "\n";
}
