<?php
/**
 * 蓝屏钙软件导航网 - 统一软件提交表单（唯一数据源）
 *
 * 独立提交页、全局提交弹窗共用此片段，确保两处控件、字段、校验与样式
 * 完全一致，任何改动只需维护本文件。
 *
 * 依赖的调用方变量：
 *   $submitFormId  string  表单 DOM id（同一页面可能出现两份表单）
 *   $submitScope   string  'page' | 'modal'，决定容器类名与是否提交 is_ajax
 *   $submitCategories array 分类列表
 *   $submitCaptcha string  人机验证 HTML
 */
$submitFormId = $submitFormId ?? 'softnav-submit-form';
$submitScope = $submitScope ?? 'page';
$submitCategories = $submitCategories ?? [];
$submitCaptcha = $submitCaptcha ?? '';
$currentUser = Auth::currentUser();
$isModal = ($submitScope === 'modal');
?>
<form action="<?= Url::route('submit/save') ?>"
      method="POST"
      id="<?= View::e($submitFormId) ?>"
      class="softnav-submit-form"
      data-scope="<?= View::e($submitScope) ?>"
      novalidate>
  <?= Auth::csrfField() ?>
  <?php if ($isModal): ?>
    <input type="hidden" name="is_ajax" value="1">
  <?php endif; ?>

  <div class="form-section">
    <div class="form-section-title">
      <?= FluentIcon::render('desktop', 'fluent-icon-sm') ?>
      <span>软件基本信息</span>
    </div>

    <div class="form-group">
      <label class="form-label" for="<?= $submitFormId ?>-name">
        软件名称 <span class="form-required">*</span>
      </label>
      <input type="text" id="<?= $submitFormId ?>-name" name="name" class="form-input" maxlength="100"
             placeholder="例如：VS Code、7-Zip、PotPlayer、Snipaste" required>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-official">官方网站 <span class="form-required">*</span></label>
        <input type="url" id="<?= $submitFormId ?>-official" name="official_url" class="form-input" maxlength="500"
               placeholder="https://..." required>
      </div>

      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-icon">软件图标链接</label>
        <input type="url" id="<?= $submitFormId ?>-icon" name="icon_url" class="form-input" maxlength="500"
               placeholder="留空则自动抓取官网图标">
        <div class="form-hint">可以不填：系统会去官方网站抓它自己声明的图标（约 3 秒）。也可以贴一张 256×256 的 PNG 直链。</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-category">软件分类</label>
        <select id="<?= $submitFormId ?>-category" name="category_id" class="form-select">
          <option value="0">由系统智能判定</option>
          <?php foreach ($submitCategories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"><?= View::e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!empty($currentUser)): ?>
        <input type="hidden" id="<?= $submitFormId ?>-email" name="submitter_email" value="<?= View::e($currentUser['email'] ?? '') ?>">
      <?php else: ?>
        <div class="form-group">
          <label class="form-label" for="<?= $submitFormId ?>-email">联系邮箱 <span class="form-required">*</span></label>
          <input type="email" id="<?= $submitFormId ?>-email" name="submitter_email" class="form-input" maxlength="254"
                 placeholder="用于接收审核结果通知" autocomplete="email" required>
          <div class="form-hint">审核通过或驳回后，审核结果与理由将发送至此邮箱。</div>
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
        <label class="form-label" for="<?= $submitFormId ?>-opensource">源码授权</label>
        <select id="<?= $submitFormId ?>-opensource" name="is_open_source" class="form-select"
                data-license-toggle="<?= $submitFormId ?>-opensource-group">
          <option value="1">开源代码</option>
          <option value="0">闭源专有</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-developer">开发团队 / 作者</label>
        <input type="text" id="<?= $submitFormId ?>-developer" name="developer" class="form-input" maxlength="100"
               placeholder="例如：Microsoft、个人开发者">
      </div>
    </div>

    <div class="form-conditional" id="<?= $submitFormId ?>-opensource-group">
      <div class="form-conditional-title">
        <?= FluentIcon::render('code', 'fluent-icon-sm') ?>
        <span>开源项目信息</span>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="<?= $submitFormId ?>-license">开源协议</label>
          <input type="text" id="<?= $submitFormId ?>-license" name="license" class="form-input" maxlength="100"
                 placeholder="如：MIT, Apache-2.0, GPL-3.0" data-license-input>
        </div>
        <div class="form-group">
          <label class="form-label" for="<?= $submitFormId ?>-github">GitHub 仓库</label>
          <input type="url" id="<?= $submitFormId ?>-github" name="github_url" class="form-input" maxlength="500"
                 placeholder="https://github.com/owner/repo">
          <div class="form-hint">填写后将自动启用 GitHub Release 直链解析与镜像加速。</div>
        </div>
      </div>
    </div>

  </div>

  <div class="form-section">
    <div class="form-section-title">
      <?= FluentIcon::render('money', 'fluent-icon-sm') ?>
      <span>收费属性</span>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-pricetype">收费类别</label>
        <select id="<?= $submitFormId ?>-pricetype" name="price_type" class="form-select"
                data-pricing-toggle="<?= $submitFormId ?>-pricing-group">
          <option value="free">免费获取</option>
          <option value="freemium">免费体验 (含内购/增值服务)</option>
          <option value="paid">付费购买</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-install">安装支持类型</label>
        <input type="text" id="<?= $submitFormId ?>-install" name="install_type" class="form-input" maxlength="100"
               placeholder="例如：标准安装包 / 免安装便携版">
      </div>
    </div>

    <div class="form-conditional" id="<?= $submitFormId ?>-pricing-group" hidden>
      <div class="form-conditional-title">
        <?= FluentIcon::render('money', 'fluent-icon-sm') ?>
        <span>收费方式与价格明细</span>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="<?= $submitFormId ?>-pricemethod">收费方式</label>
          <select id="<?= $submitFormId ?>-pricemethod" name="price_method" class="form-select">
            <option value="">请选择</option>
            <option value="买断制">永久买断制</option>
            <option value="订阅制">按期订阅制 (月/年)</option>
            <option value="高级功能内购">基础免费 + 高级功能内购</option>
            <option value="按次付费">按量/按次付费</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="<?= $submitFormId ?>-priceamount">参考价格</label>
          <input type="text" id="<?= $submitFormId ?>-priceamount" name="price_amount" class="form-input" maxlength="100"
                 placeholder="例如：￥68 或 $9.99/月">
        </div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-title">
      <?= FluentIcon::render('globe', 'fluent-icon-sm') ?>
      <span>运行环境</span>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-os">适用操作系统与要求</label>
        <input type="text" id="<?= $submitFormId ?>-os" name="os_requirements" class="form-input" maxlength="200"
               placeholder="例如：Windows 10 版本 17763.0 或更高版本">
      </div>
      <div class="form-group">
        <label class="form-label" for="<?= $submitFormId ?>-lang">支持的语言</label>
        <input type="text" id="<?= $submitFormId ?>-lang" name="supported_languages" class="form-input" maxlength="200"
               placeholder="例如：简体中文, 英语 (多语言支持)">
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-title">
      <?= FluentIcon::render('arrow-download', 'fluent-icon-sm') ?>
      <span>其它获取渠道</span>
    </div>
    <div class="form-hint" style="margin-bottom:8px;">
      留空表示仅有官网一个下载来源。类型决定后续展示样式，名称将直接显示在详情页按钮上。
    </div>
    <div class="channel-editor" data-channel-editor="<?= $submitFormId ?>">
      <div class="channel-row" data-channel-row>
        <select name="channel_type[]" class="form-select channel-type-select" aria-label="渠道类型">
          <option value="Github">Github</option>
          <option value="Microsoft Store">Microsoft Store</option>
          <option value="第三方下载站" selected>第三方下载站</option>
        </select>
        <input type="text" name="channel_name[]" class="form-input channel-name-input" maxlength="100"
               placeholder="下载方式名称 (如：蓝奏云、百度网盘)">
        <input type="url" name="channel_url[]" class="form-input channel-url-input" maxlength="500"
               placeholder="下载直链或分享网页链接 https://...">
        <button type="button" class="channel-remove" data-channel-remove title="删除该渠道" aria-label="删除该渠道">&times;</button>
      </div>
    </div>
    <button type="button" class="btn-store-pill" data-channel-add style="margin-top:8px;">+ 添加渠道</button>
  </div>

  <div class="form-section">
    <div class="form-section-title">
      <?= FluentIcon::render('tag', 'fluent-icon-sm') ?>
      <span>标签</span>
    </div>
    <div class="form-group">
      <label class="form-label" for="<?= $submitFormId ?>-tags">软件标签</label>
      <input type="text" id="<?= $submitFormId ?>-tags" name="tags" class="form-input" maxlength="500"
             data-tag-input placeholder="例如：开源, 工具, 便携版, 免安装" autocomplete="off">
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
      <span>特别说明</span>
    </div>
    <div class="form-group">
      <label class="form-label" for="<?= $submitFormId ?>-notes">特别说明</label>
      <textarea id="<?= $submitFormId ?>-notes" name="special_notes" class="form-textarea"
                maxlength="200" rows="2"
                placeholder="例如：永久免费且无任何弹窗广告，推荐下载 64 位版本。"></textarea>
      <div class="form-hint">纯文本即可，无需 Markdown。</div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-title">
      <?= FluentIcon::render('grid', 'fluent-icon-sm') ?>
      <span>软件说明</span>
    </div>
    <div class="form-group">
      <label class="form-label" for="<?= $submitFormId ?>-descmd">软件说明 / 简介 <span style="color: var(--text-muted); font-weight: normal;">(Markdown)</span></label>
      <textarea id="<?= $submitFormId ?>-descmd" name="description_md" class="form-textarea markdown-editor"
                maxlength="10000" rows="8"
                placeholder="支持 Markdown 语法，详细介绍软件的功能特性、安装方法、运行环境等…"></textarea>
      <div class="form-hint">支持标题、列表、代码块、表格、链接等 Markdown 语法，审核通过后在详情页「说明」卡片渲染展示。</div>
    </div>
  </div>

  <?php if ($submitCaptcha !== ''): ?>
    <div class="form-section">
      <div class="form-section-title">
        <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
        <span>人机安全验证</span>
      </div>
      <?= $submitCaptcha ?>
    </div>
  <?php endif; ?>

  <div class="<?= $isModal ? 'modal-footer' : 'form-actions form-actions-stretch' ?>">
    <?php if ($isModal): ?>
      <button type="button" class="btn-store-secondary" data-submit-cancel>取消</button>
    <?php else: ?>
      <a href="<?= Url::route('home') ?>" class="btn-store-secondary">返回首页</a>
    <?php endif; ?>
    <button type="submit" class="btn-store-primary">
      <?= FluentIcon::render('plus', 'fluent-icon-sm') ?>
      <span data-submit-label>提交软件</span>
    </button>
  </div>
</form>
