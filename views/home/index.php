<?php
/**
 * 蓝屏钙软件导航网 - 主页视图 (Fluent 2 风格)
 */
$sidebarCollapsed = (isset($_COOKIE['fluent_sidebar_collapsed']) && $_COOKIE['fluent_sidebar_collapsed'] === '1');
?>
<main class="main-layout">
  <!-- 侧边栏：分类导航 (支持折叠收起为纯图标态) -->
  <aside class="sidebar <?= $sidebarCollapsed ? 'collapsed' : '' ?>" id="main-sidebar">
    <div class="sidebar-card">
      <div class="sidebar-header" style="padding: 2px 6px 8px;">
        <span class="sidebar-title" style="padding: 0;">分类浏览</span>
      </div>
      <ul class="category-nav">
        <li>
          <a href="<?= Url::route('home') ?>" class="category-link <?= empty($currentCategory) ? 'active' : '' ?>" title="全部软件 (<?= $totalCount ?>)">
            <div class="category-link-left">
              <?= FluentIcon::render('grid', 'fluent-icon-sm') ?>
              <span class="category-name">全部软件</span>
            </div>
            <span class="category-count"><?= $totalCount ?></span>
          </a>
        </li>
        <?php foreach ($categories as $cat): ?>
          <li>
            <a href="<?= Url::category($cat['slug']) ?>" class="category-link <?= (!empty($currentCategory) && $currentCategory['id'] == $cat['id']) ? 'active' : '' ?>" title="<?= View::e($cat['name']) ?> (<?= (int)$cat['software_count'] ?>)">
              <div class="category-link-left">
                <?= FluentIcon::render($cat['icon'] ?: 'folder', 'fluent-icon-sm') ?>
                <span class="category-name"><?= View::e($cat['name']) ?></span>
              </div>
              <span class="category-count"><?= (int) $cat['software_count'] ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>

  <!-- 移动端抽屉遮罩（仅小屏由 CSS 启用） -->
  <div class="sidebar-scrim" id="sidebar-scrim" aria-hidden="true"></div>

  <!-- 主内容展示区 -->
  <section class="content-area">
    <!-- 工具栏 -->
    <div class="toolbar">
      <div class="toolbar-title">
        <span><?= !empty($currentCategory) ? View::e($currentCategory['name']) : (!empty($search) ? '搜索结果' : '探索发现') ?></span>
        <span style="font-size: 0.8125rem; color: var(--text-muted); font-weight: normal;">(<?= $totalCount ?> 款)</span>
      </div>

      <div class="sort-chips">
        <a href="<?= Url::category(!empty($currentCategory) ? (string)$currentCategory['slug'] : '', ['q' => $search, 'sort' => 'random']) ?>" class="sort-chip <?= $sort === 'random' ? 'active' : '' ?>">
          <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?> 随机发现
        </a>
        <a href="<?= Url::category(!empty($currentCategory) ? (string)$currentCategory['slug'] : '', ['q' => $search, 'sort' => 'views']) ?>" class="sort-chip <?= $sort === 'views' ? 'active' : '' ?>">
          热门访问
        </a>
        <a href="<?= Url::category(!empty($currentCategory) ? (string)$currentCategory['slug'] : '', ['q' => $search, 'sort' => 'rating']) ?>" class="sort-chip <?= $sort === 'rating' ? 'active' : '' ?>">
          高分好评
        </a>
        <a href="<?= Url::category(!empty($currentCategory) ? (string)$currentCategory['slug'] : '', ['q' => $search, 'sort' => 'latest']) ?>" class="sort-chip <?= $sort === 'latest' ? 'active' : '' ?>">
          最新收录
        </a>
      </div>
    </div>

    <!-- 软件展示容器 (严格保持官方卡片状，无需列表切换) -->
    <div id="software-display-container" class="software-grid">
      <?php if (empty($softwares)): ?>
        <div style="grid-column: 1 / -1; padding: 48px; text-align: center; background: var(--bg-surface); border: 1px dashed var(--border-subtle); border-radius: var(--radius-md); color: var(--text-muted);">
          <div style="margin-bottom: 8px;"><?= FluentIcon::render('search', 'fluent-icon-lg') ?></div>
          <div>未找到匹配软件，可点击右下角按钮申请提交收录！</div>
        </div>
      <?php else: ?>
        <?php foreach ($softwares as $app): ?>
          <?php 
            $detailUrl = Url::detail($app['slug']);
            $isFree = ($app['price_type'] ?? 'free') === 'free';
            [$cardR, $cardG, $cardB] = Software::getThemeRgb($app);
            $cardRgbStr = "{$cardR}, {$cardG}, {$cardB}";
          ?>
          <!-- 网格大卡片视图 (严格复刻 Microsoft Store 官方卡片布局：上段图标取色渐变+居中图标，下段白底名称+评分+免费下载) -->
          <div class="software-card" data-href="<?= $detailUrl ?>" data-slug="<?= View::e($app['slug']) ?>" data-theme-rgb="<?= $cardRgbStr ?>" title="点击查看 <?= View::e($app['name']) ?> 详情">
            <div class="card-banner-wrapper" data-theme-rgb="<?= $cardRgbStr ?>" style="--card-theme-rgb: <?= $cardRgbStr ?>;">
              <img src="<?= View::e($app['icon_url']) ?>" class="card-large-icon" alt="<?= View::e($app['name']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><rect width=%2220%22 height=%2220%22 fill=%22%23cbd5e1%22/></svg>'">
            </div>
            <div class="card-bottom-content">
              <h3 class="card-store-name" title="<?= View::e($app['name']) ?>"><?= View::e($app['name']) ?></h3>
              <div class="card-store-footer">
                <div class="card-store-rating">
                  <?php if ((float)$app['rating_avg'] > 0): ?>
                    <span class="rating-num"><?= number_format((float)$app['rating_avg'], 1) ?></span>
                    <span class="rating-star">★</span>
                  <?php endif; ?>
                </div>
                <span class="card-store-action"><?= $isFree ? '免费下载' : '获取' ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>
