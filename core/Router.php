<?php
/**
 * 蓝屏钙软件导航网 - 轻量路由调度器
 */

class Router {
    /**
     * 免 CSRF 校验的写路由。
     * 目前所有写入口都是本站自己的表单或同源 AJAX，因此应当为空；
     * 将来接入外部回调（webhook 等）时在此登记，并在注释里写明理由。
     */
    private const CSRF_EXEMPT_ROUTES = [];

    private static string $resolvedRoute = '';

    /**
     * 本次请求最终解析到的路由（如 admin/users、software/view、home/index）。
     * 伪静态形态下 $_GET['r'] 是空的，任何「我当前在哪一页」的判断都要用它。
     */
    public static function currentRoute(): string
    {
        return self::$resolvedRoute;
    }

    /** 当前路由是否命中某个控制器（比较时忽略大小写与动作段，如 in('user') 匹配 user/profile） */
    public static function routeIs(string $prefix, bool $wholeRoute = false): bool
    {
        $route = strtolower(self::$resolvedRoute);
        $prefix = strtolower(trim($prefix, '/'));
        return $wholeRoute ? $route === $prefix : ($route === $prefix || str_starts_with($route, $prefix . '/'));
    }

    /**
     * 路径第二段是不是该控制器的一个动作 —— 别名（把 <slug> 塞进查询串）只能在它不是动作时才让路。
     *
     * 'view' 是例外：没有任何链接会去 GET /software/view，而 slug 恰好叫 view 的软件是有可能的，
     * 所以这一段继续按 slug 解释。方法名的变形规则与 dispatch 里那套保持一致，否则判断会失真。
     */
    private static function isAction(string $prefix, string $segment): bool
    {
        if (strtolower($segment) === 'view') {
            return false;
        }
        $class = ucfirst($prefix) . 'Controller';
        $file = CONTROLLER_PATH . '/' . $class . '.php';
        if (!is_file($file)) {
            return false;
        }
        require_once $file;
        if (!class_exists($class)) {
            return false;
        }
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment))));
        return method_exists($class, $method) || method_exists($class, $segment);
    }

    public static function dispatch(): void {
        // 优先从 GET 参数 r 获取路由，例如 ?r=software/view&slug=xxx
        $route = $_GET['r'] ?? '';

        // 若没有 r 参数，尝试从 PATH_INFO 解析
        if (empty($route)) {
            $pathInfo = $_SERVER['PATH_INFO'] ?? '';
            if (empty($pathInfo) && isset($_SERVER['REQUEST_URI'])) {
                $uriParts = explode('?', $_SERVER['REQUEST_URI'], 2);
                $path = trim($uriParts[0], '/');
                if (!empty($path) && $path !== 'index.php') {
                    $pathInfo = '/' . $path;
                }
            }

            if (!empty($pathInfo)) {
                $trimmed = trim($pathInfo, '/');
                $parts = explode('/', $trimmed);
                // 别名只在「这一段确实不是控制器里的动作」时生效。
                // 早先 /submit 别名会把 /submit/save 一起吞成提交页，/software/<slug> 会把
                // /software/addTag 当成一个叫 addTag 的 slug —— 前端 fetch 拿回来的是 HTML，
                // JSON 解析失败，用户只看到一句「网络通信异常，请稍后重试」。
                if ($parts[0] === 'software' && isset($parts[1]) && !self::isAction('software', $parts[1])) {
                    $_GET['slug'] = urldecode($parts[1]);
                    $route = 'software/view';
                } elseif ($parts[0] === 'category' && isset($parts[1]) && !self::isAction('category', $parts[1])) {
                    $_GET['cat'] = urldecode($parts[1]);
                    $route = 'home/index';
                } elseif ($parts[0] === 'admin') {
                    $action = $parts[1] ?? 'dashboard';
                    $route = 'admin/' . $action;
                } else {
                    // 不再给 submit 单开别名：/submit 走通用解析就是 SubmitController::index，
                    // 而 /submit/save 必须留在通用解析里才不会被吃掉
                    $route = $trimmed;
                }
            }
        }

        $route = trim($route, '/');
        if (empty($route)) {
            $route = 'home/index';
        }

        // /api/v1/<action> 与 ?r=api/v1/<action> 都归到 ApiadminController::<action>()。
        // 只吃 api/v1/ 这一段：api/ 前缀已经被前端自己的 ApiController（搜索、图标代理）占用，
        // 一把改写过去会把站点首页的实时搜索打断。
        if ($route === 'api/v1' || $route === 'api/v1/') {
            $route = 'apiadmin/ping';
        } elseif (str_starts_with($route, 'api/v1/')) {
            $route = 'apiadmin/' . substr($route, 7);
        }

        // 记住「这次请求到底是哪个路由」：伪静态下 $_GET['r'] 是空的，
        // 而侧栏高亮、顶栏形态、公开页白名单都要知道当前路由，只能从这里取
        // （见 Router::currentRoute()，别再回去读 $_GET['r']）。
        self::$resolvedRoute = $route;

        // 状态变更请求统一在此要求 CSRF 凭据，控制器不必各写一遍；
        // 漏写校验的端点（此前后台 settings/saveSoftware 等 6 处）也就不会再成为例外
        //
        // 唯一的例外是开放 API（apiadmin/*）：它不使用会话 cookie，App 端拿不到本站的 CSRF token，
        // 改由「后台生成的密钥 + 管理员用户名 + 口令」三件套鉴权并记调用日志（见 core/ApiAuth.php）。
        // 少了这道豁免，API 的每个写请求都会撞在 CSRF 上；少了 ApiAuth，这些端点就是裸奔。
        if (self::isStateChangingMethod() && !self::isOpenApiRoute($route)
            && !in_array($route, self::CSRF_EXEMPT_ROUTES, true)) {
            if (!Auth::verifyRequestCsrf()) {
                self::rejectCsrf();
                return;
            }
        }

        $parts = explode('/', $route);
        $controllerName = ucfirst($parts[0] ?? 'Home') . 'Controller';
        $actionName = $parts[1] ?? 'index';

        $controllerFile = CONTROLLER_PATH . '/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) {
            http_response_code(404);
            View::render('home.404', ['title' => '页面未找到 - 蓝屏钙软件导航'], true);
            return;
        }

        require_once $controllerFile;
        if (!class_exists($controllerName)) {
            http_response_code(404);
            View::render('home.404', ['title' => '页面未找到'], true);
            return;
        }

        $controller = new $controllerName();
        $actionMethod = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $actionName))));
        if (!method_exists($controller, $actionMethod)) {
            if (method_exists($controller, $actionName)) {
                $actionMethod = $actionName;
            } elseif (method_exists($controller, 'notFound')) {
                // API 类控制器自己把「没有这个端点」表达成 JSON，而不是回一段 HTML 404
                $controller->notFound($actionName);
                return;
            } else {
                http_response_code(404);
                View::render('home.404', ['title' => '操作不存在'], true);
                return;
            }
        }

        try {
            $controller->$actionMethod();
        } catch (Throwable $e) {
            error_log(sprintf(
                '[%s] %s in %s:%d%s%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                PHP_EOL,
                $e->getTraceAsString()
            ));

            if (APP_DEBUG) {
                echo "<div style='padding:20px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;margin:20px;font-family:sans-serif;'>";
                echo "<h3>系统错误</h3>";
                echo "<p><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
                echo "<pre style='font-size:12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                echo "</div>";
            } else {
                http_response_code(500);
                echo "服务器异常，请稍后再试。";
            }
        }
    }

    private static function isStateChangingMethod(): bool {
        return in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /** 开放 API 的路由前缀，鉴权方式与网页后台完全不同，见 core/ApiAuth */
    private static function isOpenApiRoute(string $route): bool {
        return $route === 'apiadmin' || str_starts_with($route, 'apiadmin/');
    }

    /** 拒绝未通过 CSRF 校验的请求：AJAX 回 JSON，页面提交回一段极简提示。 */
    private static function rejectCsrf(): void {
        error_log(sprintf(
            '[CSRF] 拒绝 %s %s (r=%s)',
            $_SERVER['REQUEST_METHOD'] ?? '?',
            $_SERVER['REQUEST_URI'] ?? '?',
            self::$resolvedRoute
        ));

        $wantsJson = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || !empty($_POST['is_ajax']);

        if ($wantsJson) {
            $msg = '会话校验未通过，请刷新页面后重试';
            View::json(['success' => false, 'error' => $msg, 'message' => $msg], 400);
            return;
        }

        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>请求已被拒绝</title>'
            . '<p style="font:14px/1.6 system-ui,sans-serif;padding:24px">页面会话已过期，或请求来源不被信任。请返回上一页重新提交。</p>';
    }
}
