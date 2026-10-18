<?php require VIEW_PATH . '/admin/layout_nav.php'; ?>

<div class="admin-card">
  <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">结构升级</h2>
  <p style="font-size: .8125rem; color: var(--text-muted); margin: 0 0 14px;">
    PHP 装在容器里、不方便进命令行时，在这里把 <code>database/migrations/*.sql</code> 补齐。
    执行逻辑与 <code>php database/migrate.php</code> 是同一份（<code>core/Migrator.php</code>），
    要跑的语句只来自代码仓库里的文件 —— 这个页面没有任何输入能拼进 SQL。
  </p>

  <?php if (!empty($_GET['success'])): ?>
    <div style="background:rgba(16,185,129,.10); border:1px solid rgba(16,185,129,.35); color:#065f46; border-radius:8px; padding:10px 14px; font-size:.8125rem; margin-bottom:14px;">
      ✓ <?= View::e((string) $_GET['success']) ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
    <div style="background:rgba(239,68,68,.10); border:1px solid rgba(239,68,68,.35); color:#991b1b; border-radius:8px; padding:10px 14px; font-size:.8125rem; margin-bottom:14px;">
      ✗ <?= View::e((string) $_GET['error']) ?>
    </div>
  <?php endif; ?>

  <div style="display:flex; gap:18px; flex-wrap:wrap; font-size:.8125rem; margin-bottom:16px;">
    <span>驱动：<b><?= View::e($driver) ?></b></span>
    <span>代码基线：<b>v<?= (int) $baseline ?></b></span>
    <span>库里已登记：<b><?= $applied === [] ? '（无）' : View::e('v' . implode('、v', array_keys($applied))) ?></b></span>
    <span>待执行：<b><?= $pending === [] ? '（无）' : View::e('v' . implode('、v', array_column($pending, 'version'))) ?></b></span>
  </div>

  <?php if ($skipped !== []): ?>
    <div style="background:rgba(245,158,11,.10); border:1px solid rgba(245,158,11,.35); color:#92400e; border-radius:8px; padding:10px 14px; font-size:.8125rem; margin-bottom:14px;">
      命名不合规、已忽略的文件：<?= View::e(implode('、', $skipped)) ?>
      （应为 <code>NNNN_说明.sql</code>，方言专属再加 <code>.sqlite</code> / <code>.mysql</code>）
    </div>
  <?php endif; ?>

  <?php if ($pending === []): ?>
    <div style="font-size:.875rem;">结构已是最新。页面底部的「重新同步安装标记」可以只把 <code>config/install.lock</code> 里的版本号对齐到代码基线。</div>
  <?php else: ?>
    <form method="POST" action="<?= Url::route('admin/migrate') ?>" style="margin:0;">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="action" value="run">

      <?php foreach ($pending as $item): ?>
        <div style="border:1px solid var(--border-subtle); border-radius:8px; padding:12px 14px; margin-bottom:12px;">
          <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
            <b style="font-size:.9375rem;">v<?= (int) $item['version'] ?></b>
            <span style="font-size:.8125rem; color:var(--text-muted);"><?= View::e($item['names']) ?></span>
            <?php if ($item['destructive']): ?>
              <span style="background:rgba(239,68,68,.12); color:#b91c1c; font-size:.75rem; padding:2px 8px; border-radius:999px;">含不可回滚语句</span>
            <?php endif; ?>
          </div>
          <pre style="background:var(--bg-page); border:1px solid var(--border-subtle); border-radius:6px; padding:10px; font-size:.75rem; overflow-x:auto; max-height:220px; margin:0;"><?= View::e($item['sql']) ?></pre>
        </div>
      <?php endforeach; ?>

      <?php $hasDestructive = false; foreach ($pending as $item) { $hasDestructive = $hasDestructive || $item['destructive']; } ?>
      <?php if ($hasDestructive): ?>
        <label style="display:flex; align-items:flex-start; gap:8px; font-size:.8125rem; margin-bottom:12px; cursor:pointer;">
          <input type="checkbox" name="confirm_destructive" value="1" style="margin-top:2px; accent-color:#dc2626;">
          <span>我已经备份好数据库（SQLite 连 <code>-wal</code> 一起复制 / MySQL 用 <code>mysqldump --single-transaction</code>），
            确认可以执行含 DROP / TRUNCATE 的迁移。</span>
        </label>
      <?php endif; ?>

      <button type="submit" class="btn-store-primary" style="height:38px; padding:0 18px;">
        执行 <?= count($pending) ?> 个待处理迁移
      </button>
    </form>
  <?php endif; ?>

  <details style="margin-top:20px; border-top:1px solid var(--border-light); padding-top:14px;">
    <summary style="cursor:pointer; font-size:.875rem; font-weight:600;">高级：手工对齐（不推荐，除非你确认结构已经一致）</summary>
    <p style="font-size:.8125rem; color:var(--text-muted); margin:8px 0;">
      只往 <code>schema_migrations</code> 写一条登记，<b>不改任何表</b>。用于「我已经在容器外手工跑过 SQL」的情况 ——
      不登记的话，下次迁移会在错的基础上重跑。填 <code>baseline</code> 表示登记成当前代码基线 v<?= (int) $baseline ?>。
    </p>
    <form method="POST" action="<?= Url::route('admin/migrate') ?>" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="action" value="stamp">
      <input type="text" name="stamp_version" class="form-input" style="max-width:160px;" placeholder="如 <?= (int) $baseline ?> 或 baseline" required>
      <button type="submit" class="btn-store-secondary" style="height:36px; padding:0 14px;">只登记版本号</button>
    </form>
  </details>
</div>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
