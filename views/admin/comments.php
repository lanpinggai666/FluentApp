<?php
require VIEW_PATH . '/admin/layout_nav.php';
?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
  <!-- 用户评价管理 -->
  <div class="admin-card">
    <h2 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
      <?= FluentIcon::render('comment') ?>
      <span>最新用户评价 (<?= count($comments) ?>)</span>
    </h2>

    <div style="overflow-x: auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>软件</th>
            <th>昵称/评分</th>
            <th>内容</th>
            <th style="text-align: right;">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($comments as $c): ?>
            <tr>
              <td style="font-weight: 600;"><?= View::e($c['software_name'] ?? '已删') ?></td>
              <td>
                <div><?= View::e($c['nickname']) ?></div>
                <div style="color: #eab308;">★ <?= (int)$c['rating'] ?></div>
              </td>
              <td style="color: var(--text-secondary); max-width: 220px;">
                <?= nl2br(View::e($c['content'])) ?>
              </td>
              <td style="text-align: right;">
                <form action="<?= Url::route('admin/comments') ?>" method="POST" style="display:inline;"
                      data-confirm="确定删除该评价吗？删除后无法恢复。"
                      data-confirm-title="删除评价"
                      data-confirm-ok="删除"
                      data-confirm-tone="danger">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="delete_comment">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn-card-action" style="color: #ef4444; background: #fee2e2;">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 违禁敏感词过滤库 -->
  <div class="admin-card">
    <h2 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
      <?= FluentIcon::render('shield') ?>
      <span>敏感词过滤库</span>
    </h2>

    <form action="<?= Url::route('admin/comments') ?>" method="POST" style="margin-bottom: 16px;">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="add_word">
      <div style="display: flex; flex-direction: column; gap: 8px;">
        <textarea name="words" class="form-input" placeholder="输入要屏蔽的词汇，支持每行一个批量添加" rows="3" required style="line-height: 1.4; font-size: 0.8125rem;"></textarea>
        <div>
          <button type="submit" class="btn-store-primary" style="padding: 0 16px; height: 34px; font-size: 0.8125rem;">批量添加敏感词</button>
        </div>
      </div>
    </form>

    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
      <?php foreach ($sensitiveWords as $w): ?>
        <span class="badge" style="padding: 4px 8px; font-size: 0.75rem; background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle); display: inline-flex; align-items: center; gap: 6px;">
          <?= View::e($w['word']) ?>
          <form action="<?= Url::route('admin/comments') ?>" method="POST" style="display:inline; margin: 0;"
                data-confirm="确认从过滤库中移除敏感词「<?= View::e($w['word']) ?>」？"
                data-confirm-title="移除敏感词"
                data-confirm-ok="移除"
                data-confirm-tone="warning">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete_word">
            <input type="hidden" name="id" value="<?= $w['id'] ?>">
            <button type="submit" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:0;line-height:1;" title="删除词汇">&times;</button>
          </form>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
