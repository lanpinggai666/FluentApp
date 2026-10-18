<?php require VIEW_PATH . '/admin/layout_nav.php'; ?>

<div class="admin-card">
  <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">开放 API 密钥</h2>
  <p style="font-size: .8125rem; color: var(--text-muted); margin: 0 0 14px;">
    给 App / 桌面端调用后台管理能力用。每次请求都要带三样东西：<b>密钥</b>（请求头 <code>X-Api-Key</code>）+
    <b>管理员用户名</b> + <b>管理员口令</b>（放 body）。完整端点清单见项目根目录的 <code>API.md</code>。
  </p>

  <div style="background: rgba(0,103,184,.06); border:1px solid rgba(0,103,184,.22); border-radius:8px; padding:12px 14px; font-size:.8125rem; line-height:1.7; margin-bottom:18px;">
    <b>安全前提：</b>口令是每次请求都要上传的，所以站点必须走 HTTPS —— 明文 HTTP 下这等于把管理员账号送给路径上的任何观察者。
    密钥在库里只保存 SHA-256 摘要，明文只在生成时显示一次；吊销立即生效；每次调用都会留下日志（不记录参数，凭据不落日志）。
    密钥的生成与吊销只能在后台做，API 自己没有这个能力，避免泄露的密钥铸造新密钥。
  </div>

  <?php if (!empty($notice)): ?>
    <div style="background:#eaf7ef; border:1px solid #c2e8d0; color:#1a7f4b; border-radius:8px; padding:10px 14px; font-size:.875rem; margin-bottom:16px;">
      <?= View::e($notice) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($once)): ?>
    <div style="border:1px dashed var(--primary); background:#f2f6fb; border-radius:8px; padding:14px; margin-bottom:18px;">
      <div style="font-size:.8125rem; color:var(--text-muted); margin-bottom:6px;">
        「<?= View::e($once['name']) ?>」的密钥明文 —— 现在抄走，离开这页就再也查不到：
      </div>
      <code style="display:block; font-size:1rem; word-break:break-all; padding:8px; background:#fff; border-radius:6px;"><?= View::e($once['key']) ?></code>
    </div>
  <?php endif; ?>

  <div style="display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); margin-bottom:20px;">
    <form method="post" action="<?= Url::route('admin/apiKeys') ?>" style="border:1px solid var(--border-subtle); border-radius:8px; padding:14px;">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="create">
      <label style="display:block; font-size:.8125rem; color:var(--text-muted); margin-bottom:4px;">密钥用途名称 *</label>
      <input type="text" name="name" placeholder="例如：安卓 App" required maxlength="64"
             style="width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:6px; font:inherit; margin-bottom:10px;">
      <div style="font-size:.8125rem; color:var(--text-muted); margin-bottom:6px;">权限范围</div>
      <label style="font-size:.875rem; margin-right:14px;">
        <input type="checkbox" name="scopes[]" value="read" checked> 只读（查看数据）
      </label>
      <label style="font-size:.875rem;">
        <input type="checkbox" name="scopes[]" value="write"> 读写（审核、增删改、设置）
      </label>
      <button type="submit" class="btn-store-primary" style="margin-top:12px; width:100%;">生成密钥</button>
    </form>

    <form method="post" action="<?= Url::route('admin/apiKeys') ?>" style="border:1px solid var(--border-subtle); border-radius:8px; padding:14px;">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="switch">
      <input type="hidden" name="switch" value="api_enabled">
      <label style="display:flex; gap:8px; align-items:flex-start; font-size:.875rem; margin-bottom:14px;">
        <input type="checkbox" name="value" value="1" <?= $apiEnabled ? 'checked' : '' ?> onchange="this.form.submit()">
        <span><b>启用开放 API</b><br><span style="font-size:.75rem; color:var(--text-muted);">关闭后所有端点统一返回 503，用作紧急总闸</span></span>
      </label>
    </form>

    <form method="post" action="<?= Url::route('admin/apiKeys') ?>" style="border:1px solid var(--border-subtle); border-radius:8px; padding:14px;">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="switch">
      <input type="hidden" name="switch" value="api_allow_insecure">
      <label style="display:flex; gap:8px; align-items:flex-start; font-size:.875rem;">
        <input type="checkbox" name="value" value="1" <?= $allowInsecure ? 'checked' : '' ?> onchange="this.form.submit()">
        <span><b>允许明文 HTTP 调用</b><br><span style="font-size:.75rem; color:#c0392b;">生产环境不要开：口令会随请求明文传输</span></span>
      </label>
    </form>
  </div>

  <h3 style="font-size:1rem; font-weight:700; margin:0 0 10px;">已有密钥（<?= count($keys) ?>）</h3>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead><tr><th>名称</th><th>识别前缀</th><th>权限</th><th>创建</th><th>最后使用</th><th>状态</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($keys as $row): ?>
        <tr>
          <td><?= View::e($row['name']) ?><?= $row['created_by'] ? ' <span style="color:var(--text-muted);font-size:.75rem;">by ' . View::e($row['created_by']) . '</span>' : '' ?></td>
          <td><code><?= View::e($row['key_prefix']) ?>…</code></td>
          <td><?= View::e((string) $row['scopes']) ?></td>
          <td><?= View::e((string) $row['created_at']) ?></td>
          <td><?= $row['last_used_at'] ? View::e((string) $row['last_used_at']) : '从未使用' ?></td>
          <td><?= $row['revoked_at'] ? '<span style="color:#c0392b;">已吊销</span>' : '<span style="color:#1a7f4b;">有效</span>' ?></td>
          <td>
            <?php if (!$row['revoked_at']): ?>
              <form method="post" action="<?= Url::route('admin/apiKeys') ?>" style="display:inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button type="submit" class="btn-store-secondary" style="padding:3px 10px; font-size:.75rem;">吊销</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if ($keys === []): ?>
        <tr><td colspan="7" style="color:var(--text-muted); padding:18px 0;">还没有密钥，用左边的表单生成一把。</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h3 style="font-size:1rem; font-weight:700; margin:22px 0 10px;">最近调用（最多 20 条，不含请求参数）</h3>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead><tr><th>时间</th><th>端点</th><th>方法</th><th>结果</th><th>HTTP</th><th>来源 IP</th><th>密钥</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= View::e((string) $log['created_at']) ?></td>
          <td><code><?= View::e((string) $log['endpoint']) ?></code></td>
          <td><?= View::e((string) $log['method']) ?></td>
          <td><?= View::e((string) $log['result']) ?><?= $log['detail'] ? ' · ' . View::e(mb_substr((string) $log['detail'], 0, 60)) : '' ?></td>
          <td><?= (int) $log['http_status'] ?></td>
          <td><?= View::e((string) $log['ip']) ?></td>
          <td><code><?= View::e((string) $log['key_prefix']) ?></code></td>
        </tr>
      <?php endforeach; if ($logs === []): ?>
        <tr><td colspan="7" style="color:var(--text-muted); padding:18px 0;">还没有调用记录。</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h3 style="font-size:1rem; font-weight:700; margin:22px 0 8px;">最小可用示例</h3>
  <pre style="background:#f7f8fa; border:1px solid var(--border-subtle); border-radius:8px; padding:12px; font-size:.75rem; overflow-x:auto;">curl -X GET "https://你的域名/index.php?r=api/v1/ping" \
  -H "X-Api-Key: nav_你的密钥" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"管理员口令"}'

# 审核通过一条提交（需要 write 权限）
curl -X POST "https://你的域名/index.php?r=apiadmin/review" \
  -H "X-Api-Key: nav_你的密钥" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"...","id":123,"action":"approve","category_id":3}'</pre>
</div>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
