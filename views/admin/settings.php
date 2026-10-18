<?php
require VIEW_PATH . '/admin/layout_nav.php';

// 凭据类字段只写不读：值不回显到 HTML，留空提交即保持库里原值
$secretHint = static function (string $key) use ($settings): string {
    return !empty($settings[$key]) ? '已设置 · 留空表示保持不变' : '尚未设置';
};
?>

<div class="admin-card" style="max-width: 820px;">
  <div style="margin-bottom: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px;">
    <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
      <?= FluentIcon::render('settings') ?>
      <span>系统与全局配置</span>
    </h2>
    <p style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 4px;">
      配置网站信息、大模型 AI API Key、人机验证密钥及 Google ADS 代码。
    </p>
  </div>

  <?php if (!empty($msg)): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.875rem; margin-bottom: 20px;">
      <?= View::e($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($testMailResult)): ?>
    <div style="background: <?= $testMailResult['type'] === 'success' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $testMailResult['type'] === 'success' ? '#166534' : '#991b1b' ?>; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.875rem; margin-bottom: 20px;">
      <?= View::e($testMailResult['text']) ?>
    </div>
  <?php endif; ?>

  <form action="<?= Url::route('admin/settings') ?>" method="POST">
    <?= Auth::csrfField() ?>
    <!-- 1. 站点基础与 SEO 模块 -->
    <div style="margin-bottom: 28px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px;">
        1. 站点基础与 SEO 搜索优化
      </h3>
      <div class="form-group">
        <label class="form-label">网站主标题 (Site Name)</label>
        <input type="text" name="site_name" class="form-input" value="<?= View::e($settings['site_name'] ?? '蓝屏钙软件导航') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">副标题 / 口号</label>
        <input type="text" name="site_subtitle" class="form-input" value="<?= View::e($settings['site_subtitle'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">SEO 描述 (Description)</label>
        <textarea name="site_description" class="form-textarea" style="min-height: 60px;"><?= View::e($settings['site_description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">SEO 关键词 (Keywords，以逗号分隔)</label>
        <input type="text" name="site_keywords" class="form-input" value="<?= View::e($settings['site_keywords'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">网站 Logo 图标链接 (URL)</label>
        <input type="url" name="site_logo" class="form-input" placeholder="可填入自定义图片直链 (支持 PNG/SVG/WebP)，留空使用默认 Fluent 矢量图标" value="<?= View::e($settings['site_logo'] ?? '') ?>">
        <div class="form-hint">展示在全站顶栏左侧。留空时展示优雅内置微软风格 Fluent 矢量电脑图标。</div>
      </div>
      <div class="form-group">
        <label class="form-label">网站 Favicon 图标链接 (URL / .ico)</label>
        <input type="url" name="site_favicon" class="form-input" placeholder="例如：https://.../favicon.ico" value="<?= View::e($settings['site_favicon'] ?? '') ?>">
        <div class="form-hint">展示在浏览器标签页中的小图标，支持 .ico, .png 或 .svg。</div>
      </div>
      <div class="form-group">
        <label class="form-label">网页底部版权与信息 (Footer)</label>
        <textarea name="site_footer" class="form-textarea" style="min-height: 70px;" placeholder="支持自定义 HTML 或文字，如：&copy; 2026 我的软件导航 · 浙ICP备xxxxxxxx号"><?= View::e($settings['site_footer'] ?? '') ?></textarea>
        <div class="form-hint">留空则展示系统默认版权与收录说明。支持直接填入备案号或自定义超链接 HTML。</div>
      </div>
    </div>

    <!-- 2. AI 智能补全与多线路容灾配置 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin: 0; display: flex; align-items: center; gap: 6px;">
          <?= FluentIcon::render('sparkle', 'fluent-icon-sm') ?>
          2. AI 智能补全与多线路容灾配置
        </h3>
        <span style="font-size: 0.75rem; background: var(--bg-hover); padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border-subtle); color: var(--text-secondary);">
          支持多提供商自动故障切换与本地离线规则兜底
        </span>
      </div>

      <div class="form-grid-2" style="margin-bottom: 16px;">
        <div class="form-group">
          <label class="form-label">启用 AI 辅助补全</label>
          <select name="ai_enabled" class="form-select">
            <option value="1" <?= ($settings['ai_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>开启 (审核新软件或重新分析时自动智能总结并补全元数据)</option>
            <option value="0" <?= ($settings['ai_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>关闭 (仅使用本地内置规则)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">联网检索增强 (RAG 官网与搜索预抓取)</label>
          <select name="ai_web_search_enabled" class="form-select">
            <option value="1" <?= ($settings['ai_web_search_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>开启 (自动抓取官网/GitHub与DuckDuckGo/Bing网络搜索)</option>
            <option value="0" <?= ($settings['ai_web_search_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>关闭 (仅使用大模型自带离线知识)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">同一条线路重试次数</label>
          <select name="ai_retries" class="form-select">
            <?php $retries = max(1, min(5, (int) ($settings['ai_retries'] ?? 3))); ?>
            <?php for ($n = 1; $n <= 5; $n++): ?>
              <option value="<?= $n ?>" <?= $retries === $n ? 'selected' : '' ?>><?= $n ?> 次<?= $n === 1 ? '（不重试，失败就换线路）' : '' ?></option>
            <?php endfor; ?>
          </select>
          <div class="form-hint">只对超时、限流(429)、5xx 和「吐回来的不是能用的 JSON」重试；密钥错、模型名写错这类 4xx 不重试，直接换线路。</div>
        </div>
        <div class="form-group">
          <label class="form-label">AI 补全总时长上限（秒）</label>
          <input type="number" name="ai_total_timeout" class="form-input" min="10" max="120" step="5"
                 value="<?= max(10, min(120, (int) ($settings['ai_total_timeout'] ?? 20))) ?>">
          <div class="form-hint">审核点「AI 补全后发布」与后台「重新生成」都是<b>当场跑、当场等</b>：重试与切线路都在这个预算内，超了就落本地规则兜底。php-fpm 的 max_execution_time 与 Nginx 的 fastcgi_read_timeout 必须大于它（默认 30s，所以这里默认压到 20s）。提交软件时不会调用 AI，用户不等。</div>
        </div>
      </div>

      <div class="form-grid-2" style="margin-bottom: 16px;">
        <div class="form-group">
          <label class="form-label">让 AI 自己用工具（搜索 + 打开网页）</label>
          <select name="ai_tool_use" class="form-select">
            <option value="1" <?= ($settings['ai_tool_use'] ?? '1') !== '0' ? 'selected' : '' ?>>开启 (模型自己决定搜什么、翻几轮、打开哪条结果，接近 AI 网页端的联网问答)</option>
            <option value="0" <?= ($settings['ai_tool_use'] ?? '1') === '0' ? 'selected' : '' ?>>关闭 (服务端先抓完再一次性喂给模型)</option>
          </select>
          <div class="form-hint">线路不支持 tools 时会自动退回「服务端预取」这一条路，不会因此失败。工具发出的每个请求仍走 SSRF 校验、robots 与同主机间隔。</div>
        </div>
        <div class="form-group">
          <label class="form-label">工具最多来回几轮</label>
          <?php $rounds = max(0, min(4, (int) ($settings['ai_tool_rounds'] ?? 3))); ?>
          <select name="ai_tool_rounds" class="form-select">
            <?php for ($n = 0; $n <= 4; $n++): ?>
              <option value="<?= $n ?>" <?= $rounds === $n ? 'selected' : '' ?>><?= $n ?> 轮<?= $n === 0 ? '（等于不用工具）' : '' ?></option>
            <?php endfor; ?>
          </select>
          <div class="form-hint">一轮 = 模型调一次工具、服务端回一次结果。总时长仍受上面「AI 补全总时长上限」约束。</div>
        </div>
        <div class="form-group">
          <label class="form-label">单页正文最多取多少字</label>
          <input type="number" name="ai_page_chars" class="form-input" min="600" max="8000" step="200"
                 value="<?= max(600, min(8000, (int) ($settings['ai_page_chars'] ?? 3000))) ?>">
          <div class="form-hint">给模型的每页正文上限。太小判不出开发者与协议，太大又慢又贵。</div>
        </div>
        <div class="form-group">
          <label class="form-label">遵守 robots.txt</label>
          <select name="ai_respect_robots" class="form-select">
            <option value="1" <?= ($settings['ai_respect_robots'] ?? '1') !== '0' ? 'selected' : '' ?>>遵守 (明确 Disallow 的路径就不抓)</option>
            <option value="0" <?= ($settings['ai_respect_robots'] ?? '1') === '0' ? 'selected' : '' ?>>不遵守（容易吃 403，也可能给人家添麻烦）</option>
          </select>
          <div class="form-hint">同主机最小间隔也建议一起保留：秒开十几个域名是最典型的脚本特征。</div>
        </div>
        <div class="form-group">
          <label class="form-label">同一主机抓取之间留间隔</label>
          <select name="ai_courtesy_delay" class="form-select">
            <option value="1" <?= ($settings['ai_courtesy_delay'] ?? '1') !== '0' ? 'selected' : '' ?>>留（约 1.2–1.9 秒 + 抖动）</option>
            <option value="0" <?= ($settings['ai_courtesy_delay'] ?? '1') === '0' ? 'selected' : '' ?>>不留（更快，但更容易被判定为爬虫）</option>
          </select>
        </div>
      </div>

      <div class="form-grid-2" style="margin-bottom: 16px;">
        <?php
        // 搜索后端密钥同样是只写不读：不回显，留空表示沿用原值
        $searchBackends = [
            ['search_tavily_key', 'Tavily', 'https://tavily.com，有免费额度，返回的就是能直接用的结构化结果'],
            ['search_brave_key', 'Brave Search API', 'https://api.search.brave.com'],
            ['search_serper_key', 'Serper（Google SERP）', 'https://serper.dev'],
            ['search_firecrawl_key', 'Firecrawl', 'https://firecrawl.dev，搜索结果能顺带带回正文，Key 在 dashboard 里生成'],
        ];
        foreach ($searchBackends as $backend):
            $filled = trim((string) Setting::get($backend[0], '')) !== '';
        ?>
          <div class="form-group">
            <label class="form-label"><?= View::e($backend[1]) ?> Key<?= $filled ? '（已配置）' : '（未配置）' ?></label>
            <input type="password" name="<?= View::e($backend[0]) ?>" class="form-input" autocomplete="off"
                   placeholder="<?= $filled ? '已保存，留空表示沿用' : '选填 — ' . View::e($backend[2]) ?>">
          </div>
        <?php endforeach; ?>
        <div class="form-group" style="grid-column: 1 / -1;">
          <div class="form-hint">
            填了任意一个，搜索就优先走官方接口（稳定、也不会被当爬虫）。三个都不填时，系统按
            <b>DuckDuckGo HTML → DDG Lite → Bing</b> 抓公开搜索结果页 —— 这条路依赖对方页面结构，
            也可能被临时限流。想要 AI 网页端那种稳定的「先搜再答」，建议至少配一个。
          </div>
        </div>
      </div>

      <div style="padding: 12px 14px; background: rgba(0, 103, 184, 0.05); border: 1px solid rgba(0, 103, 184, 0.2); border-radius: var(--radius-sm); font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 16px;">
        <strong>线路调度与自动容灾机制：</strong>
        系统按下方线路列表<strong>自上而下</strong>的顺序发起请求。若主路线发生超时、API 额度耗尽（429/402）或 HTTP 报错，系统将<strong>自动无缝切换至下一备用路线</strong>；若所有线路均无法访问，将自动切换到系统内置离线规则库完成补全，确保审核流程永不中断。
      </div>

      <?php
      // 只写不读：AI 线路的密钥一律不回显进 HTML。隐藏域里也必须放脱敏副本 ——
      // 之前这里直接输出 getProviders()，只在旧版单密钥配置下也会把 sk-xxx 打进页面源码。
      // 保存时控制器按 name + api_base 沿用库里已有的密钥，留空不等于清空。
      $providers = AiService::getProviders();
      if (empty($providers)) {
          $providers = [
              ['name' => 'DeepSeek', 'api_base' => 'https://api.deepseek.com/v1', 'api_key' => '', 'model' => 'deepseek-chat', 'enabled' => '1']
          ];
      }
      $safeProviders = [];
      foreach ($providers as $provider) {
          $provider['api_key'] = '';
          $safeProviders[] = $provider;
      }
      ?>
      <!-- 隐藏域存储最终 JSON 与兼容字段 -->
      <input type="hidden" name="ai_providers_json" id="ai_providers_json" value="<?= View::e(json_encode($safeProviders, JSON_UNESCAPED_UNICODE)) ?>">
      <input type="hidden" name="ai_provider" id="legacy_ai_provider" value="<?= View::e($settings['ai_provider'] ?? '') ?>">
      <input type="hidden" name="ai_api_base" id="legacy_ai_api_base" value="<?= View::e($settings['ai_api_base'] ?? '') ?>">
      <input type="hidden" name="ai_api_key" id="legacy_ai_api_key" value="">
      <input type="hidden" name="ai_model" id="legacy_ai_model" value="<?= View::e($settings['ai_model'] ?? '') ?>">

      <!-- 提供商卡片列表容器 -->
      <div id="ai-providers-container" style="display: flex; flex-direction: column; gap: 14px;">
        <?php
        foreach ($providers as $idx => $p):
            $isPrimary = ($idx === 0);
            $isEnabled = (!isset($p['enabled']) || $p['enabled'] === '1' || $p['enabled'] === 1 || $p['enabled'] === true);
        ?>
        <div class="ai-provider-card" style="border: 1px solid var(--border-subtle); background: var(--bg-hover); border-radius: var(--radius-sm); padding: 16px; position: relative;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="ai-badge-rank" style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; background: <?= $isPrimary ? 'var(--primary)' : 'rgba(0,0,0,0.1)' ?>; color: <?= $isPrimary ? '#fff' : 'inherit' ?>;">
                <?= $isPrimary ? '主路线 #1' : ('备用路线 #' . ($idx + 1)) ?>
              </span>
              <label style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.8125rem; cursor: pointer; user-select: none;">
                <input type="checkbox" class="ai-field-enabled" <?= $isEnabled ? 'checked' : '' ?> style="accent-color: var(--primary);">
                <span>启用此路线</span>
              </label>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
              <button type="button" class="btn-store-secondary ai-btn-move-up" title="上移（提高优先级）" style="padding: 3px 8px; font-size: 0.75rem; height: 26px;">↑</button>
              <button type="button" class="btn-store-secondary ai-btn-move-down" title="下移（降低优先级）" style="padding: 3px 8px; font-size: 0.75rem; height: 26px;">↓</button>
              <button type="button" class="btn-store-secondary ai-btn-delete" title="删除此服务商" style="padding: 3px 8px; font-size: 0.75rem; height: 26px; color: #dc2626;">✕ 删除</button>
            </div>
          </div>

          <div style="margin-bottom: 10px;">
            <select class="form-select ai-preset-selector" style="font-size: 0.8125rem; height: 32px; padding: 2px 8px; background: var(--bg-surface);">
              <option value="">-- 选择预设模板快速填充 --</option>
              <option value="deepseek" data-base="https://api.deepseek.com/v1" data-model="deepseek-chat" data-name="DeepSeek 官方">DeepSeek 官方 (推荐: deepseek-chat)</option>
              <option value="qwen" data-base="https://dashscope.aliyuncs.com/compatible-mode/v1" data-model="qwen-plus" data-name="通义千问 DashScope">阿里云通义千问 (推荐: qwen-plus)</option>
              <option value="zhipu" data-base="https://open.bigmodel.cn/api/paas/v4" data-model="glm-4-flash" data-name="智谱 GLM">智谱 AI (极速: glm-4-flash)</option>
              <option value="siliconflow" data-base="https://api.siliconflow.cn/v1" data-model="deepseek-ai/DeepSeek-V3" data-name="硅基流动">硅基流动 SiliconFlow (DeepSeek-V3)</option>
              <option value="moonshot" data-base="https://api.moonshot.cn/v1" data-model="moonshot-v1-8k" data-name="Moonshot Kimi">月之暗面 (moonshot-v1-8k)</option>
              <option value="openai" data-base="https://api.openai.com/v1" data-model="gpt-4o-mini" data-name="OpenAI 官方">OpenAI (gpt-4o-mini)</option>
              <option value="ollama" data-base="http://localhost:11434/v1" data-model="qwen2.5:7b" data-name="本地 Ollama">本地 Ollama (qwen2.5:7b)</option>
            </select>
          </div>

          <div class="form-grid-2">
            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 0.8125rem;">提供商名称</label>
              <input type="text" class="form-input ai-field-name" placeholder="例如：DeepSeek 主路线" value="<?= View::e($p['name'] ?? '') ?>" style="height: 34px; font-size: 0.875rem;">
            </div>
            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 0.8125rem;">模型名称 (Model)</label>
              <input type="text" class="form-input ai-field-model" placeholder="例如：deepseek-chat" value="<?= View::e($p['model'] ?? 'deepseek-chat') ?>" style="height: 34px; font-size: 0.875rem;">
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 10px;">
            <label class="form-label" style="font-size: 0.8125rem;">API 接口基址 (API Base URL)</label>
            <input type="text" class="form-input ai-field-base" placeholder="https://api.deepseek.com/v1" value="<?= View::e($p['api_base'] ?? 'https://api.openai.com/v1') ?>" style="height: 34px; font-size: 0.875rem;">
          </div>

          <div class="form-group" style="margin-bottom: 12px;">
            <label class="form-label" style="font-size: 0.8125rem;">API Key 密钥</label>
            <div style="display: flex; gap: 8px;">
              <input type="password" class="form-input ai-field-key" placeholder="<?= !empty($p['api_key']) ? '已设置 · 留空表示保持不变' : 'sk-...' ?>" autocomplete="new-password" style="height: 34px; font-size: 0.875rem;">
              <button type="button" class="btn-store-secondary ai-btn-toggle-key" style="white-space: nowrap; padding: 0 12px; height: 34px; font-size: 0.8125rem;">显示</button>
            </div>
          </div>

          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; border-top: 1px dashed var(--border-subtle); padding-top: 10px; margin-top: 4px;">
            <button type="button" class="btn-store-secondary ai-btn-test" style="font-size: 0.8125rem; height: 30px; padding: 0 12px; display: inline-flex; align-items: center; gap: 5px;">
              ⚡ 测试此线路
            </button>
            <div class="ai-test-result" style="font-size: 0.8125rem; color: var(--text-secondary); flex: 1; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top: 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn-store-secondary" id="btn-add-ai-provider" style="font-size: 0.875rem; padding: 6px 14px; height: 36px; display: inline-flex; align-items: center; gap: 6px;">
          ＋ 添加 AI 提供商线路
        </button>
        <span class="form-hint" style="margin: 0;">支持任意兼容 OpenAI 规范的 API 接口，修改后点击页面底部的「保存所有配置」生效。</span>
      </div>
    </div>

    <!-- 2b. AI 调用日志：容灾切了几路、每路耗时、失败原因、token 用量 -->
    <?php $aiLogs = $aiLogs ?? []; $aiStats = $aiStats ?? ['calls' => 0, 'failures' => 0, 'avg_latency_ms' => 0, 'total_tokens' => 0]; ?>
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 6px;">
        2b. AI 调用日志
      </h3>
      <div class="form-hint" style="margin-bottom: 12px;">
        近 <?= (int) ($aiStats['days'] ?? 7) ?> 天：调用 <?= (int) $aiStats['calls'] ?> 次，失败 <?= (int) $aiStats['failures'] ?> 次，
        平均耗时 <?= number_format((int) $aiStats['avg_latency_ms']) ?>ms，累计 token <?= number_format((int) $aiStats['total_tokens']) ?>。
        容灾切换会留下多行记录；日志只记线路、模型、耗时与错误摘要，<b>不记提示词与密钥</b>。
      </div>
      <?php if ($aiLogs === []): ?>
        <div class="form-hint">还没有 AI 调用记录（提交补全、审核时 AI 补全、以及这里的「测试」按钮都会写入本表）。</div>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table class="admin-table" style="width:100%; font-size:0.8125rem;">
            <thead><tr><th>时间</th><th>用途</th><th>线路 / 模型</th><th>HTTP</th><th>结果</th><th>耗时</th><th>token</th><th>说明</th></tr></thead>
            <tbody>
            <?php
            $purposeLabels = ['submit_enrich' => '提交补全', 'approve_ai' => '审核补全', 'test_provider' => '连通性测试', 'chat' => '其他'];
            foreach ($aiLogs as $log):
                $okRow = ($log['result'] ?? '') === 'ok';
            ?>
              <tr>
                <td style="white-space:nowrap;"><?= View::e(substr((string) ($log['created_at'] ?? ''), 0, 19)) ?></td>
                <td><?= View::e($purposeLabels[$log['purpose'] ?? ''] ?? ($log['purpose'] ?? '')) ?></td>
                <td><?= View::e($log['provider'] ?? '') ?> / <?= View::e($log['model'] ?? '') ?></td>
                <td><?= (int) ($log['http_status'] ?? 0) ?></td>
                <td style="color: <?= $okRow ? '#10b981' : '#dc2626' ?>; font-weight:600;"><?= $okRow ? '成功' : '失败' ?></td>
                <td style="white-space:nowrap;"><?= number_format((int) ($log['latency_ms'] ?? 0)) ?>ms</td>
                <td style="white-space:nowrap;"><?= (int) ($log['total_tokens'] ?? 0) ?: '-' ?></td>
                <td style="max-width:320px; word-break:break-all;"><?= View::e((string) ($log['detail'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- 3. 人机安全验证配置 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
        3. 人机验证服务配置
      </h3>
      <div class="form-group">
        <label class="form-label">验证码提供商</label>
        <?php $cap = $settings['captcha_provider'] ?? 'local'; ?>
        <select name="captcha_provider" class="form-select">
          <option value="local" <?= $cap === 'local' ? 'selected' : '' ?>>内置轻量数学算术验证 (开箱即用，零配置)</option>
          <option value="turnstile" <?= $cap === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile (极力推荐)</option>
          <option value="hcaptcha" <?= $cap === 'hcaptcha' ? 'selected' : '' ?>>hCaptcha</option>
          <option value="geetest" <?= $cap === 'geetest' ? 'selected' : '' ?>>极验 (Geetest)</option>
          <option value="none" <?= $cap === 'none' ? 'selected' : '' ?>>关闭验证 (仅建议本地测试使用)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">公钥 (Site Key / Captcha ID)</label>
        <input type="text" name="captcha_site_key" class="form-input" value="<?= View::e($settings['captcha_site_key'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">私钥 (Secret Key / Key)</label>
        <input type="password" name="captcha_secret_key" class="form-input" autocomplete="new-password" placeholder="<?= View::e($secretHint('captcha_secret_key')) ?>">
      </div>
    </div>

    <!-- 4. Google ADS 预留配置 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px;">
        4. Google ADS 广告位预留
      </h3>
      <div class="form-group">
        <label class="form-label">启用广告展示</label>
        <select name="google_ads_enabled" class="form-select">
          <option value="0" <?= ($settings['google_ads_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>关闭</option>
          <option value="1" <?= ($settings['google_ads_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>开启</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">AdSense 客户端 ID (Client ID)</label>
        <input type="text" name="google_ads_client" class="form-input" placeholder="ca-pub-xxxxxxxxxxxxxxxx" value="<?= View::e($settings['google_ads_client'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">广告槽位 ID (Slot ID)</label>
        <input type="text" name="google_ads_slot" class="form-input" placeholder="1234567890" value="<?= View::e($settings['google_ads_slot'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">或者直接粘贴自定义广告代码 HTML (优先)</label>
        <textarea name="google_ads_code" class="form-textarea" placeholder="<script>...</script>"><?= View::e($settings['google_ads_code'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- 5. SMTP 邮件通知 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px;">
        5. SMTP 邮件通知
      </h3>
      <div class="form-group">
        <label class="form-label">启用邮件通知</label>
        <select name="smtp_enabled" class="form-select">
          <option value="0" <?= ($settings['smtp_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>关闭</option>
          <option value="1" <?= ($settings['smtp_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>开启</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(120px,1fr) minmax(140px,1fr);gap:12px;">
        <div class="form-group">
          <label class="form-label">SMTP 主机</label>
          <input type="text" name="smtp_host" class="form-input" placeholder="smtp.example.com" value="<?= View::e($settings['smtp_host'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">端口</label>
          <input type="number" name="smtp_port" class="form-input" min="1" max="65535" value="<?= View::e($settings['smtp_port'] ?? '587') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">加密方式</label>
          <select name="smtp_encryption" class="form-select">
            <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
            <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
            <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>不加密</option>
          </select>
        </div>
      </div>
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">SMTP 用户名</label>
          <input type="text" name="smtp_username" class="form-input" autocomplete="username" value="<?= View::e($settings['smtp_username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">SMTP 密码</label>
          <input type="password" name="smtp_password" class="form-input" autocomplete="new-password" placeholder="<?= View::e($secretHint('smtp_password')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">发件邮箱</label>
          <input type="email" name="smtp_from_email" class="form-input" value="<?= View::e($settings['smtp_from_email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">发件人名称</label>
          <input type="text" name="smtp_from_name" class="form-input" value="<?= View::e($settings['smtp_from_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">管理员邮箱</label>
          <input type="email" name="admin_email" class="form-input" value="<?= View::e($settings['admin_email'] ?? '') ?>"
                 placeholder="审核通知与「发送测试邮件」留空时的兜底收件人">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">站点公开地址</label>
        <input type="url" name="site_base_url" class="form-input" placeholder="https://example.com" value="<?= View::e($settings['site_base_url'] ?? '') ?>">
        <div class="form-hint">用于邮件中的绝对链接（如驳回后的修改链接）。留空时将退化为相对地址。</div>
      </div>
      <div class="form-group">
        <label class="form-label">URL 形态</label>
        <input type="hidden" name="url_rewrite" value="0">
        <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem;cursor:pointer;">
          <input type="checkbox" name="url_rewrite" value="1" <?= ($settings['url_rewrite'] ?? '1') === '1' ? 'checked' : '' ?> style="accent-color: var(--primary);">
          <span>站内链接用伪静态形态（<code>/software/&lt;slug&gt;</code>、<code>/category/&lt;slug&gt;</code>、<code>/admin/users</code>）</span>
        </label>
        <div class="form-hint">默认开启，前提是 Web 服务器把不存在的路径交给 <code>index.php</code>
          （<code>deploy/nginx.conf.example</code> 与 <code>public/.htaccess</code> 都是这个配置）。
          取消勾选立刻全站回到 <code>index.php?r=控制器/动作</code> 形态，两种地址始终都可达；
          开启状态下访问旧形态地址会自动 301 到伪静态地址。上线前用
          <code>php deploy/preflight.php --url=https://你的域</code> 验一次规则是否到位。</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <button type="button" class="btn-store-secondary" id="smtp-test-btn">发送测试邮件</button>
        <input type="email" name="test_mail_to" id="smtp-test-to" class="form-input" style="max-width:280px;" placeholder="测试收件邮箱（留空则发往管理员邮箱）">
        <span class="form-hint" style="margin:0;">需先保存上方 SMTP 配置</span>
    </div>

    <!-- 6. 用户认证、注册邮箱白名单与第三方 OAuth 登录 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('person', 'fluent-icon-sm') ?>
        6. 用户注册邮箱白名单与第三方快捷登录 (Microsoft / Google / GitHub)
      </h3>
      
      <div class="form-group">
        <label class="form-label">注册邮箱白名单后缀 (以英文逗号分隔)</label>
        <textarea name="allowed_email_domains" class="form-textarea" style="min-height: 56px;" placeholder="默认已内置 QQ, 163, 126, Gmail, Outlook, Hotmail, iCloud, 教育邮箱等。可额外追加如：company.com, school.edu.cn"><?= View::e($settings['allowed_email_domains'] ?? '') ?></textarea>
        <div class="form-hint">为防范恶意/临时垃圾邮箱注册，系统仅允许上述白名单后缀。系统默认已内置主流邮箱及 .edu 教育邮箱，留空则完全使用默认内置白名单。</div>
      </div>

      <div style="margin-top: 18px; padding: 14px; background: var(--bg-hover); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
          <span>Microsoft 账号登录 (Azure AD / 个人微软账号)</span>
        </h4>
        <div class="form-grid-2">
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">应用程序 (客户端) ID (Client ID)</label>
            <input type="text" name="oauth_ms_client_id" class="form-input" placeholder="例如：xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?= View::e($settings['oauth_ms_client_id'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">客户端密码值 (Client Secret)</label>
            <input type="password" name="oauth_ms_client_secret" class="form-input" autocomplete="new-password" placeholder="<?= View::e($secretHint('oauth_ms_client_secret')) ?>">
          </div>
        </div>
        <div class="form-hint" style="margin-top: 4px;">回调重定向 URI: <code><?= View::e(OAuthService::getCallbackUrl('microsoft')) ?></code></div>
      </div>

      <div style="margin-top: 14px; padding: 14px; background: var(--bg-hover); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
          <span>Google 账号登录 (支持 Google 登录与一键弹窗登录)</span>
        </h4>
        <div class="form-grid-2">
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">Google 客户端 ID (Client ID)</label>
            <input type="text" name="oauth_google_client_id" class="form-input" placeholder="例如：xxxxxx.apps.googleusercontent.com" value="<?= View::e($settings['oauth_google_client_id'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">Google 客户端密钥 (Client Secret)</label>
            <input type="password" name="oauth_google_client_secret" class="form-input" autocomplete="new-password" placeholder="<?= View::e($secretHint('oauth_google_client_secret')) ?>">
          </div>
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">One Tap 自动弹窗</label>
            <select name="google_one_tap" class="form-select">
              <option value="1" <?= ($settings['google_one_tap'] ?? '1') !== '0' ? 'selected' : '' ?>>开启 (未登录访客进站在角落弹一次账号卡)</option>
              <option value="0" <?= ($settings['google_one_tap'] ?? '1') === '0' ? 'selected' : '' ?>>关闭 (只保留登录框里的「Google 登录」按钮)</option>
            </select>
          </div>
        </div>
        <div class="form-hint" style="margin-top: 4px;">回调重定向 URI: <code><?= View::e(OAuthService::getCallbackUrl('google')) ?></code> (配置 Client ID 后前端将同步激活 Google 一键快速登录)</div>
        <div class="form-hint" style="margin-top: 4px;">
          弹窗要在 Google Cloud 控制台把这个客户端的 <b>已授权的 JavaScript 来源</b> 加上
          <code><?= View::e(Url::siteBase()) ?></code> —— 它和「重定向 URI」是两处配置，只配后者按钮能用但 One Tap 不弹。
          浏览器挡第三方 Cookie 时 One Tap 也可能不显示；未弹的原因会打在浏览器控制台（<code>[One Tap] 未显示: …</code>）。
          同一浏览器会话只弹一次，访客点过关闭后 7 天内不再打扰。
        </div>
      </div>

      <div style="margin-top: 14px; padding: 14px; background: var(--bg-hover); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
          <span>GitHub 账号登录</span>
        </h4>
        <div class="form-grid-2">
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">GitHub Client ID</label>
            <input type="text" name="oauth_github_client_id" class="form-input" placeholder="例如：Iv1.xxxxxxxxxxxx" value="<?= View::e($settings['oauth_github_client_id'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin-bottom: 8px;">
            <label class="form-label">GitHub Client Secret</label>
            <input type="password" name="oauth_github_client_secret" class="form-input" autocomplete="new-password" placeholder="<?= View::e($secretHint('oauth_github_client_secret')) ?>">
          </div>
        </div>
        <div class="form-hint" style="margin-top: 4px;">Authorization callback URL: <code><?= View::e(OAuthService::getCallbackUrl('github')) ?></code></div>
      </div>
    </div>

    <!-- 7. 管理员账号与安全 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px;">
        7. 管理员账户安全
      </h3>
      <div class="form-group">
        <label class="form-label">管理员登录账号</label>
        <input type="text" name="admin_username" class="form-input" value="<?= View::e($settings['admin_username'] ?? 'admin') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">修改密码 (留空表示不修改)</label>
        <input type="password" name="new_password" class="form-input" placeholder="输入新密码">
      </div>
    </div>

    <!-- 8. 前台操作门槛与登录要求 -->
    <div style="margin-bottom: 28px; border-top: 1px solid var(--border-light); padding-top: 20px;">
      <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--primary); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
        <?= FluentIcon::render('shield-checkmark', 'fluent-icon-sm') ?>
        8. 前台操作门槛与登录限制
      </h3>
      <input type="hidden" name="has_access_settings" value="1">
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; cursor: pointer;">
          <input type="checkbox" name="require_login_comment" value="1" <?= ($settings['require_login_comment'] ?? '0') === '1' ? 'checked' : '' ?> style="accent-color: var(--primary);">
          <span>发表软件评价必须先登录账号</span>
        </label>
        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; cursor: pointer;">
          <input type="checkbox" name="require_login_submit_software" value="1" <?= ($settings['require_login_submit_software'] ?? '0') === '1' ? 'checked' : '' ?> style="accent-color: var(--primary);">
          <span>提交新软件收录必须先登录账号</span>
        </label>
        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; cursor: pointer;">
          <input type="checkbox" name="require_login_submit_resource" value="1" <?= ($settings['require_login_submit_resource'] ?? '0') === '1' ? 'checked' : '' ?> style="accent-color: var(--primary);">
          <span>提交软件相关资源（网盘、汉化包等）必须先登录账号</span>
        </label>
        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; cursor: pointer;">
          <input type="checkbox" name="require_login_correction" value="1" <?= ($settings['require_login_correction'] ?? '0') === '1' ? 'checked' : '' ?> style="accent-color: var(--primary);">
          <span>提交软件修改纠错/信息补充必须先登录账号</span>
        </label>
        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; cursor: pointer;">
          <input type="checkbox" name="require_login_report" value="1" <?= ($settings['require_login_report'] ?? '0') === '1' ? 'checked' : '' ?> style="accent-color: var(--primary);">
          <span>提交违规举报必须先登录账号</span>
        </label>
      </div>
    </div>

    <button type="submit" class="btn-store-primary" style="padding: 0 24px; height: 40px; font-size: 0.9375rem;">
      保存所有配置
    </button>
  </form>
</div>

<script>
  (function () {
    var btn = document.getElementById('smtp-test-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var to = document.getElementById('smtp-test-to');
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '<?= Url::route('admin/testMail') ?>';
      var fields = [
        ['csrf_token', <?= json_encode($csrfToken ?? '') ?>],
        ['test_mail_to', to ? to.value : '']
      ];
      fields.forEach(function (pair) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = pair[0];
        input.value = pair[1];
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    });
  })();

  // AI 多提供商配置与测试逻辑
  (function () {
    var container = document.getElementById('ai-providers-container');
    var addBtn = document.getElementById('btn-add-ai-provider');
    var jsonInput = document.getElementById('ai_providers_json');
    var form = document.querySelector('form[action*="admin/settings"]');
    var csrfToken = <?= json_encode($csrfToken ?? '') ?>;

    if (!container || !addBtn) return;

    function updateRanks() {
      var cards = container.querySelectorAll('.ai-provider-card');
      cards.forEach(function (card, index) {
        var badge = card.querySelector('.ai-badge-rank');
        if (badge) {
          if (index === 0) {
            badge.textContent = '主路线 #1';
            badge.style.background = 'var(--primary)';
            badge.style.color = '#fff';
          } else {
            badge.textContent = '备用路线 #' + (index + 1);
            badge.style.background = 'rgba(0,0,0,0.1)';
            badge.style.color = 'inherit';
          }
        }
      });
    }

    function bindCardEvents(card) {
      // 预设切换
      var presetSelect = card.querySelector('.ai-preset-selector');
      if (presetSelect) {
        presetSelect.addEventListener('change', function () {
          var opt = presetSelect.selectedOptions[0];
          if (!opt || !opt.value) return;
          var base = opt.getAttribute('data-base') || '';
          var model = opt.getAttribute('data-model') || '';
          var name = opt.getAttribute('data-name') || '';
          if (base) card.querySelector('.ai-field-base').value = base;
          if (model) card.querySelector('.ai-field-model').value = model;
          if (name) card.querySelector('.ai-field-name').value = name;
        });
      }

      // 密码显示/隐藏切换
      var toggleBtn = card.querySelector('.ai-btn-toggle-key');
      var keyInput = card.querySelector('.ai-field-key');
      if (toggleBtn && keyInput) {
        toggleBtn.addEventListener('click', function () {
          if (keyInput.type === 'password') {
            keyInput.type = 'text';
            toggleBtn.textContent = '隐藏';
          } else {
            keyInput.type = 'password';
            toggleBtn.textContent = '显示';
          }
        });
      }

      // 上移
      var upBtn = card.querySelector('.ai-btn-move-up');
      if (upBtn) {
        upBtn.addEventListener('click', function () {
          var prev = card.previousElementSibling;
          if (prev) {
            container.insertBefore(card, prev);
            updateRanks();
          }
        });
      }

      // 下移
      var downBtn = card.querySelector('.ai-btn-move-down');
      if (downBtn) {
        downBtn.addEventListener('click', function () {
          var next = card.nextElementSibling;
          if (next) {
            container.insertBefore(next, card);
            updateRanks();
          }
        });
      }

      // 删除
      var delBtn = card.querySelector('.ai-btn-delete');
      if (delBtn) {
        delBtn.addEventListener('click', function () {
          var total = container.querySelectorAll('.ai-provider-card').length;
          if (total <= 1) {
            alert('至少保留一条 AI 提供商配置');
            return;
          }
          if (confirm('确定要删除此 AI 提供商线路吗？')) {
            card.remove();
            updateRanks();
          }
        });
      }

      // 测试连接
      var testBtn = card.querySelector('.ai-btn-test');
      var resultSpan = card.querySelector('.ai-test-result');
      if (testBtn && resultSpan) {
        testBtn.addEventListener('click', function () {
          var base = (card.querySelector('.ai-field-base').value || '').trim();
          var key = (card.querySelector('.ai-field-key').value || '').trim();
          var model = (card.querySelector('.ai-field-model').value || '').trim();

          if (!base) {
            resultSpan.style.color = '#dc2626';
            resultSpan.textContent = '请先填写 API 接口基址';
            return;
          }
          if (!key) {
            resultSpan.style.color = '#dc2626';
            resultSpan.textContent = '请先填写 API Key';
            return;
          }
          if (!model) {
            resultSpan.style.color = '#dc2626';
            resultSpan.textContent = '请先填写模型名称';
            return;
          }

          testBtn.disabled = true;
          resultSpan.style.color = 'var(--text-secondary)';
          resultSpan.textContent = '正在测试连接...';

          var formData = new FormData();
          formData.append('csrf_token', csrfToken);
          formData.append('api_base', base);
          formData.append('api_key', key);
          formData.append('model', model);

          fetch('<?= Url::route('admin/test-ai-provider') ?>', {
            method: 'POST',
            body: formData
          })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            testBtn.disabled = false;
            if (data && data.success) {
              resultSpan.style.color = '#16a34a';
              resultSpan.textContent = '✓ ' + (data.message || '连接成功');
            } else {
              resultSpan.style.color = '#dc2626';
              resultSpan.textContent = '✕ ' + (data.message || '连接失败');
            }
          })
          .catch(function (err) {
            testBtn.disabled = false;
            resultSpan.style.color = '#dc2626';
            resultSpan.textContent = '✕ 网络请求异常: ' + err.message;
          });
        });
      }
    }

    // 初始化现有卡片事件
    container.querySelectorAll('.ai-provider-card').forEach(bindCardEvents);
    updateRanks();

    // 点击添加新卡片
    addBtn.addEventListener('click', function () {
      var currentCards = container.querySelectorAll('.ai-provider-card');
      var templateCard = currentCards[currentCards.length - 1];
      var newCard = templateCard.cloneNode(true);

      // 清空输入值
      var newIndex = currentCards.length + 1;
      newCard.querySelector('.ai-field-name').value = '备用路线 #' + newIndex;
      newCard.querySelector('.ai-field-base').value = 'https://dashscope.aliyuncs.com/compatible-mode/v1';
      newCard.querySelector('.ai-field-model').value = 'qwen-plus';
      newCard.querySelector('.ai-field-key').value = '';
      newCard.querySelector('.ai-field-key').type = 'password';
      newCard.querySelector('.ai-btn-toggle-key').textContent = '显示';
      newCard.querySelector('.ai-field-enabled').checked = true;
      newCard.querySelector('.ai-preset-selector').value = 'qwen';
      var res = newCard.querySelector('.ai-test-result');
      if (res) res.textContent = '';

      container.appendChild(newCard);
      bindCardEvents(newCard);
      updateRanks();
    });

    // 表单提交前，序列化卡片数据到 JSON 隐藏域及兼容隐藏域
    if (form) {
      form.addEventListener('submit', function () {
        var cards = container.querySelectorAll('.ai-provider-card');
        var list = [];
        cards.forEach(function (card) {
          var name = (card.querySelector('.ai-field-name').value || '').trim();
          var base = (card.querySelector('.ai-field-base').value || '').trim();
          var model = (card.querySelector('.ai-field-model').value || '').trim();
          var key = (card.querySelector('.ai-field-key').value || '').trim();
          var enabled = card.querySelector('.ai-field-enabled').checked ? '1' : '0';

          if (name || base || key) {
            list.push({
              name: name || 'AI 提供商',
              api_base: base || 'https://api.openai.com/v1',
              api_key: key,
              model: model || 'gpt-4o-mini',
              enabled: enabled
            });
          }
        });

        if (jsonInput) {
          jsonInput.value = JSON.stringify(list);
        }

        if (list.length > 0) {
          var first = list[0];
          var legName = document.getElementById('legacy_ai_provider');
          var legBase = document.getElementById('legacy_ai_api_base');
          var legKey = document.getElementById('legacy_ai_api_key');
          var legModel = document.getElementById('legacy_ai_model');
          if (legName) legName.value = first.name;
          if (legBase) legBase.value = first.api_base;
          if (legKey) legKey.value = first.api_key;
          if (legModel) legModel.value = first.model;
        }
      });
    }
  })();
</script>

<?php require VIEW_PATH . '/admin/layout_footer.php'; ?>
