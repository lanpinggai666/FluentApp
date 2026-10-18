<!-- 软件修改信息提交弹窗 -->
<div class="modal-overlay" id="software-correction-modal">
  <div class="modal-container" style="max-width: 860px; max-height: 92vh; overflow-y: auto;">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('edit', 'fluent-icon') ?>
        <span>修改软件信息</span>
      </div>
      <button type="button" class="notice-close" onclick="closeModal('software-correction-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form id="software-correction-form" class="modal-body" method="POST" action="<?= Url::route('software/submitCorrection') ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="software_id" value="<?= (int)$software['id'] ?>">
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">软件名称</label>
          <input type="text" name="name" class="form-input" maxlength="100" value="<?= View::e($software['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">软件分类</label>
          <select name="category_id" class="form-select" required>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int)$category['id'] ?>" <?= (int)$category['id'] === (int)($software['category_id'] ?? 0) ? 'selected' : '' ?>><?= View::e($category['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">图标网址</label>
          <input type="url" name="icon_url" class="form-input" maxlength="500" placeholder="https://" value="<?= View::e($software['icon_url'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">截图网址</label>
          <input type="url" name="screenshot_url" class="form-input" maxlength="500" placeholder="https://" value="<?= View::e($software['screenshot_url'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">收费模式</label>
          <select name="price_type" class="form-select">
            <option value="free" <?= ($software['price_type'] ?? 'free') === 'free' ? 'selected' : '' ?>>免费</option>
            <option value="freemium" <?= ($software['price_type'] ?? '') === 'freemium' ? 'selected' : '' ?>>免费增值</option>
            <option value="paid" <?= ($software['price_type'] ?? '') === 'paid' ? 'selected' : '' ?>>付费</option>
          </select>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_open_source" value="1" data-open-source-switch <?= !empty($software['is_open_source']) ? 'checked' : '' ?>>
            <span>开源软件</span>
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">特别说明</label>
        <textarea name="special_notes" class="form-textarea" maxlength="200" rows="2" placeholder="例如：永久免费且无任何弹窗广告，推荐下载 64 位版本。"><?= View::e($software['special_notes'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">软件说明 / 简介 <span style="color: var(--text-muted); font-weight: normal;">(Markdown)</span></label>
        <textarea name="description_md" class="form-textarea markdown-editor" maxlength="10000" rows="8" placeholder="支持 Markdown，详细介绍软件的功能特性、安装方法、运行环境等…"><?= View::e($software['description_md'] ?? '') ?></textarea>
      </div>
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">官网</label>
          <input type="url" name="official_url" class="form-input" maxlength="500" placeholder="https://" value="<?= View::e($software['official_url'] ?? '') ?>">
        </div>
        <div class="form-group" data-hide-if-open-source>
          <label class="form-label">GitHub</label>
          <input type="url" name="github_url" class="form-input" maxlength="500" placeholder="https://github.com/" value="<?= View::e($software['github_url'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">开发者</label>
          <input type="text" name="developer" class="form-input" maxlength="100" value="<?= View::e($software['developer'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">开源协议</label>
          <input type="text" name="license" class="form-input" maxlength="100" value="<?= View::e($software['license'] ?? '') ?>" placeholder="例如：MIT、GPL-3.0">
        </div>
        <div class="form-group" style="grid-column:1 / -1;">
          <label class="form-label">标签</label>
          <input type="text" name="tags" class="form-input" maxlength="500" value="<?= View::e($software['tags'] ?? '') ?>" placeholder="多个标签用逗号分隔">
        </div>
      </div>
      <div class="form-group">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">
          <label class="form-label" style="margin:0;">下载渠道</label>
          <button type="button" class="btn-store-secondary" id="add-correction-channel" style="padding:5px 12px;">添加渠道</button>
        </div>
        <div id="correction-channel-list" style="display:grid;gap:8px;">
          <?php $correctionChannels = !empty($otherUrls) ? $otherUrls : [['type' => '第三方下载', 'name' => '', 'url' => '']]; ?>
          <?php foreach ($correctionChannels as $channel): ?>
            <?php
              $curType = (string)($channel['type'] ?? '第三方下载站');
              $typeOptions = ['Github', 'Microsoft Store', '第三方下载站'];
              if (!in_array($curType, $typeOptions, true)) {
                  $typeOptions[] = $curType;
              }
            ?>
            <div class="correction-channel-row">
              <select name="channel_type[]" class="form-select" aria-label="渠道类型">
                <?php foreach ($typeOptions as $typeOption): ?>
                  <option value="<?= View::e($typeOption) ?>" <?= $typeOption === $curType ? 'selected' : '' ?>><?= View::e($typeOption) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="channel_name[]" class="form-input" maxlength="100" placeholder="渠道名称" value="<?= View::e($channel['name'] ?? '') ?>">
              <input type="url" name="channel_url[]" class="form-input" maxlength="500" placeholder="https://" value="<?= View::e($channel['url'] ?? '') ?>">
              <button type="button" class="btn-store-secondary remove-correction-channel" style="padding:6px 10px;" aria-label="删除下载渠道">删除</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (!empty($currentUser)): ?>
        <input type="hidden" name="submitter_email" value="<?= View::e($currentUser['email'] ?? '') ?>">
      <?php else: ?>
        <div class="form-group">
          <label class="form-label">联系邮箱 <span style="color:#ef4444;">*</span></label>
          <input type="email" name="submitter_email" class="form-input" maxlength="254" placeholder="用于接收审核结果和修改链接" autocomplete="email" required>
        </div>
      <?php endif; ?>
      <?= $correctionCaptchaHtml ?>
      <div class="modal-footer" style="padding:14px 0 0;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('software-correction-modal')">取消</button>
        <button type="submit" class="btn-store-primary">提交审核</button>
      </div>
    </form>
  </div>
</div>

