<?php
require VIEW_PATH . '/user/layout_nav.php';
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">我管理的软件</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      审核通过后您作为该软件信息的管理者，可随时直接修改软件信息。
    </div>
  </div>
  <div>
    <a href="<?= Url::route('submit') ?>" class="btn-store-primary" style="height: 36px; padding: 0 16px; font-size: 0.875rem;">
      <?= FluentIcon::render('add', 'fluent-icon-sm') ?>
      <span>提交新软件</span>
    </a>
  </div>
</div>

<div class="admin-card">
  <?php if (empty($softwares)): ?>
    <div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
      您当前还没有提交或管理的软件。<br><br>
      <a href="<?= Url::route('submit') ?>" class="btn-store-primary" style="display: inline-flex; height: 34px; padding: 0 14px; font-size: 0.8125rem;">
        立即提交新软件
      </a>
    </div>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>软件</th>
            <th>分类</th>
            <th>状态</th>
            <th>数据</th>
            <th>提交时间</th>
            <th style="text-align: right;">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($softwares as $sw): ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <img src="<?= View::e($sw['icon_url'] ?: 'assets/images/default-app.svg') ?>" alt="" style="width: 38px; height: 38px; border-radius: 8px; object-fit: contain; background: #fff; padding: 2px; border: 1px solid var(--border-subtle); flex-shrink: 0;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><rect width=%2220%22 height=%2220%22 fill=%22%23cbd5e1%22/></svg>'">
                  <div>
                    <div style="font-weight: 600; color: var(--text-main);">
                      <?= View::e($sw['name']) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                      <?= View::e($sw['summary'] ?: '暂无简介') ?>
                    </div>
                  </div>
                </div>
              </td>
              <td style="color: var(--text-muted);">
                <?= View::e($sw['category_name'] ?: '未分类') ?>
              </td>
              <td>
                <?php if ($sw['status'] === 'published'): ?>
                  <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: #16a34a;">已收录</span>
                <?php elseif ($sw['status'] === 'pending'): ?>
                  <span class="badge" style="background: rgba(234, 179, 8, 0.12); color: #ca8a04;">待审核</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626;">已下架/拒绝</span>
                <?php endif; ?>
              </td>
              <td style="color: var(--text-muted); font-size: 0.8125rem;">
                浏览 <?= (int)($sw['views_count'] ?? $sw['views'] ?? 0) ?> · 下载 <?= (int)($sw['download_clicks'] ?? $sw['downloads'] ?? 0) ?>
              </td>
              <td style="color: var(--text-muted); font-size: 0.8125rem;">
                <?= date('Y-m-d H:i', strtotime($sw['created_at'])) ?>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 8px;">
                  <a href="<?= Url::route('user/editSoftware', ['id' => (int)$sw['id']]) ?>" class="btn-store-primary" style="height: 30px; padding: 0 12px; font-size: 0.75rem;">
                    修改
                  </a>
                  <a href="<?= Url::detail($sw['slug']) ?>" target="_blank" class="btn-store-secondary" style="height: 30px; padding: 0 10px; font-size: 0.75rem;">
                    前台主页
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
