<?php
/**
 * 蓝屏钙软件导航网 - 后台待审核软件列表
 *
 * 审核规则：
 *  - 通过：可选择调用 AI 补全缺失字段，结果将邮件通知提交者
 *  - 驳回：必须填写驳回理由，系统自动生成安全修改链接并邮件通知提交者
 */
require VIEW_PATH . '/admin/layout_nav.php';
?>

<div class="admin-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('shield-checkmark') ?>
        <span>待审核软件列表</span>
      </h2>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">
        驳回必须填写理由，提交者将收到含修改链接的邮件。所有审核结果均通过邮箱通知。
      </p>
    </div>
  </div>

  <?php if (!empty($success)): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.875rem; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
      <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
      <span><?= View::e($success) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($notice)): ?>
    <div style="background: #fef3c7; color: #92400e; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.875rem; margin-bottom: 16px;">
      <?= View::e($notice) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($list)): ?>
    <div style="padding: 48px; text-align: center; color: var(--text-muted);">
      <?= FluentIcon::render('checkmark', 'fluent-icon-lg') ?>
      <div style="margin-top: 10px; font-size: 0.9375rem;">当前没有待处理的审核任务</div>
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
      <?php foreach ($list as $item): ?>
        <?php $pendingChannels = !empty($item['other_urls']) ? json_decode((string)$item['other_urls'], true) : []; ?>
        <div class="admin-review-card">
          <div class="admin-review-head">
            <img src="<?= View::e($item['icon_url']) ?>" style="width: 48px; height: 48px; border-radius: 8px; object-fit: contain; background: #fff; border: 1px solid var(--border-subtle);" alt="">
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h3 style="font-size: 1.0625rem; font-weight: 700; color: var(--text-primary); margin: 0;"><?= View::e($item['name']) ?></h3>
                <span class="badge badge-os"><?= View::e($item['category_name'] ?? '待定') ?></span>
              </div>
              <div style="font-size: 0.8125rem; margin-top: 6px; color: var(--text-secondary); display: flex; flex-wrap: wrap; gap: 14px;">
                <span>官网: <a href="<?= Url::href($item['official_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-link); text-decoration: underline;"><?= View::e($item['official_url']) ?></a></span>
                <?php if (!empty($item['github_url'])): ?>
                  <span>GitHub: <a href="<?= Url::href($item['github_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-link);"><?= View::e($item['github_url']) ?></a></span>
                <?php endif; ?>
              </div>
              <div style="font-size: 0.75rem; margin-top: 6px; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 14px;">
                <span>提交邮箱:
                  <?php if (!empty($item['submitter_email'])): ?>
                    <a href="mailto:<?= View::e($item['submitter_email']) ?>" style="color: var(--text-link);"><?= View::e($item['submitter_email']) ?></a>
                  <?php else: ?>
                    <span style="color:#dc2626;">未填写（历史数据）</span>
                  <?php endif; ?>
                </span>
                <span>提交IP: <?= View::e($item['submitter_ip'] ?? '-') ?></span>
                <span><?= substr((string)$item['created_at'], 0, 16) ?></span>
              </div>
            </div>
          </div>

          <div class="admin-review-body">
            <div>
              <strong>详细说明:</strong>
              <?php if (!empty($item['special_notes'])): ?>
                <div class="md-body admin-review-markdown"><?= View::markdown($item['special_notes']) ?></div>
              <?php else: ?>
                <span style="color: var(--text-muted);">【未填写，可由 AI 补全】</span>
              <?php endif; ?>
            </div>

            <div style="margin-top: 10px;">
              <strong>其它获取渠道:</strong>
              <?php if (!empty($pendingChannels) && is_array($pendingChannels)): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                  <?php foreach ($pendingChannels as $channel): ?>
                    <a class="tag-pill" href="<?= Url::href($channel['url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">
                      <?= View::e($channel['name'] ?? $channel['type'] ?? '渠道') ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span style="color: var(--text-muted);">【未填写，可由 AI 补全】</span>
              <?php endif; ?>
            </div>

            <?php if (!empty($item['tags'])): ?>
              <div style="margin-top: 10px;">
                <strong>标签:</strong>
                <span style="color: var(--text-secondary);"><?= View::e($item['tags']) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- 审核操作：审核说明（驳回时必填） -->
          <form action="<?= Url::route('admin/review') ?>" method="POST" class="admin-review-form" id="review-form-<?= (int)$item['id'] ?>"
                data-software-name="<?= View::e($item['name']) ?>">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken ?? '') ?>">
            <input type="hidden" name="action" value="approve">

            <div class="form-group" style="margin-bottom: 12px;">
              <label class="form-label" for="review-note-<?= (int)$item['id'] ?>">
                审核说明
                <span style="color: var(--text-muted); font-weight: 400;">（通过时选填，驳回时必填）</span>
              </label>
              <textarea id="review-note-<?= (int)$item['id'] ?>" name="review_note" rows="2" maxlength="500"
                        class="form-textarea" style="min-height: 56px;"
                        placeholder="驳回时请写明具体原因，例如：官网链接无法访问 / 图标链接已失效 / 分类选择有误"></textarea>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
              <select name="category_id" class="form-select" style="width: 150px;" aria-label="发布分类">
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)($item['category_id'] ?? 0) ? 'selected' : '' ?>>
                    <?= View::e($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <button type="submit" name="action_btn" value="reject" onclick="this.form.elements['action'].value='reject'" class="btn-store-secondary admin-reject-btn"
                      style="color: #dc2626; border-color: rgba(220, 38, 38, 0.3); font-size: 0.8125rem;">
                <?= FluentIcon::render('dismiss', 'fluent-icon-sm') ?>
                填写理由并驳回
              </button>

              <button type="submit" name="action_btn" value="approve" onclick="this.form.elements['action'].value='approve'" class="btn-store-secondary admin-approve-btn"
                      style="font-size: 0.8125rem;">
                <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
                直接通过
              </button>

              <button type="submit" name="action_btn" value="approve_ai" onclick="this.form.elements['action'].value='approve_ai'" class="btn-store-primary"
                      style="font-size: 0.8125rem;">
                <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?>
                AI 智能补全并通过
              </button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  // 驳回二次确认 + 理由必填校验（使用自定义确认框，绝不使用浏览器原生 confirm）
  document.querySelectorAll('.admin-review-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form._submitting) return;

      var action = (event.submitter && event.submitter.value) ? event.submitter.value : (form.elements['action'].value || 'approve');
      var softwareName = form.getAttribute('data-software-name') || '该软件';

      function doSubmit(act) {
        form.elements['action'].value = act;
        form._submitting = true;
        // 「AI 补全后发布」要等一次外呼（最长 ai_total_timeout 秒）。
        // 这里显式交给对话面板接管，而不是靠拦 submit 事件 —— 程序化 form.submit() 不再触发
        // submit 事件，两处各提交一次会真把审核跑两遍。脚本没加载上就退回原来的提交。
        if (act === 'approve_ai' && window.SoftnavAi
            && window.SoftnavAi.run(form, 'AI 补全并发布', { actionValue: act })) {
          return;
        }
        form.submit();
      }

      if (action !== 'reject') {
        event.preventDefault();
        window.FluentConfirm({
          title: '确认通过',
          message: '确认通过「' + softwareName + '」并公开发布？系统将向提交者邮箱发送通过通知。',
          tone: 'success',
          confirmText: '通过并通知',
          cancelText: '再看看'
        }).then(function (ok) {
          if (ok) doSubmit(action);
        });
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
      }).then(function (ok) {
        if (ok) doSubmit('reject');
      });
    });
  });
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
