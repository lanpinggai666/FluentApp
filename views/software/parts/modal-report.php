<!-- 举报模态弹窗 -->
<div class="modal-overlay" id="report-modal">
  <div class="modal-container">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('flag', 'fluent-icon') ?>
        <span>举报软件问题</span>
      </div>
      <button class="notice-close" onclick="closeModal('report-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form id="report-form" class="modal-body">
      <input type="hidden" name="software_id" value="<?= (int)$software['id'] ?>">

      <div class="form-group">
        <label class="form-label">举报类型 / 理由</label>
        <select name="reason" class="form-select" required onchange="var g = document.getElementById('report-resource-group'); if (g) g.style.display = (this.value === '相关资源违规' ? 'block' : 'none');">
          <option value="官网或下载链接失效">官网或下载链接失效</option>
          <option value="相关资源违规">相关第三方资源失效或违规</option>
          <option value="包含恶意木马或病毒">包含恶意木马或病毒</option>
          <option value="仿冒流氓软件/捆绑篡改">仿冒流氓软件/捆绑篡改</option>
          <option value="侵犯版权或违规违法">侵犯版权或违规违法</option>
          <option value="其他问题">其他问题</option>
        </select>
      </div>

      <?php if (!empty($resources)): ?>
        <div class="form-group" id="report-resource-group" style="display: none;">
          <label class="form-label">选择涉及的具体资源</label>
          <select name="resource_id" class="form-select">
            <option value="">-- 请选择关联资源 (可选) --</option>
            <?php foreach ($resources as $res): ?>
              <option value="<?= (int)$res['id'] ?>"><?= View::e($res['title']) ?> (<?= View::e(mb_strimwidth($res['url'], 0, 45, '...')) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">说明 (选填)</label>
        <textarea name="details" class="form-textarea" placeholder="请简要描述您遇到的问题..."></textarea>
      </div>

      <?php if (!empty($currentUser)): ?>
        <input type="hidden" name="email" value="<?= View::e($currentUser['email'] ?? '') ?>">
      <?php else: ?>
        <div class="form-group">
          <label class="form-label">联系邮箱 <span style="color:#ef4444;">*</span></label>
          <input type="email" name="email" class="form-input" maxlength="254" placeholder="用于接收处理结果" autocomplete="email" required>
        </div>
      <?php endif; ?>

      <?php if (!empty($reportCaptchaHtml)): ?>
      <div class="form-group">
        <?= $reportCaptchaHtml ?>
      </div>
      <?php endif; ?>

      <div class="modal-footer" style="padding: 14px 0 0;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('report-modal')">取消</button>
        <button type="submit" class="btn-store-primary">提交</button>
      </div>
    </form>
  </div>
</div>

<script>
// 切换撰写评价表单
function toggleReviewForm() {
  const form = document.getElementById('comment-form');
  if (form) {
