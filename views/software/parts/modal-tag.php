<!-- 用户打标签弹窗 -->
<div class="modal-overlay" id="add-tag-modal">
  <div class="modal-container" style="max-width: 440px;">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('tag', 'fluent-icon') ?>
        <span>为「<?= View::e($software['name']) ?>」打标签</span>
      </div>
      <button class="notice-close" onclick="closeModal('add-tag-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form id="add-tag-form" class="modal-body">
      <input type="hidden" name="software_id" value="<?= (int)$software['id'] ?>">
      
      <div class="form-group">
        <label class="form-label">输入标签名称</label>
        <input type="text" name="tag" id="new-tag-input" class="form-input" placeholder="例如：解压缩、开源、剪贴板" maxlength="15" required autocomplete="off">
      </div>

      <div style="margin-bottom: 12px;">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px;">快捷推荐标签:</div>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
          <?php $presetTags = ['开源', '免费无广告', '免安装', '绿色便携', 'Win11', '高效办公', '轻量小巧', '装机必备']; ?>
          <?php foreach ($presetTags as $pt): ?>
            <span class="tag-pill" style="cursor: pointer;" onclick="document.getElementById('new-tag-input').value='<?= $pt ?>'"><?= $pt ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="modal-footer" style="padding: 12px 0 0;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('add-tag-modal')">取消</button>
        <button type="submit" class="btn-store-primary">确认添加</button>
      </div>
    </form>
  </div>
</div>

