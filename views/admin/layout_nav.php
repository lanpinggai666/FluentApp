<?php
/**
 * 蓝屏钙软件导航网 - 后台与用户中心通用导航布局
 */
$currentUser = Auth::currentUser();
if (!$currentUser && !empty($_SESSION['admin_user'])) {
    $currentUser = Database::fetch("SELECT * FROM users WHERE role = 'admin' LIMIT 1") ?: [
        'id' => 0,
        'username' => $_SESSION['admin_user']['username'] ?? '管理员',
        'email' => 'admin@localhost',
        'role' => 'admin',
        'avatar_url' => ''
    ];
}

// 当前路由要从 Router 取：伪静态形态（/admin/users）下 $_GET['r'] 是空的，
// 拿 $_GET['r'] 判断会让侧栏永远停在「仪表盘」，和实际页面不同步
$currentRoute = Router::currentRoute() ?: 'admin/dashboard';
$isAdmin = (Auth::check() || ($currentUser['role'] ?? '') === 'admin');

// 个人中心统计
$uid = (int)($currentUser['id'] ?? 0);
$userStats = $uid > 0 ? User::getUserStats($uid) : ['softwares_total' => 0, 'reports_pending' => 0, 'comments_count' => 0];
$unreadNoticeCount = $uid > 0 ? Notification::getUnreadCount($uid) : 0;
$userBadges = !empty($currentUser) ? User::getBadges($currentUser) : [];

// 全站管理待办统计 (仅管理员查询)
$pendingSoftCount = $isAdmin ? (int) (Database::fetch("SELECT COUNT(*) as c FROM softwares WHERE status = 'pending'")['c'] ?? 0) : 0;
$pendingReportCount = $isAdmin ? Report::countPending() : 0;
$pendingCorrCount = $isAdmin ? SoftwareCorrection::countPending() : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= Auth::csrfToken() ?>">
  <title><?= View::e($title) ?></title>
  <?php require VIEW_PATH . '/partials/stylesheets.php'; ?>
  <link rel="stylesheet" href="<?= Url::basePath() ?>assets/css/components.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/components.css') ?>">
  <link rel="stylesheet" href="<?= Url::basePath() ?>assets/vendor/easymde/easymde.min.css?v=<?= filemtime(BASE_PATH . '/public/assets/vendor/easymde/easymde.min.css') ?>">
  <script>
    const saved = localStorage.getItem('theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
  </script>
</head>
<body style="background-color: var(--bg-page);">

  <header class="navbar">
    <div class="navbar-container" style="max-width: 1560px; margin: 0 auto; padding: 0 32px;">
      <a href="<?= Url::route($isAdmin ? 'admin/dashboard' : 'user/dashboard') ?>" class="brand">
        <span class="brand-icon"><?= FluentIcon::render($isAdmin ? 'settings' : 'person') ?></span>
        <span class="admin-brand-title" style="font-weight: 700; font-size: 1.0625rem;"><?= View::e(Setting::get('site_name', '蓝屏钙软件导航')) ?> · <?= $isAdmin ? '管理与控制中心' : '用户中心' ?></span>
      </a>

      <div class="nav-actions" style="gap: 12px;">
        <button class="btn-icon" id="theme-toggle" title="切换深色/浅色模式">
          <?= FluentIcon::render('sun') ?>
        </button>
        <a href="<?= Url::route('home') ?>" target="_blank" class="btn-store-secondary" style="font-size: 0.8125rem; height: 34px; padding: 0 14px;">
          <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
          <span>前台首页</span>
        </a>
        <a href="<?= Url::route('auth/logout') ?>" class="btn-store-secondary" style="font-size: 0.8125rem; height: 34px; padding: 0 14px; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
          退出登录
        </a>
      </div>
    </div>
  </header>

  <div class="admin-layout">
    <!-- 左侧菜单栏 -->
    <aside class="admin-sidebar">
      <div class="sidebar-card">
        <!-- 用户信息卡片 -->
        <?php if (!empty($currentUser)): ?>
          <div style="padding: 10px 12px 16px; border-bottom: 1px solid var(--border-subtle); margin-bottom: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <?php if (!empty($currentUser['avatar_url'])): ?>
                <img src="<?= View::e($currentUser['avatar_url']) ?>" alt="<?= View::e($currentUser['username']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
              <?php else: ?>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.125rem;">
                  <?= mb_substr($currentUser['username'] ?? '用', 0, 1, 'UTF-8') ?>
                </div>
              <?php endif; ?>
              <div style="min-width: 0; flex: 1;">
                <div style="font-weight: 700; font-size: 0.9375rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <?= View::e($currentUser['username']) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <?= View::e($currentUser['email']) ?>
                </div>
              </div>
            </div>
            <?php if (!empty($userBadges)): ?>
              <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 10px;">
                <?php foreach ($userBadges as $badge): ?>
                  <span style="font-size: 0.6875rem; padding: 2px 6px; border-radius: 4px; background: rgba(0, 103, 184, 0.12); color: var(--primary); font-weight: 600;">
                    <?= View::e($badge) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- 个人中心菜单 -->
        <div class="sidebar-title">个人管理</div>
        <ul class="category-nav">
          <li>
            <a href="<?= Url::route('user/dashboard') ?>" class="category-link <?= $currentRoute === 'user/dashboard' ? 'active' : '' ?>">
              <div class="category-link-left">
                <?= FluentIcon::render('speed', 'fluent-icon-sm') ?>
                <span>个人总览</span>
              </div>
            </a>
          </li>
          <li>
            <a href="<?= Url::route('user/profile') ?>" class="category-link <?= $currentRoute === 'user/profile' ? 'active' : '' ?>">
              <div class="category-link-left">
                <?= FluentIcon::render('person', 'fluent-icon-sm') ?>
                <span>账号与资料设置</span>
              </div>
            </a>
          </li>
          <li>
            <a href="<?= Url::route('user/softwares') ?>" class="category-link <?= $currentRoute === 'user/softwares' || $currentRoute === 'user/editSoftware' ? 'active' : '' ?>">
              <div class="category-link-left">
                <?= FluentIcon::render('desktop', 'fluent-icon-sm') ?>
                <span>我管理的软件</span>
              </div>
              <?php if (($userStats['softwares_total'] ?? 0) > 0): ?>
                <span class="badge" style="background:var(--bg-hover);color:var(--text-primary);"><?= $userStats['softwares_total'] ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="<?= Url::route('user/reports') ?>" class="category-link <?= $currentRoute === 'user/reports' ? 'active' : '' ?>">
              <div class="category-link-left">
                <?= FluentIcon::render('flag', 'fluent-icon-sm') ?>
                <span>软件举报处理</span>
              </div>
              <?php if (($userStats['reports_pending'] ?? 0) > 0): ?>
                <span class="badge" style="background:#fef3c7;color:#b45309;font-weight:700;"><?= $userStats['reports_pending'] ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="<?= Url::route('user/comments') ?>" class="category-link <?= $currentRoute === 'user/comments' ? 'active' : '' ?>">
              <div class="category-link-left">
                <?= FluentIcon::render('comment', 'fluent-icon-sm') ?>
                <span>我的评价</span>
              </div>
              <?php if (($userStats['comments_count'] ?? 0) > 0): ?>
                <span class="badge" style="background:var(--bg-hover);color:var(--text-primary);"><?= $userStats['comments_count'] ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="<?= Url::route('user/notifications') ?>" class="category-link <?= $currentRoute === 'user/notifications' ? 'active' : '' ?>">
              <div class="category-link-left">
                <?= FluentIcon::render('megaphone', 'fluent-icon-sm') ?>
                <span>站内通知</span>
              </div>
              <?php if ($unreadNoticeCount > 0): ?>
                <span class="badge" style="background:#fee2e2;color:#b91c1c;font-weight:700;"><?= $unreadNoticeCount ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>

        <?php if ($isAdmin): ?>
          <div class="sidebar-title" style="margin-top: 18px; border-top: 1px solid var(--border-subtle); padding-top: 12px;">全站管理</div>
          <ul class="category-nav">
            <li>
              <a href="<?= Url::route('admin/dashboard') ?>" class="category-link <?= $currentRoute === 'admin/dashboard' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('speed', 'fluent-icon-sm') ?>
                  <span>管理看板</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/pending') ?>" class="category-link <?= $currentRoute === 'admin/pending' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
                  <span>软件审核</span>
                </div>
                <?php if ($pendingSoftCount > 0): ?>
                  <span class="badge" style="background:#fee2e2;color:#b91c1c;font-weight:700;"><?= $pendingSoftCount ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/corrections') ?>" class="category-link <?= $currentRoute === 'admin/corrections' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('edit', 'fluent-icon-sm') ?>
                  <span>修改信息审核</span>
                </div>
                <?php if ($pendingCorrCount > 0): ?>
                  <span class="badge" style="background:#dbeafe;color:#1d4ed8;font-weight:700;"><?= $pendingCorrCount ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/reports') ?>" class="category-link <?= $currentRoute === 'admin/reports' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('flag', 'fluent-icon-sm') ?>
                  <span>全站举报处理</span>
                </div>
                <?php if ($pendingReportCount > 0): ?>
                  <span class="badge" style="background:#fef3c7;color:#b45309;font-weight:700;"><?= $pendingReportCount ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/users') ?>" class="category-link <?= $currentRoute === 'admin/users' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('person', 'fluent-icon-sm') ?>
                  <span>用户与权限</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/softwares') ?>" class="category-link <?= $currentRoute === 'admin/softwares' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('folder', 'fluent-icon-sm') ?>
                  <span>全站软件数据</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/comments') ?>" class="category-link <?= $currentRoute === 'admin/comments' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('comment', 'fluent-icon-sm') ?>
                  <span>评价与敏感词</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/mirrors') ?>" class="category-link <?= $currentRoute === 'admin/mirrors' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('github', 'fluent-icon-sm') ?>
                  <span>GitHub 镜像</span>
                </div>
              </a>
              <a href="<?= Url::route('admin/apiKeys') ?>" class="category-link <?= $currentRoute === 'admin/apiKeys' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('shield', 'fluent-icon-sm') ?>
                  <span>开放 API 密钥</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/notices') ?>" class="category-link <?= $currentRoute === 'admin/notices' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('megaphone', 'fluent-icon-sm') ?>
                  <span>全站公告</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/settings') ?>" class="category-link <?= $currentRoute === 'admin/settings' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('settings', 'fluent-icon-sm') ?>
                  <span>系统全局设置</span>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= Url::route('admin/migrate') ?>" class="category-link <?= $currentRoute === 'admin/migrate' ? 'active' : '' ?>">
                <div class="category-link-left">
                  <?= FluentIcon::render('wrench', 'fluent-icon-sm') ?>
                  <span>结构升级</span>
                </div>
              </a>
            </li>
          </ul>
        <?php endif; ?>
      </div>
    </aside>

    <!-- 右侧主体内容容器 -->
    <main style="flex: 1; min-width: 0;">
