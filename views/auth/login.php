<?php
/**
 * 蓝屏钙软件导航网 - 独立用户登录页面
 */
$googleClientId = trim(Setting::get('oauth_google_client_id', ''));
$msClientId = trim(Setting::get('oauth_ms_client_id', ''));
$githubClientId = trim(Setting::get('oauth_github_client_id', ''));
$redirect = $_GET['redirect'] ?? Url::route('home');
?>

<div class="auth-page-container" style="display: flex; justify-content: center; align-items: center; min-height: calc(100vh - 120px); padding: 40px 16px;">
  <div class="auth-card" style="width: 100%; max-width: 420px; background: var(--bg-surface); padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm);">
    <div class="auth-card-header" style="text-align: center; margin-bottom: 24px;">
      <h1 class="auth-card-title" style="font-size: 1.375rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">账号登录</h1>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;">支持使用用户名或绑定邮箱登录</p>
    </div>

    <!-- 第三方快捷登录入口 -->
    <div class="oauth-section" style="margin-bottom: 16px;">
      <div class="oauth-buttons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
        <button type="button" class="btn-oauth btn-oauth-ms" onclick="startOAuthLogin('microsoft')">
          <svg width="16" height="16" viewBox="0 0 21 21">
            <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
            <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
            <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
            <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
          </svg>
          <span>Microsoft 登录</span>
        </button>

        <button type="button" class="btn-oauth btn-oauth-google" onclick="startOAuthLogin('google')">
          <svg width="16" height="16" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
          </svg>
          <span>Google 登录</span>
        </button>
      </div>

      <div class="auth-divider" style="margin: 14px 0 12px; text-align: center;">
        <span style="font-size: 0.75rem; color: var(--text-muted); background: var(--bg-surface); padding: 0 8px;">或使用账号密码</span>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div style="padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 16px; font-size: 0.8125rem;">
        <?= View::e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= Url::route('auth/login') ?>">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="redirect" value="<?= View::e($redirect) ?>">

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-login-account" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">账号 (用户名或邮箱)</label>
        <input type="text" id="page-login-account" name="account" class="form-input" placeholder="输入用户名或邮箱" required autocomplete="username">
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" for="page-login-password" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">密码</label>
        <input type="password" id="page-login-password" name="password" class="form-input" placeholder="输入密码" required autocomplete="current-password">
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <?= CaptchaService::renderHtml('login') ?>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
        <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; color: var(--text-secondary); cursor: pointer;">
          <input type="checkbox" name="remember" value="1" checked style="accent-color: var(--primary);">
          <span>30 天内保持登录</span>
        </label>
        <a href="<?= Url::route('auth/forgotPassword') ?>" style="font-size: 0.8125rem; color: var(--primary); text-decoration: none;">忘记密码？</a>
      </div>

      <button type="submit" class="btn-store-primary" style="width: 100%; height: 38px; font-size: 0.875rem;">
        <span>登录</span>
      </button>
    </form>

    <div style="margin-top: 18px; text-align: center; font-size: 0.8125rem; color: var(--text-muted);">
      还没有账号？<a href="<?= Url::route('auth/register', ['redirect' => $redirect]) ?>" style="color: var(--primary); text-decoration: none;">立即注册</a>
    </div>
  </div>
</div>
