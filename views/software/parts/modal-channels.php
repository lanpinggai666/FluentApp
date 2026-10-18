<!-- 「全部下载渠道」弹窗：保证任何已录入的下载来源都不会被静默丢弃 -->
<?php if (!empty($allChannels)): ?>
  <div class="modal-overlay" id="download-channel-modal">
    <div class="modal-container" style="max-width: 560px;">
      <div class="modal-header">
        <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
          <?= FluentIcon::render('list', 'fluent-icon') ?>
          <span>全部下载渠道</span>
        </div>
        <button type="button" class="notice-close" onclick="closeModal('download-channel-modal')" aria-label="关闭"><?= FluentIcon::render('dismiss') ?></button>
      </div>
      <div class="modal-body" style="display: flex; flex-direction: column; gap: 8px;">
        <a href="<?= Url::href($software['official_url']) ?>" target="_blank" rel="noopener noreferrer"
           class="btn-store-primary" style="justify-content: flex-start; width: 100%;">
          <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
          <span>官方网站</span>
        </a>

        <?php if (!empty($msStoreUrl)): ?>
          <a href="<?= Url::href($msStoreUrl) ?>" target="_blank" rel="noopener noreferrer"
             class="btn-store-secondary" style="justify-content: flex-start; width: 100%;">
            <?= FluentIcon::render('microsoft-store', 'fluent-icon-sm') ?>
            <span>Microsoft Store</span>
          </a>
        <?php endif; ?>

        <?php foreach ($allChannels as $channel): ?>
          <a href="<?= Url::href($channel['url']) ?>" target="_blank" rel="noopener noreferrer"
             class="btn-store-secondary" style="justify-content: space-between; width: 100%;">
            <span style="display: flex; align-items: center; gap: 8px; min-width: 0;">
              <?= FluentIcon::render($channel['type'] === 'Github' ? 'code' : 'arrow-download', 'fluent-icon-sm') ?>
              <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= View::e($channel['name']) ?></span>
            </span>
            <span style="font-size: 0.75rem; color: var(--text-muted); flex-shrink: 0;"><?= View::e($channel['type']) ?></span>
          </a>
        <?php endforeach; ?>

        <?php if (!empty($githubUrl)): ?>
          <button type="button" class="btn-store-secondary" style="width: 100%; justify-content: center;"
                  onclick="closeModal('download-channel-modal'); openModal('github-mirror-modal'); window.initGithubMirrorSpeedTest();">
            <?= FluentIcon::render('speed', 'fluent-icon-sm') ?>
            <span>GitHub Release 镜像加速</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

