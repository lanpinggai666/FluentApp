<?php
/**
 * 蓝屏钙软件导航网 - 主页控制器
 */

class HomeController {
    public function index(): void {
        $categorySlug = $_GET['cat'] ?? null;
        $search = trim($_GET['q'] ?? '');
        $sort = $_GET['sort'] ?? 'random';
        $viewMode = $_COOKIE['view_mode'] ?? 'grid';
        if (isset($_GET['view'])) {
            $viewMode = in_array($_GET['view'], ['grid', 'list']) ? $_GET['view'] : 'grid';
            setcookie('view_mode', $viewMode, time() + 86400 * 365, '/');
        }

        $currentCategory = null;
        $categoryId = null;
        if (!empty($categorySlug)) {
            $currentCategory = Category::findBySlug($categorySlug);
            if ($currentCategory) {
                $categoryId = (int) $currentCategory['id'];
            }
        }

        $categories = Category::getAllWithCounts();
        $notice = Notice::getActiveFirst();

        // 软件列表检索 (默认随机抽取，保持页面探索乐趣)
        $softwares = Software::getList([
            'category_id' => $categoryId,
            'search' => $search,
            'sort' => $sort,
            'limit' => 36
        ]);

        $totalCount = Software::count([
            'category_id' => $categoryId,
            'search' => $search
        ]);

        $siteName = Setting::get('site_name', '蓝屏钙软件导航');
        $siteDesc = Setting::get('site_description', '汇聚纯净权威的软件下载渠道');

        $title = $siteName . ' - ' . Setting::get('site_subtitle', '纯净高效的软件下载导航');
        if ($currentCategory) {
            $title = $currentCategory['name'] . ' - ' . $siteName;
        } elseif (!empty($search)) {
            $title = "搜索 '{$search}' - " . $siteName;
        }

        View::render('home.index', [
            'title' => $title,
            'siteName' => $siteName,
            'siteDesc' => $siteDesc,
            'categories' => $categories,
            'currentCategory' => $currentCategory,
            'softwares' => $softwares,
            'totalCount' => $totalCount,
            'notice' => $notice,
            'search' => $search,
            'sort' => $sort,
            'viewMode' => $viewMode,
        ]);
    }
}
