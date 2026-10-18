/**
 * 蓝屏钙软件导航网 - GitHub 镜像节点自动测速与直链生成器
 * 遵循极简高能规范，杜绝冗余重复前缀
 */

(function () {
  'use strict';

  function buildMirrorUrl(template, originalUrl) {
    if (!originalUrl) return '';
    template = (template || '').trim();
    if (!template || template === 'direct' || template === 'https://github.com/' || template === 'https://github.com') {
      return originalUrl;
    }
    if (!/^https?:\/\//i.test(originalUrl)) {
      originalUrl = 'https://github.com/' + originalUrl.replace(/^\//, '');
    }
    // 彻底清除重复前缀
    while (/^https?:\/\/github\.com\/https?:\/\//i.test(originalUrl)) {
      originalUrl = originalUrl.replace(/^https?:\/\/github\.com\/(https?:\/\/)/i, '$1');
    }
    if (template.includes('boki.moe') || template.includes('moeyy.xyz') || template.includes('fastgit')) {
      var cleanTpl = template.replace(/\/$/, '');
      return originalUrl.replace(/^https?:\/\/github\.com/i, cleanTpl);
    }
    var cleanTpl = template.replace(/\/$/, '');
    return cleanTpl + '/' + originalUrl;
  }

  window.buildMirrorUrl = buildMirrorUrl;

  window.initGithubMirrorSpeedTest = function () {
    const mirrorContainer = document.getElementById('mirror-speed-container');
    const releaseBox = document.getElementById('github-release-card');
    if (!mirrorContainer) return;

    const softwareId = mirrorContainer.getAttribute('data-software-id');
    const githubRepo = mirrorContainer.getAttribute('data-github-repo');
    const mirrorItems = Array.from(mirrorContainer.querySelectorAll('.mirror-item'));

    function testMirror(item) {
      const pingEl = item.querySelector('.ping-tag');
      const template = item.getAttribute('data-template');
      if (!pingEl) return Promise.resolve({ item, time: 9999 });

      pingEl.textContent = '测速中';
      pingEl.className = 'ping-tag';

      let probeUrl = '';
      if (template === 'direct' || template === 'https://github.com/' || template === 'https://github.com') {
        probeUrl = 'https://github.com/favicon.ico?_t=' + Date.now();
      } else if (template.includes('boki.moe') || template.includes('moeyy.xyz')) {
        probeUrl = template.replace(/\/$/, '') + '/favicon.ico?_t=' + Date.now();
      } else {
        probeUrl = template.replace(/\/$/, '') + '/https://github.com/favicon.ico?_t=' + Date.now();
      }

      const start = performance.now();
      return new Promise((resolve) => {
        const img = new Image();
        let done = false;

        const timer = setTimeout(() => {
          if (!done) {
            done = true;
            pingEl.textContent = '超时';
            pingEl.className = 'ping-tag ping-red';
            resolve({ item, time: 9999 });
          }
        }, 3200);

        img.onload = function () {
          if (!done) {
            done = true;
            clearTimeout(timer);
            const elapsed = Math.round(performance.now() - start);
            pingEl.textContent = elapsed + 'ms';
            pingEl.className = 'ping-tag ' + (elapsed < 160 ? 'ping-green' : (elapsed < 400 ? 'ping-yellow' : 'ping-red'));
            resolve({ item, time: elapsed });
          }
        };

        img.onerror = function () {
          if (!done) {
            done = true;
            clearTimeout(timer);
            const elapsed = Math.round(performance.now() - start);
            pingEl.textContent = elapsed + 'ms';
            pingEl.className = 'ping-tag ' + (elapsed < 250 ? 'ping-green' : 'ping-yellow');
            resolve({ item, time: elapsed });
          }
        };

        img.src = probeUrl;
      });
    }

    // 并发测试并排序
    if (mirrorItems.length > 0) {
      Promise.all(mirrorItems.map(testMirror)).then(results => {
        results.sort((a, b) => a.time - b.time);
        const parent = mirrorItems[0].parentNode;
        results.forEach(res => {
          parent.appendChild(res.item);
        });
      });
    }

    // 异步加载最新 Release x64 直链
    if (releaseBox && softwareId && !releaseBox.dataset.loaded) {
      releaseBox.dataset.loaded = 'true';
      fetch('?r=api/release&id=' + softwareId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.release) {
              const rel = data.release;
            let currentDl = rel.download_url;
            const cats = rel.categories || {};
            const allAssets = rel.all_assets || [];

            function updateMirrorDownloadUrls(targetUrl) {
              currentDl = targetUrl;
              mirrorItems.forEach(item => {
                const tpl = item.getAttribute('data-template');
                const linkBtn = item.querySelector('.mirror-link-btn');
                if (linkBtn && targetUrl) {
                  linkBtn.href = buildMirrorUrl(tpl, targetUrl);
                  linkBtn.target = '_blank';
                }
              });
            }

            // 初始应用默认首选直链
            if (currentDl) updateMirrorDownloadUrls(currentDl);

            const catLabels = {
              x64: 'x64 (64位 Windows 安装包)',
              x86: 'x86 (32位 Windows 安装包)',
              arm64: 'ARM64 (ARM 架构安装包)',
              portable: '便携版 / 免安装压缩包'
            };

            const hasCategorized = (cats.x64 && cats.x64.length) || 
                                   (cats.x86 && cats.x86.length) || 
                                   (cats.arm64 && cats.arm64.length) || 
                                   (cats.portable && cats.portable.length);

            let html = `
              <div style="background: var(--bg-surface-secondary); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); margin-bottom: 12px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                  <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                    <svg class="fluent-icon fluent-icon-sm" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm3.78 6.28-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06L8.75 11.19l3.97-3.97a.75.75 0 1 1 1.06 1.06Z"/></svg>
                    <span>最新 Release: <code>${escapeHtml(rel.tag)}</code></span>
                  </div>
                  <span style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(rel.published_at || '')}</span>
                </div>
            `;

            function renderAssetRow(asset, isPrimary) {
              return `
                <div class="release-asset-item ${isPrimary ? 'selected-asset' : ''}" style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 8px 10px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-xs); margin-bottom: 6px;">
                  <div style="min-width: 0; flex: 1;">
                    <div style="font-weight: 600; font-size: 0.8125rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(asset.name)}">${escapeHtml(asset.name)}</div>
                    <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 2px;">${escapeHtml(asset.size_formatted)} · ${asset.download_count || 0} 次下载</div>
                  </div>
                  <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                    <button type="button" class="btn-card-action set-mirror-target-btn" data-url="${escapeHtml(asset.download_url)}" title="以此文件为目标进行下方测速加速">选为此项加速</button>
                    <a href="${escapeHtml(asset.download_url)}" target="_blank" rel="noopener noreferrer" class="btn-store-pill" style="height: 28px; padding: 0 10px; font-size: 0.75rem;">直下</a>
                  </div>
                </div>
              `;
            }

            if (hasCategorized) {
              // 分类展示 x64, x86, arm64, portable
              for (const [key, label] of Object.entries(catLabels)) {
                const list = cats[key];
                if (list && list.length > 0) {
                  html += `<div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin: 8px 0 4px; text-transform: uppercase;">${label}</div>`;
                  list.forEach(item => {
                    html += renderAssetRow(item, item.download_url === currentDl);
                  });
                }
              }

              // 折叠展开全部
              if (allAssets.length > 0) {
                html += `
                  <button type="button" id="btn-toggle-all-release-assets" class="btn-store-secondary" style="width: 100%; margin-top: 8px; height: 32px; font-size: 0.75rem; justify-content: center;">
                    查看 / 展开全部 Release 文件 (共 ${rel.total_assets} 个) ▼
                  </button>
                  <div id="all-release-assets-container" style="display: none; margin-top: 8px; max-height: 200px; overflow-y: auto; padding-right: 4px;">
                `;
                allAssets.forEach(item => {
                  html += renderAssetRow(item, item.download_url === currentDl);
                });
                html += `</div>`;
              }
            } else {
              // 没有分类时直接展示全部
              html += `<div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin: 8px 0 4px;">全部 Release 文件 (${allAssets.length})</div>`;
              allAssets.forEach(item => {
                html += renderAssetRow(item, item.download_url === currentDl);
              });
            }

            html += `</div>`;
            releaseBox.innerHTML = html;

            // 绑定选为此项加速和折叠事件
            const toggleAllBtn = document.getElementById('btn-toggle-all-release-assets');
            const allContainer = document.getElementById('all-release-assets-container');
            if (toggleAllBtn && allContainer) {
              toggleAllBtn.addEventListener('click', function () {
                const isHidden = allContainer.style.display === 'none';
                allContainer.style.display = isHidden ? 'block' : 'none';
                toggleAllBtn.textContent = isHidden ? '收起全部 Release 文件 ▲' : `查看 / 展开全部 Release 文件 (共 ${rel.total_assets} 个) ▼`;
              });
            }

            releaseBox.querySelectorAll('.set-mirror-target-btn').forEach(btn => {
              btn.addEventListener('click', function () {
                const url = this.getAttribute('data-url');
                if (url) {
                  updateMirrorDownloadUrls(url);
                  releaseBox.querySelectorAll('.release-asset-item').forEach(el => el.classList.remove('selected-asset'));
                  this.closest('.release-asset-item').classList.add('selected-asset');
                  const oldText = this.textContent;
                  this.textContent = '已选择 ✓';
                  setTimeout(() => { this.textContent = oldText; }, 1500);
                }
              });
            });
          } else {
            releaseBox.innerHTML = `
              <div style="font-size: 0.8125rem; color: var(--text-muted); padding: 8px 12px; background: var(--bg-muted); border-radius: var(--radius-sm);">
                ${escapeHtml(data.message || '未获取到直链，可访问官方仓库')}
              </div>
            `;
          }
        })
        .catch(() => {
          releaseBox.innerHTML = '<div style="font-size: 0.8125rem; color: var(--text-muted);">获取直链超时</div>';
        });
    }
  };

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // 页面加载或弹窗呼出时触发
  document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.getElementById('github-mirror-btn');
    if (trigger) {
      trigger.addEventListener('click', function () {
        openModal('github-mirror-modal');
        window.initGithubMirrorSpeedTest();
      });
    }
  });

})();

