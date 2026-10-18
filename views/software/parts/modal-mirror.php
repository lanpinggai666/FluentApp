<!-- 「GitHub 镜像」专属测速弹窗 (区分下载加速与网页加速) -->
<?php if (!empty($githubUrl)): 
  $downloadMirrors = array_values(array_filter($mirrors, function($m) {
      $t = $m['type'] ?? 'download';
      return $t === 'download' || $t === 'both';
  }));
  $webMirrors = array_values(array_filter($mirrors, function($m) {
      $t = $m['type'] ?? 'download';
      return $t === 'web' || $t === 'both';
  }));
?>
  <div class="modal-overlay" id="github-mirror-modal">
    <div class="modal-container" style="max-width: 580px;">
      <div class="modal-header">
        <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
          <?= FluentIcon::render('github', 'fluent-icon') ?>
          <span>GitHub 镜像</span>
        </div>
        <button class="notice-close" onclick="closeModal('github-mirror-modal')"><?= FluentIcon::render('dismiss') ?></button>
      </div>

      <div class="modal-body">
        <!-- Release x64 直链信息卡片 -->
        <div id="github-release-card">
          <div style="background: var(--bg-surface-secondary); padding: 10px 14px; border-radius: var(--radius-sm); font-size: 0.8125rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
            <?= FluentIcon::render('speed', 'fluent-icon-sm') ?>
            <span>正在解析最新稳定版 Release 直链...</span>
          </div>
        </div>

        <!-- 1. 文件下载加速专区 -->
        <div style="font-size: 0.8125rem; font-weight: 600; margin: 16px 0 8px; color: var(--text-primary); display: flex; align-items: center; justify-content: space-between;">
          <span style="display: flex; align-items: center; gap: 6px;">
            <?= FluentIcon::render('arrow-download', 'fluent-icon-sm') ?>
            <span>安装包文件下载加速</span>
          </span>
          <span style="font-size: 0.6875rem; color: var(--text-muted);">并发测速 · 自动优选</span>
        </div>

        <div id="mirror-speed-container"
             data-software-id="<?= (int)$software['id'] ?>" 
             data-github-repo="<?= View::e($githubUrl) ?>" 
             class="mirror-list">
          <?php foreach ($downloadMirrors as $m): ?>
            <div class="mirror-item" data-template="<?= View::e($m['url_template']) ?>">
              <div>
                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.8125rem;"><?= View::e($m['name']) ?></div>
                <div style="font-size: 0.6875rem; color: var(--text-muted);"><?= View::e($m['url_template']) ?></div>
              </div>
              <div style="display: flex; align-items: center; gap: 10px;">
                <span class="ping-tag">测速中</span>
                <a href="<?= Url::href(GithubService::buildMirrorUrl($m['url_template'], $githubUrl, (string) ($m['url_style'] ?? ''))) ?>" target="_blank" rel="noopener noreferrer" class="btn-store-pill mirror-link-btn">
                  加速下载
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- 2. 仓库网页浏览加速专区 -->
        <div style="font-size: 0.8125rem; font-weight: 600; margin: 18px 0 8px; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
          <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
          <span>GitHub 仓库网页浏览加速</span>
        </div>

        <div class="mirror-list">
          <?php foreach ($webMirrors as $m): ?>
            <div class="mirror-item" style="padding: 10px 14px;">
              <div>
                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.8125rem;"><?= View::e($m['name']) ?></div>
                <div style="font-size: 0.6875rem; color: var(--text-muted);"><?= View::e($m['url_template']) ?></div>
              </div>
              <div>
                <a href="<?= Url::href(GithubService::buildMirrorUrl($m['url_template'], $githubUrl, (string) ($m['url_style'] ?? ''))) ?>" target="_blank" rel="noopener noreferrer" class="btn-store-pill">
                  访问网页
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </div>
  <script src="<?= Url::basePath() ?>assets/js/mirror.js"></script>
<?php endif; ?>

