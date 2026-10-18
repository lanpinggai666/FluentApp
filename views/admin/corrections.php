<?php
/**
 * 蓝屏钙软件导航网 - 后台修改信息审核
 *
 * 驳回必须填写理由，系统自动生成一次性安全修改链接并邮件通知提交者。
 */
require VIEW_PATH . '/admin/layout_nav.php';
?>

<?php if (!empty($success)): ?>
  <div class="admin-card" style="margin-bottom:16px;background:#dcfce7;color:#166534;">
    <?= View::e($success) ?>
  </div>
<?php endif; ?>

<?php if (!empty($notice)): ?>
  <div class="admin-card" style="margin-bottom:16px;background:#fee2e2;color:#b91c1c;">
    <?= View::e($notice) ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <div style="margin-bottom:20px;">
    <h2 style="font-size:1.25rem;font-weight:700;display:flex;align-items:center;gap:8px;">
      <?= FluentIcon::render('edit') ?>
      <span>修改信息审核</span>
    </h2>
    <p style="font-size:0.8125rem;color:var(--text-muted);margin-top:4px;">
      审核用户提交的软件资料修正和下载渠道。驳回必须填写理由，提交者将收到含安全修改链接的通知邮件。
    </p>
  </div>

  <?php if (empty($list)): ?>
    <div style="padding:48px;text-align:center;color:var(--text-muted);">
      <?= FluentIcon::render('checkmark', 'fluent-icon-lg') ?>
      <div style="margin-top:8px;">目前没有待审核的修改申请</div>
    </div>
  <?php else: ?>
    <div style="display:grid;gap:14px;">
      <?php foreach ($list as $item): ?>
        <?php
          $fields = [
              'name' => '软件名称',
              'icon_url' => '图标网址',
              'official_url' => '官网',
              'github_url' => 'GitHub',
              'category_id' => '分类 ID',
              'price_type' => '收费模式',
              'is_open_source' => '是否开源',
              'license' => '开源协议',
              'developer' => '开发者',
              'publisher' => '发布者',
              'tags' => '标签',
              'screenshot_url' => '截图网址',
          ];
          $channels = !empty($item['download_channels'])
              ? json_decode($item['download_channels'], true)
              : [];
        ?>
        <section class="admin-review-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px;">
            <div style="min-width:0;">
              <div style="font-size:1rem;font-weight:700;">
                <?= View::e($item['software_name'] ?? '已移除软件') ?>
              </div>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;display:flex;flex-wrap:wrap;gap:10px;">
                <span>邮件通知：<?= View::e($item['notification_status'] ?? 'pending') ?><?= !empty($item['notification_error']) ? ' · ' . View::e($item['notification_error']) : '' ?></span>
                <?php if (!empty($item['submitter_email'])): ?>
                  <span>提交邮箱:
                    <a href="mailto:<?= View::e($item['submitter_email']) ?>" style="color:var(--text-link);"><?= View::e($item['submitter_email']) ?></a>
                  </span>
                <?php else: ?>
                  <span style="color:#dc2626;">提交邮箱: 未填写（历史数据）</span>
                <?php endif; ?>
                <span><?= View::e($item['submitter_ip'] ?? '') ?></span>
                <span><?= View::e($item['created_at'] ?? '') ?></span>
                <?php if (!empty($item['contact'])): ?>
                  <span>其它联系: <?= View::e($item['contact']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!empty($item['software_slug'])): ?>
              <a class="btn-card-action" href="<?= Url::route('software/view', ['slug' => $item['software_slug']]) ?>" target="_blank" rel="noopener">
                查看软件
              </a>
            <?php endif; ?>
          </div>

          <div class="admin-review-body">
            <?php foreach ($fields as $field => $label): ?>
              <?php if (isset($item[$field]) && trim((string)$item[$field]) !== ''): ?>
                <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;font-size:0.875rem;margin-bottom:4px;">
                  <strong><?= View::e($label) ?></strong>
                  <div style="color:var(--text-secondary);overflow-wrap:anywhere;">
                    <?= nl2br(View::e($item[$field])) ?>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if (isset($item['special_notes']) && trim((string)$item['special_notes']) !== ''): ?>
              <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;font-size:0.875rem;">
                <strong>特别说明</strong>
                <div style="color: var(--text-secondary);"><?= nl2br(View::e($item['special_notes'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if (isset($item['description_md']) && trim((string)$item['description_md']) !== ''): ?>
              <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;font-size:0.875rem;">
                <strong>软件说明</strong>
                <div class="md-body admin-review-markdown"><?= View::markdown($item['description_md']) ?></div>
              </div>
            <?php endif; ?>

            <?php if (is_array($channels) && $channels): ?>
              <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;font-size:0.875rem;margin-top:4px;">
                <strong>下载渠道</strong>
                <div style="display:grid;gap:6px;">
                  <?php foreach ($channels as $channel): ?>
                    <div>
                      <?= View::e($channel['name'] ?? $channel['type'] ?? '下载渠道') ?>：
                      <a href="<?= Url::href($channel['url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">
                        <?= View::e($channel['url'] ?? '') ?>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <form action="<?= Url::route('admin/reviewCorrection') ?>" method="POST" class="admin-review-form"
                data-software-name="<?= View::e($item['software_name'] ?? '该软件') ?>">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="action" value="approve">

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label" for="correction-note-<?= (int)$item['id'] ?>">
                审核说明
                <span style="color: var(--text-muted); font-weight: 400;">（通过时可选，驳回时必填）</span>
              </label>
              <textarea id="correction-note-<?= (int)$item['id'] ?>" name="review_note" rows="2" maxlength="500"
                        class="form-textarea" style="min-height: 56px;"
                        placeholder="驳回时请写明具体原因，例如：官网链接无法访问 / 渠道链接已失效"></textarea>
            </div>

            <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap;">
              <button type="submit" name="action_btn" value="reject" onclick="this.form.elements['action'].value='reject'" class="btn-store-secondary"
                      style="color:#dc2626;border-color:rgba(220,38,38,.3);font-size:0.8125rem;">
                <?= FluentIcon::render('dismiss', 'fluent-icon-sm') ?>
                填写理由并驳回
              </button>
              <button type="submit" name="action_btn" value="approve" onclick="this.form.elements['action'].value='approve'" class="btn-store-primary" style="font-size:0.8125rem;">
                <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
                审核通过
              </button>
            </div>
          </form>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  // 驳回二次确认 + 理由必填校验（使用自定义确认框）
  document.querySelectorAll('.admin-review-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form._submitting) return;

      var action = (event.submitter && event.submitter.value) ? event.submitter.value : (form.elements['action'].value || 'approve');
      var softwareName = form.getAttribute('data-software-name') || '该软件';

      function doSubmit(act) {
        form.elements['action'].value = act;
        form._submitting = true;
        form.submit();
      }

      if (action !== 'reject') {
        event.preventDefault();
        window.FluentConfirm({
          title: '确认通过',
          message: '确认通过「' + softwareName + '」的修改申请？修改将立即覆盖正式软件资料。',
          tone: 'success',
          confirmText: '通过并通知',
          cancelText: '再看看'
        }).then(function (ok) { if (ok) doSubmit(action); });
        return;
      }

      var note = form.querySelector('textarea[name="review_note"]');
      if (note && !note.value.trim()) {
        event.preventDefault();
        note.focus();
        window.FluentMessage('驳回必须填写驳回理由，提交者将依据该理由修改后重新提交。', 'warning');
        return;
      }

      event.preventDefault();
      window.FluentConfirm({
        title: '确认驳回',
        message: '驳回后系统会自动生成一条安全修改链接，并连同驳回理由发送至提交者邮箱。确认继续？',
        tone: 'danger',
        confirmText: '确认驳回',
        cancelText: '取消'
      }).then(function (ok) { if (ok) doSubmit('reject'); });
    });
  });
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
