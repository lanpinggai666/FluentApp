<?php
require VIEW_PATH . '/user/layout_nav.php';
$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';
$categories = $categories ?? Category::getAll();
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">举报处理</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      处理您管理的软件的举报。
    </div>
  </div>
</div>

<?php if ($successMsg): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 20px; font-size: 0.875rem;">
    <?= View::e($successMsg) ?>
  </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 20px; font-size: 0.875rem;">
    <?= View::e($errorMsg) ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <?php if (empty($reports)): ?>
    <div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
      当前没有任何举报记录。
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 14px;">
      <?php foreach ($reports as $rp): ?>
        <div class="user-report-item" id="user-report-item-<?= (int)$rp['id'] ?>" style="padding: 14px 16px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle); transition: all 0.2s ease;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div>
              <div style="font-size: 0.9375rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <span>软件：<?= View::e($rp['software_name']) ?></span>
                <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-size: 0.75rem;">
                  <?= View::e($rp['reason']) ?>
                </span>
                <?php if ($rp['status'] === 'pending'): ?>
                  <span class="badge" style="background: rgba(234, 179, 8, 0.12); color: #ca8a04; font-size: 0.75rem;">待处理</span>
                <?php elseif ($rp['status'] === 'resolved'): ?>
                  <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: #16a34a; font-size: 0.75rem;">已确认处理</span>
                <?php else: ?>
                  <span class="badge" style="background: var(--bg-surface); color: var(--text-muted); font-size: 0.75rem;">已驳回</span>
                <?php endif; ?>
              </div>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                举报时间：<?= date('Y-m-d H:i', strtotime($rp['created_at'])) ?>
                <?php if (!empty($rp['contact'])): ?>
                  · 举报人联系方式：<?= View::e($rp['contact']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <a href="<?= Url::detail($rp['software_slug']) ?>" target="_blank" class="btn-store-secondary" style="height: 28px; padding: 0 10px; font-size: 0.75rem;">
                查看对应软件
              </a>
            </div>
          </div>

          <?php if (!empty($rp['resource_id'])): ?>
            <div style="padding: 10px 12px; border-radius: var(--radius-sm); background: rgba(245, 158, 11, 0.08); border: 1px dashed rgba(245, 158, 11, 0.3); margin-bottom: 12px; font-size: 0.8125rem;">
              <strong style="color: #b45309;">涉嫌违规资源：</strong>
              <?= View::e($rp['resource_title'] ?: ('资源ID #' . $rp['resource_id'])) ?>
              <?php if (!empty($rp['resource_url'])): ?>
                <a href="<?= Url::href($rp['resource_url']) ?>" target="_blank" style="margin-left: 8px; color: var(--primary); text-decoration: none;">[打开链接验证]</a>
              <?php endif; ?>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                (注：该资源已下架隐藏)
              </div>
            </div>
          <?php endif; ?>

          <div style="background: var(--bg-surface); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); font-size: 0.875rem; margin-bottom: 12px;">
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">举报详情说明：</div>
            <div style="color: var(--text-main); line-height: 1.5;">
              <?= nl2br(View::e($rp['details'] ?: ($rp['description'] ?? '举报人未填写补充说明'))) ?>
            </div>
          </div>

          <?php if ($rp['status'] === 'pending'): ?>
            <div style="display: flex; justify-content: flex-end; align-items: center; flex-wrap: wrap; gap: 8px; border-top: 1px solid var(--border-subtle); padding-top: 12px;">
              <script type="application/json" id="user-report-data-<?= (int)$rp['id'] ?>">
              <?= json_encode([
                'id' => (int)$rp['id'],
                'software_id' => (int)$rp['software_id'],
                'software_name' => $rp['software_name'] ?? '',
                'software_slug' => $rp['software_slug'] ?? '',
                'reason' => $rp['reason'] ?? '',
                'details' => $rp['details'] ?: ($rp['description'] ?? '举报人未填写文字说明'),
                'contact' => $rp['contact'] ?? '',
                'created_at' => substr($rp['created_at'], 0, 16),
                'is_resource' => !empty($rp['resource_id']) ? 1 : 0,
                'resource_title' => $rp['resource_title'] ?? '',
                'resource_url' => $rp['resource_url'] ?? '',
                'category_id' => (int)($rp['category_id'] ?? 0),
                'official_url' => $rp['official_url'] ?? '',
                'icon_url' => $rp['icon_url'] ?? '',
                'github_url' => $rp['github_url'] ?? '',
                'developer' => $rp['developer'] ?? '',
                'tags' => $rp['tags'] ?? '',
                'special_notes' => $rp['special_notes'] ?? '',
                'description_md' => $rp['description_md'] ?? ''
              ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
              </script>
              <button type="button" class="btn-store-primary" style="height: 32px; padding: 0 16px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 6px;"
                      onclick="openUserReportModal(<?= (int)$rp['id'] ?>)">
                <?= FluentIcon::render('edit', 'fluent-icon-xs') ?>
                <span>处理举报</span>
              </button>
            </div>
          <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-top: 1px solid var(--border-subtle); padding-top: 10px; font-size: 0.8125rem; color: var(--text-muted);">
              <div>
                处理结果：<strong style="color: <?= $rp['status'] === 'resolved' ? '#16a34a' : 'var(--text-muted)' ?>;"><?= $rp['status'] === 'resolved' ? '已核实处理' : '已驳回' ?></strong>
                <?php if (!empty($rp['review_reason'])): ?>
                  · 处理附言：<?= View::e($rp['review_reason']) ?>
                <?php endif; ?>
                · 处理时间：<?= View::e($rp['notified_at'] ?: $rp['updated_at'] ?? '') ?>
              </div>
              <button type="button" class="btn-store-secondary" onclick="deleteUserReport(<?= (int)$rp['id'] ?>)" style="height: 28px; padding: 0 10px; font-size: 0.75rem; color: #ef4444; display: inline-flex; align-items: center; gap: 4px;">
                <?= FluentIcon::render('delete', 'fluent-icon-xs') ?>
                <span>删除记录</span>
              </button>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 软件管理者处理举报与修改软件信息 Fluent UI 模态对话框 -->
<div class="modal-overlay" id="user-report-modal">
  <div class="modal-container" style="max-width: 800px; width: 95%;">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('shield-checkmark') ?>
        <span id="user-report-modal-title">核查举报与修改软件信息</span>
      </div>
      <button type="button" class="notice-close" onclick="closeModal('user-report-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>

    <form action="<?= Url::route('user/handleReport') ?>" method="POST" id="user-report-form" class="modal-body" style="max-height: calc(85vh - 120px); overflow-y: auto; display: flex; flex-direction: column; gap: 16px; padding: 18px 24px;">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="report_id" id="urp-id" value="0">
      <input type="hidden" name="action" id="urp-action" value="resolve_update">

      <!-- 1. 举报信息概要卡片 -->
      <div style="padding: 12px 16px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <strong id="urp-summary-name" style="font-size: 0.9375rem; color: var(--text-primary);"></strong>
            <span class="badge" id="urp-summary-reason" style="background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 0.75rem;"></span>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted);" id="urp-summary-meta"></div>
        </div>
        <div style="font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.5;">
          <span style="font-weight: 600; color: var(--text-muted);">举报详情说明：</span>
          <span id="urp-summary-details" style="white-space: pre-wrap; word-break: break-word;"></span>
        </div>
        <div id="urp-resource-box" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(239, 68, 68, 0.2); font-size: 0.8125rem; color: #b45309;">
          <strong>涉嫌违规资源：</strong><span id="urp-resource-info"></span>
        </div>
      </div>

      <!-- 2. 修改名下软件信息 (可直接修正并保存) -->
      <div style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 14px 16px;">
        <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
          <?= FluentIcon::render('edit', 'fluent-icon-xs') ?>
          <span>修改软件信息</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">软件名称 <span style="color:#ef4444;">*</span></label>
            <input type="text" name="software_name" id="urp-field-name" class="form-input" required maxlength="100">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">软件分类</label>
            <select name="software_category_id" id="urp-field-category" class="form-select">
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= View::e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">官方网站</label>
            <input type="url" name="software_official_url" id="urp-field-official" class="form-input" maxlength="500">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">图标直链</label>
            <input type="url" name="software_icon_url" id="urp-field-icon" class="form-input" maxlength="500">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">GitHub / 开源地址</label>
            <input type="url" name="software_github_url" id="urp-field-github" class="form-input" maxlength="500">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">开发团队 / 作者</label>
            <input type="text" name="software_developer" id="urp-field-developer" class="form-input" maxlength="100">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.8125rem;">搜索标签 (逗号分隔)</label>
          <input type="text" name="software_tags" id="urp-field-tags" class="form-input" maxlength="500">
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.8125rem;">一句话特别说明</label>
          <input type="text" name="software_special_notes" id="urp-field-notes" class="form-input" maxlength="200">
        </div>

        <div class="form-group" style="margin: 0;">
          <label class="form-label" style="font-size: 0.8125rem;">详细说明 (支持 Markdown )</label>
          <textarea name="software_description_md" id="urp-field-desc" class="form-textarea markdown-editor" rows="6" maxlength="10000" placeholder="支持 Markdown 语法，可排版软件介绍..."></textarea>
        </div>
      </div>

      <!-- 3. 处理说明 / 附言 -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" for="urp-review-note" style="font-weight: 600; font-size: 0.8125rem;">
          <span>处理备注 / 附言 </span>
        </label>
        <textarea name="note" id="urp-review-note" class="form-textarea" rows="2" maxlength="500" placeholder="可填写具体的修正说明或核对结论..."></textarea>
      </div>

      <!-- 4. 底部动作条：取消 | 驳回、下架、修改后处理 -->
      <div class="modal-footer" style="padding-top: 14px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('user-report-modal')">取消</button>
        
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
          <button type="button" class="btn-store-secondary" id="urp-btn-resource-del" style="display: none; color: #dc2626; border-color: rgba(239, 68, 68, 0.4);" onclick="submitUserReportAction('resolve_delete_resource')">
            确认违规并删除该资源
          </button>
          
          <button type="button" class="btn-store-secondary" onclick="submitUserReportAction('reject')" title="认定为误报，予以驳回">
            驳回
          </button>

          <button type="button" class="btn-store-secondary" style="color: #dc2626; border-color: rgba(239, 68, 68, 0.4);" onclick="submitUserReportAction('resolve_offline')" title="软件信息存在严重问题，主动下架整改">
            下架
          </button>

          <button type="button" class="btn-store-primary" onclick="submitUserReportAction('resolve_update')" title="保存上方修改的软件信息并完成处理">
            处理
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openUserReportModal(reportId) {
  const dataEl = document.getElementById('user-report-data-' + reportId);
  if (!dataEl) return;
  let data = {};
  try {
    data = JSON.parse(dataEl.textContent);
  } catch (e) {
    console.error('Failed to parse report data:', e);
    return;
  }

  document.getElementById('urp-id').value = data.id || 0;
  document.getElementById('urp-summary-name').innerText = data.software_name || '软件 #' + data.software_id;
  document.getElementById('urp-summary-reason').innerText = data.reason || '其他原因';
  document.getElementById('urp-summary-meta').innerText = '举报时间: ' + (data.created_at || '');
  document.getElementById('urp-summary-details').innerText = data.details || '举报人未填写文字说明';

  const resBox = document.getElementById('urp-resource-box');
  const resDelBtn = document.getElementById('urp-btn-resource-del');
  if (data.is_resource) {
    resBox.style.display = 'block';
    document.getElementById('urp-resource-info').innerText = (data.resource_title || '关联资源') + ' (' + (data.resource_url || '') + ')';
    if (resDelBtn) resDelBtn.style.display = 'inline-block';
  } else {
    resBox.style.display = 'none';
    if (resDelBtn) resDelBtn.style.display = 'none';
  }

  document.getElementById('urp-field-name').value = data.software_name || '';
  document.getElementById('urp-field-category').value = data.category_id || 1;
  document.getElementById('urp-field-official').value = data.official_url || '';
  document.getElementById('urp-field-icon').value = data.icon_url || '';
  document.getElementById('urp-field-github').value = data.github_url || '';
  document.getElementById('urp-field-developer').value = data.developer || '';
  document.getElementById('urp-field-tags').value = data.tags || '';
  document.getElementById('urp-field-notes').value = data.special_notes || '';
  document.getElementById('urp-review-note').value = '';

  const descEl = document.getElementById('urp-field-desc');
  const descVal = data.description_md || '';

  if (typeof window.openModal === 'function') {
    window.openModal('user-report-modal');
  } else {
    document.getElementById('user-report-modal').classList.add('active');
  }

  // 待模态框展现有尺寸后初始化或回填 EasyMDE
  setTimeout(function () {
    if (window.FluentMarkdown) {
      let mde = window.FluentMarkdown.get(descEl);
      if (!mde) {
        mde = window.FluentMarkdown.build(descEl);
      }
      if (mde) {
        mde.value(descVal);
        setTimeout(function () {
          if (mde.codemirror) {
            mde.codemirror.refresh();
          }
        }, 50);
      } else {
        descEl.value = descVal;
      }
    } else {
      descEl.value = descVal;
    }
  }, 60);
}

function submitUserReportAction(action) {
  document.getElementById('urp-action').value = action;

  // 同步 EasyMDE 内容回 textarea
  const descEl = document.getElementById('urp-field-desc');
  if (window.FluentMarkdown && window.FluentMarkdown.get(descEl)) {
    descEl.value = window.FluentMarkdown.get(descEl).value();
  }

  const reasonEl = document.getElementById('urp-review-note');
  if (!reasonEl.value.trim()) {
    if (action === 'resolve_update') {
      reasonEl.value = '软件管理者已核实并更正软件信息，处理完毕。感谢您的监督！';
    } else if (action === 'resolve_offline') {
      reasonEl.value = '软件管理者已确认问题并将软件执行下架整改。';
    } else if (action === 'reject' || action === 'dismiss') {
      reasonEl.value = '软件管理者核实无违规或属误报，予以驳回。';
    } else if (action === 'resolve_delete_resource') {
      reasonEl.value = '软件管理者已核实违规并彻底删除了该扩展资源。';
    }
  }
  document.getElementById('user-report-form').submit();
}

function deleteUserReport(id) {
  const item = document.getElementById('user-report-item-' + id);
  const formData = new FormData();
  formData.append('id', id);

  fetch('<?= Url::route('user/deleteReport') ?>', {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  }).then(res => res.json()).then(data => {
    if (data.success) {
      if (item) {
        item.style.opacity = '0';
        item.style.transform = 'scale(0.98)';
        setTimeout(() => {
          item.remove();
        }, 200);
      }
    }
  }).catch(err => {
    console.error('Failed to delete report:', err);
  });
}
</script>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
