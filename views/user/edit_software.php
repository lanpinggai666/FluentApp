<?php
require VIEW_PATH . '/user/layout_nav.php';
?>

<div class="admin-content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <div style="margin-bottom: 6px;">
      <a href="<?= Url::route('user/softwares') ?>" style="font-size: 0.8125rem; color: var(--text-muted); text-decoration: none;">&larr; 返回我管理的软件</a>
    </div>
    <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; color: var(--text-main);">修改软件：<?= View::e($software['name']) ?></h1>
    <div style="font-size: 0.875rem; color: var(--text-muted);">
      您是此软件的所有者/管理者，修改保存后将直接发布生效，无需经过后台再次审核。
    </div>
  </div>
  <div>
    <a href="<?= Url::detail($software['slug']) ?>" target="_blank" class="btn-store-secondary" style="height: 36px; padding: 0 16px; font-size: 0.875rem;">
      <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
      <span>前台页面</span>
    </a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #dc2626; margin-bottom: 20px; font-size: 0.875rem;">
    <?= View::e($error) ?>
  </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
  <div style="padding: 12px 16px; border-radius: var(--radius-md); background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #16a34a; margin-bottom: 20px; font-size: 0.875rem;">
    <?= View::e($success) ?>
  </div>
<?php endif; ?>

<div class="admin-card" style="max-width: 900px;">
  <form method="POST" action="<?= Url::route('user/editSoftware') ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$software['id'] ?>">

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">软件名称 <span style="color:#ef4444;">*</span></label>
        <input type="text" name="name" class="form-input" required value="<?= View::e($software['name']) ?>">
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">主分类 <span style="color:#ef4444;">*</span></label>
        <select name="category_id" class="form-select" required>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= ((int)$cat['id'] === (int)$software['category_id']) ? 'selected' : '' ?>>
              <?= View::e($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
      <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">软件图标 URL <span style="color:#ef4444;">*</span></label>
      <div style="display: flex; gap: 10px; align-items: center;">
        <img id="icon-preview" src="<?= View::e($software['icon_url']) ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: contain; background: #fff; border: 1px solid var(--border-subtle); flex-shrink: 0;" onerror="this.src='assets/images/default-app.svg'">
        <input type="url" name="icon_url" id="icon-input" class="form-input" required value="<?= View::e($software['icon_url']) ?>" style="flex: 1;" oninput="document.getElementById('icon-preview').src = this.value || 'assets/images/default-app.svg';">
      </div>
    </div>

    <!-- 下载渠道管理 -->
    <div style="background: var(--bg-page); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 16px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 0;">下载与获取链接</label>
        <button type="button" class="btn-store-secondary" onclick="addChannelRow()" style="height: 28px; padding: 0 10px; font-size: 0.75rem;">
          + 添加其他渠道
        </button>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">官方网站 / 主下载链接 <span style="color:#ef4444;">*</span></label>
          <input type="url" name="official_url" class="form-input" required value="<?= View::e($software['official_url']) ?>" placeholder="https://...">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="font-size: 0.8125rem; margin-bottom: 4px; display: block;">GitHub 仓库链接 (选填)</label>
          <input type="url" name="github_url" class="form-input" value="<?= View::e($software['github_url']) ?>" placeholder="https://github.com/...">
        </div>
      </div>

      <div id="channels-container" style="display: flex; flex-direction: column; gap: 8px;">
        <?php foreach ($channels as $ch): ?>
          <div class="channel-row" style="display: grid; grid-template-columns: 120px 140px 1fr 36px; gap: 8px; align-items: center;">
            <select name="channel_type[]" class="form-select" style="height: 34px; font-size: 0.8125rem;">
              <option value="官方直链" <?= ($ch['type'] ?? '') === '官方直链' ? 'selected' : '' ?>>官方直链</option>
              <option value="Microsoft Store" <?= ($ch['type'] ?? '') === 'Microsoft Store' ? 'selected' : '' ?>>微软商店</option>
              <option value="百度网盘" <?= ($ch['type'] ?? '') === '百度网盘' ? 'selected' : '' ?>>百度网盘</option>
              <option value="蓝奏云" <?= ($ch['type'] ?? '') === '蓝奏云' ? 'selected' : '' ?>>蓝奏云</option>
              <option value="123云盘" <?= ($ch['type'] ?? '') === '123云盘' ? 'selected' : '' ?>>123云盘</option>
              <option value="夸克网盘" <?= ($ch['type'] ?? '') === '夸克网盘' ? 'selected' : '' ?>>夸克网盘</option>
              <option value="第三方下载站" <?= ($ch['type'] ?? '') === '第三方下载站' ? 'selected' : '' ?>>第三方下载</option>
            </select>
            <input type="text" name="channel_name[]" class="form-input" style="height: 34px; font-size: 0.8125rem;" value="<?= View::e($ch['name'] ?? '下载') ?>" placeholder="渠道名">
            <input type="url" name="channel_url[]" class="form-input" style="height: 34px; font-size: 0.8125rem;" value="<?= View::e($ch['url'] ?? '') ?>" placeholder="链接 URL">
            <button type="button" onclick="this.closest('.channel-row').remove()" class="btn-icon" style="color: #ef4444;" title="删除">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">收费模式</label>
        <select name="price_type" class="form-select">
          <option value="free" <?= ($software['price_type'] ?? '') === 'free' ? 'selected' : '' ?>>免费获取</option>
          <option value="freemium" <?= ($software['price_type'] ?? '') === 'freemium' ? 'selected' : '' ?>>免费含内购/商业增值</option>
          <option value="paid" <?= ($software['price_type'] ?? '') === 'paid' ? 'selected' : '' ?>>付费软件</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">开发者 / 团队</label>
        <input type="text" name="developer" class="form-input" value="<?= View::e($software['developer'] ?? '') ?>" placeholder="例如：Microsoft">
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">源码授权</label>
        <select name="is_open_source" class="form-select">
          <option value="1" <?= !empty($software['is_open_source']) ? 'selected' : '' ?>>开源代码</option>
          <option value="0" <?= empty($software['is_open_source']) ? 'selected' : '' ?>>闭源专有</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">开源协议 (若开源)</label>
        <input type="text" name="license" class="form-input" value="<?= View::e($software['license'] ?? '') ?>" placeholder="例如：MIT, GPL-3.0, Apache-2.0">
      </div>
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
      <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">软件标签 (半角逗号分隔)</label>
      <input type="text" name="tags" class="form-input" value="<?= View::e($software['tags'] ?? '') ?>" placeholder="例如：效率, 开源, 文本编辑">
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
      <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">截图展示 URL (选填)</label>
      <input type="url" name="screenshot_url" class="form-input" value="<?= View::e($software['screenshot_url'] ?? '') ?>" placeholder="https://...">
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
      <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">版本特性 / 特别提示 (选填)</label>
      <input type="text" name="special_notes" class="form-input" value="<?= View::e($software['special_notes'] ?? '') ?>" placeholder="例如：Windows 11 必备组件、便携免安装版">
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
      <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">软件详细图文介绍 (Markdown 格式)</label>
      <textarea name="description_md" id="user-software-editor" class="form-input markdown-editor" style="min-height: 220px;"><?= View::e($software['description_md'] ?? '') ?></textarea>
      <div class="form-hint">支持 Markdown。列表页与搜索结果里那句「简介」会自动取这里的第一句话，不用另外填。</div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-subtle); padding-top: 16px;">
      <a href="<?= Url::route('user/softwares') ?>" class="btn-store-secondary" style="height: 38px; padding: 0 18px; font-size: 0.875rem;">取消</a>
      <button type="submit" class="btn-store-primary" style="height: 38px; padding: 0 24px; font-size: 0.875rem;">保存并立即生效</button>
    </div>
  </form>
</div>

<script>
function addChannelRow() {
  const container = document.getElementById('channels-container');
  const div = document.createElement('div');
  div.className = 'channel-row';
  div.style.cssText = 'display: grid; grid-template-columns: 120px 140px 1fr 36px; gap: 8px; align-items: center;';
  div.innerHTML = `
    <select name="channel_type[]" class="form-select" style="height: 34px; font-size: 0.8125rem;">
      <option value="官方直链">官方直链</option>
      <option value="Microsoft Store">微软商店</option>
      <option value="百度网盘">百度网盘</option>
      <option value="蓝奏云">蓝奏云</option>
      <option value="123云盘">123云盘</option>
      <option value="夸克网盘">夸克网盘</option>
      <option value="第三方下载站">第三方下载</option>
    </select>
    <input type="text" name="channel_name[]" class="form-input" style="height: 34px; font-size: 0.8125rem;" value="下载" placeholder="渠道名">
    <input type="url" name="channel_url[]" class="form-input" style="height: 34px; font-size: 0.8125rem;" placeholder="链接 URL">
    <button type="button" onclick="this.closest('.channel-row').remove()" class="btn-icon" style="color: #ef4444;" title="删除">✕</button>
  `;
  container.appendChild(div);
}
</script>

<?php require VIEW_PATH . '/user/layout_footer.php'; ?>
