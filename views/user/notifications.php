<?php
require VIEW_PATH . '/user/layout_nav.php';
$unreadCount = Notification::getUnreadCount($user['id']);
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">站内信与通知</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      包括软件审核结果、软件管理变更、违规举报提醒及系统通知。
    </div>
  </div>
  <div style="display: flex; gap: 8px; align-items: center;">
    <?php if ($unreadCount > 0): ?>
      <form method="POST" action="<?= Url::route('user/readAllNotifications') ?>" style="margin: 0;">
        <?= Auth::csrfField() ?>
        <button type="submit" class="btn-store-secondary" style="height: 34px; padding: 0 14px; font-size: 0.8125rem;">
          全部标为已读
        </button>
      </form>
    <?php endif; ?>
    <?php if (!empty($notifications)): ?>
      <form method="POST" action="<?= Url::route('user/deleteReadNotifications') ?>" style="margin: 0;">
        <?= Auth::csrfField() ?>
        <button type="submit" class="btn-store-secondary" style="height: 34px; padding: 0 14px; font-size: 0.8125rem; color: #ef4444; display: inline-flex; align-items: center; gap: 4px;">
          <?= FluentIcon::render('delete', 'fluent-icon-xs') ?>
          <span>清空已读</span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <?php if (empty($notifications)): ?>
    <div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
      暂无站内信通知。
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 10px;" id="notifications-list">
      <?php foreach ($notifications as $nt): ?>
        <?php
          $isUnread = empty($nt['is_read']);
          $contentRaw = (string)$nt['content'];
          $summary = mb_substr(preg_replace('/\s+/', ' ', trim($contentRaw)), 0, 75, 'UTF-8');
          if (mb_strlen(trim($contentRaw), 'UTF-8') > 75) {
              $summary .= '...';
          }
        ?>
        <div class="notification-item <?= $isUnread ? 'is-unread' : 'is-read' ?>" 
             id="nt-item-<?= (int)$nt['id'] ?>"
             data-id="<?= (int)$nt['id'] ?>"
             data-unread="<?= $isUnread ? '1' : '0' ?>"
             style="border-radius: var(--radius-sm); background: <?= $isUnread ? 'rgba(0, 103, 184, 0.04)' : 'var(--bg-surface-secondary)' ?>; border: 1px solid <?= $isUnread ? 'rgba(0, 103, 184, 0.25)' : 'var(--border-subtle)' ?>; transition: all 0.2s ease; overflow: hidden;">
          
          <!-- 简略头部：默认展示，点击展开/收起 -->
          <div class="nt-header" 
               onclick="toggleNotification(<?= (int)$nt['id'] ?>)"
               style="padding: 12px 16px; cursor: pointer; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; user-select: none;">
            
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <?php if ($isUnread): ?>
                  <span class="nt-unread-dot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0;"></span>
                <?php endif; ?>
                <span class="nt-title" style="font-weight: 600; font-size: 0.9375rem; color: var(--text-main);">
                  <?= View::e($nt['title']) ?>
                </span>
                <span class="badge" style="background: var(--bg-surface); color: var(--text-muted); font-size: 0.6875rem; padding: 2px 6px;">
                  <?= View::e($nt['type'] ?? 'system') ?>
                </span>
              </div>
              
              <!-- 简略摘要预览 -->
              <div class="nt-summary" style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-left: <?= $isUnread ? '16px' : '0' ?>;">
                <?= View::e($summary) ?>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0; padding-top: 2px;">
              <span style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;">
                <?= date('Y-m-d H:i', strtotime($nt['created_at'])) ?>
              </span>
              <button type="button" class="btn-store-secondary" onclick="deleteNotification(<?= (int)$nt['id'] ?>, event)" title="删除此条站内信" style="height: 26px; width: 26px; padding: 0; font-size: 0.75rem; color: var(--text-muted); border: none; background: transparent; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px;">
                <?= FluentIcon::render('delete', 'fluent-icon-xs') ?>
              </button>
              <span class="nt-toggle-icon" id="nt-icon-<?= (int)$nt['id'] ?>" style="color: var(--text-muted); transition: transform 0.2s ease; display: inline-flex; align-items: center;">
                <?= FluentIcon::render('chevron-down', 'fluent-icon-xs') ?>
              </span>
            </div>
          </div>

          <!-- 详细内容区域：默认折叠，点开后展示 -->
          <div class="nt-detail-panel" id="nt-detail-<?= (int)$nt['id'] ?>" style="display: none; padding: 0 16px 14px; border-top: 1px dashed var(--border-subtle); margin-top: 2px;">
            <div style="padding-top: 12px; font-size: 0.875rem; color: var(--text-main); line-height: 1.6; white-space: pre-wrap; word-break: break-word;">
              <?= View::e($nt['content']) ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border-subtle);">
              <?php if (!empty($nt['link'])): ?>
                <a href="<?= View::e($nt['link']) ?>" class="btn-store-primary" style="height: 30px; padding: 0 14px; font-size: 0.8125rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                  <span>前往处理 / 查看详情</span>
                  <?= FluentIcon::render('arrow-right', 'fluent-icon-xs') ?>
                </a>
              <?php else: ?>
                <div></div>
              <?php endif; ?>

              <div style="display: flex; gap: 8px;">
                <?php if ($isUnread): ?>
                  <button type="button" class="btn-store-secondary nt-read-btn" onclick="markNotificationRead(<?= (int)$nt['id'] ?>, event)" style="height: 28px; padding: 0 10px; font-size: 0.75rem;">
                    标为已读
                  </button>
                <?php endif; ?>
                <button type="button" class="btn-store-secondary" onclick="deleteNotification(<?= (int)$nt['id'] ?>, event)" style="height: 28px; padding: 0 10px; font-size: 0.75rem; color: #ef4444; display: inline-flex; align-items: center; gap: 4px;">
                  <?= FluentIcon::render('delete', 'fluent-icon-xs') ?>
                  <span>删除</span>
                </button>
                <button type="button" class="btn-store-secondary" onclick="toggleNotification(<?= (int)$nt['id'] ?>)" style="height: 28px; padding: 0 10px; font-size: 0.75rem;">
                  收起
                </button>
              </div>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
function toggleNotification(id) {
  const panel = document.getElementById('nt-detail-' + id);
  const icon = document.getElementById('nt-icon-' + id);
  const item = document.getElementById('nt-item-' + id);
  if (!panel) return;

  const isHidden = panel.style.display === 'none';
  if (isHidden) {
    panel.style.display = 'block';
    if (icon) icon.style.transform = 'rotate(180deg)';
    
    // 如果是未读，点开后自动在后台标为已读
    if (item && item.getAttribute('data-unread') === '1') {
      markNotificationRead(id, null, true);
    }
  } else {
    panel.style.display = 'none';
    if (icon) icon.style.transform = 'rotate(0deg)';
  }
}

function markNotificationRead(id, event, silent) {
  if (event) {
    event.stopPropagation();
  }
  const item = document.getElementById('nt-item-' + id);
  const formData = new FormData();
  formData.append('id', id);

  fetch('<?= Url::route('user/readNotification') ?>', {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  }).then(res => res.json()).then(data => {
    if (data.success) {
      if (item) {
        item.setAttribute('data-unread', '0');
        item.style.background = 'var(--bg-surface-secondary)';
        item.style.borderColor = 'var(--border-subtle)';
        const dot = item.querySelector('.nt-unread-dot');
        if (dot) dot.remove();
        const readBtn = item.querySelector('.nt-read-btn');
        if (readBtn) readBtn.remove();
        const summary = item.querySelector('.nt-summary');
        if (summary) summary.style.paddingLeft = '0';
      }
    }
  }).catch(err => {
    console.error('Failed to mark read:', err);
  });
}

function deleteNotification(id, event) {
  if (event) {
    event.stopPropagation();
  }
  const item = document.getElementById('nt-item-' + id);
  const formData = new FormData();
  formData.append('id', id);

  fetch('<?= Url::route('user/deleteNotification') ?>', {
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
          const list = document.getElementById('notifications-list');
          if (list && list.querySelectorAll('.notification-item').length === 0) {
            list.innerHTML = '<div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">暂无站内信通知。</div>';
          }
        }, 200);
      }
    }
  }).catch(err => {
    console.error('Failed to delete notification:', err);
  });
}
</script>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
