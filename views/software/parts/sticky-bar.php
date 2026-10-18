<!-- 吸顶快速操作悬浮栏 (严格按照用户图1：仅在滑过下载按钮时出现，顶部居中悬浮毛玻璃，限宽不超过下方控件) -->
<div class="ms-sticky-bar" id="ms-sticky-bar">
  <div class="ms-sticky-container">
    <div class="ms-sticky-left">
      <img src="<?= View::e($software['icon_url']) ?>" class="ms-sticky-icon" alt="" onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><rect width=%2224%22 height=%2224%22 rx=%226%22 fill=%22%230067b8%22/></svg>';">
      <div class="ms-sticky-titles">
        <div class="ms-sticky-name"><?= View::e($software['name']) ?></div>
        <div class="ms-sticky-sub"><?= View::e($developerName) ?></div>
      </div>
    </div>
    <div class="ms-sticky-right">
      <?php if (!empty($githubUrl)): ?>
        <button type="button" class="ms-sticky-btn" onclick="openModal('github-mirror-modal'); window.initGithubMirrorSpeedTest();">
          <?= FluentIcon::render('github', 'fluent-icon-sm') ?>
          <span>GitHub 镜像</span>
        </button>
      <?php endif; ?>
      <?php if (!empty($isOfficialGithub)): ?>
        <button type="button" class="ms-sticky-download-btn" onclick="openModal('github-mirror-modal'); window.initGithubMirrorSpeedTest();">
          下载
        </button>
      <?php else: ?>
        <a href="<?= Url::href($software['official_url']) ?>" target="_blank" rel="noopener noreferrer" class="ms-sticky-download-btn">
          下载
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

