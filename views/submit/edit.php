<?php
/**
 * 蓝屏钙软件导航网 - 驳回后的安全修改与重新提交页
 *
 * 访问凭证为审核邮件中的一次性安全链接（?r=submit/edit&token=…），
 * 令牌以 SHA-256 摘要存储、7 天过期、使用后立即失效。
 */
$editChannels = !empty($channels) ? $channels : [['type' => '第三方下载站', 'name' => '', 'url' => '']];
?>
<main class="main-layout" style="display: flex; justify-content: center; padding: 36px 16px;">
  <div style="max-width: 820px; width: 100%; margin: 0 auto;">

    <div class="form-shell" style="padding: 30px;">

      <div class="form-page-header">
        <h1 class="form-page-title">
          <?= FluentIcon::render('edit', 'fluent-icon') ?>
          <span>修改软件提交</span>
        </h1>
        <p class="form-page-subtitle">请依据驳回理由修改内容后重新提交，审核结果将发送至联系邮箱</p>
      </div>

      <?php if (!empty($success)): ?>
        <div class="form-alert form-alert-success">
          <?= FluentIcon::render('checkmark', 'fluent-icon-sm') ?>
          <div>
            <strong>重新提交成功！</strong><br>
            <span>修改内容已进入待审核队列。该修改链接已失效，如需再次修改请等待新的审核结果邮件。</span>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;">
          <a href="<?= Url::route('home') ?>" class="btn-store-primary" style="padding: 0 24px; height: 38px;">返回首页</a>
        </div>
      <?php else: ?>

        <?php if (!empty($software['review_note'])): ?>
          <div class="form-alert form-alert-warning">
            <?= FluentIcon::render('warning', 'fluent-icon-sm') ?>
            <div>
              <strong>驳回理由：</strong><br>
              <?= nl2br(View::e($software['review_note'])) ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="form-alert form-alert-error">
            <?= FluentIcon::render('warning', 'fluent-icon-sm') ?>
            <span><?= View::e($error) ?></span>
          </div>
        <?php endif; ?>

        <form action="<?= Url::route('submit/resubmit') ?>" method="POST" id="softnav-submit-resubmit" class="softnav-resubmit-form" novalidate>
          <?= Auth::csrfField() ?>
          <input type="hidden" name="token" value="<?= View::e($token) ?>">

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('desktop', 'fluent-icon-sm') ?>
              <span>软件基本信息</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="resubmit-name">软件名称 <span class="form-required">*</span></label>
              <input type="text" id="resubmit-name" name="name" class="form-input" maxlength="100" required
                     value="<?= View::e($software['name'] ?? '') ?>">
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="resubmit-official">官方网站 <span class="form-required">*</span></label>
                <input type="url" id="resubmit-official" name="official_url" class="form-input" maxlength="500" required
                       value="<?= View::e($software['official_url'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-icon">软件图标链接</label>
                <input type="url" id="resubmit-icon" name="icon_url" class="form-input" maxlength="500"
                       placeholder="留空则自动抓取官网图标" value="<?= View::e($software['icon_url'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-category">软件分类 <span class="form-required">*</span></label>
                <select id="resubmit-category" name="category_id" class="form-select" required>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)($software['category_id'] ?? 0) ? 'selected' : '' ?>>
                      <?= View::e($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php $curUser = Auth::currentUser(); ?>
              <?php if (!empty($curUser)): ?>
                <input type="hidden" id="resubmit-email" name="submitter_email" value="<?= View::e($curUser['email'] ?? ($software['submitter_email'] ?? '')) ?>">
              <?php else: ?>
                <div class="form-group">
                  <label class="form-label" for="resubmit-email">联系邮箱 <span class="form-required">*</span></label>
                  <input type="email" id="resubmit-email" name="submitter_email" class="form-input" maxlength="254" required
                         autocomplete="email" value="<?= View::e($software['submitter_email'] ?? '') ?>">
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('code', 'fluent-icon-sm') ?>
              <span>源码与授权</span>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="resubmit-opensource">源码授权</label>
                <select id="resubmit-opensource" name="is_open_source" class="form-select" data-license-toggle="resubmit-opensource-group">
                  <option value="1" <?= (int)($software['is_open_source'] ?? 1) === 1 ? 'selected' : '' ?>>开源代码</option>
                  <option value="0" <?= (int)($software['is_open_source'] ?? 1) === 0 ? 'selected' : '' ?>>闭源专有</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-publisher">发布机构</label>
                <input type="text" id="resubmit-publisher" name="publisher" class="form-input" maxlength="100"
                       value="<?= View::e($software['publisher'] ?? '') ?>">
              </div>
            </div>

            <div class="form-conditional" id="resubmit-opensource-group">
              <div class="form-conditional-title">
                <?= FluentIcon::render('code', 'fluent-icon-sm') ?>
                <span>开源项目信息</span>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label" for="resubmit-license">开源协议</label>
                  <input type="text" id="resubmit-license" name="license" class="form-input" maxlength="100" data-license-input
                         value="<?= View::e($software['license'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label class="form-label" for="resubmit-github">GitHub 仓库</label>
                  <input type="url" id="resubmit-github" name="github_url" class="form-input" maxlength="500"
                         value="<?= View::e($software['github_url'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('money', 'fluent-icon-sm') ?>
              <span>收费与运行环境</span>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="resubmit-pricetype">收费类别</label>
                <select id="resubmit-pricetype" name="price_type" class="form-select" data-pricing-toggle="resubmit-pricing-group">
                  <?php $currentPrice = (string)($software['price_type'] ?? 'free'); ?>
                  <option value="free" <?= $currentPrice === 'free' ? 'selected' : '' ?>>免费获取</option>
                  <option value="freemium" <?= $currentPrice === 'freemium' ? 'selected' : '' ?>>免费体验 (含内购/增值服务)</option>
                  <option value="paid" <?= $currentPrice === 'paid' ? 'selected' : '' ?>>付费购买</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-developer">开发团队 / 作者</label>
                <input type="text" id="resubmit-developer" name="developer" class="form-input" maxlength="100"
                       value="<?= View::e($software['developer'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-os">适用操作系统与要求</label>
                <input type="text" id="resubmit-os" name="os_requirements" class="form-input" maxlength="200"
                       value="<?= View::e($software['os_requirements'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-lang">支持的语言</label>
                <input type="text" id="resubmit-lang" name="supported_languages" class="form-input" maxlength="200"
                       value="<?= View::e($software['supported_languages'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="resubmit-install">安装支持类型</label>
                <input type="text" id="resubmit-install" name="install_type" class="form-input" maxlength="100"
                       value="<?= View::e($software['install_type'] ?? '') ?>">
              </div>
            </div>

            <div class="form-conditional" id="resubmit-pricing-group" hidden>
              <div class="form-conditional-title">
                <?= FluentIcon::render('money', 'fluent-icon-sm') ?>
                <span>收费方式与价格明细</span>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label" for="resubmit-pricemethod">收费方式</label>
                  <select id="resubmit-pricemethod" name="price_method" class="form-select">
                    <option value="">请选择</option>
                    <option value="买断制">永久买断制</option>
                    <option value="订阅制">按期订阅制 (月/年)</option>
                    <option value="高级功能内购">基础免费 + 高级功能内购</option>
                    <option value="按次付费">按量/按次付费</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="resubmit-priceamount">参考价格</label>
                  <input type="text" id="resubmit-priceamount" name="price_amount" class="form-input" maxlength="100"
                         placeholder="例如：￥68 或 $9.99/月">
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('arrow-download', 'fluent-icon-sm') ?>
              <span>其它获取渠道</span>
            </div>
            <div class="form-hint" style="margin-bottom:8px;">留空表示仅有官网一个下载来源。</div>
            <div class="channel-editor" data-channel-editor="resubmit">
              <?php foreach ($editChannels as $channel): ?>
                <div class="channel-row" data-channel-row>
                  <select name="channel_type[]" class="form-select channel-type-select" aria-label="渠道类型">
                    <option value="Github" <?= ($channel['type'] ?? '') === 'Github' ? 'selected' : '' ?>>Github</option>
                    <option value="Microsoft Store" <?= ($channel['type'] ?? '') === 'Microsoft Store' ? 'selected' : '' ?>>Microsoft Store</option>
                    <option value="第三方下载站" <?= ($channel['type'] ?? '') === '第三方下载站' ? 'selected' : '' ?>>第三方下载站</option>
                  </select>
                  <input type="text" name="channel_name[]" class="form-input channel-name-input" maxlength="100"
                         placeholder="下载方式名称 (如：蓝奏云、百度网盘)"
                         value="<?= View::e($channel['name'] ?? '') ?>">
                  <input type="url" name="channel_url[]" class="form-input channel-url-input" maxlength="500"
                         placeholder="下载直链或分享网页链接 https://..."
                         value="<?= View::e($channel['url'] ?? '') ?>">
                  <button type="button" class="channel-remove" data-channel-remove title="删除该渠道" aria-label="删除该渠道">&times;</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn-store-pill" data-channel-add style="margin-top:8px;">+ 添加渠道</button>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('tag', 'fluent-icon-sm') ?>
              <span>标签</span>
            </div>
            <div class="form-group">
              <label class="form-label" for="resubmit-tags">软件标签</label>
              <input type="text" id="resubmit-tags" name="tags" class="form-input" maxlength="500" data-tag-input
                     value="<?= View::e($software['tags'] ?? '') ?>" autocomplete="off">
              <div class="tag-picker" data-tag-picker>
                <?php foreach (['开源', '免费', '免安装', '便携版', 'Win11', '办公', '解压缩', '影音播放', '系统增强', '绿色'] as $presetTag): ?>
                  <span class="tag-pill" data-tag-preset="<?= View::e($presetTag) ?>">+ <?= View::e($presetTag) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('list', 'fluent-icon-sm') ?>
              <span>详细说明</span>
            </div>
            <div class="form-group">
              <label class="form-label" for="resubmit-notes">详细说明</label>
              <textarea id="resubmit-notes" name="special_notes" class="form-textarea markdown-editor" maxlength="10000" rows="8"
                        placeholder="支持 Markdown 语法…"><?= View::e($software['special_notes'] ?? '') ?></textarea>
              <div class="form-hint">支持标题、列表、代码块、表格、链接等 Markdown 语法。</div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title">
              <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
              <span>人机安全验证</span>
            </div>
            <?= $captchaHtml ?>
          </div>

          <div class="form-actions">
            <a href="<?= Url::route('home') ?>" class="btn-store-secondary">放弃并返回</a>
            <button type="submit" class="btn-store-primary" style="padding: 0 24px; height: 38px;">
              <?= FluentIcon::render('send', 'fluent-icon-sm') ?>
              <span>重新提交审核</span>
            </button>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php if (empty($success)): ?>
  <script src="<?= Url::basePath() ?>assets/js/submit-form.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/submit-form.js') ?>"></script>
  <script>
    // 复用提交表单的校验规则，保证修改页与首次提交页校验完全一致
    (function () {
      var form = document.getElementById('softnav-submit-resubmit');
      if (!form) return;
      form.addEventListener('submit', function (event) {
        var nameInput = form.querySelector('[name="name"]');
        var official = form.querySelector('[name="official_url"]');
        var icon = form.querySelector('[name="icon_url"]');
        var email = form.querySelector('[name="submitter_email"]');

        if (!nameInput.value.trim()) {
          event.preventDefault();
          nameInput.focus();
          return window.FluentMessage('请填写软件名称', 'warning');
        }
        [official, icon].forEach(function (input) {
          if (!/^https?:\/\//i.test(input.value.trim())) {
            event.preventDefault();
            input.focus();
            return window.FluentMessage('网址必须以 http:// 或 https:// 开头', 'warning');
          }
        });
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) {
          event.preventDefault();
          email.focus();
          return window.FluentMessage('必须填写有效的联系邮箱', 'warning');
        }
      });

      // 初始化渠道联动与开源/收费区块
      if (window.SoftnavSubmitForm) window.SoftnavSubmitForm.scan(form);
    })();
  </script>
<?php endif; ?>
