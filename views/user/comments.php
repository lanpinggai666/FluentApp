<?php
require VIEW_PATH . '/user/layout_nav.php';
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">我的评价</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      您在各软件详情页发表的评分与评价记录。
    </div>
  </div>
</div>

<div class="admin-card">
  <?php if (empty($comments)): ?>
    <div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
      暂无发表的软件评价。前往软件详情页体验并写下您的评语吧！
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 12px;">
      <?php foreach ($comments as $cm): ?>
        <div style="padding: 14px 16px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-weight: 600; font-size: 0.9375rem; color: var(--text-main);">
                <?= View::e($cm['software_name']) ?>
              </span>
              <span style="color: #f59e0b; font-size: 0.875rem; letter-spacing: 2px;">
                <?= str_repeat('★', (int)$cm['rating']) . str_repeat('☆', 5 - (int)$cm['rating']) ?>
              </span>
              <?php if (($cm['status'] ?? 'approved') === 'approved'): ?>
                <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: #16a34a; font-size: 0.75rem;">已公开展示</span>
              <?php else: ?>
                <span class="badge" style="background: rgba(234, 179, 8, 0.12); color: #ca8a04; font-size: 0.75rem;">审核中/屏蔽</span>
              <?php endif; ?>
            </div>
            <div>
              <span style="font-size: 0.75rem; color: var(--text-muted); margin-right: 10px;">
                <?= date('Y-m-d H:i', strtotime($cm['created_at'])) ?>
              </span>
              <a href="<?= Url::detail($cm['software_slug']) ?>" target="_blank" class="btn-store-secondary" style="height: 26px; padding: 0 8px; font-size: 0.75rem;">
                查看
              </a>
            </div>
          </div>
          <div style="font-size: 0.875rem; color: var(--text-main); line-height: 1.5;">
            <?= nl2br(View::e($cm['content'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
