<?php
/**
 * 蓝屏钙软件导航网 - 提交收录软件（独立页面）
 *
 * 表单主体来自 views/partials/submit_form.php，与全站弹窗提交入口共用同一份代码。
 */
?>
<main class="main-layout" style="display: flex; justify-content: center; padding: 36px 16px;">
  <div style="max-width: 780px; width: 100%; margin: 0 auto;">

    <div class="form-shell" style="padding: 30px;">

      <div class="form-page-header">
        <h1 class="form-page-title">
          <?= FluentIcon::render('plus', 'fluent-icon') ?>
          <span>提交软件</span>
        </h1>
        <p class="form-page-subtitle">收录优质、纯净、可靠的官方分发软件 · 审核结果将通过邮箱通知</p>
      </div>

      <?php if (!empty($success)): ?>
        <div class="form-alert form-alert-success">
          <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
          <span>提交成功！已进入待审核池，审核结果将发送至您填写的邮箱。</span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="form-alert form-alert-error">
          <?= FluentIcon::render('warning', 'fluent-icon-sm') ?>
          <span><?= View::e($error) ?></span>
        </div>
      <?php endif; ?>

      <?php
      $submitFormId = 'softnav-submit-page';
      $submitScope = 'page';
      $submitCategories = $categories ?? [];
      $submitCaptcha = $captchaHtml ?? '';
      require VIEW_PATH . '/partials/submit_form.php';
      ?>

    </div>

  </div>
</main>
