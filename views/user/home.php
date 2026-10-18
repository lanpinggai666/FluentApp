<?php
/**
 * 蓝屏钙软件导航网 - 用户公开个人主页 (Fluent UI)
 */
$avatarUrl = $targetUser['avatar_url'] ?? '';
$joinDays = max(1, (int) ceil((time() - strtotime($targetUser['created_at'])) / 86400));
$isAdmin = ($targetUser['role'] === 'admin');
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 24px 16px 60px;">

  <!-- 面包屑导航 -->
  <nav style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
    <a href="<?= Url::route('home') ?>" style="color: var(--text-muted); text-decoration: none;">首页</a>
    <span>&gt;</span>
    <span style="color: var(--text-primary); font-weight: 500;">用户空间</span>
    <span>&gt;</span>
    <span style="color: var(--text-primary); font-weight: 600;"><?= View::e($targetUser['username']) ?></span>
  </nav>

  <!-- 用户资料 Hero 卡片 -->
  <div class="admin-card" style="padding: 32px 28px; border-radius: var(--radius-lg); margin-bottom: 28px; box-shadow: var(--shadow-fluent-hover); background: var(--bg-surface); position: relative; overflow: hidden;">
    
    <!-- 浅层背景微光 -->
    <div style="position: absolute; right: -40px; top: -40px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(0, 120, 212, 0.12) 0%, transparent 70%); pointer-events: none;"></div>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 24px; position: relative; z-index: 1;">
      
      <!-- 左侧：头像与核心信息 -->
      <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
        <div style="position: relative;">
          <?php if (!empty($avatarUrl)): ?>
            <img src="<?= View::e($avatarUrl) ?>" alt="<?= View::e($targetUser['username']) ?>"
                 style="width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 3px solid var(--bg-surface); box-shadow: var(--shadow-card); background: var(--bg-surface-secondary);"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%2394a3b8%22><path d=%22M10 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm-6 13a6 6 0 1 1 12 0v.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-.5Z%22/></svg>';">
          <?php else: ?>
            <div style="width: 84px; height: 84px; border-radius: 50%; background: linear-gradient(135deg, #0078d4, #00bcf2); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2rem; font-weight: 700; box-shadow: var(--shadow-card);">
              <?= mb_strtoupper(mb_substr($targetUser['username'], 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div>
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-primary); line-height: 1.3;">
              <?= View::e($targetUser['username']) ?>
            </h1>

            <?php if ($isAdmin): ?>
              <span class="badge" style="background: rgba(0, 120, 212, 0.12); color: var(--ms-blue); border: 1px solid rgba(0, 120, 212, 0.25); font-weight: 600; font-size: 0.75rem; padding: 2px 8px;">
                <?= FluentIcon::render('shield-checkmark', 'fluent-icon-xs') ?>
                <span>平台管理员</span>
              </span>
            <?php endif; ?>

            <?php foreach ($badges as $bg): ?>
              <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); font-weight: 600; font-size: 0.75rem; padding: 2px 8px;">
                <?= View::e($bg) ?>
              </span>
            <?php endforeach; ?>
          </div>

          <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap; margin-top: 8px; font-size: 0.8125rem; color: var(--text-muted);">
            <span>UID: #<?= (int)$targetUser['id'] ?></span>
            <span>·</span>
            <span>已加入 <strong><?= $joinDays ?></strong> 天 (<?= substr($targetUser['created_at'], 0, 10) ?>)</span>
                      </div>
        </div>
      </div>

      <!-- 右侧：自己的主页时展示快捷入口 -->
      <?php if ($isSelf): ?>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <a href="<?= Url::route('user/profile') ?>" class="btn-store-secondary" style="height: 34px; padding: 0 14px; font-size: 0.8125rem;">
            <?= FluentIcon::render('settings', 'fluent-icon-xs') ?>
            <span>资料与设置</span>
          </a>
          <a href="<?= Url::route('user/dashboard') ?>" class="btn-store-primary" style="height: 34px; padding: 0 16px; font-size: 0.8125rem;">
            <?= FluentIcon::render('person', 'fluent-icon-xs') ?>
            <span>进入管理中心</span>
          </a>
        </div>
      <?php endif; ?>

    </div>

    <!-- 统计指标格 (Fluent 极简卡片) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--border-subtle);">
      
      <div style="padding: 12px 16px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
        <div style="font-size: 0.75rem; color: var(--text-muted);">维护收录软件</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--ms-blue); margin-top: 4px;">
          <?= count($softwares) ?> <span style="font-size: 0.8125rem; font-weight: normal; color: var(--text-muted);">款</span>
        </div>
      </div>

      <div style="padding: 12px 16px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
        <div style="font-size: 0.75rem; color: var(--text-muted);">发表软件评价</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 4px;">
          <?= (int)$stats['comments_count'] ?> <span style="font-size: 0.8125rem; font-weight: normal; color: var(--text-muted);">条</span>
        </div>
      </div>

      <div style="padding: 12px 16px; border-radius: var(--radius-sm); background: var(--bg-surface-secondary); border: 1px solid var(--border-subtle);">
        <div style="font-size: 0.75rem; color: var(--text-muted);">软件总浏览人次</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6; margin-top: 4px;">
          <?= number_format($totalViews) ?>
        </div>
      </div>

    </div>

  </div>

  <!-- 内容选项卡切换 -->
  <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-subtle); margin-bottom: 24px;">
    <button type="button" class="user-tab-btn active" id="tab-btn-softwares" onclick="switchUserTab('softwares')">
      <?= FluentIcon::render('desktop', 'fluent-icon-sm') ?>
      <span>管理的软件 (<?= count($softwares) ?>)</span>
    </button>
    <button type="button" class="user-tab-btn" id="tab-btn-comments" onclick="switchUserTab('comments')">
      <?= FluentIcon::render('comment', 'fluent-icon-sm') ?>
      <span>发表的评价 (<?= count($comments) ?>)</span>
    </button>
  </div>

  <!-- Tab 1: 维护管理的软件 -->
  <div id="tab-content-softwares" class="user-tab-content active">
    <?php if (empty($softwares)): ?>
      <div class="admin-card" style="padding: 50px 20px; text-align: center; color: var(--text-muted);">
        <?= FluentIcon::render('desktop', 'fluent-icon-lg') ?>
        <div style="margin-top: 10px; font-size: 0.9375rem;">该用户暂未发布任何软件</div>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px;">
        <?php foreach ($softwares as $sw): ?>
          <a href="<?= Url::detail($sw['slug']) ?>" class="user-software-card">
            <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 12px;">
              <img src="<?= View::e($sw['icon_url']) ?>" alt="" style="width: 44px; height: 44px; border-radius: 8px; object-fit: contain; background: #fff; border: 1px solid var(--border-subtle); flex-shrink: 0;" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%230078d4%22><rect width=%2220%22 height=%2220%22 rx=%224%22/></svg>';">
              <div style="min-width: 0; flex: 1;">
                <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <?= View::e($sw['name']) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                  <?= View::e($sw['category_name'] ?? '实用工具') ?> · <?= View::e($sw['developer'] ?: '官方开发团队') ?>
                </div>
              </div>
            </div>

            <div style="font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 14px; min-height: 38px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
              <?= View::e($sw['special_notes'] ?: '暂无特殊说明，点击直接进入官方下载通道。') ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted); padding-top: 10px; border-top: 1px solid var(--border-subtle);">
              <span style="display: flex; align-items: center; gap: 4px;">
                <span style="color: #f59e0b;">★</span>
                <span><?= number_format((float)$sw['rating_avg'], 1) ?></span>
              </span>
              <span><?= number_format((int)$sw['views_count']) ?> 次查看</span>
              <span style="color: var(--ms-blue); font-weight: 500;">查看详情 &rarr;</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Tab 2: 发表的评价 -->
  <div id="tab-content-comments" class="user-tab-content" style="display: none;">
    <?php if (empty($comments)): ?>
      <div class="admin-card" style="padding: 50px 20px; text-align: center; color: var(--text-muted);">
        <?= FluentIcon::render('comment', 'fluent-icon-lg') ?>
        <div style="margin-top: 10px; font-size: 0.9375rem;">该用户暂无评价</div>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($comments as $cm): ?>
          <div class="admin-card" style="padding: 16px 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
              <a href="<?= Url::detail($cm['software_slug']) ?>" style="font-weight: 600; font-size: 0.9375rem; color: var(--ms-blue); text-decoration: none; display: flex; align-items: center; gap: 8px;">
                <?php if (!empty($cm['software_icon'])): ?>
                  <img src="<?= View::e($cm['software_icon']) ?>" alt="" style="width: 20px; height: 20px; border-radius: 4px; object-fit: contain;">
                <?php endif; ?>
                <span>评「<?= View::e($cm['software_name']) ?>」</span>
              </a>

              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="color: #f59e0b; font-size: 0.8125rem;">
                  <?= str_repeat('★', (int)$cm['rating']) ?><?= str_repeat('☆', 5 - (int)$cm['rating']) ?>
                </span>
                <span style="font-size: 0.75rem; color: var(--text-muted);"><?= substr($cm['created_at'], 0, 16) ?></span>
              </div>
            </div>

            <?php if (!empty($cm['title'])): ?>
              <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-primary); margin-bottom: 4px;">
                <?= View::e($cm['title']) ?>
              </div>
            <?php endif; ?>

            <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
              <?= nl2br(View::e($cm['content'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<style>
.user-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border: none;
  background: none;
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.16s ease;
}
.user-tab-btn:hover {
  color: var(--text-primary);
}
.user-tab-btn.active {
  color: var(--ms-blue);
  border-bottom-color: var(--ms-blue);
  font-weight: 600;
}
.user-software-card {
  display: block;
  text-decoration: none;
  background: var(--bg-surface);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-md);
  padding: 18px;
  box-shadow: var(--shadow-card);
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, border-color 0.2s ease;
}
.user-software-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-fluent-hover);
  border-color: rgba(0, 120, 212, 0.35);
}
</style>

<script>
function switchUserTab(tabKey) {
  document.getElementById('tab-btn-softwares').classList.toggle('active', tabKey === 'softwares');
  document.getElementById('tab-btn-comments').classList.toggle('active', tabKey === 'comments');
  
  document.getElementById('tab-content-softwares').style.display = (tabKey === 'softwares') ? 'block' : 'none';
  document.getElementById('tab-content-comments').style.display = (tabKey === 'comments') ? 'block' : 'none';
}
</script>
