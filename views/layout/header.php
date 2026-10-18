<?php
/**
 * 蓝屏钙软件导航网 - 全局头部视图
 */
$theme = $_COOKIE['theme'] ?? 'light';
$siteName = Setting::get('site_name', '蓝屏钙软件导航');
$siteLogo = Setting::get('site_logo', '');
$siteFavicon = Setting::get('site_favicon', '');
// 运维如果把 logo/favicon 填成了站内相对路径，在 /software/xxx 这种带段数的地址下会被解析到子目录去
// —— 这里统一补上站内根，绝对地址与协议相对地址原样放行
$toRootUrl = static function (string $value): string {
    if ($value === '' || preg_match('#^(https?:)?/|^data:|^mailto:#i', $value)) {
        return $value;
    }
    return Url::basePath() . ltrim($value, '/');
};
$siteDesc = $siteDesc ?? Setting::get('site_description', '专门收集各个软件的官方下载方式');
$siteKeywords = Setting::get('site_keywords', '软件导航,官网下载,GitHub镜像,纯净软件');
$pageTitle = $title ?? $siteName;
$notice = $notice ?? Notice::getActiveFirst();
$viewMode = $_COOKIE['view_mode'] ?? 'grid';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= View::e($pageTitle) ?></title>
  
  <?php if (!empty($siteFavicon)): ?>
    <link rel="icon" href="<?= View::e($toRootUrl($siteFavicon)) ?>">
  <?php endif; ?>
  
  <!-- SEO Meta 标签 -->
  <meta name="description" content="<?= View::e($siteDesc) ?>">
  <meta name="keywords" content="<?= View::e($siteKeywords) ?>">
  <meta name="robots" content="index, follow">
  <?php if (!empty($canonical)): ?>
  <!-- 同一篇内容的旧形态地址照常可访问，用 canonical 收口，比 301 少一次跳转、也不会打断直接访问旧链接的访客 -->
  <link rel="canonical" href="<?= View::e($canonical) ?>">
  <meta property="og:url" content="<?= View::e($canonical) ?>">
  <?php endif; ?>
  
  <!-- Open Graph 社交分享 -->
  <meta property="og:title" content="<?= View::e($pageTitle) ?>">
  <meta property="og:description" content="<?= View::e($siteDesc) ?>">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary">
  <meta name="csrf-token" content="<?= Auth::csrfToken() ?>">

  <!-- 预先应用主题与侧边栏折叠态，彻底杜绝白屏与收起动画闪烁 -->
  <script>
    (function() {
      try {
        const saved = localStorage.getItem('theme');
        if (saved) {
          document.documentElement.setAttribute('data-theme', saved);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
        if (window.matchMedia && window.matchMedia('(min-width: 769px)').matches
            && localStorage.getItem('fluent_sidebar_collapsed') === '1') {
          document.documentElement.classList.add('sidebar-init-collapsed');
        }
      } catch(e) {}
    })();
  </script>

  <?php require VIEW_PATH . '/partials/stylesheets.php'; ?>
  <!-- 统一表单控件 / 自定义确认框 / Markdown 渲染样式 -->
  <link rel="stylesheet" href="<?= Url::basePath() ?>assets/css/components.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/components.css') ?>">
  <!-- 开源 Markdown 编辑器 EasyMDE (MIT License, 本地离线资源) -->
  <link rel="stylesheet" href="<?= Url::basePath() ?>assets/vendor/easymde/easymde.min.css?v=<?= filemtime(BASE_PATH . '/public/assets/vendor/easymde/easymde.min.css') ?>">
  
  <?php 
    $googleClientId = trim(Setting::get('oauth_google_client_id', ''));
    // One Tap 弹窗的三个前提：配了客户端 ID、后台开关没关、当前未登录。
    // 具体弹不弹还有前端几道抑制（本会话只弹一次、用户点过「不再提醒」、登录框正开着）。
    $oneTapReady = $googleClientId !== ''
        && trim((string) Setting::get('google_one_tap', '1')) !== '0'
        && !Auth::checkUser();
    if ($googleClientId !== ''): 
  ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>window.SOFTNAV_GOOGLE = <?= json_encode(
        ['clientId' => $googleClientId, 'oneTap' => $oneTapReady],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;</script>
  <?php endif; ?>
</head>
<body>

  <!-- 公告横幅 (点开显示详情) -->
  <?php if ($notice): ?>
    <div class="notice-banner" id="global-notice-banner">
      <div class="notice-content" onclick="openModal('notice-detail-modal')" style="cursor: pointer;">
        <?= FluentIcon::render('megaphone', 'fluent-icon-sm') ?>
        <span><strong>公告:</strong> <?= View::e($notice['title']) ?> (点击查看详情)</span>
      </div>
      <button class="notice-close" onclick="document.getElementById('global-notice-banner').style.display='none';" title="关闭">
        <?= FluentIcon::render('dismiss', 'fluent-icon-sm') ?>
      </button>
    </div>

    <!-- 公告详情弹窗 -->
    <div class="modal-overlay" id="notice-detail-modal">
      <div class="modal-container">
        <div class="modal-header">
          <div class="modal-title" style="display:flex;align-items:center;gap:8px;">
            <?= FluentIcon::render('megaphone', 'fluent-icon') ?>
            <span><?= View::e($notice['title']) ?></span>
          </div>
          <button class="notice-close" onclick="closeModal('notice-detail-modal')"><?= FluentIcon::render('dismiss') ?></button>
        </div>
        <div class="modal-body">
          <p style="white-space: pre-wrap; line-height: 1.6; font-size: 0.9375rem; color: var(--text-secondary);">
            <?= nl2br(View::e($notice['content'])) ?>
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-primary" onclick="closeModal('notice-detail-modal')">我知道了</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php
    $currentRoute = Router::currentRoute() ?: 'home';
    $isHomePage = ($currentRoute === 'home' || $currentRoute === '');
  ?>
  <!-- 顶部主导航 -->
  <header class="navbar">
    <div class="navbar-container">
      <!-- 顶栏左侧品牌区域 (始终常显 Logo 与站点名，位置固定不变，详情页提供同尺寸返回主页图标) -->
      <div class="navbar-brand-wrapper" id="navbar-brand-wrapper">
        <?php if ($isHomePage): ?>
          <button type="button" class="btn-icon sidebar-nav-toggle" id="sidebar-toggle-btn" title="收起/展开分类栏">
            <?= FluentIcon::render('navigation', 'fluent-icon') ?>
          </button>
          <div class="navbar-btn-divider" id="sidebar-divider"></div>
        <?php else: ?>
          <a href="<?= Url::route('home') ?>" class="btn-icon back-nav-btn" id="back-nav-btn" title="返回上一级" style="text-decoration:none;" onclick="if(window.navigateBack){window.navigateBack(event);}else if(window.history.length>1){event.preventDefault();window.history.back();}">
            <?= FluentIcon::render('arrow-left', 'fluent-icon') ?>
          </a>
          <div class="navbar-btn-divider" id="sidebar-divider"></div>
        <?php endif; ?>
        <a href="<?= Url::route('home') ?>" class="brand navbar-brand-link">
          <?php if (!empty($siteLogo)): ?>
            <img src="<?= View::e($toRootUrl($siteLogo)) ?>" alt="<?= View::e($siteName) ?>" class="brand-img-logo" style="width: 28px; height: 28px; object-fit: contain; border-radius: 4px;">
          <?php else: ?>
            <span class="brand-icon"><?= FluentIcon::render('desktop', 'fluent-icon-lg') ?></span>
          <?php endif; ?>
          <span class="brand-name-text"><?= View::e($siteName) ?></span>
        </a>
      </div>

      <!-- 搜索框与实时下拉列表 -->
      <div class="search-wrapper">
        <form action="<?= Url::basePath() ?>index.php" method="GET" class="search-input-group">
          <input type="hidden" name="r" value="home">
          <span class="search-icon"><?= FluentIcon::render('search', 'fluent-icon-sm') ?></span>
          <input type="text" name="q" id="main-search-input" class="search-input" placeholder="快速检索软件名称、标签或详细说明..." value="<?= View::e($_GET['q'] ?? '') ?>" autocomplete="off">
        </form>
        <div class="search-results-dropdown" id="search-dropdown"></div>
      </div>

      <!-- 顶栏右侧操作区 (靠右对齐：保留暗黑模式切换、提交软件以及用户登录状态) -->
      <div class="nav-actions">
        <!-- 暗黑模式切换 -->
        <button class="btn-icon" id="theme-toggle" title="切换暗黑/浅色模式">
          <?= FluentIcon::render('sun') ?>
        </button>

        <!-- 提交软件按钮 (顶栏提交按钮) -->
        <button type="button" onclick="openModal('submit-software-modal')" class="btn-navbar-submit" title="提交新软件">
          <?= FluentIcon::render('plus', 'fluent-icon-sm') ?>
          <span>提交软件</span>
        </button>

        <!-- 用户登录 / 个人中心 -->
        <?php $currentUser = Auth::currentUser(); if ($currentUser): ?>
          <div class="user-menu-wrapper" id="user-menu-wrapper">
            <button type="button" class="btn-user-avatar" id="user-avatar-btn" onclick="toggleUserDropdown(event)" title="个人账号 (<?= View::e($currentUser['username'] ?? '') ?>)">
              <?php if (!empty($currentUser['avatar_url'])): ?>
                <img src="<?= View::e($currentUser['avatar_url']) ?>" alt="<?= View::e($currentUser['username']) ?>" class="user-avatar-img">
              <?php else: ?>
                <span class="user-avatar-text"><?= mb_substr($currentUser['username'] ?? '用', 0, 1, 'UTF-8') ?></span>
              <?php endif; ?>
              <span class="user-name-label"><?= View::e($currentUser['username']) ?></span>
              <?= FluentIcon::render('chevron-down', 'fluent-icon-xs') ?>
            </button>
            <div class="user-dropdown-menu" id="user-dropdown-menu">
              <div class="user-dropdown-header">
                <div class="user-dropdown-name"><?= View::e($currentUser['username']) ?></div>
                <div class="user-dropdown-email"><?= View::e($currentUser['email']) ?></div>
              </div>
              <a href="<?= $currentUser['id'] ? Url::route('user/home', ['id' => (int)$currentUser['id']]) : Url::route('user/home') ?>" class="user-dropdown-item">
                <?= FluentIcon::render('open', 'fluent-icon-sm') ?>
                <span>我的主页</span>
              </a>
              <a href="<?= Url::route('user/dashboard') ?>" class="user-dropdown-item">
                <?= FluentIcon::render('person', 'fluent-icon-sm') ?>
                <span>个人中心</span>
              </a>
              <a href="javascript:void(0)" onclick="openModal('submit-software-modal'); toggleUserDropdown();" class="user-dropdown-item">
                <?= FluentIcon::render('plus', 'fluent-icon-sm') ?>
                <span>提交软件</span>
              </a>
              <?php if (Auth::check()): ?>
                <a href="<?= Url::route('admin/dashboard') ?>" class="user-dropdown-item">
                  <?= FluentIcon::render('settings', 'fluent-icon-sm') ?>
                  <span>管理后台</span>
                </a>
              <?php endif; ?>
              <div class="user-dropdown-divider"></div>
              <a href="<?= Url::route('auth/logout') ?>" class="user-dropdown-item user-dropdown-logout">
                <?= FluentIcon::render('arrow-left', 'fluent-icon-sm') ?>
                <span>退出登录</span>
              </a>
            </div>
          </div>
        <?php else: ?>
          <button type="button" class="btn-navbar-auth" onclick="openAuthModal()" title="登录或注册账号">
            <?= FluentIcon::render('person', 'fluent-icon-sm') ?>
            <span>登录 / 注册</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Fluent 极速单页无刷新导航顶部光效进度条 -->
  <div id="fluent-progress-bar"></div>

  <!-- Fluent SPA 主内容容器 -->
  <div id="fluent-app-root">
