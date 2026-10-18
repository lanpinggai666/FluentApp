<?php
/**
 * 蓝屏钙软件导航网 - 全局用户登录/注册/找回密码模态框 (Fluent 风格)
 */
$allowedDomains = User::getAllowedDomains();
$googleClientId = trim(Setting::get('oauth_google_client_id', ''));
$msClientId = trim(Setting::get('oauth_ms_client_id', ''));
$githubClientId = trim(Setting::get('oauth_github_client_id', ''));
?>

<div class="modal-overlay" id="auth-modal" style="z-index: 100010;">
  <div class="modal-container auth-modal-container" style="max-width: 440px;">
    <div class="modal-header">
      <div class="modal-title auth-modal-title">
        <span class="auth-modal-logo"><?= FluentIcon::render('person', 'fluent-icon') ?></span>
        <span id="auth-modal-title-text">登录账号</span>
      </div>
      <button type="button" class="notice-close" onclick="closeAuthModal()" title="关闭">
        <?= FluentIcon::render('dismiss') ?>
      </button>
    </div>

    <div class="modal-body auth-modal-body" style="padding: 16px 20px 20px;">
      <!-- 选项卡切换: 登录 / 注册 / 找回密码 -->
      <div class="auth-tabs" style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 8px;">
        <button type="button" class="auth-tab active" id="auth-tab-login" onclick="switchAuthTab('login')">登录</button>
        <button type="button" class="auth-tab" id="auth-tab-register" onclick="switchAuthTab('register')">注册</button>
        <button type="button" class="auth-tab" id="auth-tab-forgot" onclick="switchAuthTab('forgot')">找回密码</button>
      </div>

      <!-- 第三方快速登录入口 (仅在登录/注册面板展示) -->
      <div id="auth-oauth-section" class="oauth-section" style="margin-bottom: 14px;">
        <div class="oauth-buttons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
          <button type="button" class="btn-oauth btn-oauth-ms" onclick="startOAuthLogin('microsoft')" title="使用 Microsoft 账号登录">
            <svg width="16" height="16" viewBox="0 0 21 21">
              <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
              <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
              <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
              <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
            </svg>
            <span>Microsoft 登录</span>
          </button>

          <button type="button" class="btn-oauth btn-oauth-google" onclick="startOAuthLogin('google')" title="使用 Google 账号登录">
            <svg width="16" height="16" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Google 登录</span>
          </button>
        </div>

        <?php /* Google 凭证回调由 app.js 里 google.accounts.id.initialize() 命令式注册。
                 这里不放声明式的.onload 容器：两套并存时 GSI 会以声明式为准，
                 One Tap 的「只在未登录 + 每会话一次 + 可被用户关掉」这几道抑制就全废了。 */ ?>

        <div class="auth-divider" style="margin: 12px 0 10px;">
          <span>或</span>
        </div>
      </div>

      <!-- 提示消息容器 -->
      <div id="auth-error-msg" class="auth-alert auth-alert-error" style="display: none; padding: 8px 12px; font-size: 0.8125rem; margin-bottom: 12px;"></div>
      <div id="auth-success-msg" class="auth-alert auth-alert-success" style="display: none; padding: 8px 12px; font-size: 0.8125rem; margin-bottom: 12px;"></div>

      <!-- 1. 登录表单 -->
      <form id="auth-login-form" onsubmit="handleAuthLogin(event)">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="login-account" style="font-size: 0.8125rem; margin-bottom: 4px;">账号 (昵称或邮箱)</label>
          <input type="text" id="login-account" name="account" class="form-input" placeholder="输入昵称或邮箱" required autocomplete="username">
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="login-password" style="font-size: 0.8125rem; margin-bottom: 4px;">登录密码</label>
          <input type="password" id="login-password" name="password" class="form-input" placeholder="输入密码" required autocomplete="current-password">
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <?= CaptchaService::renderHtml('modal_login') ?>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; color: var(--text-secondary); cursor: pointer;">
            <input type="checkbox" name="remember" value="1" checked style="accent-color: var(--primary);">
            <span>30 天内保持登录</span>
          </label>
          <a href="javascript:void(0)" onclick="switchAuthTab('forgot')" style="font-size: 0.8125rem; color: var(--primary); text-decoration: none;">忘记密码？</a>
        </div>

        <button type="submit" class="btn-store-primary auth-submit-btn" id="btn-login-submit" style="width: 100%; height: 36px;">
          <span>登录</span>
        </button>

        <div class="auth-switch-note" style="text-align: center; margin-top: 12px; font-size: 0.8125rem; color: var(--text-muted);">
          还没有账号？<a href="javascript:void(0)" onclick="switchAuthTab('register')" style="color: var(--primary);">立即注册</a>
        </div>
      </form>

      <!-- 2. 注册表单 -->
      <form id="auth-register-form" onsubmit="handleAuthRegister(event)" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="reg-username" style="font-size: 0.8125rem; margin-bottom: 4px;">昵称 (纯字母或字母+数字)</label>
          <input type="text" id="reg-username" name="username" class="form-input" placeholder="3-20 位字母/数字" required pattern="^[a-zA-Z0-9_]{3,20}$" autocomplete="username">
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 4px;">常用电子邮箱</label>
          <div style="display: flex; gap: 6px;">
            <input type="text" id="reg-email-prefix" name="email_prefix" class="form-input" placeholder="邮箱前缀" required style="flex: 1;" autocomplete="off">
            <select id="reg-email-suffix" name="email_suffix" class="form-select" style="width: 140px; flex-shrink: 0;">
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

        <div class="form-group" style="margin-bottom: 12px;">
          <?= CaptchaService::renderHtml('modal_reg') ?>
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="reg-email-code" style="font-size: 0.8125rem; margin-bottom: 4px;">邮箱验证码</label>
          <div style="display: flex; gap: 8px;">
            <input type="text" id="reg-email-code" name="email_code" class="form-input" placeholder="6 位数字验证码" required maxlength="6" style="flex: 1;" autocomplete="off">
            <button type="button" class="btn-store-secondary" id="btn-reg-send-code" onclick="sendAuthCode(this, 'register', 'reg')" style="height: 36px; padding: 0 12px; font-size: 0.75rem; white-space: nowrap; flex-shrink: 0;">
              获取验证码
            </button>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="reg-password" style="font-size: 0.8125rem; margin-bottom: 4px;">设置密码</label>
          <input type="password" id="reg-password" name="password" class="form-input" placeholder="至少 6 位" required minlength="6" autocomplete="new-password">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" for="reg-password-confirm" style="font-size: 0.8125rem; margin-bottom: 4px;">确认密码</label>
          <input type="password" id="reg-password-confirm" name="password_confirm" class="form-input" placeholder="再次输入密码" required minlength="6" autocomplete="new-password">
        </div>

        <button type="submit" class="btn-store-primary auth-submit-btn" id="btn-register-submit" style="width: 100%; height: 36px;">
          <span>注册并登录</span>
        </button>

        <div class="auth-switch-note" style="text-align: center; margin-top: 12px; font-size: 0.8125rem; color: var(--text-muted);">
          已有账号？<a href="javascript:void(0)" onclick="switchAuthTab('login')" style="color: var(--primary);">直接登录</a>
        </div>
      </form>

      <!-- 3. 找回密码表单 -->
      <form id="auth-forgot-form" onsubmit="handleAuthForgot(event)" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 4px;">注册邮箱</label>
          <div style="display: flex; gap: 6px;">
            <input type="text" id="forgot-email-prefix" name="email_prefix" class="form-input" placeholder="邮箱前缀" required style="flex: 1;" autocomplete="off">
            <select id="forgot-email-suffix" name="email_suffix" class="form-select" style="width: 140px; flex-shrink: 0;">
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

        <div class="form-group" style="margin-bottom: 12px;">
          <?= CaptchaService::renderHtml('modal_forgot') ?>
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="forgot-email-code" style="font-size: 0.8125rem; margin-bottom: 4px;">邮箱验证码</label>
          <div style="display: flex; gap: 8px;">
            <input type="text" id="forgot-email-code" name="email_code" class="form-input" placeholder="6 位数字验证码" required maxlength="6" style="flex: 1;" autocomplete="off">
            <button type="button" class="btn-store-secondary" id="btn-forgot-send-code" onclick="sendAuthCode(this, 'reset_password', 'forgot')" style="height: 36px; padding: 0 12px; font-size: 0.75rem; white-space: nowrap; flex-shrink: 0;">
              获取验证码
            </button>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="forgot-password" style="font-size: 0.8125rem; margin-bottom: 4px;">新密码</label>
          <input type="password" id="forgot-password" name="new_password" class="form-input" placeholder="至少 6 位" required minlength="6" autocomplete="new-password">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" for="forgot-password-confirm" style="font-size: 0.8125rem; margin-bottom: 4px;">确认新密码</label>
          <input type="password" id="forgot-password-confirm" name="new_password_confirm" class="form-input" placeholder="再次输入新密码" required minlength="6" autocomplete="new-password">
        </div>

        <button type="submit" class="btn-store-primary auth-submit-btn" id="btn-forgot-submit" style="width: 100%; height: 36px;">
          <span>重置密码并登录</span>
        </button>

        <div class="auth-switch-note" style="text-align: center; margin-top: 12px; font-size: 0.8125rem; color: var(--text-muted);">
          想起密码？<a href="javascript:void(0)" onclick="switchAuthTab('login')" style="color: var(--primary);">直接登录</a>
        </div>
      </form>
    </div>
  </div>
</div>
