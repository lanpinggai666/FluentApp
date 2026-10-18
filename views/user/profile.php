<?php
require VIEW_PATH . '/user/layout_nav.php';
$csrfToken = Auth::csrfToken();
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">账号与资料设置</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      管理昵称、头像、密码等。
    </div>
  </div>
</div>

<?php if (!empty($success)): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 20px; font-size: 0.875rem;">
    <?= View::e($success) ?>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 20px; font-size: 0.875rem;">
    <?= View::e($error) ?>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 24px; align-items: flex-start;">

  <!-- 左侧：基本资料与密码修改 -->
  <div style="display: flex; flex-direction: column; gap: 24px;">

    <!-- 1. 基本资料设置 -->
    <div class="admin-card" style="margin-bottom: 0;">
      <h3 style="font-size: 1.0625rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('person', 'fluent-icon-sm') ?>
        <span>基本资料设置</span>
      </h3>

      <form method="POST" action="<?= Url::route('user/updateProfile') ?>">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <!-- 头像预览与自定义链接 -->
        <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 20px; padding: 14px; background: var(--bg-surface-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
          <div style="flex-shrink: 0; position: relative;">
            <img id="avatar-preview-img" src="<?= View::e($user['avatar_url'] ?: 'assets/images/default-app.svg') ?>" alt="" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; background: #fff; border: 2px solid var(--border-default); box-shadow: var(--shadow-sm);" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><circle cx=%2210%22 cy=%2210%22 r=%2210%22 fill=%22%23cbd5e1%22/></svg>'">
          </div>
          <div style="min-width: 0; flex: 1;">
            <div style="font-size: 0.875rem; font-weight: 600; color: var(--text-main);">当前头像</div>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">用户昵称 <span style="color:#ef4444;">*</span></label>
          <input type="text" name="username" class="form-input" required value="<?= View::e($user['username']) ?>" placeholder="纯字母或字母+数字 (3-24位)" pattern="^[a-zA-Z0-9]{3,24}$" title="只能由纯字母或字母与数字组成（3-24位）">
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">用于全站评论、个人主页展示与快速登录。</div>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">头像链接</label>
          <input type="url" name="avatar_url" id="avatar-url-input" class="form-input" value="<?= View::e($user['avatar_url']) ?>" placeholder="https://example.com/avatar.png" oninput="previewAvatar(this.value)">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">注册绑定邮箱</label>
          <input type="text" class="form-input" disabled value="<?= View::e($user['email']) ?>" style="background: var(--bg-surface-secondary); opacity: 0.8;">
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
          <div class="captcha-math-row" style="background: var(--bg-surface-secondary); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <?= $captchaProfile ?>
          </div>
        </div>

        <button type="submit" class="btn-store-primary" style="height: 36px; padding: 0 20px; font-size: 0.875rem;">
          保存个人资料
        </button>
      </form>
    </div>

    <!-- 2. 安全密码修改 -->
    <div class="admin-card" style="margin-bottom: 0;">
      <h3 style="font-size: 1.0625rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
        <span>登录密码修改</span>
      </h3>

      <form method="POST" action="<?= Url::route('user/changePassword') ?>">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <?php if (!empty($user['password_hash'])): ?>
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">原密码 <span style="color:#ef4444;">*</span></label>
            <input type="password" name="old_password" class="form-input" required placeholder="请输入当前登录密码" autocomplete="current-password">
          </div>
        <?php else: ?>
          <div style="padding: 10px 14px; background: rgba(0, 103, 184, 0.08); border-radius: var(--radius-sm); font-size: 0.8125rem; color: var(--primary); margin-bottom: 16px;">
            提示：您当前通过第三方授权创建，尚未设置独立登录密码。设置后即可支持昵称/邮箱加密码直接登录。
          </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">新密码 <span style="color:#ef4444;">*</span></label>
          <input type="password" name="new_password" class="form-input" required minlength="6" placeholder="至少 6 位字符" autocomplete="new-password">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">确认新密码 <span style="color:#ef4444;">*</span></label>
          <input type="password" name="confirm_password" class="form-input" required minlength="6" placeholder="再次输入新密码" autocomplete="new-password">
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
          <div class="captcha-math-row" style="background: var(--bg-surface-secondary); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <?= $captchaPassword ?>
          </div>
        </div>

        <button type="submit" class="btn-store-primary" style="height: 36px; padding: 0 20px; font-size: 0.875rem;">
          确认更新密码
        </button>
      </form>
    </div>

  </div>

  <!-- 右侧：第三方账号绑定与头像同步 -->
  <div class="admin-card" style="margin-bottom: 0;">
    <h3 style="font-size: 1.0625rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
      <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
      <span>第三方快捷登录与绑定</span>
    </h3>

    <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 20px;">
      绑定第三方平台后，您可直接使用对应账号一键登录。
    </p>

    <div style="display: flex; flex-direction: column; gap: 16px;">

      <!-- Google 绑定项 -->
      <?php 
        $googleBound = $boundMap['google'] ?? null; 
      ?>
      <div style="padding: 16px; border-radius: var(--radius-md); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-subtle); flex-shrink: 0;">
              <svg width="20" height="20" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
            </div>
            <div>
              <div style="font-weight: 600; font-size: 0.9375rem; color: var(--text-main);">Google 账号</div>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                <?php if ($googleBound): ?>
                  <span style="color: #16a34a; font-weight: 600;">已绑定</span> · <?= View::e($googleBound['provider_email'] ?: $googleBound['provider_name']) ?>
                <?php else: ?>
                  <span>未绑定</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div>
            <?php if ($googleBound): ?>
              <form method="POST" action="<?= Url::route('user/unbindOAuth') ?>" style="margin: 0; display: inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="provider" value="google">
                <button type="submit" class="btn-store-secondary" style="height: 30px; padding: 0 10px; font-size: 0.75rem; color: #dc2626;"
                        data-confirm="确认解除与 Google 账号的关联绑定？解除后将无法使用 Google 快捷登录。"
                        data-confirm-title="解除绑定确认"
                        data-confirm-ok="确认解绑"
                        data-confirm-tone="danger">
                  解除绑定
                </button>
              </form>
            <?php else: ?>
              <a href="<?= Url::route('auth/oauthLogin', ['provider' => 'google', 'redirect' => Url::route('user/profile')]) ?>" class="btn-store-primary" style="height: 30px; padding: 0 12px; font-size: 0.75rem;">
                立即绑定
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($googleBound && !empty($googleBound['provider_avatar'])): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px dashed var(--border-subtle); margin-top: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <img src="<?= View::e($googleBound['provider_avatar']) ?>" alt="" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
              <span style="font-size: 0.75rem; color: var(--text-muted);">Google 官方头像</span>
            </div>
            <form method="POST" action="<?= Url::route('user/useOAuthAvatar') ?>" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
              <input type="hidden" name="provider" value="google">
              <button type="submit" class="btn-store-secondary" style="height: 26px; padding: 0 8px; font-size: 0.75rem;">
                使用此头像
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- Microsoft 绑定项 -->
      <?php 
        $msBound = $boundMap['microsoft'] ?? null; 
      ?>
      <div style="padding: 16px; border-radius: var(--radius-md); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-subtle); flex-shrink: 0;">
              <svg width="20" height="20" viewBox="0 0 24 24"><path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#00a4ef" d="M1 13h10v10H1z"/><path fill="#7fba00" d="M13 1h10v10H13z"/><path fill="#ffb900" d="M13 13h10v10H13z"/></svg>
            </div>
            <div>
              <div style="font-weight: 600; font-size: 0.9375rem; color: var(--text-main);">Microsoft 微软账号</div>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                <?php if ($msBound): ?>
                  <span style="color: #16a34a; font-weight: 600;">已绑定</span> · <?= View::e($msBound['provider_email'] ?: $msBound['provider_name']) ?>
                <?php else: ?>
                  <span>未绑定</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div>
            <?php if ($msBound): ?>
              <form method="POST" action="<?= Url::route('user/unbindOAuth') ?>" style="margin: 0; display: inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="provider" value="microsoft">
                <button type="submit" class="btn-store-secondary" style="height: 30px; padding: 0 10px; font-size: 0.75rem; color: #dc2626;"
                        data-confirm="确认解除与 Microsoft 账号的关联绑定？解除后将无法使用 Microsoft 快捷登录。"
                        data-confirm-title="解除绑定确认"
                        data-confirm-ok="确认解绑"
                        data-confirm-tone="danger">
                  解除绑定
                </button>
              </form>
            <?php else: ?>
              <a href="<?= Url::route('auth/oauthLogin', ['provider' => 'microsoft', 'redirect' => Url::route('user/profile')]) ?>" class="btn-store-primary" style="height: 30px; padding: 0 12px; font-size: 0.75rem;">
                立即绑定
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($msBound && !empty($msBound['provider_avatar'])): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px dashed var(--border-subtle); margin-top: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <img src="<?= View::e($msBound['provider_avatar']) ?>" alt="" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;">
              <span style="font-size: 0.75rem; color: var(--text-muted);">Microsoft 头像</span>
            </div>
            <form method="POST" action="<?= Url::route('user/useOAuthAvatar') ?>" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
              <input type="hidden" name="provider" value="microsoft">
              <button type="submit" class="btn-store-secondary" style="height: 26px; padding: 0 8px; font-size: 0.75rem;">
                使用此头像
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

</div>

<script>
function previewAvatar(url) {
  const img = document.getElementById('avatar-preview-img');
  if (!img) return;
  url = (url || '').trim();
  if (url.startsWith('http://') || url.startsWith('https://')) {
    img.src = url;
  }
}
</script>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
