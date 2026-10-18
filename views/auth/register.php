<?php
/**
 * 蓝屏钙软件导航网 - 独立用户注册页面
 */
$googleClientId = trim(Setting::get('oauth_google_client_id', ''));
$msClientId = trim(Setting::get('oauth_ms_client_id', ''));
$githubClientId = trim(Setting::get('oauth_github_client_id', ''));
$redirect = $_GET['redirect'] ?? Url::route('home');
?>

<div class="auth-page-container" style="display: flex; justify-content: center; align-items: center; min-height: calc(100vh - 120px); padding: 40px 16px;">
  <div class="auth-card" style="width: 100%; max-width: 440px; background: var(--bg-surface); padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm);">
    <div class="auth-card-header" style="text-align: center; margin-bottom: 24px;">
      <h1 class="auth-card-title" style="font-size: 1.375rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">注册新账号</h1>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;">收录管理优质软件，记录个人评价与贡献</p>
    </div>

    <!-- 第三方快捷注册入口 -->
    <div class="oauth-section" style="margin-bottom: 16px;">
      <div class="oauth-buttons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
        <button type="button" class="btn-oauth btn-oauth-ms" onclick="startOAuthLogin('microsoft')">
          <svg width="16" height="16" viewBox="0 0 21 21">
            <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
            <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
            <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
            <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
          </svg>
          <span>Microsoft 注册</span>
        </button>

        <button type="button" class="btn-oauth btn-oauth-google" onclick="startOAuthLogin('google')">
          <svg width="16" height="16" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
          </svg>
          <span>Google 注册</span>
        </button>
      </div>

      <div class="auth-divider" style="margin: 14px 0 12px; text-align: center;">
        <span style="font-size: 0.75rem; color: var(--text-muted); background: var(--bg-surface); padding: 0 8px;">或使用邮箱注册</span>
      </div>
    </div>

    <div id="page-reg-alert-err" style="display: none; padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 16px; font-size: 0.8125rem;"></div>
    <div id="page-reg-alert-succ" style="display: none; padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 16px; font-size: 0.8125rem;"></div>

    <form method="POST" action="<?= Url::route('auth/register') ?>" id="page-register-form">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="redirect" value="<?= View::e($redirect) ?>">

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-reg-username" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">用户昵称 (纯字母或字母+数字)</label>
        <input type="text" id="page-reg-username" name="username" class="form-input" placeholder="3-20 位字母/数字" required pattern="^[a-zA-Z0-9_]{3,20}$" autocomplete="username">
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">常用电子邮箱</label>
        <div style="display: flex; gap: 6px;">
          <input type="text" id="page-reg-email-prefix" name="email_prefix" class="form-input" placeholder="邮箱前缀" required style="flex: 1;" autocomplete="off">
          <select id="page-reg-email-suffix" name="email_suffix" class="form-select" style="width: 140px; flex-shrink: 0;">
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
        <?= CaptchaService::renderHtml('page_reg') ?>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-reg-email-code" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">邮箱验证码</label>
        <div style="display: flex; gap: 8px;">
          <input type="text" id="page-reg-email-code" name="email_code" class="form-input" placeholder="6 位验证码" required maxlength="6" style="flex: 1;" autocomplete="off">
          <button type="button" class="btn-store-secondary" id="page-btn-reg-code" onclick="sendAuthCode(this, 'register', 'page-reg')" style="height: 38px; padding: 0 14px; font-size: 0.8125rem; white-space: nowrap; flex-shrink: 0;">
            获取验证码
          </button>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-reg-password" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">登录密码</label>
        <input type="password" id="page-reg-password" name="password" class="form-input" placeholder="至少 6 位" required minlength="6" autocomplete="new-password">
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label" for="page-reg-password-confirm" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">确认密码</label>
        <input type="password" id="page-reg-password-confirm" name="password_confirm" class="form-input" placeholder="再次输入密码" required minlength="6" autocomplete="new-password">
      </div>

      <button type="submit" class="btn-store-primary" style="width: 100%; height: 38px; font-size: 0.875rem;">
        <span>注册并登录</span>
      </button>
    </form>

    <div style="margin-top: 18px; text-align: center; font-size: 0.8125rem; color: var(--text-muted);">
      已有账号？<a href="<?= Url::route('auth/login', ['redirect' => $redirect]) ?>" style="color: var(--primary); text-decoration: none;">直接登录</a>
    </div>
  </div>
</div>
