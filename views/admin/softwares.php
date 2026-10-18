<?php
require VIEW_PATH . '/admin/layout_nav.php';
?>

<div class="admin-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('folder') ?>
        <span>软件资源管理</span>
      </h2>
      <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">
        共收录 <?= $total ?> 款软件，支持编辑修改详情、快速检测修复损坏图标。
      </p>
    </div>
    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn-store-secondary" onclick="detectAllIcons()" title="检查本页图标是否能正常加载">
        <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?> 探测损坏图标
      </button>
      <button class="btn-store-primary" onclick="openModal('add-software-modal')">
        <?= FluentIcon::render('plus', 'fluent-icon-sm') ?> 添加新软件
      </button>
    </div>
  </div>

  <?php if (!empty($success)): ?>
    <div style="background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 0.8125rem; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
      <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
      <span>操作已成功保存并实时生效！</span>
    </div>
  <?php endif; ?>

  <!-- 筛选选项卡 (全部 / 损坏图标) -->
  <div style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid var(--border-light); padding-bottom: 10px;">
    <button type="button" class="admin-tab active" id="tab-all-softwares" onclick="filterSoftwareList('all')">
      全部软件 (<?= $total ?>)
    </button>
    <button type="button" class="admin-tab" id="tab-broken-softwares" onclick="filterSoftwareList('broken')" style="color: #dc2626;">
      <?= FluentIcon::render('warning', 'fluent-icon-sm') ?>
      图标损坏 (<span id="broken-count-badge">0</span>)
    </button>
  </div>

  <div style="overflow-x: auto;">
    <table class="admin-table" id="admin-software-table">
      <thead>
        <tr>
          <th>图标/名称</th>
          <th>分类</th>
          <th>授权/价格</th>
          <th>评分/浏览</th>
          <th>状态</th>
          <th style="text-align: right;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $s): ?>
          <tr class="software-row" id="software-row-<?= $s['id'] ?>" data-broken="0" style="border-bottom: 1px solid var(--border-light);">
            <td style="padding: 12px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="position: relative; width: 32px; height: 32px; flex-shrink: 0;">
                  <img src="<?= View::e($s['icon_url']) ?>" 
                       class="admin-software-icon" 
                       data-id="<?= $s['id'] ?>"
                       onerror="handleAdminIconBroken(this, <?= $s['id'] ?>)" 
                       style="width: 32px; height: 32px; border-radius: 6px; object-fit: contain; background: var(--bg-muted); border: 1px solid var(--border-subtle);" 
                       alt="">
                  <div class="icon-broken-alert" id="icon-broken-badge-<?= $s['id'] ?>" style="display:none; position: absolute; -top: 4px; right: -4px; width: 14px; height: 14px; border-radius: 50%; background: #ef4444; color: #fff; font-size: 10px; font-weight: bold; text-align: center; line-height: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.3);" title="图标加载失败或直链已失效">!</div>
                </div>
                <div>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <a href="<?= Url::detail($s['slug']) ?>" target="_blank" style="font-weight: 600; color: var(--text-main);">
                      <?= View::e($s['name']) ?>
                    </a>
                    <span class="badge" id="tag-broken-<?= $s['id'] ?>" style="display: none; background: #fee2e2; color: #dc2626; font-size: 0.6875rem;">
                      图标损坏
                    </span>
                    <?php if ($s['is_featured']): ?>
                      <span class="badge badge-featured" style="font-size: 0.6875rem;">精选</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size: 0.6875rem; color: var(--text-muted);"><?= View::e($s['slug']) ?></div>
                </div>
              </div>
            </td>
            <td style="padding: 12px;"><?= View::e($s['category_name'] ?? '默认') ?></td>
            <td style="padding: 12px;">
              <div style="font-size: 0.75rem;">
                <span class="badge <?= !empty($s['is_open_source']) ? 'badge-os' : 'badge-prop' ?>" style="font-size: 0.6875rem;">
                  <?= !empty($s['is_open_source']) ? '开源' : '闭源' ?>
                </span>
                <span class="badge <?= ($s['price_type'] ?? 'free') === 'free' ? 'badge-free' : 'badge-paid' ?>" style="font-size: 0.6875rem; margin-left: 2px;">
                  <?= ($s['price_type'] ?? 'free') === 'free' ? '免费' : (($s['price_type'] ?? '') === 'paid' ? '付费' : '内购') ?>
                </span>
              </div>
            </td>
            <td style="padding: 12px;">
              <div>★ <?= number_format((float)$s['rating_avg'], 1) ?></div>
              <div style="font-size: 0.6875rem; color: var(--text-muted);"><?= (int)$s['views_count'] ?> 浏览</div>
            </td>
            <td style="padding: 12px;">
              <span class="badge" style="background: <?= $s['status'] === 'published' ? '#dcfce7;color:#166534;' : ($s['status'] === 'pending' ? '#fef3c7;color:#92400e;' : '#fee2e2;color:#991b1b;') ?>">
                <?= $s['status'] === 'published' ? '已发布' : ($s['status'] === 'pending' ? '待审核' : '已下架') ?>
              </span>
              <?php if (!empty($s['is_ai_generated'])): ?>
                <?php $aiFields = json_decode((string) ($s['ai_generated_fields'] ?? ''), true) ?: []; ?>
                <div style="font-size: 0.6875rem; margin-top: 4px; color: #166534;">
                  AI 已补全<?= $aiFields !== [] ? '：' . View::e(implode('、', array_slice($aiFields, 0, 4))) : '' ?>
                </div>
              <?php endif; ?>
            </td>
            <td style="padding: 12px; text-align: right;">
              <div style="display: inline-flex; gap: 6px;">
                <button type="button" class="btn-card-action" onclick="openEditSoftwareModal(<?= htmlspecialchars(json_encode($s, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" style="background: var(--ms-blue-subtle); color: var(--ms-blue); border-color: var(--ms-blue-border); font-weight: 500;">
                  <?= FluentIcon::render('edit', 'fluent-icon-sm') ?> 编辑
                </button>
                <a href="<?= Url::detail($s['slug']) ?>" target="_blank" class="btn-card-action" title="前台查看">
                  查看
                </a>
                <form action="<?= Url::route('admin/aiRetry') ?>" method="POST" style="display: inline;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="software_id" value="<?= (int) $s['id'] ?>">
                  <input type="hidden" name="back" value="softwares">
                  <button type="submit" class="btn-card-action" title="当场调用 AI 补全这条软件：只补仍空着的字段，人工与审核填过的不动">
                    <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?> AI
                  </button>
                  <button type="submit" name="overwrite_ai" value="1" class="btn-card-action" style="color:#b45309;"
                          title="覆盖重生成：先清掉上次 AI 生成的文本字段，再整段重写（会等一次外呼，最长约 ai_total_timeout 秒）">覆盖</button>
                </form>
                <form action="<?= Url::route('admin/deleteSoftware') ?>" method="POST" style="display: inline;"
                      data-confirm="确定彻底删除「<?= View::e($s['name']) ?>」吗？该操作不可恢复，其评价与补充信息记录将一并失效。"
                      data-confirm-title="删除软件"
                      data-confirm-ok="彻底删除"
                      data-confirm-tone="danger">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn-card-action" style="color: #ef4444; background: #fee2e2;" title="删除软件">删除</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div id="no-broken-notice" style="display: none; padding: 40px; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
    <?= FluentIcon::render('checkmark', 'fluent-icon') ?>
    <div style="margin-top: 8px;">太棒了！当前没有任何检测到损坏失效的图标。</div>
  </div>

  <!-- 分页条 -->
  <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;" id="admin-pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="<?= Url::route('admin/softwares', ['page' => $p]) ?>" class="btn-icon <?= $p === $page ? 'active' : '' ?>" style="font-size: 0.8125rem;">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 模态框 1：编辑软件详情 -->
<div class="modal-overlay" id="edit-software-modal">
  <div class="modal-container" style="max-width: 640px; max-height: 90vh; overflow-y: auto;">
    <div class="modal-header">
      <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
        <?= FluentIcon::render('edit', 'fluent-icon-sm') ?>
        <span id="edit-modal-title">编辑软件详情</span>
      </div>
      <button type="button" class="notice-close" onclick="closeModal('edit-software-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form action="<?= Url::route('admin/saveSoftware') ?>" method="POST" class="modal-body" id="edit-software-form">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="id" id="edit-field-id" value="0">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label class="form-label">软件名称 <span style="color:#ef4444;">*</span></label>
          <input type="text" name="name" id="edit-field-name" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">分类</label>
          <select name="category_id" id="edit-field-category_id" class="form-select">
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">软件图标 URL <span style="color:#ef4444;">*</span></label>
        <div style="display: flex; gap: 8px; align-items: center;">
          <img id="edit-icon-preview" src="" style="width: 38px; height: 38px; border-radius: 6px; object-fit: contain; background: var(--bg-muted); border: 1px solid var(--border-subtle); flex-shrink: 0;" alt="" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><text y=%2215%22 font-size=%2214%22>❌</text></svg>'; document.getElementById('edit-icon-status').innerText='图标损坏/无法访问';">
          <input type="url" name="icon_url" id="edit-field-icon_url" class="form-input" required oninput="previewEditIcon(this.value)" style="flex: 1;">
        </div>
        <div id="edit-icon-status" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">输入图标直链后自动实时预览</div>
      </div>

      <!-- 下载获取渠道配置 (支持修改官方下载、GitHub 链接、Microsoft Store 及网盘等额外下载渠道) -->
      <div style="background: var(--bg-surface-secondary); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 0; display: flex; align-items: center; gap: 6px;">
            <?= FluentIcon::render('arrow-download', 'fluent-icon-sm') ?>
            <span>下载链接与获取渠道管理</span>
          </label>
          <button type="button" class="btn-outline" onclick="addEditChannelRow()" style="font-size: 0.75rem; padding: 2px 10px; height: 26px;">
            + 添加多渠道下载
          </button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">官方主下载 / 官网链接 <span style="color:#ef4444;">*</span></label>
            <input type="url" name="official_url" id="edit-field-official_url" class="form-input" required placeholder="https://...">
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">GitHub 仓库直链 (选填)</label>
            <input type="url" name="github_url" id="edit-field-github_url" class="form-input" placeholder="https://github.com/owner/repo">
          </div>
        </div>

        <!-- 额外下载渠道列表容器 (如 Microsoft Store, 百度网盘, 蓝奏云等) -->
        <div id="edit-channels-container" style="display: flex; flex-direction: column; gap: 8px;"></div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label class="form-label">收费模式</label>
          <select name="price_type" id="edit-field-price_type" class="form-select" onchange="toggleEditPriceField(this.value)">
            <option value="free">免费获取</option>
            <option value="freemium">免费含内购/高级版</option>
            <option value="paid">付费软件</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">开发者/开发团队</label>
          <input type="text" name="developer" id="edit-field-developer" class="form-input" placeholder="例如：Microsoft">
        </div>
      </div>

      <!-- 动态收费方式与价格 (收费时展示，免费时隐藏) -->
      <div class="form-group" id="edit-pricing-group" style="display: none; background: var(--bg-surface-secondary); padding: 12px; border-radius: var(--radius-xs); border: 1px dashed var(--border-subtle);">
        <label class="form-label">收费方式与价格明细 (选填)</label>
        <input type="text" name="price_desc" id="edit-field-price_desc" class="form-input" placeholder="例如：买断制 ￥68 或 $9.99/月">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label class="form-label">源码授权</label>
          <select name="is_open_source" id="edit-field-is_open_source" class="form-select" onchange="toggleEditLicenseField(this.value)">
            <option value="1">开源代码</option>
            <option value="0">闭源专有</option>
          </select>
        </div>
        <div class="form-group" id="edit-license-group">
          <label class="form-label">开源协议</label>
          <input type="text" name="license" id="edit-field-license" class="form-input" placeholder="如：MIT, Apache-2.0, GPL-3.0">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label class="form-label">发布状态</label>
          <select name="status" id="edit-field-status" class="form-select">
            <option value="published">已发布上线</option>
            <option value="pending">待审核</option>
            <option value="rejected">已下架/驳回</option>
          </select>
        </div>
        <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 8px;">
          <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 0;">
            <input type="checkbox" name="is_featured" id="edit-field-is_featured" value="1" style="accent-color: var(--ms-blue); width: 16px; height: 16px;">
            <span>设为首页精选推荐</span>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">软件标签（逗号分隔）</label>
        <input type="text" name="tags" id="edit-field-tags" class="form-input" placeholder="如：代码编辑, 微软, 跨平台, 免费">
      </div>

      <div class="form-group">
        <label class="form-label">发布机构</label>
        <input type="text" name="publisher" id="edit-field-publisher" class="form-input" maxlength="100"
               placeholder="详情页展示的发布者，留空则回退到开发者">
      </div>

      <div class="form-group">
        <label class="form-label">特别说明（纯文本）</label>
        <textarea name="special_notes" id="edit-field-special_notes" class="form-textarea" rows="2" maxlength="200"
                  placeholder="例如：永久免费且无任何弹窗广告，推荐下载 64 位版本。"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">软件说明 / 简介（支持 Markdown）</label>
        <textarea name="description_md" id="edit-field-description_md" class="form-textarea markdown-editor" rows="6"
                  placeholder="支持 Markdown 语法，可填写功能特性、安装方法、运行环境等"></textarea>
      </div>

      <div class="modal-footer" style="padding: 14px 0 0; display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('edit-software-modal')">取消</button>
        <button type="submit" class="btn-store-primary">保存修改</button>
      </div>
    </form>
  </div>
</div>

<!-- 模态框 2：添加新软件弹窗 -->
<div class="modal-overlay" id="add-software-modal">
  <div class="modal-container" style="max-width: 600px;">
    <div class="modal-header">
      <div class="modal-title">添加新软件</div>
      <button class="notice-close" onclick="closeModal('add-software-modal')"><?= FluentIcon::render('dismiss') ?></button>
    </div>
    <form action="<?= Url::route('admin/saveSoftware') ?>" method="POST" class="modal-body">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label class="form-label">软件名称</label>
        <input type="text" name="name" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">图标 URL</label>
        <input type="url" name="icon_url" class="form-input" required>
      </div>
      <!-- 下载获取渠道配置 -->
      <div style="background: var(--bg-surface-secondary); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 0; display: flex; align-items: center; gap: 6px;">
            <?= FluentIcon::render('arrow-download', 'fluent-icon-sm') ?>
            <span>下载链接与获取渠道配置</span>
          </label>
          <button type="button" class="btn-outline" onclick="addAddChannelRow()" style="font-size: 0.75rem; padding: 2px 10px; height: 26px;">
            + 添加多渠道下载
          </button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">官方网址 / 官方下载 <span style="color:#ef4444;">*</span></label>
            <input type="url" name="official_url" class="form-input" required placeholder="https://...">
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">GitHub 仓库直链</label>
            <input type="url" name="github_url" class="form-input" placeholder="https://github.com/owner/repo">
          </div>
        </div>

        <div id="add-channels-container" style="display: flex; flex-direction: column; gap: 8px;"></div>
      </div>
      <div class="form-group">
        <label class="form-label">分类</label>
        <select name="category_id" class="form-select">
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label class="form-label">收费模式</label>
          <select name="price_type" class="form-select">
            <option value="free">免费获取</option>
            <option value="freemium">免费含内购</option>
            <option value="paid">付费软件</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">开发者/开发团队</label>
          <input type="text" name="developer" class="form-input" placeholder="如：Microsoft">
        </div>
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label class="form-label" style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
            <input type="checkbox" name="is_open_source" value="1" style="accent-color: var(--ms-blue); width: 16px; height: 16px;">
            <span>是否开源项目</span>
          </label>
        </div>
        <div class="form-group">
          <label class="form-label">开源协议</label>
          <input type="text" name="license" class="form-input" placeholder="如：MIT, Apache-2.0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">标签（逗号分隔）</label>
        <input type="text" name="tags" class="form-input" placeholder="代码编辑, 微软, 跨平台">
      </div>
      <div class="form-group">
        <label class="form-label">特别说明（纯文本）</label>
        <textarea name="special_notes" class="form-textarea" rows="2" maxlength="200"
                  placeholder="例如：永久免费且无任何弹窗广告，推荐下载 64 位版本。"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">软件说明 / 简介（支持 Markdown）</label>
        <textarea name="description_md" class="form-textarea markdown-editor" rows="6"
                  placeholder="支持 Markdown 语法，可填写功能特性、安装方法、运行环境等"></textarea>
      </div>
      <div class="modal-footer" style="padding: 14px 0 0; display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn-store-secondary" onclick="closeModal('add-software-modal')">取消</button>
        <button type="submit" class="btn-store-primary">保存入库</button>
      </div>
    </form>
  </div>
</div>

<script>
// 1. 图标损坏处理逻辑
const brokenSoftwareIds = new Set();

function handleAdminIconBroken(imgElem, softwareId) {
  imgElem.src = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22><rect width=%2232%22 height=%2232%22 fill=%22%23fee2e2%22/><text x=%2250%%22 y=%2254%%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2216%22>⚠️</text></svg>';
  
  brokenSoftwareIds.add(softwareId);
  const row = document.getElementById('software-row-' + softwareId);
  if (row) {
    row.setAttribute('data-broken', '1');
    row.style.background = 'rgba(254, 242, 242, 0.45)';
  }
  
  const tag = document.getElementById('tag-broken-' + softwareId);
  if (tag) tag.style.display = 'inline-block';
  
  const iconBadge = document.getElementById('icon-broken-badge-' + softwareId);
  if (iconBadge) iconBadge.style.display = 'block';

  updateBrokenBadgeCount();
}

function updateBrokenBadgeCount() {
  const badge = document.getElementById('broken-count-badge');
  if (badge) {
    badge.innerText = brokenSoftwareIds.size;
  }
}

// 探测当前页所有软件的图标
function detectAllIcons() {
  const icons = document.querySelectorAll('.admin-software-icon');
  let checked = 0;
  icons.forEach(img => {
    const sId = img.getAttribute('data-id');
    const testImg = new Image();
    testImg.onload = () => {
      checked++;
    };
    testImg.onerror = () => {
      handleAdminIconBroken(img, sId);
      checked++;
    };
    testImg.src = img.src;
  });
}

// 筛选 全部 / 损坏
function filterSoftwareList(mode) {
  const rows = document.querySelectorAll('.software-row');
  const tabAll = document.getElementById('tab-all-softwares');
  const tabBroken = document.getElementById('tab-broken-softwares');
  const noBrokenNotice = document.getElementById('no-broken-notice');
  const pagination = document.getElementById('admin-pagination');

  if (mode === 'broken') {
    tabBroken.classList.add('active');
    tabAll.classList.remove('active');
    let visibleCount = 0;
    rows.forEach(r => {
      if (r.getAttribute('data-broken') === '1') {
        r.style.display = '';
        visibleCount++;
      } else {
        r.style.display = 'none';
      }
    });
    if (noBrokenNotice) noBrokenNotice.style.display = visibleCount === 0 ? 'block' : 'none';
    if (pagination) pagination.style.display = 'none';
  } else {
    tabAll.classList.add('active');
    tabBroken.classList.remove('active');
    rows.forEach(r => r.style.display = '');
    if (noBrokenNotice) noBrokenNotice.style.display = 'none';
    if (pagination) pagination.style.display = '';
  }
}

// 2. 编辑软件模态框回填
function openEditSoftwareModal(data) {
  if (!data) return;
  document.getElementById('edit-modal-title').innerText = '编辑软件：' + (data.name || '');
  document.getElementById('edit-field-id').value = data.id || 0;
  document.getElementById('edit-field-name').value = data.name || '';
  document.getElementById('edit-field-category_id').value = data.category_id || 1;
  document.getElementById('edit-field-icon_url').value = data.icon_url || '';
  document.getElementById('edit-field-official_url').value = data.official_url || '';
  document.getElementById('edit-field-github_url').value = data.github_url || '';
  document.getElementById('edit-field-price_type').value = data.price_type || 'free';
  document.getElementById('edit-field-price_desc').value = data.price_desc || '';
  document.getElementById('edit-field-developer').value = data.developer || '';
  document.getElementById('edit-field-is_open_source').value = data.is_open_source ? '1' : '0';
  document.getElementById('edit-field-license').value = data.license || '';
  document.getElementById('edit-field-status').value = data.status || 'published';
  document.getElementById('edit-field-is_featured').checked = !!parseInt(data.is_featured);
  document.getElementById('edit-field-tags').value = data.tags || '';
  document.getElementById('edit-field-publisher').value = data.publisher || '';

  // 详细说明由 EasyMDE 接管，需要走编辑器实例写回
  var editNotes = document.getElementById('edit-field-special_notes');
  var notesValue = data.special_notes || '';
  if (window.FluentMarkdown && window.FluentMarkdown.get(editNotes)) {
    window.FluentMarkdown.get(editNotes).value(notesValue);
  } else {
    editNotes.value = notesValue;
  }

  // 下载渠道动态回填
  const chContainer = document.getElementById('edit-channels-container');
  if (chContainer) {
    chContainer.innerHTML = '';
    let otherUrls = [];
    if (data.other_urls) {
      try {
        otherUrls = typeof data.other_urls === 'string' ? JSON.parse(data.other_urls) : data.other_urls;
      } catch (e) {
        otherUrls = [];
      }
    }
    if (Array.isArray(otherUrls) && otherUrls.length > 0) {
      otherUrls.forEach(item => {
        addEditChannelRow(item.type || '第三方下载站', item.name || '', item.url || '');
      });
    }
  }

  toggleEditPriceField(data.price_type || 'free');
  toggleEditLicenseField(data.is_open_source ? '1' : '0');
  previewEditIcon(data.icon_url || '');

  openModal('edit-software-modal');
}

function addEditChannelRow(type = '第三方下载站', name = '', url = '') {
  const container = document.getElementById('edit-channels-container');
  if (!container) return;
  const rowId = 'ch-edit-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
  const div = document.createElement('div');
  div.id = rowId;
  div.style.cssText = 'display: grid; grid-template-columns: 140px 140px 1fr 34px; gap: 8px; align-items: center; background: var(--bg-surface); padding: 8px; border-radius: var(--radius-xs); border: 1px solid var(--border-subtle);';
  div.innerHTML = `
    <select name="channel_type[]" class="form-select" style="font-size: 0.8125rem; height: 32px; padding: 2px 6px;">
      <option value="Microsoft Store" ${type === 'Microsoft Store' ? 'selected' : ''}>Microsoft Store</option>
      <option value="Github" ${type === 'Github' ? 'selected' : ''}>Github</option>
      <option value="百度网盘" ${type === '百度网盘' ? 'selected' : ''}>百度网盘</option>
      <option value="蓝奏云" ${type === '蓝奏云' ? 'selected' : ''}>蓝奏云</option>
      <option value="123云盘" ${type === '123云盘' ? 'selected' : ''}>123云盘</option>
      <option value="夸克网盘" ${type === '夸克网盘' ? 'selected' : ''}>夸克网盘</option>
      <option value="官方备用下载" ${type === '官方备用下载' ? 'selected' : ''}>官方备用下载</option>
      <option value="第三方下载站" ${type === '第三方下载站' ? 'selected' : ''}>第三方下载站</option>
      <option value="其它方式" ${type === '其它方式' ? 'selected' : ''}>其它方式</option>
    </select>
    <input type="text" name="channel_name[]" class="form-input" placeholder="渠道名称/备注" value="${escapeHtml(name)}" style="font-size: 0.8125rem; height: 32px; padding: 2px 8px;">
    <input type="text" name="channel_url[]" class="form-input" placeholder="下载链接 / 产品ID" value="${escapeHtml(url)}" style="font-size: 0.8125rem; height: 32px; padding: 2px 8px;" required>
    <button type="button" class="btn-icon" onclick="document.getElementById('${rowId}').remove()" title="删除该渠道" style="width: 32px; height: 32px; color: #ef4444; border-radius: 4px;">
      ✕
    </button>
  `;
  container.appendChild(div);
}

function addAddChannelRow(type = '第三方下载站', name = '', url = '') {
  const container = document.getElementById('add-channels-container');
  if (!container) return;
  const rowId = 'ch-add-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
  const div = document.createElement('div');
  div.id = rowId;
  div.style.cssText = 'display: grid; grid-template-columns: 140px 140px 1fr 34px; gap: 8px; align-items: center; background: var(--bg-surface); padding: 8px; border-radius: var(--radius-xs); border: 1px solid var(--border-subtle);';
  div.innerHTML = `
    <select name="channel_type[]" class="form-select" style="font-size: 0.8125rem; height: 32px; padding: 2px 6px;">
      <option value="Microsoft Store" ${type === 'Microsoft Store' ? 'selected' : ''}>Microsoft Store</option>
      <option value="Github" ${type === 'Github' ? 'selected' : ''}>Github</option>
      <option value="百度网盘" ${type === '百度网盘' ? 'selected' : ''}>百度网盘</option>
      <option value="蓝奏云" ${type === '蓝奏云' ? 'selected' : ''}>蓝奏云</option>
      <option value="123云盘" ${type === '123云盘' ? 'selected' : ''}>123云盘</option>
      <option value="夸克网盘" ${type === '夸克网盘' ? 'selected' : ''}>夸克网盘</option>
      <option value="官方备用下载" ${type === '官方备用下载' ? 'selected' : ''}>官方备用下载</option>
      <option value="第三方下载站" ${type === '第三方下载站' ? 'selected' : ''}>第三方下载站</option>
      <option value="其它方式" ${type === '其它方式' ? 'selected' : ''}>其它方式</option>
    </select>
    <input type="text" name="channel_name[]" class="form-input" placeholder="渠道名称/备注" value="${escapeHtml(name)}" style="font-size: 0.8125rem; height: 32px; padding: 2px 8px;">
    <input type="text" name="channel_url[]" class="form-input" placeholder="下载链接 / 产品ID" value="${escapeHtml(url)}" style="font-size: 0.8125rem; height: 32px; padding: 2px 8px;" required>
    <button type="button" class="btn-icon" onclick="document.getElementById('${rowId}').remove()" title="删除该渠道" style="width: 32px; height: 32px; color: #ef4444; border-radius: 4px;">
      ✕
    </button>
  `;
  container.appendChild(div);
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function previewEditIcon(url) {
  const img = document.getElementById('edit-icon-preview');
  const status = document.getElementById('edit-icon-status');
  if (!url) {
    img.src = '';
    status.innerText = '请输入有效图标直链';
    return;
  }
  img.src = url;
  status.innerText = '正在加载图标预览...';
  img.onload = () => { status.innerText = '✓ 图标加载正常'; status.style.color = '#10b981'; };
}

function toggleEditLicenseField(val) {
  const group = document.getElementById('edit-license-group');
  const licenseInput = document.getElementById('edit-field-license');
  if (group) {
    group.style.display = val === '1' ? 'block' : 'none';
    if (val !== '1' && licenseInput) licenseInput.value = '';
  }
}

function toggleEditPriceField(val) {
  const group = document.getElementById('edit-pricing-group');
  if (group) {
    group.style.display = val === 'free' ? 'none' : 'block';
  }
}
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
