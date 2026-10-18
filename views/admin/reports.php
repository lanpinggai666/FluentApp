<?php
/**
 * 蓝屏钙软件导航网 - 后台举报与异常反馈处理
 */
require VIEW_PATH . '/admin/layout_nav.php';

$currentStatus = $currentStatus ?? 'all';
$currentType = $currentType ?? 'all';
$stats = $stats ?? ['total' => 0, 'pending' => 0, 'resolved' => 0, 'dismissed' => 0, 'resource_count' => 0, 'software_count' => 0];
$categories = $categories ?? Category::getAll();
?>

<div class="admin-content-header" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
        <?= FluentIcon::render('flag') ?>
        <span>违规举报与异常反馈处理</span>
      </h1>
      <div style="font-size: 0.875rem; color: var(--text-muted);">
        核实并处置全站软件与关联扩展资源（失效、木马病毒、侵权、虚假等），联动下架、资源清理与站内信/邮件反馈。
      </div>
    </div>
  </div>
</div>

<?php if (!empty($success)): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 20px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px;">
    <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
    <span><?= View::e($success) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($notice)): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 20px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px;">
    <?= FluentIcon::render('warning', 'fluent-icon-sm') ?>
    <span><?= View::e($notice) ?></span>
  </div>
<?php endif; ?>

<!-- 统计概览卡片 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
  <a href="<?= Url::route('admin/reports', ['status' => 'pending', 'type' => $currentType]) ?>" class="admin-card" style="text-decoration: none; padding: 16px; border-left: 4px solid #f59e0b; transition: transform 0.15s ease;">
    <div style="font-size: 0.8125rem; color: var(--text-muted);">待处理举报</div>
    <div style="font-size: 1.5rem; font-weight: 700; color: #b45309; margin-top: 4px;">
      <?= (int)$stats['pending'] ?>
    </div>
  </a>

  <a href="<?= Url::route('admin/reports', ['status' => 'resolved', 'type' => $currentType]) ?>" class="admin-card" style="text-decoration: none; padding: 16px; border-left: 4px solid #10b981; transition: transform 0.15s ease;">
    <div style="font-size: 0.8125rem; color: var(--text-muted);">已核实处理</div>
    <div style="font-size: 1.5rem; font-weight: 700; color: #047857; margin-top: 4px;">
      <?= (int)$stats['resolved'] ?>
    </div>
  </a>

  <a href="<?= Url::route('admin/reports', ['status' => 'dismissed', 'type' => $currentType]) ?>" class="admin-card" style="text-decoration: none; padding: 16px; border-left: 4px solid #6b7280; transition: transform 0.15s ease;">
    <div style="font-size: 0.8125rem; color: var(--text-muted);">已驳回 / 误报</div>
    <div style="font-size: 1.5rem; font-weight: 700; color: #4b5563; margin-top: 4px;">
      <?= (int)$stats['dismissed'] ?>
    </div>
  </a>

  <a href="<?= Url::route('admin/reports', ['status' => 'all', 'type' => 'resource']) ?>" class="admin-card" style="text-decoration: none; padding: 16px; border-left: 4px solid var(--ms-blue); transition: transform 0.15s ease;">
    <div style="font-size: 0.8125rem; color: var(--text-muted);">涉及相关资源举报</div>
    <div style="font-size: 1.5rem; font-weight: 700; color: var(--ms-blue); margin-top: 4px;">
      <?= (int)$stats['resource_count'] ?>
    </div>
  </a>
</div>

<!-- 筛选工具栏 -->
<div class="admin-card" style="padding: 14px 20px; margin-bottom: 20px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    <!-- 状态切换 -->
    <div style="display: inline-flex; background: var(--bg-surface-secondary); padding: 3px; border-radius: var(--radius-sm); gap: 2px;">
      <?php
        $statusTabs = [
          'all' => '全部 (' . (int)$stats['total'] . ')',
          'pending' => '待处理 (' . (int)$stats['pending'] . ')',
          'resolved' => '已处理 (' . (int)$stats['resolved'] . ')',
          'dismissed' => '已驳回 (' . (int)$stats['dismissed'] . ')'
        ];
        foreach ($statusTabs as $k => $label):
          $active = ($currentStatus === $k);
      ?>
        <a href="<?= Url::route('admin/reports', ['status' => $k, 'type' => $currentType]) ?>"
           style="padding: 6px 14px; font-size: 0.8125rem; font-weight: 500; text-decoration: none; border-radius: 4px; transition: all 0.15s ease; color: <?= $active ? 'var(--text-primary)' : 'var(--text-muted)' ?>; background: <?= $active ? 'var(--bg-surface)' : 'transparent' ?>; box-shadow: <?= $active ? 'var(--shadow-card)' : 'none' ?>;">
          <?= View::e($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- 类型切换 -->
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="font-size: 0.8125rem; color: var(--text-muted);">举报类型：</span>
      <select class="form-select" style="height: 32px; font-size: 0.8125rem; padding: 0 10px;" onchange="const u = new URL('<?= Url::route('admin/reports') ?>', window.location.href); u.search = new URLSearchParams({ status: '<?= View::e((string)$currentStatus) ?>', type: this.value }); location.href = u.toString()">
        <option value="all" <?= $currentType === 'all' ? 'selected' : '' ?>>全部对象类型</option>
        <option value="software" <?= $currentType === 'software' ? 'selected' : '' ?>>仅软件本体举报</option>
        <option value="resource" <?= $currentType === 'resource' ? 'selected' : '' ?>>仅相关资源举报</option>
      </select>
    </div>
  </div>
</div>

<!-- 举报列表 -->
<div class="admin-card" style="padding: 20px;">
  <?php if (empty($reports)): ?>
    <div style="padding: 48px; text-align: center; color: var(--text-muted);">
      <?= FluentIcon::render('checkmark', 'fluent-icon-lg') ?>
      <div style="margin-top: 10px; font-size: 0.9375rem;">当前筛选条件下没有举报记录</div>
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
      <?php foreach ($reports as $r): 
        $isPending = ($r['status'] === 'pending');
        $isResolved = ($r['status'] === 'resolved');
        $isResourceReport = (!empty($r['resource_id']) || ($r['reported_type'] ?? '') === 'resource');
      ?>
        <div style="padding: 18px 20px; border-radius: var(--radius-md); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle); transition: border-color 0.15s ease;">
          
          <!-- 头部：ID、类型标签、状态、举报人与时间 -->
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <span style="font-weight: 700; font-size: 0.875rem; color: var(--text-muted);">#<?= (int)$r['id'] ?></span>
              
              <?php if ($isResourceReport): ?>
                <span class="badge" style="background: rgba(245, 158, 11, 0.12); color: #b45309; font-weight: 600; font-size: 0.75rem;">
                  <?= FluentIcon::render('link', 'fluent-icon-xs') ?>
                  <span>相关资源举报</span>
                </span>
              <?php else: ?>
                <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: #0284c7; font-weight: 600; font-size: 0.75rem;">
                  <?= FluentIcon::render('cube', 'fluent-icon-xs') ?>
                  <span>软件本体举报</span>
                </span>
              <?php endif; ?>

              <span class="badge" style="background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 0.75rem;">
                <?= View::e($r['reason']) ?>
              </span>

              <?php if ($isPending): ?>
                <span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 600; font-size: 0.75rem;">待处理</span>
              <?php elseif ($isResolved): ?>
                <span class="badge" style="background: #dcfce7; color: #166534; font-weight: 600; font-size: 0.75rem;">已核实处理</span>
              <?php else: ?>
                <span class="badge" style="background: #f3f4f6; color: #4b5563; font-weight: 600; font-size: 0.75rem;">已驳回</span>
              <?php endif; ?>
            </div>

            <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 12px;">
              <span>举报时间：<?= substr($r['created_at'], 0, 16) ?></span>
              <span>IP: <?= View::e($r['ip_address']) ?></span>
            </div>
          </div>

          <!-- 举报涉及目标信息 -->
          <div style="background: var(--bg-surface); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); margin-bottom: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-weight: 600; font-size: 0.9375rem; color: var(--text-primary);">
                  目标软件：<?= View::e($r['software_name'] ?? '已移除软件') ?>
                </span>
                <?php if (!empty($r['software_status'])): ?>
                  <span class="badge" style="font-size: 0.6875rem; padding: 1px 6px; background: <?= $r['software_status'] === 'published' ? 'rgba(34, 197, 94, 0.1);color:#16a34a' : 'rgba(239, 68, 68, 0.1);color:#dc2626' ?>;">
                    状态: <?= $r['software_status'] === 'published' ? '已上架发布' : ($r['software_status'] === 'offline' ? '已下架' : $r['software_status']) ?>
                  </span>
                <?php endif; ?>
              </div>

              <?php if (!empty($r['software_slug'])): ?>
                <div style="display: flex; gap: 8px;">
                  <a href="<?= Url::detail($r['software_slug']) ?>" target="_blank" class="btn-store-secondary" style="height: 26px; padding: 0 10px; font-size: 0.75rem;">
                    <?= FluentIcon::render('globe', 'fluent-icon-xs') ?> 前台查看
                  </a>
                  <a href="<?= Url::route('admin/softwares') ?>" target="_blank" class="btn-store-secondary" style="height: 26px; padding: 0 10px; font-size: 0.75rem;">
                    <?= FluentIcon::render('settings', 'fluent-icon-xs') ?> 管理软件
                  </a>
                </div>
              <?php endif; ?>
            </div>

            <!-- 若为具体资源举报，突出展示资源信息及外链 -->
            <?php if ($isResourceReport): ?>
              <div style="margin-top: 10px; padding: 10px 12px; border-radius: var(--radius-xs); background: rgba(245, 158, 11, 0.06); border: 1px dashed rgba(245, 158, 11, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                  <div>
                    <span style="font-size: 0.8125rem; font-weight: 600; color: #b45309;">被举报的相关资源：</span>
                    <span style="font-size: 0.875rem; font-weight: 600; color: var(--text-primary);"><?= View::e($r['resource_title'] ?: ('资源ID #' . (int)$r['resource_id'])) ?></span>
                    <?php if (!empty($r['resource_type'])): ?>
                      <span class="badge" style="font-size: 0.6875rem; margin-left: 6px;"><?= View::e($r['resource_type']) ?></span>
                    <?php endif; ?>
                    
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 8px;">
                      (资源状态: <?= $r['resource_status'] === 'reported' ? '<span style="color:#b45309">已临时自动隐藏</span>' : ($r['resource_status'] === 'published' ? '<span style="color:#16a34a">已恢复展示</span>' : '无') ?>)
                    </span>
                  </div>

                  <?php if (!empty($r['resource_url'])): ?>
                    <a href="<?= Url::href($r['resource_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-store-secondary" style="height: 24px; padding: 0 8px; font-size: 0.75rem; color: var(--ms-blue);">
                      <?= FluentIcon::render('open', 'fluent-icon-xs') ?> 打开外链排查
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- 举报详情说明与举报人信息 -->
          <div style="margin-bottom: 12px; font-size: 0.875rem; line-height: 1.6; color: var(--text-secondary);">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">举报人陈述详情：</div>
            <div style="padding: 10px 14px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-xs); color: var(--text-primary); white-space: pre-wrap; word-break: break-word;">
              <?= View::e($r['details'] ?: '举报人未填写补充文字说明') ?>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px;">
            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
              <span>举报人：
                <?php if (!empty($r['reporter_username'])): ?>
                  <span style="font-weight: 600; color: var(--text-primary);"><?= View::e($r['reporter_username']) ?> (UID: <?= (int)$r['user_id'] ?>)</span>
                <?php else: ?>
                  <span>访客 (未登录)</span>
                <?php endif; ?>
              </span>

              <span>联系邮箱：<?= !empty($r['email']) ? View::e($r['email']) : '未填写' ?></span>
              <?php if (!empty($r['contact'])): ?>
                <span>其他联系方式：<?= View::e($r['contact']) ?></span>
              <?php endif; ?>
            </div>

            <?php if (!empty($r['notification_status']) && $r['notification_status'] !== 'pending'): ?>
              <div>
                邮件通知: <span class="badge" style="font-size: 0.6875rem;"><?= View::e($r['notification_status']) ?><?= !empty($r['notification_error']) ? ' (' . View::e($r['notification_error']) : '' ?>)</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- 已处理记录详情 -->
          <?php if (!$isPending): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 10px 14px; border-radius: var(--radius-xs); background: var(--bg-surface); border: 1px solid var(--border-subtle); font-size: 0.8125rem;">
              <div>
                <span style="font-weight: 600; color: var(--text-primary);">处理附言说明：</span>
                <span style="color: var(--text-secondary);"><?= View::e($r['review_reason'] ?: '无') ?></span>
                <span style="color: var(--text-muted); font-size: 0.75rem; margin-left: 10px;">(处理时间: <?= View::e($r['notified_at'] ?? '已记录') ?>)</span>
              </div>
              <form action="<?= Url::route('admin/reports') ?>" method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-store-secondary" style="height: 28px; padding: 0 10px; font-size: 0.75rem; color: #ef4444; display: inline-flex; align-items: center; gap: 4px;">
                  <?= FluentIcon::render('delete', 'fluent-icon-xs') ?>
                  <span>删除记录</span>
                </button>
              </form>
            </div>
          <?php else: ?>
            <!-- 待处理操作栏：修改软件信息与处理举报 -->
            <div style="display: flex; justify-content: flex-end; align-items: center; flex-wrap: wrap; gap: 8px; padding-top: 12px; border-top: 1px solid var(--border-subtle);">
              <script type="application/json" id="report-data-<?= (int)$r['id'] ?>">
              <?= json_encode([
                'id' => (int)$r['id'],
                'software_id' => (int)$r['software_id'],
                'software_name' => $r['software_name'] ?? '',
                'software_slug' => $r['software_slug'] ?? '',
                'reason' => $r['reason'] ?? '',
                'details' => $r['details'] ?? '',
                'reporter' => !empty($r['reporter_username']) ? ($r['reporter_username'] . ' (UID:' . (int)$r['user_id'] . ')') : '访客 (未登录)',
                'created_at' => substr($r['created_at'], 0, 16),
                'is_resource' => $isResourceReport ? 1 : 0,
                'resource_title' => $r['resource_title'] ?? '',
                'resource_url' => $r['resource_url'] ?? '',
                'category_id' => (int)($r['category_id'] ?? 0),
                'official_url' => $r['official_url'] ?? '',
                'icon_url' => $r['icon_url'] ?? '',
                'github_url' => $r['github_url'] ?? '',
                'developer' => $r['developer'] ?? '',
                'publisher' => $r['publisher'] ?? '',
                'tags' => $r['tags'] ?? '',
                'special_notes' => $r['special_notes'] ?? '',
                'description_md' => $r['description_md'] ?? ''
              ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
              </script>
              <button type="button" class="btn-store-primary" style="height: 32px; padding: 0 16px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 6px;"
                      onclick="openReportProcessModal(<?= (int)$r['id'] ?>)">
                <?= FluentIcon::render('edit', 'fluent-icon-xs') ?>
                <span>修改软件信息 / 处理举报</span>
              </button>
            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 统一举报处理与修改软件信息 Fluent UI 模态对话框 -->
<div class="modal-overlay" id="admin-report-modal">
  <div class="modal-container" style="max-width: 800px; width: 95%;">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('shield-checkmark') ?>
        <span id="report-modal-header-title">处理举报与核查软件信息</span>
      </div>
      <button type="button" class="notice-close" onclick="closeModal('admin-report-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>

    <form action="<?= Url::route('admin/reports') ?>" method="POST" id="report-process-form" class="modal-body" style="max-height: calc(85vh - 120px); overflow-y: auto; display: flex; flex-direction: column; gap: 16px; padding: 18px 24px;">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="id" id="rpm-id" value="0">
      <input type="hidden" name="action" id="rpm-action" value="resolve_update">

      <!-- 1. 举报信息概要卡片 -->
      <div style="padding: 12px 16px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <strong id="rpm-summary-name" style="font-size: 0.9375rem; color: var(--text-primary);"></strong>
            <span class="badge" id="rpm-summary-reason" style="background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 0.75rem;"></span>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted);" id="rpm-summary-meta"></div>
        </div>
        <div style="font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.5;">
          <span style="font-weight: 600; color: var(--text-muted);">举报陈述：</span>
          <span id="rpm-summary-details" style="white-space: pre-wrap; word-break: break-word;"></span>
        </div>
        <div id="rpm-resource-box" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(239, 68, 68, 0.2); font-size: 0.8125rem; color: #b45309;">
          <strong>关联资源：</strong><span id="rpm-resource-info"></span>
        </div>
      </div>

      <!-- 2. 修改软件信息 (可直接核查修正) -->
      <div style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 14px 16px;">
        <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
          <?= FluentIcon::render('edit', 'fluent-icon-xs') ?>
          <span>修改软件信息 (可直接排查纠正，保存后同步更新至正式详情页)</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">软件名称 <span style="color:#ef4444;">*</span></label>
            <input type="text" name="software_name" id="rpm-field-name" class="form-input" required maxlength="100">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">软件分类</label>
            <select name="software_category_id" id="rpm-field-category" class="form-select">
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= View::e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">官方网站</label>
            <input type="url" name="software_official_url" id="rpm-field-official" class="form-input" maxlength="500">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">图标直链</label>
            <input type="url" name="software_icon_url" id="rpm-field-icon" class="form-input" maxlength="500">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">GitHub / 开源地址</label>
            <input type="url" name="software_github_url" id="rpm-field-github" class="form-input" maxlength="500">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label" style="font-size: 0.8125rem;">开发团队 / 作者</label>
            <input type="text" name="software_developer" id="rpm-field-developer" class="form-input" maxlength="100">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.8125rem;">搜索标签 (英文或中文逗号分隔)</label>
          <input type="text" name="software_tags" id="rpm-field-tags" class="form-input" maxlength="500">
        </div>

        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.8125rem;">一句话特别说明 (展示在详情页头部)</label>
          <input type="text" name="software_special_notes" id="rpm-field-notes" class="form-input" maxlength="200">
        </div>

        <div class="form-group" style="margin: 0;">
          <label class="form-label" style="font-size: 0.8125rem;">详细说明 (支持 Markdown 编辑器)</label>
          <textarea name="software_description_md" id="rpm-field-desc" class="form-textarea markdown-editor" rows="6" maxlength="10000" placeholder="支持 Markdown 语法，可排版软件介绍..."></textarea>
        </div>
      </div>

      <!-- 3. 处理说明 / 附言 -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" for="rpm-review-reason" style="font-weight: 600; font-size: 0.8125rem;">
          <span>处理说明 / 附言 (将同步通知举报者与软件作者)</span>
        </label>
        <textarea name="review_reason" id="rpm-review-reason" class="form-textarea" rows="2" maxlength="500" placeholder="可填写具体的核实说明、更正详情或驳回理由..."></textarea>
      </div>

      <!-- 4. 底部动作条：取消 | 驳回、下架、修改后处理 -->
      <div class="modal-footer" style="padding-top: 14px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('admin-report-modal')">取消</button>
        
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
          <button type="button" class="btn-store-secondary" id="rpm-btn-resource-del" style="display: none; color: #dc2626; border-color: rgba(239, 68, 68, 0.4);" onclick="submitReportAction('resolve_delete_resource')">
            确认违规并删除该资源
          </button>
          
          <button type="button" class="btn-store-secondary" onclick="submitReportAction('dismiss')" title="认定为误报或无需处理，予以驳回">
            驳回
          </button>

          <button type="button" class="btn-store-secondary" id="rpm-btn-offline" style="color: #dc2626; border-color: rgba(239, 68, 68, 0.4);" onclick="submitReportAction('resolve_offline')" title="软件存在严重违规，执行下架屏蔽">
            下架
          </button>

          <button type="button" class="btn-store-primary" onclick="submitReportAction('resolve_update')" title="保存上方修改的软件资料并标记处理完毕">
            修改后处理
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openReportProcessModal(reportId) {
  const dataEl = document.getElementById('report-data-' + reportId);
  if (!dataEl) return;
  let data = {};
  try {
    data = JSON.parse(dataEl.textContent);
  } catch (e) {
    console.error('Failed to parse report data:', e);
    return;
  }

  document.getElementById('rpm-id').value = data.id || 0;
  document.getElementById('rpm-summary-name').innerText = data.software_name || '软件 #' + data.software_id;
  document.getElementById('rpm-summary-reason').innerText = data.reason || '其他原因';
  document.getElementById('rpm-summary-meta').innerText = '举报人: ' + (data.reporter || '访客') + ' · ' + (data.created_at || '');
  document.getElementById('rpm-summary-details').innerText = data.details || '举报人未填写文字说明';

  const resBox = document.getElementById('rpm-resource-box');
  const resDelBtn = document.getElementById('rpm-btn-resource-del');
  if (data.is_resource) {
    resBox.style.display = 'block';
    document.getElementById('rpm-resource-info').innerText = (data.resource_title || '关联资源') + ' (' + (data.resource_url || '') + ')';
    if (resDelBtn) resDelBtn.style.display = 'inline-block';
  } else {
    resBox.style.display = 'none';
    if (resDelBtn) resDelBtn.style.display = 'none';
  }

  document.getElementById('rpm-field-name').value = data.software_name || '';
  document.getElementById('rpm-field-category').value = data.category_id || 1;
  document.getElementById('rpm-field-official').value = data.official_url || '';
  document.getElementById('rpm-field-icon').value = data.icon_url || '';
  document.getElementById('rpm-field-github').value = data.github_url || '';
  document.getElementById('rpm-field-developer').value = data.developer || '';
  document.getElementById('rpm-field-tags').value = data.tags || '';
  document.getElementById('rpm-field-notes').value = data.special_notes || '';
  document.getElementById('rpm-review-reason').value = '';

  const descEl = document.getElementById('rpm-field-desc');
  const descVal = data.description_md || '';

  if (typeof window.openModal === 'function') {
    window.openModal('admin-report-modal');
  } else {
    document.getElementById('admin-report-modal').classList.add('active');
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

function submitReportAction(action) {
  document.getElementById('rpm-action').value = action;
  
  // 同步 EasyMDE 内容回 textarea
  const descEl = document.getElementById('rpm-field-desc');
  if (window.FluentMarkdown && window.FluentMarkdown.get(descEl)) {
    descEl.value = window.FluentMarkdown.get(descEl).value();
  }

  const reasonEl = document.getElementById('rpm-review-reason');
  if (!reasonEl.value.trim()) {
    if (action === 'resolve_update') {
      reasonEl.value = '管理员已核实并更正软件信息，处理完毕。';
    } else if (action === 'resolve_offline') {
      reasonEl.value = '经管理员核实确认违规，已对该软件执行下架处理。';
    } else if (action === 'dismiss') {
      reasonEl.value = '经管理员核对未发现违规或属误报，予以驳回。';
    } else if (action === 'resolve_delete_resource') {
      reasonEl.value = '经核实该相关资源确属违规/失效，已彻底清理删除。';
    }
  }
  document.getElementById('report-process-form').submit();
}
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
