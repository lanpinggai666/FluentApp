<?php
require VIEW_PATH . '/admin/layout_nav.php';
$csrfToken = Auth::csrfToken();
$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">用户与权限管理</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      共 <?= (int)$total ?> 位注册用户。支持授予开发者/积极贡献者等身份标签，执行封禁或操作权力限制。
    </div>
  </div>
  <div>
    <form method="GET" action="<?= Url::basePath() ?>index.php" style="display: flex; gap: 8px;">
      <input type="hidden" name="r" value="admin/users">
      <input type="text" name="q" value="<?= View::e($search) ?>" class="form-input" placeholder="搜索用户名或邮箱" style="height: 36px; font-size: 0.8125rem; width: 220px;">
      <button type="submit" class="btn-store-secondary" style="height: 36px; padding: 0 14px; font-size: 0.8125rem;">搜索</button>
      <?php if ($search): ?>
        <a href="<?= Url::route('admin/users') ?>" class="btn-store-secondary" style="height: 36px; padding: 0 10px; font-size: 0.8125rem;">清空</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($successMsg): ?>
  <div style="padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 16px; font-size: 0.8125rem;">
    <?= View::e($successMsg) ?>
  </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
  <div style="padding: 10px 14px; border-radius: var(--radius-sm); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 16px; font-size: 0.8125rem;">
    <?= View::e($errorMsg) ?>
  </div>
<?php endif; ?>

<div class="fluent-card" style="padding: 20px; border-radius: var(--radius-lg); background: var(--bg-surface); border: 1px solid var(--border-subtle);">
  <?php if (empty($users)): ?>
    <div style="padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
      没有找到匹配的用户
    </div>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
        <thead>
          <tr style="border-bottom: 1px solid var(--border-subtle); color: var(--text-muted); text-align: left;">
            <th style="padding: 10px 12px; font-weight: 600;">ID</th>
            <th style="padding: 10px 12px; font-weight: 600;">用户</th>
            <th style="padding: 10px 12px; font-weight: 600;">角色</th>
            <th style="padding: 10px 12px; font-weight: 600;">身份标签</th>
            <th style="padding: 10px 12px; font-weight: 600;">状态与权限</th>
            <th style="padding: 10px 12px; font-weight: 600;">注册时间</th>
            <th style="padding: 10px 12px; font-weight: 600; text-align: right;">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php
              $badges = [];
              if (!empty($u['badges'])) {
                $dec = json_decode((string)$u['badges'], true);
                if (is_array($dec)) $badges = $dec;
              }
              $restricted = [];
              if (!empty($u['restricted_permissions'])) {
                $dec2 = json_decode((string)$u['restricted_permissions'], true);
                if (is_array($dec2)) $restricted = $dec2;
              }
              $isBannedPerm = !empty($u['is_banned']);
              $isBannedTemp = (!empty($u['banned_until']) && strtotime($u['banned_until']) > time());
            ?>
            <tr style="border-bottom: 1px solid var(--border-subtle);">
              <td style="padding: 12px; color: var(--text-muted); font-mono; font-size: 0.8125rem;">
                #<?= (int)$u['id'] ?>
              </td>
              <td style="padding: 12px;">
                <div style="font-weight: 600; color: var(--text-main);"><?= View::e($u['username']) ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= View::e($u['email']) ?></div>
              </td>
              <td style="padding: 12px;">
                <?php if ($u['role'] === 'admin'): ?>
                  <span class="badge" style="background: rgba(0, 103, 184, 0.12); color: var(--primary); font-weight: 600;">管理员</span>
                <?php else: ?>
                  <span class="badge" style="background: var(--bg-page); color: var(--text-muted);">普通用户</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px;">
                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                  <?php if (!empty($badges)): ?>
                    <?php foreach ($badges as $b): ?>
                      <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.6875rem;">
                        <?= View::e($b) ?>
                      </span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span style="color: var(--text-muted); font-size: 0.75rem;">无</span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="padding: 12px;">
                <?php if ($isBannedPerm): ?>
                  <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #dc2626; font-weight: 600;">永久封禁</span>
                  <?php if (!empty($u['ban_reason'])): ?>
                    <div style="font-size: 0.6875rem; color: #dc2626; margin-top: 2px;"><?= View::e($u['ban_reason']) ?></div>
                  <?php endif; ?>
                <?php elseif ($isBannedTemp): ?>
                  <span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #b45309; font-weight: 600;">封禁中</span>
                  <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 2px;">至 <?= View::e($u['banned_until']) ?></div>
                <?php elseif (!empty($restricted)): ?>
                  <span class="badge" style="background: rgba(249, 115, 22, 0.12); color: #ea580c;">部分受限</span>
                  <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 2px;"><?= View::e(implode(',', $restricted)) ?></div>
                <?php else: ?>
                  <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: #16a34a;">正常</span>
                <?php endif; ?>
              </td>
              <td style="padding: 12px; color: var(--text-muted); font-size: 0.75rem;">
                <?= date('Y-m-d H:i', strtotime($u['created_at'])) ?>
              </td>
              <td style="padding: 12px; text-align: right;">
                <div style="display: inline-flex; gap: 6px;">
                  <button type="button" class="btn-store-secondary" style="height: 28px; padding: 0 8px; font-size: 0.75rem;" onclick='openBadgeModal(<?= (int)$u['id'] ?>, <?= View::jsArg($u['username']) ?>, <?= View::jsArg(implode(",", $badges)) ?>)'>
                    标签
                  </button>
                  <?php if ($u['role'] === 'admin'): ?>
                    <span style="font-size: 0.75rem; color: var(--text-muted); padding: 4px 6px; align-self: center;">受保护</span>
                  <?php else: ?>
                    <button type="button" class="btn-store-secondary" style="height: 28px; padding: 0 8px; font-size: 0.75rem; color: #dc2626;" onclick='openPunishModal(<?= (int)$u['id'] ?>, <?= View::jsArg($u['username']) ?>, <?= (int)$isBannedPerm ?>, <?= View::jsArg($u['banned_until'] ?? '') ?>, <?= View::jsArg($u['ban_reason'] ?? '') ?>, <?= View::jsArg($restricted) ?>)'>
                      处罚
                    </button>
                    <?php if ($isBannedPerm || $isBannedTemp || !empty($restricted)): ?>
                      <form method="POST" action="<?= Url::route('admin/users') ?>" style="margin: 0; display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="unpunish">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn-store-secondary" style="height: 28px; padding: 0 8px; font-size: 0.75rem; color: #16a34a;"
                                data-confirm="确认解除该用户的全部处罚与权力限制？"
                                data-confirm-title="解除处罚"
                                data-confirm-ok="确认解封"
                                data-confirm-tone="info">
                          解封
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- 赋予标签 Modal -->
<div class="modal-overlay" id="badge-modal" style="z-index: 100020;">
  <div class="modal-container" style="max-width: 420px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700;">设置用户身份标签</h3>
      <button type="button" class="btn-icon" onclick="window.closeModal('badge-modal')">✕</button>
    </div>

    <form method="POST" action="<?= Url::route('admin/users') ?>">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <input type="hidden" name="action" value="set_badges">
      <input type="hidden" name="user_id" id="badge-user-id" value="">

      <div style="font-size: 0.875rem; margin-bottom: 12px;">
        用户：<strong id="badge-username"></strong>
      </div>

      <div class="form-group" style="margin-bottom: 12px;">
        <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 6px;">身份标签 (英文逗号分隔)</label>
        <input type="text" name="badges" id="badge-input" class="form-input" placeholder="例如：开发者, 积极贡献者, 认证作者">
      </div>

      <div style="margin-bottom: 16px;">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px;">常用标签快捷点选：</div>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
          <button type="button" class="btn-store-secondary" style="height: 24px; padding: 0 8px; font-size: 0.6875rem;" onclick="appendBadge('开发者')">+ 开发者</button>
          <button type="button" class="btn-store-secondary" style="height: 24px; padding: 0 8px; font-size: 0.6875rem;" onclick="appendBadge('积极贡献者')">+ 积极贡献者</button>
          <button type="button" class="btn-store-secondary" style="height: 24px; padding: 0 8px; font-size: 0.6875rem;" onclick="appendBadge('认证作者')">+ 认证作者</button>
          <button type="button" class="btn-store-secondary" style="height: 24px; padding: 0 8px; font-size: 0.6875rem;" onclick="appendBadge('开源贡献团队')">+ 开源贡献团队</button>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn-store-secondary" onclick="window.closeModal('badge-modal')" style="height: 34px; padding: 0 14px; font-size: 0.8125rem;">取消</button>
        <button type="submit" class="btn-store-primary" style="height: 34px; padding: 0 18px; font-size: 0.8125rem;">保存标签</button>
      </div>
    </form>
  </div>
</div>

<!-- 处罚用户 Modal -->
<div class="modal-overlay" id="punish-modal" style="z-index: 100020;">
  <div class="modal-container" style="max-width: 480px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: #dc2626;">处罚与权力限制</h3>
      <button type="button" class="btn-icon" onclick="window.closeModal('punish-modal')">✕</button>
    </div>

    <form method="POST" action="<?= Url::route('admin/users') ?>">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <input type="hidden" name="action" value="punish">
      <input type="hidden" name="user_id" id="punish-user-id" value="">

      <div style="font-size: 0.875rem; margin-bottom: 14px;">
        目标用户：<strong id="punish-username"></strong>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 6px;">账号封禁选项</label>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="radio" name="ban_type" value="none" id="ban-type-none" checked onchange="toggleBanFields()">
            <span>不封禁账号（仅限制部分操作权限）</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="radio" name="ban_type" value="temp" id="ban-type-temp" onchange="toggleBanFields()">
            <span>封禁一段时间</span>
          </label>
          <div id="ban-temp-date-box" style="display: none; margin-left: 20px;">
            <input type="datetime-local" name="banned_until" id="punish-banned-until" class="form-input" style="height: 32px; font-size: 0.8125rem; width: 220px;">
          </div>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="radio" name="ban_type" value="perm" id="ban-type-perm" onchange="toggleBanFields()">
            <span style="color: #dc2626; font-weight: 600;">永久封禁账号</span>
          </label>
          <input type="hidden" name="permanent" id="punish-permanent" value="0">
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 6px;">限制具体操作权力 (封禁时所有权力自动冻结)</label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="checkbox" name="restricted_permissions[]" value="comment" id="perm-comment">
            <span>禁止发表评价</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="checkbox" name="restricted_permissions[]" value="submit_software" id="perm-submit-software">
            <span>禁止提交新软件</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="checkbox" name="restricted_permissions[]" value="submit_resource" id="perm-submit-resource">
            <span>禁止提交相关资源</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="checkbox" name="restricted_permissions[]" value="correction" id="perm-correction">
            <span>禁止修改/补充软件</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; cursor: pointer;">
            <input type="checkbox" name="restricted_permissions[]" value="report" id="perm-report">
            <span>禁止提交违规举报</span>
          </label>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 18px;">
        <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 6px;">处罚原因 (将在通知及登录时向用户告知)</label>
        <input type="text" name="ban_reason" id="punish-reason" class="form-input" placeholder="例如：多次提交恶意垃圾链接/恶意刷评">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn-store-secondary" onclick="window.closeModal('punish-modal')" style="height: 34px; padding: 0 14px; font-size: 0.8125rem;">取消</button>
        <button type="submit" class="btn-store-primary" style="height: 34px; padding: 0 18px; font-size: 0.8125rem; background: #dc2626;">执行处罚</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBadgeModal(id, username, badges) {
  document.getElementById('badge-user-id').value = id;
  document.getElementById('badge-username').textContent = username;
  document.getElementById('badge-input').value = badges;
  window.openModal('badge-modal');
}

function appendBadge(name) {
  const inp = document.getElementById('badge-input');
  const cur = inp.value.split(',').map(s => s.trim()).filter(Boolean);
  if (!cur.includes(name)) {
    cur.push(name);
    inp.value = cur.join(', ');
  }
}

function openPunishModal(id, username, isPerm, bannedUntil, reason, restricted) {
  document.getElementById('punish-user-id').value = id;
  document.getElementById('punish-username').textContent = username;
  document.getElementById('punish-reason').value = reason || '';

  if (isPerm) {
    document.getElementById('ban-type-perm').checked = true;
  } else if (bannedUntil) {
    document.getElementById('ban-type-temp').checked = true;
    document.getElementById('punish-banned-until').value = bannedUntil.replace(' ', 'T').substring(0, 16);
  } else {
    document.getElementById('ban-type-none').checked = true;
  }
  toggleBanFields();

  // 权限复选框
  const perms = ['comment', 'submit_software', 'submit_resource', 'correction', 'report'];
  perms.forEach(p => {
    const el = document.getElementById('perm-' + p.replace('_', '-'));
    if (el) el.checked = Array.isArray(restricted) && restricted.includes(p);
  });

  window.openModal('punish-modal');
}

function toggleBanFields() {
  const isTemp = document.getElementById('ban-type-temp').checked;
  const isPerm = document.getElementById('ban-type-perm').checked;
  document.getElementById('ban-temp-date-box').style.display = isTemp ? 'block' : 'none';
  document.getElementById('punish-permanent').value = isPerm ? '1' : '0';
}
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
