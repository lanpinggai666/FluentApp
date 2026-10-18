<?php
$correction = $correction ?? [];
$categories = $categories ?? [];
$channels = $channels ?? [];
?>
<main class="main-layout" style="display: flex; justify-content: center; padding: 36px 16px;">
  <div style="width: 100%; max-width: 860px; margin: 0 auto;">
    <div class="ms-card" style="padding:28px;">
<?php if (!empty($success)): ?>
      <div style="text-align:center;padding:32px 16px;">
        <h1 style="font-size:1.25rem;font-weight:700;">重新提交成功</h1>
        <p style="color:var(--text-muted);margin:16px 0;">修改内容已重新进入审核流程。</p>
        <a href="<?= Url::route('home') ?>" class="btn-store-primary">返回首页</a>
      </div>
<?php else: ?>
      <h1 style="font-size:1.375rem;font-weight:700;margin-bottom:6px;">修改软件信息</h1>
      <p style="font-size:.8125rem;color:var(--text-muted);margin-bottom:18px;">软件：<?= View::e($correction['software_name'] ?? '') ?></p>
<?php if (!empty($correction['review_note'])): ?>
      <div style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;padding:12px 14px;margin-bottom:18px;"><strong>驳回理由：</strong><?= nl2br(View::e($correction['review_note'])) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?><div style="background:#fef2f2;color:#b91c1c;padding:12px 14px;margin-bottom:18px;"><?= View::e($error) ?></div><?php endif; ?>
      <form action="<?= Url::route('software/resubmitCorrection') ?>" method="POST">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="token" value="<?= View::e($token ?? '') ?>">
        <div class="form-grid-2">
<?php
$fields = [
  'name' => ['软件名称','text',100],
  'icon_url' => ['图标网址','url',500],
  'official_url' => ['官网','url',500],
  // 开源软件不收集 GitHub 地址：勾选「开源软件」后这一组由 submit-form.js 隐藏并禁用
  'github_url' => ['GitHub','url',500,'hide-if-open-source'],
  'developer' => ['开发者','text',100],
  'license' => ['开源协议','text',100],
  'screenshot_url' => ['截图网址','url',500],
];
foreach ($fields as $field => $meta):
?>
          <div class="form-group"<?= isset($meta[3]) ? ' data-hide-if-open-source' : '' ?>><label class="form-label"><?= $meta[0] ?></label><input type="<?= $meta[1] ?>" name="<?= $field ?>" class="form-input" maxlength="<?= $meta[2] ?>" value="<?= View::e($correction[$field] ?? '') ?>"></div>
<?php endforeach; ?>
          <div class="form-group"><label class="form-label">软件分类</label><select name="category_id" class="form-select" required><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$category['id'] === (int)($correction['category_id'] ?? 0) ? 'selected' : '' ?>><?= View::e($category['name']) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">收费模式</label><select name="price_type" class="form-select"><?php foreach (['free'=>'免费','freemium'=>'免费增值','paid'=>'付费'] as $value => $label): ?><option value="<?= $value ?>" <?= ($correction['price_type'] ?? 'free') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-group"><label class="form-label">特别说明</label><textarea name="special_notes" class="form-textarea markdown-editor" maxlength="10000" rows="8" placeholder="支持 Markdown"><?= View::e($correction['special_notes'] ?? '') ?></textarea></div>
        <div class="form-group"><label class="form-label">标签</label><input type="text" name="tags" class="form-input" maxlength="500" value="<?= View::e($correction['tags'] ?? '') ?>"></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_open_source" value="1" data-open-source-switch <?= !empty($correction['is_open_source']) ? 'checked' : '' ?>> 开源软件</label></div>
        <div class="form-group"><label class="form-label">下载渠道</label><?php $channels = $channels ?: [['type'=>'第三方下载','name'=>'','url'=>'']]; foreach ($channels as $channel): ?><div style="display:grid;grid-template-columns:150px 170px minmax(220px,1fr);gap:8px;margin-bottom:8px;"><input name="channel_type[]" class="form-input" value="<?= View::e($channel['type'] ?? '第三方下载') ?>"><input name="channel_name[]" class="form-input" value="<?= View::e($channel['name'] ?? '') ?>"><input type="url" name="channel_url[]" class="form-input" value="<?= View::e($channel['url'] ?? '') ?>"></div><?php endforeach; ?></div>
        <?php $curUser = Auth::currentUser(); if (!empty($curUser)): ?>
          <input type="hidden" name="submitter_email" value="<?= View::e($curUser['email'] ?? ($correction['submitter_email'] ?? '')) ?>">
        <?php else: ?>
          <div class="form-group"><label class="form-label">联系邮箱 <span style="color:#ef4444">*</span></label><input type="email" name="submitter_email" class="form-input" maxlength="254" required value="<?= View::e($correction['submitter_email'] ?? '') ?>"></div>
        <?php endif; ?>
        <?= $captchaHtml ?? '' ?>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;"><a href="<?= Url::route('home') ?>" class="btn-store-secondary">取消</a><button type="submit" class="btn-store-primary">重新提交审核</button></div>
      </form>
<?php endif; ?>
    </div>
  </div>
</main>
