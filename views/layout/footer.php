<?php
/**
 * 蓝屏钙软件导航网 - 全局页脚视图 (极简 Fluent 纯净风格)
 */
$allCategories = Category::getAll();
$captchaModalHtml = CaptchaService::renderHtml('submit');
?>
  </div><!-- /#fluent-app-root -->

  <?php $customFooter = Setting::get('site_footer', ''); ?>
  <footer class="footer">
    <div class="footer-container">
      <div>
        <?php if (!empty($customFooter)): ?>
          <?= View::sanitizeHtml($customFooter) ?>
        <?php else: ?>
          <span>&copy; <?= date('Y') ?> <?= View::e(Setting::get('site_name', '蓝屏钙软件导航')) ?>. 收录优质、纯净、可靠的官方分发软件</span>
        <?php endif; ?>
      </div>
      <div style="display: flex; gap: 16px; align-items: center;">
        <a href="<?= Url::route('home') ?>" style="color: var(--text-muted); transition: color 0.15s ease;">主页导航</a>
        <span>·</span>
        <a href="javascript:void(0)" onclick="openModal('submit-software-modal'); return false;" style="color: var(--text-muted); transition: color 0.15s ease;">提交软件</a>
        <span>·</span>
        <a href="<?= Url::route('admin/dashboard') ?>" style="color: var(--text-muted); transition: color 0.15s ease;">管理后台</a>
      </div>
    </div>
  </footer>

  <!-- 固定在屏幕右下角的提交/添加软件加号悬浮按钮 -->
  <button type="button" class="btn-fab-fixed" onclick="openModal('submit-software-modal')" title="提交/添加软件">
    <?= FluentIcon::render('plus', 'fluent-icon-lg') ?>
  </button>

  <!-- 全局提交软件模态框：与独立提交页共用 views/partials/submit_form.php -->
  <div class="modal-overlay" id="submit-software-modal">
    <div class="modal-container" style="max-width: 720px; max-height: 92vh;">
      <div class="modal-header">
        <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
          <?= FluentIcon::render('plus', 'fluent-icon') ?>
          <span>提交软件</span>
        </div>
        <button type="button" class="notice-close" onclick="closeModal('submit-software-modal')" aria-label="关闭"><?= FluentIcon::render('dismiss') ?></button>
      </div>
      <div class="modal-body" style="max-height: calc(92vh - 64px); overflow-y: auto;">
        <?php
        $submitFormId = 'softnav-submit-modal';
        $submitScope = 'modal';
        $submitCategories = $allCategories;
        $submitCaptcha = $captchaModalHtml;
        require VIEW_PATH . '/partials/submit_form.php';
        ?>
      </div>
    </div>
  </div>

  <!-- 全局用户登录/注册模态框 (Fluent 2 风格) -->
  <?php require VIEW_PATH . '/partials/auth_modal.php'; ?>

  <script src="<?= Url::basePath() ?>assets/vendor/easymde/easymde.min.js?v=<?= filemtime(BASE_PATH . '/public/assets/vendor/easymde/easymde.min.js') ?>"></script>
  <script src="<?= Url::basePath() ?>assets/js/dialog.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/dialog.js') ?>"></script>
  <script src="<?= Url::basePath() ?>assets/js/markdown.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/markdown.js') ?>"></script>
  <script src="<?= Url::basePath() ?>assets/js/app.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/app.js') ?>"></script>
  <script src="<?= Url::basePath() ?>assets/js/submit-form.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/submit-form.js') ?>"></script>
  <?php // AI 对话面板只在后台用得到，别让它进前台访客的资源里 ?>
  <?php if (str_starts_with(Router::currentRoute(), 'admin')): ?>
  <script src="<?= Url::basePath() ?>assets/js/ai-trace.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/ai-trace.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
