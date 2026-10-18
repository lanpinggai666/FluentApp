<?php
/**
 * 性能与正确性：分页总数与结果集一致、浏览量按会话去重、README 相对资源解析到仓库
 */

group('35 列表与计数条件一致');

// 造一条只有 developer 字段能命中的记录：断言不依赖样本数据里恰好有没有开发者名
$marker = 'DevOnlyMarker' . bin2hex(random_bytes(4));
$category = (int) Sandbox::$db->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
Sandbox::$db->prepare("INSERT INTO softwares (name, slug, icon_url, official_url, category_id, developer, status, summary)
    VALUES (?, ?, ?, ?, ?, ?, 'published', ?)")->execute([
    '仅开发者命中的样本', strtolower($marker), 'assets/images/default-app.svg', 'https://example.com',
    $category, $marker . ' 工作室', '用于验证搜索字段覆盖']);
$list = Software::getList(['search' => $marker, 'limit' => 100]);
$count = Software::count(['search' => $marker]);
ok("developer 命中词的回包数与总数一致（" . count($list) . '/' . $count . '）', $count === 1 && count($list) === 1);
Sandbox::$db->prepare('DELETE FROM softwares WHERE slug = ?')->execute([strtolower($marker)]);

$featured = (int) Sandbox::$db->query('SELECT COUNT(*) FROM softwares WHERE status=\'published\' AND is_featured=1')->fetchColumn();
ok('featured_only 的总数与结果集一致', Software::count(['featured_only' => true, 'search' => '']) === $featured
    && count(Software::getList(['featured_only' => true, 'limit' => 200])) === $featured);

// 制造一个必然分页不一致的场景：搜索命中数大于页大小
$total = Software::count([]);
$firstPage = Software::getList(['limit' => 5]);
ok('普通分页首页不超过页大小且总数不小于结果数', count($firstPage) <= 5 && $total >= count($firstPage));
$kw = (string) Sandbox::$db->query("SELECT name FROM softwares WHERE status='published' LIMIT 1")->fetchColumn();
$kwWord = mb_substr($kw, 0, 4);
ok("同关键词下 count 与 getList 上限内一致", Software::count(['search' => $kwWord]) === count(Software::getList(['search' => $kwWord, 'limit' => 200])));

group('36 浏览量按会话去重');

$slug = 'vscode';
$before = (int) Sandbox::$db->query("SELECT views_count FROM softwares WHERE slug='" . $slug . "'")->fetchColumn();
$jarA = Sandbox::newJar();
Sandbox::http('/?r=software/view&slug=' . $slug, [], [], $jarA);
Sandbox::http('/?r=software/view&slug=' . $slug, [], [], $jarA);
Sandbox::http('/?r=software/view&slug=' . $slug, [], [], $jarA);
$afterSameSession = (int) Sandbox::$db->query("SELECT views_count FROM softwares WHERE slug='" . $slug . "'")->fetchColumn();
ok('同一会话连访 3 次只 +1（原为每次 GET 都写一次）', $afterSameSession === $before + 1);

$jarB = Sandbox::newJar();
Sandbox::http('/?r=software/view&slug=' . $slug, [], [], $jarB);
ok('换会话仍然计数', (int) Sandbox::$db->query("SELECT views_count FROM softwares WHERE slug='" . $slug . "'")->fetchColumn() === $afterSameSession + 1);

// 详情页仍然正常渲染，没因为计数改动出错
[$code, $body] = Sandbox::http('/?r=software/view&slug=' . $slug, [], [], $jarB);
ok('详情页渲染无异常', $code === 200 && !preg_match('/系统错误|Fatal error|Deprecated:/', $body));

group('37 README 相对资源解析');

$render = function (string $md): string {
    $m = new ReflectionMethod('GithubService', 'simpleMarkdownToHtml');
    $m->setAccessible(true);
    return $m->invoke(null, $md, 'microsoft/vscode');
};

$out = $render("![界面](docs/screen.png)");
ok('相对图片指向 raw 仓库地址', str_contains($out, 'src="https://raw.githubusercontent.com/microsoft/vscode/HEAD/docs/screen.png"'));
$out = $render("![同目录](./shot.png)");
ok('./ 前缀被正确剥离', str_contains($out, 'src="https://raw.githubusercontent.com/microsoft/vscode/HEAD/shot.png"'));
$out = $render("[指南](docs/guide.md#install)");
ok('相对链接指向仓库 blob 地址并保留锚点', str_contains($out, 'href="https://github.com/microsoft/vscode/blob/HEAD/docs/guide.md#install"'));
$out = $render("![根路径](/assets/logo.png)");
ok('根相对路径也挂到仓库上', str_contains($out, 'src="https://raw.githubusercontent.com/microsoft/vscode/HEAD/assets/logo.png"'));
$out = $render("![外链](https://img.shields.io/badge/a-b)");
ok('绝对地址不受影响', str_contains($out, 'src="https://img.shields.io/badge/a-b"'));
$out = $render("[上跳](../../etc/passwd)");
ok('含 .. 的路径不被拼接（交回白名单处理）', !str_contains($out, 'etc/passwd" ') || str_contains($out, 'href="#"'));
$out = $render("[伪协议](javascript:alert(1))");
ok('解析路径不绕过协议白名单', !str_contains($out, 'javascript:'));
$out = (new ReflectionMethod('GithubService', 'simpleMarkdownToHtml'))->invoke(null, "![相对](a.png)");
// 占位图要带根斜杠：不带的话在 /software/<slug> 页面会解析成 /software/assets/...（子目录部署同理）
ok('不传仓库时回落到站内占位图', str_contains($out, 'src="/assets/images/default-app.svg"'));
