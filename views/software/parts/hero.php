  <!-- 网页级背景取色氛围层 (与主页卡片严格保持 100% 相同色彩来源，最上面浅，由深变浅，向下自然过渡融入网页原本背景色) -->
  <div class="ms-page-gradient-bg" id="detail-page-gradient-bg" style="--hero-sample-rgb: <?= $themeRgbStr ?>; background: linear-gradient(180deg, rgba(<?= $themeRgbStr ?>, 0.28) 0%, rgba(<?= $themeRgbStr ?>, 0.14) 180px, rgba(<?= $themeRgbStr ?>, 0.04) 420px, var(--bg-page) 580px);"></div>

  <!-- 1. Hero 顶部展示区域 (严格遵循用户图2：开放式无卡片边框、左侧大图标、右侧大标题与链接、下方描述与大按钮) -->
  <div class="ms-hero-wrapper" id="ms-hero-wrapper">
    <section class="ms-hero" id="ms-hero-section">
      <div class="ms-hero-top">
        <div class="ms-hero-icon-column">
          <img src="<?= View::e($software['icon_url']) ?>" class="ms-app-icon" id="ms-hero-icon" style="view-transition-name: hero-icon;" alt="<?= View::e($software['name']) ?>" onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 48 48%22><rect width=%2248%22 height=%2248%22 rx=%2210%22 fill=%22%230067b8%22/><path d=%22M14 16h20v14H14z%22 fill=%22%23ffffff%22/><circle cx=%2224%22 cy=%2223%22 r=%224%22 fill=%22%2360cdff%22/></svg>';">
        </div>
        <div class="ms-hero-info">
          <h1 class="ms-app-title" style="view-transition-name: hero-title;"><?= View::e($software['name']) ?></h1>
          <div class="ms-hero-meta-links">
            <a href="<?= Url::href($software['official_url']) ?>" target="_blank" rel="noopener noreferrer" class="ms-hero-dev-link"><?= View::e($developerName) ?></a>
            <a href="<?= Url::category($software['category_slug'] ?? '') ?>" class="ms-hero-cat-link"><?= View::e($software['category_name'] ?? '实用工具') ?></a>
            <?php if (!empty($managerUser)): ?>
              <a href="<?= Url::route('user/home', ['id' => (int)$managerUser['id']]) ?>" class="ms-hero-manager-badge" title="该软件由 <?= View::e($managerUser['username']) ?> 维护管理，点击查看个人主页">
                <?php if (!empty($managerUser['avatar_url'])): ?>
                  <img src="<?= View::e($managerUser['avatar_url']) ?>" class="ms-hero-manager-avatar" alt="">
                <?php else: ?>
                  <?= FluentIcon::render('person', 'fluent-icon-xs') ?>
                <?php endif; ?>
                <span>由 <strong><?= View::e($managerUser['username']) ?></strong> 管理</span>
              </a>
            <?php else: ?>
              <span class="ms-hero-manager-badge ms-hero-manager-badge-official">
                <?= FluentIcon::render('shield-checkmark', 'fluent-icon-xs') ?>
                <span>官方维护管理</span>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      

      

            <div class="ms-hero-description"><?= View::e($heroDescription) ?></div>

      <!-- 操作按钮栏 (严格遵循图2：纯蓝大号下载按钮、在 Microsoft Store 中查看、GitHub 镜像等) -->
      <div class="ms-hero-actions">
        <?php if (!empty($isOfficialGithub)): ?>
          <button type="button" class="btn-store-hero-download" id="hero-download-btn" onclick="openModal('github-mirror-modal'); window.initGithubMirrorSpeedTest();">
            下载
          </button>
        <?php else: ?>
          <a href="<?= Url::href($software['official_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-store-hero-download" id="hero-download-btn">
            下载
          </a>
        <?php endif; ?>

        <?php if (!empty($msStoreProductId)): ?>
          <script type="module" src="https://get.microsoft.com/badge/ms-store-badge.bundled.js"></script>
          <div class="ms-store-badge-wrap">
            <ms-store-badge
              productid="<?= View::e($msStoreProductId) ?>"
              window-mode="direct"
              theme="auto"
              size="small"
              language="zh-cn"
              animation="on">
            </ms-store-badge>
          </div>
        <?php elseif (!empty($msStoreUrl)): ?>
          <a href="<?= Url::href($msStoreUrl) ?>" target="_blank" rel="noopener noreferrer" class="ms-hero-secondary-btn">
            <?= FluentIcon::render('microsoft-store', 'fluent-icon-sm') ?>
            <span>在 Microsoft Store 中获取</span>
          </a>
        <?php endif; ?>

        <?php if (!empty($githubUrl)): ?>
          <button type="button" class="ms-hero-secondary-btn" id="github-mirror-btn" onclick="openModal('github-mirror-modal'); window.initGithubMirrorSpeedTest();">
            <?= FluentIcon::render('github', 'fluent-icon-sm') ?>
            <span>GitHub 镜像</span>
          </button>
        <?php endif; ?>

        <?php foreach ($heroChannels as $tp): ?>
          <a href="<?= Url::href($tp['url']) ?>" target="_blank" rel="noopener noreferrer" class="ms-hero-secondary-btn">
            <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
            <span><?= View::e($tp['name']) ?></span>
          </a>
        <?php endforeach; ?>

        <?php if (count($thirdPartyUrls) > count($heroChannels)): ?>
          <button type="button" class="ms-hero-secondary-btn" onclick="openModal('download-channel-modal')">
            <?= FluentIcon::render('list', 'fluent-icon-sm') ?>
            <span>全部渠道 (<?= count($allChannels) ?>)</span>
          </button>
        <?php elseif (!empty($githubChannels) && empty($githubUrl)): ?>
          <button type="button" class="ms-hero-secondary-btn" onclick="openModal('download-channel-modal')">
            <?= FluentIcon::render('list', 'fluent-icon-sm') ?>
            <span>全部渠道 (<?= count($allChannels) ?>)</span>
          </button>
        <?php endif; ?>

      </div>

      <?php if ($hasSpecialNotes): ?>
        <div class="ms-hero-notice md-notice">
          <p class="ms-hero-notice-text"><?= nl2br(View::e($specialNotesRaw)) ?></p>
        </div>
      <?php endif; ?>

      <div class="ms-hero-extra-actions">
        <button type="button" class="ms-hero-secondary-btn" onclick="openModal('software-correction-modal')">
          <?= FluentIcon::render('edit', 'fluent-icon-sm') ?>
          <span>修改信息</span>
        </button>

        <button type="button" class="btn-icon" onclick="openModal('report-modal')" title="举报软件" style="margin-left: 4px;">
          <?= FluentIcon::render('flag', 'fluent-icon-sm') ?>
        </button>
      </div>
    </section>
  </div>

