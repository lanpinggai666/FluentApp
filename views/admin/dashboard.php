<?php
require VIEW_PATH . '/admin/layout_nav.php';
?>

<div class="admin-stat-grid">
  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('folder', 'fluent-icon-sm') ?>
      <span>已收录软件</span>
    </div>
    <div class="admin-stat-val" style="color: var(--ms-blue);"><?= $totalSoftwares ?></div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
      <span>待审核提交</span>
    </div>
    <div class="admin-stat-val" style="color: #f59e0b;"><?= $pendingCount ?></div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('desktop', 'fluent-icon-sm') ?>
      <span>全网总浏览量</span>
    </div>
    <div class="admin-stat-val" style="color: var(--text-primary);"><?= number_format($totalViews) ?></div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('comment', 'fluent-icon-sm') ?>
      <span>用户评价数</span>
    </div>
    <div class="admin-stat-val" style="color: #10b981;"><?= $totalComments ?></div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('flag', 'fluent-icon-sm') ?>
      <span>待处理举报</span>
    </div>
    <div class="admin-stat-val" style="color: #ef4444;"><?= $pendingReports ?></div>
  </div>
</div>

<!-- 待审核软件预览 -->
<div class="admin-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
    <h3 style="font-size: 1.0625rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
      <?= FluentIcon::render('shield-checkmark', 'fluent-icon') ?>
      <span>最新待审核提交</span>
    </h3>
    <a href="<?= Url::route('admin/pending') ?>" class="btn-store-secondary" style="font-size: 0.8125rem; height: 32px; padding: 0 14px;">查看全部 (<?= $pendingCount ?>)</a>
  </div>

  <?php if (empty($recentPending)): ?>
    <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
      太棒了！当前没有待审核的软件提交。
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <?php foreach ($recentPending as $item): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 16px; background: var(--bg-surface-secondary); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
          <div style="display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1;">
            <img src="<?= View::e($item['icon_url']) ?>" style="width: 38px; height: 38px; border-radius: 8px; object-fit: contain; background: var(--bg-surface); padding: 2px; border: 1px solid var(--border-subtle); flex-shrink: 0;" alt="" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><rect width=%2220%22 height=%2220%22 fill=%22%23cbd5e1%22/></svg>'">
            <div style="min-width: 0;">
              <div style="font-weight: 600; font-size: 0.9375rem; color: var(--text-primary);"><?= View::e($item['name']) ?></div>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                官网: <a href="<?= Url::href($item['official_url']) ?>" target="_blank" style="color: var(--ms-blue);"><?= View::e($item['official_url']) ?></a> · 提交于 <?= substr($item['created_at'], 0, 16) ?>
              </div>
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-shrink: 0;">
            <form action="<?= Url::route('admin/review') ?>" method="POST" style="display:inline;">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <input type="hidden" name="action" value="approve_ai">
              <button type="submit" class="btn-store-primary" style="font-size: 0.8125rem; height: 32px; padding: 0 14px;">
                <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?> 一键 AI 补全并发布
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 待处理举报预览 -->
<?php if (!empty($recentReports)): ?>
  <div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="font-size: 1.0625rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('flag', 'fluent-icon') ?>
        <span>待处理用户举报 (<?= count($recentReports) ?>)</span>
      </h3>
      <a href="<?= Url::route('admin/reports') ?>" class="btn-store-secondary" style="font-size: 0.8125rem; height: 32px; padding: 0 14px;">处理详情</a>
    </div>

    <div style="display: flex; flex-direction: column; gap: 10px;">
      <?php foreach ($recentReports as $rep): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: var(--bg-surface-secondary); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
          <div>
            <span class="badge" style="background: #fee2e2; color: #b91c1c; font-weight: 600;"><?= View::e($rep['reason']) ?></span>
            <strong style="margin-left: 8px; color: var(--text-primary);"><?= View::e($rep['software_name'] ?? '未知软件') ?></strong>
            <span style="font-size: 0.8125rem; color: var(--text-muted); margin-left: 8px;"><?= View::e($rep['details'] ?: '无具体说明') ?></span>
          </div>
          <form action="<?= Url::route('admin/reports') ?>" method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id" value="<?= $rep['id'] ?>">
            <input type="hidden" name="status" value="resolved">
            <button type="submit" class="btn-store-secondary" style="font-size: 0.75rem; height: 28px; padding: 0 10px;">标记已核实</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
