<?php
require VIEW_PATH . '/user/layout_nav.php';
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">个人中心总览</h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      欢迎回来，<strong style="color: var(--text-main);"><?= View::e($user['username']) ?></strong>
      <?php if (!empty($user['role']) && $user['role'] === 'admin'): ?>
        <span class="badge" style="background: rgba(0, 103, 184, 0.12); color: var(--primary); margin-left: 6px;">管理员</span>
      <?php endif; ?>
    </div>
  </div>
  <div style="display: flex; gap: 10px;">
    <a href="<?= Url::route('submit') ?>" class="btn-store-primary" style="height: 36px; padding: 0 16px; font-size: 0.875rem;">
      <?= FluentIcon::render('add', 'fluent-icon-sm') ?>
      <span>提交新软件</span>
    </a>
  </div>
</div>

<!-- 统计数据卡片 (与管理后台统一样式) -->
<div class="admin-stat-grid">
  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('folder', 'fluent-icon-sm') ?>
      <span>提交软件总数</span>
    </div>
    <div class="admin-stat-val" style="color: var(--ms-blue);"><?= (int)($stats['softwares_total'] ?? $stats['submitted_softwares'] ?? 0) ?></div>
    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">
      已收录: <?= (int)($stats['softwares_published'] ?? $stats['published_softwares'] ?? 0) ?> · 审核中: <?= (int)($stats['softwares_pending'] ?? $stats['pending_softwares'] ?? 0) ?>
    </div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('comment', 'fluent-icon-sm') ?>
      <span>发表评价</span>
    </div>
    <div class="admin-stat-val" style="color: #10b981;"><?= (int)($stats['comments_count'] ?? $stats['comments'] ?? 0) ?></div>
    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">已发表评价记录</div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('flag', 'fluent-icon-sm') ?>
      <span>待处理软件举报</span>
    </div>
    <div class="admin-stat-val" style="color: <?= ($stats['reports_pending'] ?? $stats['received_reports'] ?? 0) > 0 ? '#ef4444' : 'var(--text-primary)' ?>;">
      <?= (int)($stats['reports_pending'] ?? $stats['received_reports'] ?? 0) ?>
    </div>
    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">名下软件待处理项</div>
  </div>

  <div class="admin-stat-card">
    <div class="admin-stat-label">
      <?= FluentIcon::render('megaphone', 'fluent-icon-sm') ?>
      <span>未读站内信</span>
    </div>
    <div class="admin-stat-val" style="color: <?= ($stats['unread_notifications'] ?? 0) > 0 ? 'var(--ms-blue)' : 'var(--text-primary)' ?>;">
      <?= (int)($stats['unread_notifications'] ?? 0) ?>
    </div>
    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">系统与审核通知</div>
  </div>
</div>

<div style="display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 20px;">
  <!-- 左侧：我管理的软件与举报 -->
  <div style="display: flex; flex-direction: column; gap: 20px;">
    <!-- 软件列表 -->
    <div class="admin-card" style="margin-bottom: 0;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 1.0625rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
          <?= FluentIcon::render('desktop', 'fluent-icon-sm') ?>
          <span>我管理的软件</span>
        </h3>
        <a href="<?= Url::route('user/softwares') ?>" class="btn-store-secondary" style="font-size: 0.8125rem; height: 30px; padding: 0 12px;">全部管理 &rarr;</a>
      </div>

      <?php if (empty($recentSoftwares)): ?>
        <div style="padding: 30px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
          暂无提交的软件。<a href="<?= Url::route('submit') ?>" style="color: var(--primary);">立即提交软件</a>
        </div>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <?php foreach ($recentSoftwares as $sw): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
              <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                <img src="<?= View::e($sw['icon_url'] ?: 'assets/images/default-app.svg') ?>" alt="" style="width: 36px; height: 36px; border-radius: 8px; object-fit: contain; background: #fff; padding: 2px; border: 1px solid var(--border-subtle); flex-shrink: 0;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><rect width=%2220%22 height=%2220%22 fill=%22%23cbd5e1%22/></svg>'">
                <div style="min-width: 0;">
                  <div style="font-weight: 600; font-size: 0.9375rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= View::e($sw['name']) ?>
                  </div>
                  <div style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                    <?php if ($sw['status'] === 'published'): ?>
                      <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: #16a34a;">已收录</span>
                    <?php elseif ($sw['status'] === 'pending'): ?>
                      <span class="badge" style="background: rgba(234, 179, 8, 0.12); color: #ca8a04;">待审核</span>
                    <?php else: ?>
                      <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626;">已下架/拒绝</span>
                    <?php endif; ?>
                    <span>浏览 <?= (int)($sw['views_count'] ?? $sw['views'] ?? 0) ?></span>
                    <span>下载 <?= (int)($sw['download_clicks'] ?? $sw['downloads'] ?? 0) ?></span>
                  </div>
                </div>
              </div>
              <div style="display: flex; gap: 8px; flex-shrink: 0;">
                <a href="<?= Url::route('user/editSoftware', ['id' => (int)$sw['id']]) ?>" class="btn-store-secondary" style="height: 30px; padding: 0 10px; font-size: 0.75rem;">直接修改</a>
                <a href="<?= Url::detail($sw['slug']) ?>" target="_blank" class="btn-store-secondary" style="height: 30px; padding: 0 10px; font-size: 0.75rem;">查看前台</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- 待处理举报 -->
    <div class="admin-card" style="margin-bottom: 0;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 1.0625rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
          <?= FluentIcon::render('flag', 'fluent-icon-sm') ?>
          <span>收到的举报待处理</span>
        </h3>
        <a href="<?= Url::route('user/reports') ?>" class="btn-store-secondary" style="font-size: 0.8125rem; height: 30px; padding: 0 12px;">全部举报 &rarr;</a>
      </div>

      <?php if (empty($recentReports)): ?>
        <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
          太棒了！名下软件当前没有待处理的违规举报。
        </div>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <?php foreach ($recentReports as $rp): ?>
            <div style="padding: 12px 14px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-size: 0.875rem; font-weight: 600; color: var(--text-main);">
                  软件：<?= View::e($rp['software_name']) ?>
                  <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; margin-left: 6px;"><?= View::e($rp['reason']) ?></span>
                </div>
                <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">
                  <?= View::e(mb_strimwidth($rp['details'] ?: ($rp['description'] ?? '无具体说明'), 0, 60, '...')) ?>
                </div>
              </div>
              <div>
                <a href="<?= Url::route('user/reports') ?>" class="btn-store-secondary" style="height: 30px; padding: 0 10px; font-size: 0.75rem;">处理</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 右侧：最新站内信 -->
  <div class="admin-card" style="margin-bottom: 0; height: fit-content;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 1.0625rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('megaphone', 'fluent-icon-sm') ?>
        <span>最新站内信</span>
      </h3>
      <a href="<?= Url::route('user/notifications') ?>" class="btn-store-secondary" style="font-size: 0.8125rem; height: 30px; padding: 0 12px;">全部通知 &rarr;</a>
    </div>

    <?php if (empty($recentNotifications)): ?>
      <div style="padding: 30px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
        暂无最新通知。
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <?php foreach ($recentNotifications as $nt): ?>
          <div style="padding: 12px 14px; border-radius: var(--radius-sm); background: <?= empty($nt['is_read']) ? 'rgba(0, 103, 184, 0.04)' : 'var(--bg-surface-secondary)' ?>; border: 1px solid <?= empty($nt['is_read']) ? 'rgba(0, 103, 184, 0.2)' : 'var(--border-subtle)' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
              <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);">
                <?php if (empty($nt['is_read'])): ?>
                  <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--primary); margin-right: 4px; vertical-align: middle;"></span>
                <?php endif; ?>
                <?= View::e($nt['title']) ?>
              </span>
              <span style="font-size: 0.75rem; color: var(--text-muted);"><?= date('m-d H:i', strtotime($nt['created_at'])) ?></span>
            </div>
            <div style="font-size: 0.8125rem; color: var(--text-muted); line-height: 1.4;">
              <?= nl2br(View::e($nt['content'])) ?>
            </div>
            <?php if (!empty($nt['link'])): ?>
              <div style="margin-top: 6px;">
                <a href="<?= View::e($nt['link']) ?>" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">查看详情 &rarr;</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
