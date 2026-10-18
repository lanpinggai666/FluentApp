<?php
/**
 * 蓝屏钙软件导航网 - 异步 API 控制器
 */

class ApiController {
    public function search(): void {
        $q = trim($_GET['q'] ?? '');
        if (empty($q)) {
            View::json(['results' => []]);
            return;
        }

        $list = Software::getList([
            'search' => $q,
            'limit' => 8
        ]);

        $results = array_map(function($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'slug' => $item['slug'],
                'icon_url' => $item['icon_url'],
                'excerpt' => View::excerpt($item['special_notes'] ?? '', 80),
                'category_name' => $item['category_name'],
                'url' => Url::detail($item['slug'])
            ];
        }, $list);

        View::json(['results' => $results]);
    }

    public function release(): void {
        $softwareId = (int) ($_GET['id'] ?? 0);
        $software = Software::find($softwareId);

        $githubUrl = $software['github_url'] ?? '';
        if (empty($githubUrl) && !empty($software['official_url']) && str_contains($software['official_url'], 'github.com')) {
            $githubUrl = $software['official_url'];
        }

        if (!$software || empty($githubUrl)) {
            View::json(['success' => false, 'message' => '该软件未配置 GitHub 仓库']);
            return;
        }

        $release = GithubService::getLatestRelease($githubUrl);

        if (!$release) {
            View::json([
                'success' => false, 
                'message' => '暂未解析到匹配的 Release 资源，您可以前往官方 GitHub Releases 页面手动查看',
                'github_url' => $software['github_url']
            ]);
            return;
        }

        View::json([
            'success' => true,
            'release' => $release
        ]);
    }

    public function ping(): void {
        View::json(['pong' => true, 'timestamp' => microtime(true)]);
    }

    public function identifyMirror(): void {
        $url = trim($_GET['url'] ?? ($_POST['url'] ?? ''));
        if (empty($url)) {
            View::json(['success' => false, 'message' => '请输入加速示例链接']);
            return;
        }

        // 加速定位由运维先选好传进来：系统只负责学映射格式，不再替人选类型
        $type = trim((string) ($_GET['type'] ?? ($_POST['type'] ?? '')));
        $original = trim((string) ($_GET['original_url'] ?? ($_POST['original_url'] ?? '')));
        $res = GithubService::learnMirrorFromSample($url, $type, $original);
        View::json($res);
    }

    /**
     * 服务端镜像测速。
     * 浏览器端 <img> 探测会把 403/404 误判为「可用」，故以服务端真实请求为准。
     */
    public function probeMirror(): void {
        // 模板地址可指定任意主机，服务端会真的发起请求 —— 仅允许后台调用
        Auth::requireAuth();

        $template = trim($_GET['template'] ?? ($_POST['template'] ?? ''));
        $probeUrl = trim($_GET['url'] ?? ($_POST['url'] ?? ''));

        if ($template === '') {
            View::json(['success' => false, 'message' => '缺少镜像模板参数']);
            return;
        }

        if ($template !== 'direct' && !preg_match('#^https?://#i', $template)) {
            View::json(['success' => false, 'message' => '镜像模板格式不合法']);
            return;
        }

        if ($probeUrl !== '' && !preg_match('#^https?://(github\.com|[a-z0-9.-]*githubusercontent\.com|codeload\.github\.com|api\.github\.com|gist\.githubusercontent\.com)/#i', $probeUrl)) {
            View::json(['success' => false, 'message' => '仅允许探测 GitHub 官方域名下的资源']);
            return;
        }

        $result = GithubService::probeMirror($template, $probeUrl);
        $result['style'] = GithubService::mirrorStyle($template);
        View::json($result);
    }

    /**
     * 同源图标代理：把外部软件图标以同源身份回传给浏览器。
     * 跨域图像绘制到 canvas 后像素会被污染而无法读取，故取色统一经由本端点。
     */
    public function iconProxy(): void {
        $url = trim($_GET['url'] ?? '');
        if ($url === '') {
            http_response_code(400);
            exit;
        }

        // 必须是解析到公网 IPv4 的 http/https 地址：挡掉内网/回环/链路本地（云元数据）目标
        $routableIp = null;
        if (!Url::publiclyRoutable($url, $routableIp)) {
            http_response_code(403);
            exit;
        }

        // 证书校验由 SafeHttp 统一开启（Windows 下走系统证书库），此处不再做「不验证」重试：
        // 代理回来的字节会以本站同源身份渲染成 SVG，放宽验证等于给中间人留出注入面
        $res = SafeHttp::get($url, [
            CURLOPT_MAXFILESIZE => 3 * 1024 * 1024,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SoftwareNav-IconProxy/1.0)',
        ], 8, 3);

        $data = $res['body'];
        $status = $res['status'];
        $ctype = $res['content_type'];

        if ($data === '' || $status >= 400) {
            http_response_code(404);
            exit;
        }

        // 类型嗅探：仅放行图片（含以 XML 文本下发的 SVG）
        $head = substr($data, 0, 300);
        $isSvg = str_contains($head, '<svg');
        $isRaster = str_starts_with($head, "\x89PNG") || str_starts_with($head, "\xFF\xD8") || str_starts_with($head, 'GIF8');
        if (!$isSvg && !$isRaster && !preg_match('#^image/#i', $ctype)) {
            http_response_code(415);
            exit;
        }

        header('Content-Type: ' . ($isSvg ? 'image/svg+xml' : ($ctype ?: 'application/octet-stream')));
        header('Cache-Control: public, max-age=604800');
        header('X-Content-Type-Options: nosniff');
        echo $data;
    }

    /**
     * 前端从图标实际取样出的主色回存，之后详情页/首页由服务端直出正确氛围色。
     */
    public function saveIconColor(): void {
        $slug = trim($_POST['slug'] ?? '');
        $color = trim($_POST['color'] ?? '');
        if ($slug === '' || !preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            View::json(['success' => false, 'message' => '参数不合法']);
            return;
        }

        try {
            Software::updateThemeColor($slug, $color);
            View::json(['success' => true]);
        } catch (Throwable $e) {
            View::json(['success' => false, 'message' => '保存失败']);
        }
    }

    /**
     * 一次性测速所有启用中的镜像，服务端并发请求并按耗时排序。
     */
    public function probeAllMirrors(): void {
        Auth::requireAuth();

        $probeUrl = trim($_GET['url'] ?? '');
        if ($probeUrl !== '' && !preg_match('#^https?://#i', $probeUrl)) {
            $probeUrl = '';
        }

        $results = [];
        foreach (GithubMirror::getActive() as $mirror) {
            $template = (string)($mirror['url_template'] ?? '');
            if ($template === '') {
                continue;
            }
            $probe = GithubService::probeMirror($template, $probeUrl);
            $results[] = [
                'id' => (int) $mirror['id'],
                'name' => (string) $mirror['name'],
                'template' => $template,
                'type' => (string) $mirror['type'],
                'url_template' => $probe['url'],
            ] + $probe;
        }

        usort($results, static function ($a, $b) {
            if ($a['success'] !== $b['success']) {
                return $a['success'] ? -1 : 1;
            }
            return $a['time'] <=> $b['time'];
        });

        View::json(['success' => true, 'results' => $results]);
    }
}
