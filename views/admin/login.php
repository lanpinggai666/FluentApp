<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= View::e($title) ?></title>
  <?php require VIEW_PATH . '/partials/stylesheets.php'; ?>
  <link rel="stylesheet" href="<?= Url::basePath() ?>assets/css/components.css">
  <script>
    const saved = localStorage.getItem('theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
  </script>
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: var(--bg-body);">

  <div style="max-width: 400px; width: 100%; padding: 20px;">
    <div class="admin-card" style="padding: 32px; box-shadow: var(--shadow-fluent-hover); border-radius: var(--radius-lg);">
      
      <div style="text-align: center; margin-bottom: 24px;">
        <div style="color: var(--ms-blue); margin-bottom: 8px;">
          <?= FluentIcon::render('shield-checkmark', 'fluent-icon-lg') ?>
        </div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">管理后台登录</h2>
        <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">蓝屏钙软件导航网控制系统</p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 0.8125rem; margin-bottom: 16px;">
          <?= View::e($error) ?>
        </div>
      <?php endif; ?>

      <form action="<?= Url::route('admin/login') ?>" method="POST">
        <?= Auth::csrfField() ?>
        <div class="form-group">
          <label class="form-label">管理员账号</label>
          <input type="text" name="username" class="form-input" placeholder="默认: admin" required autocomplete="username">
        </div>

        <div class="form-group">
          <label class="form-label">登录密码</label>
          <input type="password" name="password" class="form-input" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-store-primary" style="width: 100%; justify-content: center; height: 40px; margin-top: 10px; font-size: 0.9375rem;">
          安全登录
        </button>
      </form>

      <div style="text-align: center; margin-top: 20px; font-size: 0.8125rem;">
        <a href="<?= Url::route('home') ?>" style="color: var(--text-muted);">&larr; 返回网站首页</a>
      </div>
    </div>
  </div>

  <script src="<?= Url::basePath() ?>assets/js/dialog.js"></script>
</body>
</html>
