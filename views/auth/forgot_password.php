<?php
/**
 * 蓝屏钙软件导航网 - 忘记密码/重置密码页面
 */
?>

<div class="auth-page-container" style="display: flex; justify-content: center; align-items: center; min-height: calc(100vh - 120px); padding: 40px 16px;">
  <div class="auth-card" style="width: 100%; max-width: 440px; background: var(--bg-surface); padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm);">
    <div class="auth-card-header" style="text-align: center; margin-bottom: 24px;">
      <h1 class="auth-card-title" style="font-size: 1.375rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">找回 / 重置密码</h1>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;">输入注册邮箱并验证后即可设置新密码</p>
    </div>

    <div id="page-forgot-alert-err" style="display: none; padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 16px; font-size: 0.8125rem;"></div>
    <div id="page-forgot-alert-succ" style="display: none; padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 16px; font-size: 0.8125rem;"></div>

    <form method="POST" action="<?= Url::route('auth/forgotPassword') ?>" id="page-forgot-form">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">注册电子邮箱</label>
        <div style="display: flex; gap: 6px;">
          <input type="text" id="page-forgot-email-prefix" name="email_prefix" class="form-input" placeholder="邮箱前缀" required style="flex: 1;" autocomplete="off">
          <select id="page-forgot-email-suffix" name="email_suffix" class="form-select" style="width: 140px; flex-shrink: 0;">
            <option value="@qq.com">@qq.com</option>
            <option value="@163.com">@163.com</option>
            <option value="@gmail.com">@gmail.com</option>
            <option value="@outlook.com">@outlook.com</option>
            <option value="@icloud.com">@icloud.com</option>
            <option value="@foxmail.com">@foxmail.com</option>
            <option value="@126.com">@126.com</option>
            <option value="@sina.com">@sina.com</option>
            <option value="@aliyun.com">@aliyun.com</option>
            <option value="@edu.cn">@edu.cn</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <?= CaptchaService::renderHtml('page_forgot') ?>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-forgot-email-code" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">邮箱验证码</label>
        <div style="display: flex; gap: 8px;">
          <input type="text" id="page-forgot-email-code" name="email_code" class="form-input" placeholder="6 位验证码" required maxlength="6" style="flex: 1;" autocomplete="off">
          <button type="button" class="btn-store-secondary" id="page-btn-forgot-code" onclick="sendAuthCode(this, 'reset_password', 'page-forgot')" style="height: 38px; padding: 0 14px; font-size: 0.8125rem; white-space: nowrap; flex-shrink: 0;">
            获取验证码
          </button>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-forgot-password" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">新密码</label>
        <input type="password" id="page-forgot-password" name="new_password" class="form-input" placeholder="至少 6 位新密码" required minlength="6" autocomplete="new-password">
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label" for="page-forgot-password-confirm" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">确认新密码</label>
        <input type="password" id="page-forgot-password-confirm" name="new_password_confirm" class="form-input" placeholder="再次输入新密码" required minlength="6" autocomplete="new-password">
      </div>

      <button type="submit" class="btn-store-primary" style="width: 100%; height: 38px; font-size: 0.875rem;">
        <span>重置密码并登录</span>
      </button>
    </form>

    <div style="margin-top: 18px; text-align: center; font-size: 0.8125rem; color: var(--text-muted);">
      想起密码？<a href="<?= Url::route('auth/login') ?>" style="color: var(--primary); text-decoration: none;">直接登录</a>
    </div>
  </div>
</div>
