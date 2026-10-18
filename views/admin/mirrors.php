<?php
require VIEW_PATH . '/admin/layout_nav.php';
?>

<?php if (isset($_GET['error']) && is_string($_GET['error']) && $_GET['error'] !== ''): ?>
  <div style="margin: 0 0 14px; padding: 12px 14px; border-radius: var(--radius-sm);
              background: #fdeceb; border: 1px solid #f6c9c5; color: #c0392b; font-size: .875rem; line-height: 1.6;">
    <?= View::e($_GET['error']) ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('github') ?>
        <span>GitHub 镜像与加速源配置</span>
      </h2>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">
        支持一键输入真实加速链接自动识别（智能区分【文件下载加速】与【网页浏览加速】），用户下载时自动按分类调度。
      </p>
    </div>
    <button class="btn-store-primary" onclick="openModal('add-mirror-modal')">
      <?= FluentIcon::render('plus', 'fluent-icon-sm') ?> 添加加速源
    </button>
  </div>

  <div style="overflow-x: auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>节点名称</th>
          <th>加速定位</th>
          <th>代理模板 / 前缀</th>
          <th>识别示例链接</th>
          <th style="text-align: right;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($mirrors as $m): ?>
          <tr>
            <td style="font-weight: 600;"><?= View::e($m['name']) ?></td>
            <td>
              <?php if (($m['type'] ?? 'download') === 'download'): ?>
                <span class="badge badge-free">文件下载加速</span>
              <?php elseif (($m['type'] ?? '') === 'web'): ?>
                <span class="badge badge-os">网页浏览加速</span>
              <?php else: ?>
                <span class="badge badge-ai">全能加速 (网页+下载)</span>
              <?php endif; ?>
            </td>
            <td style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--text-link);">
              <?= View::e($m['url_template']) ?>
            </td>
            <td style="font-size: 0.75rem; color: var(--text-muted); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= View::e($m['sample_url'] ?: '-') ?>
            </td>
            <td style="text-align: right;">
              <form action="<?= Url::route('admin/mirrors') ?>" method="POST" style="display:inline;"
                    data-confirm="确定移除该镜像节点吗？移除后详情页将不再展示该加速线路。"
                    data-confirm-title="移除镜像节点"
                    data-confirm-ok="移除"
                    data-confirm-tone="danger">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" class="btn-card-action" style="color: #ef4444; background: #fee2e2;">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-mirror-modal">
  <div class="modal-container" style="max-width: 540px;">
    <div class="modal-header">
      <div class="modal-title">添加 GitHub 镜像加速节点</div>
      <button class="notice-close" onclick="closeModal('add-mirror-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form action="<?= Url::route('admin/mirrors') ?>" method="POST" class="modal-body">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="add">

      <div class="form-group">
        <label class="form-label" style="font-weight: 600;">1. 加速定位（先选这个）</label>
        <select name="type" id="mirror-type-select" class="form-select">
          <option value="download">文件下载加速（Release 安装包 / 源码包 / Raw）</option>
          <option value="web">网页浏览加速（仓库页面、README）</option>
          <option value="both">全能加速（下载与网页都用它）</option>
        </select>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">
          类型由你定，系统不再从链接形态反猜 —— 它只决定这条线路在前端被用在哪个场景。
        </div>
      </div>

      <div class="form-group" style="background: var(--ms-blue-subtle); padding: 12px; border-radius: var(--radius-sm); border: 1px dashed var(--ms-blue-border);">
        <label class="form-label" style="font-weight: 600; color: var(--text-link);">
          <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?> 2. 贴一条该镜像站上的真实链接
        </label>
        <input type="url" name="sample_url" id="mirror-sample-input" class="form-input"
               placeholder="例如：https://ghproxy.net/https://github.com/microsoft/vscode/releases/download/1.90.0/VSCode.exe" style="margin-bottom: 8px;">
        <label class="form-label">对应的原始 GitHub 链接（识别不出时再贴，可留空）</label>
        <input type="url" name="sample_original" id="mirror-original-input" class="form-input"
               placeholder="例如：https://github.com/microsoft/vscode/releases/download/1.90.0/VSCode.exe" style="margin-bottom: 8px;">
        <div style="display: flex; gap: 8px; align-items: center;">
          <button type="button" class="btn-store-secondary" onclick="learnMirrorFormat()" style="white-space: nowrap;">
            解析并校验
          </button>
          <span id="mirror-style-wrap" style="display:none; font-size:.75rem; color:var(--text-muted); white-space: nowrap;">
            映射方式
            <select name="url_style" id="mirror-style-select" class="form-select" style="display:inline-block; width:auto; height:28px; font-size:.75rem; padding:0 6px;">
              <option value="">自动</option>
              <option value="prefix">原链挂前缀</option>
              <option value="replace">换域名</option>
            </select>
          </span>
        </div>
        <div id="identify-hint" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">
          贴好后点「解析并校验」：系统会反推出模板与它对应的原始链接，并把原链映射一遍，<b>逐字符等于你贴的这条</b>才算学会。
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">3. 节点名称</label>
        <input type="text" name="name" id="mirror-name-input" class="form-input" placeholder="例如：GHProxy 线路">
      </div>

      <div class="form-group">
        <label class="form-label">4. 加速前缀模板（URL Template）</label>
        <input type="text" name="url_template" id="mirror-tpl-input" class="form-input" placeholder="点「解析并校验」自动填，也可手工写：https://ghproxy.net/ 或 https://kkgithub.com/">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">
          手工改过模板后，请再点一次「解析并校验」——保存时系统会拿它复核映射，对不上会拒收。
        </div>
      </div>

      <div class="modal-footer" style="padding: 14px 0 0;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('add-mirror-modal')">取消</button>
        <button type="submit" class="btn-store-primary">添加保存</button>
      </div>
    </form>
  </div>
</div>

<script>
// 让系统「学会」这个镜像站的映射格式：类型由上面的下拉决定（这里绝不回写），
// 系统只反推模板 + 对应的原始链接，并当场把原链映射一遍做逐字符比对。
function learnMirrorFormat() {
  const hint = document.getElementById('identify-hint');
  const sample = document.getElementById('mirror-sample-input').value.trim();
  const original = document.getElementById('mirror-original-input').value.trim();
  const type = document.getElementById('mirror-type-select').value;
  if (!sample) {
    hint.style.color = '#ef4444';
    hint.textContent = '请先粘贴一条该镜像站上的真实链接';
    return;
  }
  hint.style.color = 'var(--text-muted)';
  hint.textContent = '正在解析并校验映射...';

  const params = new URLSearchParams({ url: sample, type: type });
  if (original) { params.set('original_url', original); }

  const mirrorApi = new URL('<?= Url::route('api/identifyMirror') ?>', window.location.href);
  mirrorApi.search = params.toString();

  fetch(mirrorApi.toString())
    .then(res => res.json())
    .then(data => {
      const ok = !!data.success;
      if (ok) {
        document.getElementById('mirror-tpl-input').value = data.url_template || '';
        const nameInput = document.getElementById('mirror-name-input');
        if (!nameInput.value.trim()) { nameInput.value = data.name || ''; }
        const styleWrap = document.getElementById('mirror-style-wrap');
        styleWrap.style.display = '';
        document.getElementById('mirror-style-select').value = data.style || '';
      }
      hint.style.color = ok ? '#10b981' : '#ef4444';
      hint.textContent = ok
        ? '✓ 已学会：映射方式「' + data.style_label + '」，模板 ' + data.url_template
          + '；回环校验 ' + data.original_url + ' → ' + data.mirror_url + ' 与贴入一致'
          + (data.hint ? '。' + data.hint : '')
        : (data.message || '解析失败，请手工填写模板');
    })
    .catch(() => {
      hint.style.color = '#ef4444';
      hint.textContent = '通信异常，未能完成解析';
    });
}

let mirrorLearnTimer = null;
document.getElementById('mirror-sample-input')?.addEventListener('input', function() {
  clearTimeout(mirrorLearnTimer);
  if (this.value.trim().length > 15) {
    mirrorLearnTimer = setTimeout(learnMirrorFormat, 400);
  }
});
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
