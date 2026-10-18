<!-- 用户提交视频教程等第三方额外信息 弹窗 -->
<div class="modal-overlay" id="add-resource-modal">
  <div class="modal-container" style="max-width: 480px;">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('play', 'fluent-icon') ?>
        <span>分享视频教程或第三方资源</span>
      </div>
      <button class="notice-close" onclick="closeModal('add-resource-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form id="add-resource-form" class="modal-body">
      <input type="hidden" name="software_id" value="<?= (int)$software['id'] ?>">

      <div class="form-group">
        <label class="form-label">资源类别</label>
        <select name="type" class="form-select" required>
          <option value="video">视频教程 (B站 / YouTube 等视频)</option>
          <option value="article">图文指南 (博客 / 专栏 / 官方手册)</option>
          <option value="config">配置技巧 (快捷键 / 高效设置)</option>
          <option value="patch">实用扩展 (汉化包 / 常用插件)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">教程或资源标题 <span style="color: #ef4444;">*</span></label>
        <input type="text" name="title" class="form-input" placeholder="例如：VS Code 2026从入门到精通高效配置" required>
      </div>

      <div class="form-group">
        <label class="form-label">有效访问网址 (URL) <span style="color: #ef4444;">*</span></label>
        <input type="url" name="url" class="form-input" placeholder="https://www.bilibili.com/video/BV..." required>
      </div>

      <div class="form-group">
        <label class="form-label">作者或来源平台 (选填)</label>
        <input type="text" name="author" class="form-input" placeholder="例如：Bilibili @极客教程">
      </div>

      <div class="form-group">
        <label class="form-label">简要说明或亮点 (选填)</label>
        <textarea name="description" class="form-textarea" placeholder="简述该教程的核心干货或适用人群..." style="min-height: 50px;"></textarea>
      </div>

      <div class="form-group" data-captcha-slot="resource">
        <?= $resourceCaptchaHtml ?? '' ?>
      </div>

      <div class="modal-footer" style="padding: 12px 0 0;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('add-resource-modal')">取消</button>
        <button type="submit" class="btn-store-primary">确认提交</button>
      </div>
    </form>
  </div>
</div>

