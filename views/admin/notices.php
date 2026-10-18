<?php
require VIEW_PATH . '/admin/layout_nav.php';
?>

<div class="admin-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('megaphone') ?>
        <span>网站公告横幅发布</span>
      </h2>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">
        发布的有效公告将展示在全站最上方，用户点击可查看公告详情弹窗。
      </p>
    </div>
    <button class="btn-store-primary" onclick="openModal('add-notice-modal')">
      <?= FluentIcon::render('plus', 'fluent-icon-sm') ?> 发布新公告
    </button>
  </div>

  <div style="overflow-x: auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>公告标题</th>
          <th>内容预览</th>
          <th>发布时间</th>
          <th style="text-align: right;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notices as $n): ?>
          <tr>
            <td style="font-weight: 600;"><?= View::e($n['title']) ?></td>
            <td style="color: var(--text-secondary); max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= View::e($n['content']) ?>
            </td>
            <td style="font-size: 0.75rem; color: var(--text-muted);"><?= substr($n['created_at'], 0, 16) ?></td>
            <td style="text-align: right;">
              <form action="<?= Url::route('admin/notices') ?>" method="POST" style="display:inline;"
                    data-confirm="确定删除该公告吗？删除后将不再展示。"
                    data-confirm-title="删除公告"
                    data-confirm-ok="删除"
                    data-confirm-tone="danger">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn-card-action" style="color: #ef4444; background: #fee2e2;">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-notice-modal">
  <div class="modal-container">
    <div class="modal-header">
      <div class="modal-title">发布新公告</div>
      <button class="notice-close" onclick="closeModal('add-notice-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form action="<?= Url::route('admin/notices') ?>" method="POST" class="modal-body">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">公告标题</label>
        <input type="text" name="title" class="form-input" placeholder="输入横幅简短标题..." required>
      </div>
      <div class="form-group">
        <label class="form-label">公告详细内容</label>
        <textarea name="content" class="form-textarea" placeholder="输入公告详情，支持换行..." required style="min-height: 100px;"></textarea>
      </div>
      <div class="modal-footer" style="padding: 14px 0 0; display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('add-notice-modal')">取消</button>
        <button type="submit" class="btn-store-primary">发布上线</button>
      </div>
    </form>
  </div>
</div>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
