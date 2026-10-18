/**
 * 蓝屏钙软件导航网 - Fluent 2 前端核心交互
 * 支持 SPA/PJAX 无刷新极速平滑导航、侧边栏折叠记忆、图标动态主色采样与 Fluent 微动效
 */

(function () {
  'use strict';

  // =========================================================================
  // 1. 深色模式管理
  // =========================================================================
  function initTheme() {
    const saved = localStorage.getItem('theme');
    if (saved) {
      document.documentElement.setAttribute('data-theme', saved);
    }
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn && !themeToggleBtn._bound) {
      themeToggleBtn._bound = true;
      themeToggleBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        // 如果详情页存在采样层，根据深浅色刷新渐变
        applyHeroIconSampleGradient();
        applyCardIconSample();
      });
    }
  }

  // =========================================================================
  // 2. 侧边栏折叠/收起控制与状态持久化 (支持顶栏按钮，彻底杜绝刷新动画闪烁)
  // =========================================================================
  function initSidebar() {
    const sidebar = document.getElementById('main-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    const mobileMq = window.matchMedia('(max-width: 768px)');

    // 还原本地偏好（仅桌面折叠；移动端侧边栏是抽屉，折叠态无意义）
    const isCollapsed = !mobileMq.matches && localStorage.getItem('fluent_sidebar_collapsed') === '1';
    if (sidebar) {
      sidebar.classList.toggle('collapsed', isCollapsed);
    }
    // PJAX 切页后回收抽屉展开态
    document.body.classList.remove('sidebar-open');

    // 侧边栏始终稳定固定在屏幕左侧，严丝合缝对齐顶栏下沿，不随页面滚动发生任何抖动或位移
    function alignSidebar() {
      const curSidebar = document.getElementById('main-sidebar');
      if (curSidebar) {
        curSidebar.style.top = '62px';
        curSidebar.style.height = 'calc(100vh - 62px)';
      }
    }
    alignSidebar();

    // 首次绘制后移除禁止动画的锁类，让用户点击折叠时保持自然过渡
    requestAnimationFrame(() => {
      setTimeout(() => {
        document.documentElement.classList.remove('sidebar-init-collapsed');
      }, 60);
    });

    if (toggleBtn) {
      toggleBtn.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();
        // 移动端：汉堡按钮开关抽屉，不影响桌面折叠偏好
        if (mobileMq.matches) {
          document.body.classList.toggle('sidebar-open');
          return;
        }
        const curSidebar = document.getElementById('main-sidebar');
        let nowCollapsed = false;
        if (curSidebar) {
          curSidebar.classList.toggle('collapsed');
          nowCollapsed = curSidebar.classList.contains('collapsed');
        } else {
          nowCollapsed = localStorage.getItem('fluent_sidebar_collapsed') !== '1';
        }
        localStorage.setItem('fluent_sidebar_collapsed', nowCollapsed ? '1' : '0');
        document.cookie = "fluent_sidebar_collapsed=" + (nowCollapsed ? '1' : '0') + ";path=/;max-age=" + (86400 * 365);
      };
    }

    // 抽屉交互（委托到 body，兼容 PJAX 重建的 DOM）：遮罩点击或选中分类后收起
    document.body.addEventListener('click', function (e) {
      if (!mobileMq.matches || !document.body.classList.contains('sidebar-open')) return;
      if ((e.target.closest && e.target.closest('#sidebar-scrim'))
        || (e.target.closest && e.target.closest('#main-sidebar a'))) {
        document.body.classList.remove('sidebar-open');
      }
    });

    // 跨过断点切换到桌面时，回收抽屉展开态
    if (mobileMq.addEventListener) {
      mobileMq.addEventListener('change', (ev) => {
        if (!ev.matches) document.body.classList.remove('sidebar-open');
      });
    }
  }

  // =========================================================================
  // 3. 软件详情页：图标主色采样作为网页背景，向下滑动回归原本背景色
  //    (由深变浅，最上面的颜色浅一点，滑到下面显示本来的背景色)
  // =========================================================================
  // =========================================================================
  // 3. 软件详情页：网页背景氛围流光 (与卡片背景严格共享唯一权威色彩来源)
  // =========================================================================
  function applyHeroIconSampleGradient() {
    const bgTarget = document.getElementById('detail-page-gradient-bg') || document.getElementById('hero-gradient-overlay');
    if (!bgTarget) return;

    // 优先采用服务端直出的权威取色 (保持主页卡片与详情页 100% 绝对一致)
    const storeLayout = document.querySelector('.ms-store-layout');
    const serverRgb = storeLayout?.style.getPropertyValue('--hero-sample-rgb') || bgTarget.style.getPropertyValue('--hero-sample-rgb');
    
    if (serverRgb && serverRgb.trim()) {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      const cleanRgb = serverRgb.trim();
      const alphaTop = isDark ? 0.22 : 0.28;
      const alphaMid = isDark ? 0.12 : 0.14;
      const alphaBot = isDark ? 0.04 : 0.04;
      bgTarget.style.background = `linear-gradient(180deg, rgba(${cleanRgb}, ${alphaTop}) 0%, rgba(${cleanRgb}, ${alphaMid}) 180px, rgba(${cleanRgb}, ${alphaBot}) 420px, var(--bg-page) 580px)`;
      bgTarget.style.opacity = '1';
      return;
    }

    // 降级：仅在无服务端色彩时，安全默认微软经典蓝 (绝不回退至紫色)
    bgTarget.style.background = `linear-gradient(180deg, rgba(0, 120, 212, 0.25) 0%, rgba(0, 120, 212, 0.12) 180px, rgba(0, 120, 212, 0.04) 420px, var(--bg-page) 580px)`;
    bgTarget.style.opacity = '1';
  }

  // =========================================================================
  // 3.1 首页与搜索结果卡片：图标取色渐变背景 (严格契合 Microsoft Store 风格)
  // =========================================================================
  function applyCardIconSample() {
    // 氛围色由服务端按 theme_color 直接算好输出，前端不再二次随机或改写
    const cards = document.querySelectorAll('.software-card');
    cards.forEach(card => {
      const banner = card.querySelector('.card-banner-wrapper');
      if (!banner) return;
      if (banner.style.background) return; // 保持服务端的权威色彩
      const themeRgb = card.getAttribute('data-theme-rgb');
      if (themeRgb) {
        banner.style.background = `linear-gradient(135deg, rgba(${themeRgb}, 0.12) 0%, rgba(${themeRgb}, 0.28) 100%)`;
      }
    });
  }

  // =========================================================================
  // 3.2 软件图标真实取色：同源代理 + canvas 像素采样，主色实时生效并回存数据库
  //     （外部图标直接绘制到 canvas 会因跨域污染无法读像素，统一经 iconProxy 取流）
  // =========================================================================
  const ICON_COLOR_PERSIST_DELTA = 22;

  function parseRgbAttr(str) {
    const m = /(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/.exec(str || '');
    return m ? [+m[1], +m[2], +m[3]] : null;
  }

  function rgbToHex(rgb) {
    return '#' + rgb.map(v => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('');
  }

  function colorDistance(a, b) {
    return Math.sqrt((a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2 + (a[2] - b[2]) ** 2);
  }

  async function sampleIconDominantColor(src) {
    if (!src || src.startsWith('data:') || !window.createImageBitmap) return null;
    try {
      const resp = await fetch('?r=api/iconProxy&url=' + encodeURIComponent(src), { cache: 'force-cache' });
      if (!resp.ok) return null;
      const blob = await resp.blob();
      const bmp = await createImageBitmap(blob);
      const size = 32;
      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(bmp, 0, 0, size, size);
      if (bmp.close) bmp.close();
      const data = ctx.getImageData(0, 0, size, size).data;

      const buckets = new Map();
      for (let i = 0; i < data.length; i += 4) {
        if (data[i + 3] < 200) continue;
        const r = data[i], g = data[i + 1], b = data[i + 2];
        const lum = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
        if (lum > 0.96 || lum < 0.04) continue; // 排除边缘纯白纯黑
        const max = Math.max(r, g, b), min = Math.min(r, g, b);
        const sat = max === min ? 0 : (max - min) / max;
        const key = ((r >> 4) << 8) | ((g >> 4) << 4) | (b >> 4);
        const bucket = buckets.get(key) || { count: 0, r: 0, g: 0, b: 0, satSum: 0 };
        bucket.count++; bucket.r += r; bucket.g += g; bucket.b += b; bucket.satSum += sat;
        buckets.set(key, bucket);
      }
      if (!buckets.size) return null;

      let best = null, bestScore = -1;
      buckets.forEach(bucket => {
        // 得分 = 数量 × 饱和度权重：彩色桶优先，灰白桶仅在无彩色可选时胜出
        const score = bucket.count * (0.15 + (bucket.satSum / bucket.count) * 1.6);
        if (score > bestScore) { bestScore = score; best = bucket; }
      });

      let r = best.r / best.count, g = best.g / best.count, b = best.b / best.count;
      const lum = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
      // 黑白灰图标（如 7-Zip）：把极端明度轻推至适中区间，避免氛围层脏黑或惨白
      if (lum < 0.18) {
        r = r * 0.35 + 88; g = g * 0.35 + 96; b = b * 0.35 + 116;
      } else if (lum > 0.88) {
        r = r * 0.72 + 12; g = g * 0.72 + 16; b = b * 0.72 + 24;
      }
      return [Math.round(r), Math.round(g), Math.round(b)];
    } catch (e) {
      return null;
    }
  }

  function applySampledIconColor(target) {
    sampleIconDominantColor(target.imgSrc).then(rgb => {
      if (!rgb) return;
      target.apply(rgb);
      // 与库中记录差异明显时回存，库值随即收敛为图标真实主色
      if (target.slug && (!target.storedRgb || colorDistance(rgb, target.storedRgb) > ICON_COLOR_PERSIST_DELTA)) {
        const body = new URLSearchParams({ slug: target.slug, color: rgbToHex(rgb) });
        fetch('?r=api/saveIconColor', { method: 'POST', body: body }).catch(() => {});
      }
    });
  }

  function initIconColorSampling() {
    // 详情页：hero 图标采样，驱动整页氛围渐变
    const hero = document.querySelector('.ms-store-layout');
    if (hero) {
      const icon = document.getElementById('ms-hero-icon');
      const slug = hero.getAttribute('data-slug');
      if (icon && slug && !icon._colorSampled) {
        icon._colorSampled = true;
        applySampledIconColor({
          imgSrc: icon.currentSrc || icon.getAttribute('src'),
          slug,
          storedRgb: parseRgbAttr(hero.style.getPropertyValue('--hero-sample-rgb')),
          apply: (rgb) => {
            hero.style.setProperty('--hero-sample-rgb', rgb.join(', '));
            applyHeroIconSampleGradient();
          }
        });
      }
    }

    // 首页/搜索结果卡片
    document.querySelectorAll('.software-card:not([data-color-sampled])').forEach(card => {
      card.setAttribute('data-color-sampled', '1');
      const banner = card.querySelector('.card-banner-wrapper');
      const img = card.querySelector('.card-large-icon');
      if (!banner || !img) return;
      applySampledIconColor({
        imgSrc: img.currentSrc || img.getAttribute('src'),
        slug: card.getAttribute('data-slug') || '',
        storedRgb: parseRgbAttr(card.getAttribute('data-theme-rgb')),
        apply: (rgb) => {
          const rgbStr = rgb.join(', ');
          banner.style.setProperty('--card-theme-rgb', rgbStr);
          banner.style.background = `linear-gradient(135deg, rgba(${rgbStr}, 0.12) 0%, rgba(${rgbStr}, 0.28) 100%)`;
        }
      });
    });
  }

  // =========================================================================
  // 4. 极速 SPA / PJAX 路由器 (单页无刷新无闪烁流畅导航)
  // =========================================================================
  const progressBar = document.getElementById('fluent-progress-bar');
  let progressTimer = null;

  function startProgress() {
    if (!progressBar) return;
    clearTimeout(progressTimer);
    progressBar.style.opacity = '1';
    progressBar.style.width = '25%';
    progressTimer = setTimeout(() => {
      progressBar.style.width = '75%';
    }, 150);
  }

  function finishProgress() {
    if (!progressBar) return;
    clearTimeout(progressTimer);
    progressBar.style.width = '100%';
    setTimeout(() => {
      progressBar.style.opacity = '0';
      setTimeout(() => {
        progressBar.style.width = '0%';
      }, 200);
    }, 180);
  }

  // =========================================================================
  // 5. 核心转场体系：0ms 极速响应、与加载速度自然并行的物理 FLIP 飞行无缝转场
  //    (严格遵循 motion-web affordance.md §10 零误差交接原则与 handfeel.md 触感规范)
  // =========================================================================
  // 页面处于后台/不可见时 Web Animations 永不推进，finished 会永久挂起，
  // 必须用超时兜底，否则 _isMorphing 卡死导致所有卡片点击失效
  function withTimeout(promise, ms) {
    let timer = null;
    const timeout = new Promise(resolve => { timer = setTimeout(() => resolve('timeout'), ms); });
    return Promise.race([promise, timeout]).then(result => { clearTimeout(timer); return result; });
  }

  async function morphCardToDetail(card, href) {
    if (window._isMorphing) return;
    const iconEl = card.querySelector('.card-large-icon');
    const titleEl = card.querySelector('.card-store-name');
    const bannerEl = card.querySelector('.card-banner-wrapper');
    const rootContainer = document.getElementById('fluent-app-root');

    if (!iconEl || !titleEl || !rootContainer || !window.Animation) {
      loadPagePjax(href, true);
      return;
    }

    window._isMorphing = true;

    // 1. 【立即在 t = 0ms 测量卡片起点坐标与色彩】(affordance.md §10: 首帧严丝合缝)
    const firstCardRect = card.getBoundingClientRect();
    const firstIconRect = iconEl.getBoundingClientRect();
    const firstTitleRect = titleEl.getBoundingClientRect();
    const iconSrc = iconEl.currentSrc || iconEl.getAttribute('src');
    const titleText = titleEl.textContent.trim();
    const themeRgb = card.getAttribute('data-theme-rgb') || '0, 103, 184';
    const bannerBg = bannerEl ? (bannerEl.style.background || window.getComputedStyle(bannerEl).background) : '';

    // 2. 【触感响应与加载条立即启动】(handfeel.md: 0ms 反馈，告别点击停顿)
    const grid = card.closest('.software-grid');
    if (grid) grid.classList.add('is-launching');
    card.classList.add('card-launching');
    startProgress();

    // 3. 【精确预估详情页落地几何坐标】(基于 Microsoft Store 权威设计规范)
    // 必须使用 documentElement.clientWidth 扣除滚动条，确保与 margin: 0 auto 100% 吻合
    const clientW = document.documentElement.clientWidth;
    const isMobile = clientW <= 768;
    const layoutW = isMobile ? clientW : Math.min(clientW, 1208.8);
    const layoutLeft = isMobile ? 0 : Math.max(0, (clientW - layoutW) / 2);

    // 与 CSS 保持同步：桌面 hero 预留 156px / 图标 104px；移动端 hero 预留 18px / 图标 72px 且标题与图标同行
    const targetIconTop = 62 + (isMobile ? 18 : 156); // 详情页 Navbar 62px + hero 预留高度
    const targetIconLeft = isMobile ? 16 : layoutLeft;
    const targetIconSize = isMobile ? 72 : 104;
    const targetTitleSize = isMobile ? '1.375rem' : '2.125rem';

    const targetTitleTop = isMobile ? (targetIconTop + 2) : targetIconTop;
    const targetTitleLeft = isMobile ? (16 + targetIconSize + 14) : (targetIconLeft + targetIconSize + 22);
    const targetTitleWidth = isMobile
      ? Math.max(120, layoutW - 16 - targetIconSize - 14 - 16)
      : Math.max(260, layoutW - (targetIconSize + 40));

    // 4. 【创建顶层飞行传送门 Portal】
    let portal = document.getElementById('fluent-morph-portal');
    if (!portal) {
      portal = document.createElement('div');
      portal.id = 'fluent-morph-portal';
      portal.className = 'fluent-morph-portal';
      portal.style.zIndex = '99999';
      document.body.appendChild(portal);
    }
    portal.innerHTML = '';

    // A. 飞行背景代理：继承卡片权威色彩，在飞行中向顶部氛围层自然平滑扩展
    const flyingBg = document.createElement('div');
    flyingBg.className = 'flying-bg-proxy';
    flyingBg.style.left = `${firstCardRect.left}px`;
    flyingBg.style.top = `${firstCardRect.top}px`;
    flyingBg.style.width = `${firstCardRect.width}px`;
    flyingBg.style.height = `${firstCardRect.height}px`;
    flyingBg.style.borderRadius = '12px';
    flyingBg.style.background = bannerBg || `linear-gradient(135deg, rgba(${themeRgb}, 0.12) 0%, rgba(${themeRgb}, 0.28) 100%)`;
    flyingBg.style.opacity = '0.95';
    portal.appendChild(flyingBg);

    // B. 飞行图标代理：首帧严格对齐卡片图标尺寸与位置 (零误差)
    const flyingIcon = document.createElement('img');
    flyingIcon.className = 'flying-icon-proxy';
    flyingIcon.src = iconSrc;
    flyingIcon.style.left = `${firstIconRect.left}px`;
    flyingIcon.style.top = `${firstIconRect.top}px`;
    flyingIcon.style.width = `${firstIconRect.width}px`;
    flyingIcon.style.height = `${firstIconRect.height}px`;
    flyingIcon.style.padding = '4px';
    flyingIcon.style.borderRadius = '16px';
    flyingIcon.style.boxShadow = '0 6px 16px rgba(0, 0, 0, 0.08)';
    flyingIcon.onerror = function() {
      this.onerror = null;
      this.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" rx="10" fill="%230067b8"/><path d="M14 16h20v14H14z" fill="%23ffffff"/><circle cx="24" cy="23" r="4" fill="%2360cdff"/></svg>';
    };
    portal.appendChild(flyingIcon);

    // C. 飞行标题代理：首帧严格对齐卡片文字尺寸与字重
    const flyingTitle = document.createElement('div');
    flyingTitle.className = 'flying-title-proxy';
    flyingTitle.textContent = titleText;
    flyingTitle.style.left = `${firstTitleRect.left}px`;
    flyingTitle.style.top = `${firstTitleRect.top}px`;
    flyingTitle.style.width = `${firstTitleRect.width}px`;
    flyingTitle.style.fontSize = '0.9375rem';
    flyingTitle.style.fontWeight = '600';
    flyingTitle.style.letterSpacing = '0px';
    portal.appendChild(flyingTitle);

    // 隐匿被点击卡片内部的图标与文字，消除重影
    iconEl.style.visibility = 'hidden';
    titleEl.style.visibility = 'hidden';

    // 5. 【立即在 t = 0ms 启动多轨飞行运镜】(不等待网络，流畅起飞)
    const animDuration = 360; // motion-tokens.md 标准持续时间
    const animEasing = 'cubic-bezier(0.16, 1, 0.3, 1)'; // 微软 Fluent / ease-out-expo

    const animIcon = flyingIcon.animate([
      {
        left: `${firstIconRect.left}px`,
        top: `${firstIconRect.top}px`,
        width: `${firstIconRect.width}px`,
        height: `${firstIconRect.height}px`,
        padding: '4px',
        borderRadius: '16px',
        boxShadow: '0 6px 16px rgba(0, 0, 0, 0.08)'
      },
      {
        left: `${targetIconLeft}px`,
        top: `${targetIconTop}px`,
        width: `${targetIconSize}px`,
        height: `${targetIconSize}px`,
        padding: '8px',
        borderRadius: '16px',
        boxShadow: '0 10px 24px -4px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04)'
      }
    ], {
      duration: animDuration,
      easing: animEasing,
      fill: 'forwards'
    });

    const animTitle = flyingTitle.animate([
      {
        left: `${firstTitleRect.left}px`,
        top: `${firstTitleRect.top}px`,
        width: `${firstTitleRect.width}px`,
        fontSize: '0.9375rem',
        fontWeight: '600',
        letterSpacing: '0px'
      },
      {
        left: `${targetTitleLeft}px`,
        top: `${targetTitleTop}px`,
        width: `${targetTitleWidth}px`,
        fontSize: targetTitleSize,
        fontWeight: '800',
        letterSpacing: '-0.02em'
      }
    ], {
      duration: animDuration,
      easing: animEasing,
      fill: 'forwards'
    });

    const animBg = flyingBg.animate([
      {
        left: `${firstCardRect.left}px`,
        top: `${firstCardRect.top}px`,
        width: `${firstCardRect.width}px`,
        height: `${firstCardRect.height}px`,
        borderRadius: '12px',
        opacity: 0.95
      },
      {
        left: '0px',
        top: '0px',
        width: '100vw',
        height: isMobile ? '460px' : '620px',
        borderRadius: '0px',
        opacity: 0
      }
    ], {
      duration: animDuration,
      easing: animEasing,
      fill: 'forwards'
    });

    // 旧主视图平滑淡出 (160ms，绝不应用 scale 变形以防破坏坐标基准)
    const animOld = rootContainer.animate([
      { opacity: 1 },
      { opacity: 0 }
    ], {
      duration: 160,
      easing: 'ease-out',
      fill: 'forwards'
    });

    // 6. 【并行网络请求：动画与加载速度深度协同】
    // 若请求极快(通常10-30ms)，飞行平滑执行至360ms；若请求较慢，则无缝等待至就绪后着陆
    const fetchPromise = fetch(href, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    try {
      await withTimeout(animOld.finished, animDuration + 1200);
      try { animOld.finish(); } catch (e) {}
      const response = await fetchPromise;

      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      const htmlText = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(htmlText, 'text/html');

      const newRoot = doc.getElementById('fluent-app-root');
      if (!newRoot) {
        window.location.href = href;
        return;
      }

      // 7. 【零视觉跳动 DOM 挂载 (在 rootContainer 保持透明状态下完成)】
      window.scrollTo(0, 0);
      rootContainer.classList.remove('fluent-page-enter'); // 杜绝 CSS 上浮动画引发的 16px 跳跃
      rootContainer.innerHTML = newRoot.innerHTML;
      document.title = doc.title;

      // 同步顶栏品牌与操作区
      const newBrand = doc.getElementById('navbar-brand-wrapper') || doc.querySelector('.navbar-brand-wrapper');
      const curBrand = document.getElementById('navbar-brand-wrapper') || document.querySelector('.navbar-brand-wrapper');
      if (newBrand && curBrand) curBrand.innerHTML = newBrand.innerHTML;

      const newActions = doc.querySelector('.nav-actions');
      const curActions = document.querySelector('.nav-actions');
      if (newActions && curActions) curActions.innerHTML = newActions.innerHTML;

      window.history.pushState({ pjax: true, url: href }, '', href);
      window._hasPjaxNavigated = true;
      try {
        const count = parseInt(sessionStorage.getItem('site_nav_depth') || '0', 10);
        sessionStorage.setItem('site_nav_depth', (count + 1).toString());
      } catch (err) {}

      // 获取新详情页真实目标
      const destIcon = document.getElementById('ms-hero-icon');
      const destTitle = document.querySelector('.ms-app-title');

      // 隐匿真实元素，等待飞行代理就位
      if (destIcon) destIcon.style.visibility = 'hidden';
      if (destTitle) destTitle.style.visibility = 'hidden';

      // 触发真实布局重排，以获取当前设备的绝对几何物理坐标
      let realIconRect = destIcon ? destIcon.getBoundingClientRect() : null;
      let realTitleRect = destTitle ? destTitle.getBoundingClientRect() : null;

      // 如果真实坐标与预测存在极微小差异，校准飞行动画落点
      if (realIconRect && (Math.abs(realIconRect.left - targetIconLeft) > 1 || Math.abs(realIconRect.top - targetIconTop) > 1)) {
        try {
          if (animIcon.effect && animIcon.effect.setKeyframes) {
            animIcon.effect.setKeyframes([
              {
                left: `${firstIconRect.left}px`,
                top: `${firstIconRect.top}px`,
                width: `${firstIconRect.width}px`,
                height: `${firstIconRect.height}px`,
                padding: '4px',
                borderRadius: '16px',
                boxShadow: '0 6px 16px rgba(0, 0, 0, 0.08)'
              },
              {
                left: `${realIconRect.left}px`,
                top: `${realIconRect.top}px`,
                width: `${realIconRect.width}px`,
                height: `${realIconRect.height}px`,
                padding: '8px',
                borderRadius: '16px',
                boxShadow: '0 10px 24px -4px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04)'
              }
            ]);
          }
          if (realTitleRect && animTitle.effect && animTitle.effect.setKeyframes) {
            animTitle.effect.setKeyframes([
              {
                left: `${firstTitleRect.left}px`,
                top: `${firstTitleRect.top}px`,
                width: `${firstTitleRect.width}px`,
                fontSize: '0.9375rem',
                fontWeight: '600',
                letterSpacing: '0px'
              },
              {
                left: `${realTitleRect.left}px`,
                top: `${realTitleRect.top}px`,
                width: `${realTitleRect.width}px`,
                fontSize: targetTitleSize,
                fontWeight: '800',
                letterSpacing: '-0.02em'
              }
            ]);
          }
        } catch (e) {
          // 部分老旧内核不支持动态 setKeyframes，沿用精准计算的预测落点
        }
      }

      // 新详情页平滑浮现 (纯透明度过渡，无 scale，保留 100% 物理真实位置)
      const animNew = rootContainer.animate([
        { opacity: 0 },
        { opacity: 1 }
      ], {
        duration: 180,
        easing: 'ease-out',
        fill: 'forwards'
      });

      // 8. 【等待飞行代理着陆】（后台页动画不推进，超时后手动完成交接）
      await withTimeout(Promise.all([
        animIcon.finished, animTitle.finished, animBg.finished, animNew.finished
      ]), animDuration + 1500);
      [animIcon, animTitle, animBg, animNew].forEach(a => { try { a.finish(); } catch (e) {} });

      // 9. 【零误差交接 (affordance.md §10)】
      if (destIcon) destIcon.style.visibility = '';
      if (destTitle) destTitle.style.visibility = '';
      rootContainer.style.opacity = '1';

      if (portal && portal.parentNode) {
        portal.parentNode.removeChild(portal);
      }

      // 重新初始化页面组件与取色流光
      initPageComponents();
      applyHeroIconSampleGradient();

    } catch (err) {
      console.warn('Morph transition fallback to standard PJAX/navigation:', err);
      window.location.href = href;
    } finally {
      window._isMorphing = false;
      finishProgress();
      if (portal && portal.parentNode) {
        portal.parentNode.removeChild(portal);
      }
      rootContainer.style.opacity = '';
    }
  }

  // 暴露动效测试 Oracle 探针 (affordance.md §9-10)
  window.__hold = function(t) {
    console.log('[motion-web] Transition Oracle pinned at progress t =', t);
  };

  // =========================================================================
  // 常规页面无缝无刷新加载 (用于分类切换、搜索筛选、前进后退)
  // =========================================================================
  async function loadPagePjax(url, pushState = true, direction = 'forward') {
    const rootContainer = document.getElementById('fluent-app-root');
    if (!rootContainer) {
      window.location.href = url;
      return;
    }

    // 智能识别返回/回退导航动作：
    // - 显式传参 direction === 'back'
    // - 或当前位于详情页（包含 .ms-store-layout）且目标是主页或分类（包含 r=home 或根路径）
    const isDetailView = !!rootContainer.querySelector('.ms-store-layout');
    const isGoingHome = url.includes('r=home') || url === '/' || url.startsWith('./') || url === 'index.php';
    if (direction === 'back' || (isDetailView && isGoingHome)) {
      direction = 'back';
    }

    // 在替换 DOM 前锁定侧边栏折叠态，避免返回主页时先展开再收起造成横向错位。
    const sidebarCollapsed = localStorage.getItem('fluent_sidebar_collapsed') === '1';
    document.documentElement.classList.toggle('sidebar-init-collapsed', sidebarCollapsed);

    // 返回动画：严格反向播放当前页面的进入动画，只变换内容区域，绝对不影响 fixed 侧边栏定位。
    const animatedContent = rootContainer.querySelector('.ms-store-layout, .content-area');
    if (animatedContent && window.Animation && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      try {
        const isDetailContent = animatedContent.classList.contains('ms-store-layout');
        const enterDistance = isDetailContent ? 16 : 12;
        const enterDuration = isDetailContent ? 200 : 160;

        await animatedContent.animate(direction === 'back' ? [
          { opacity: 1, transform: 'translateY(0)' },
          { opacity: 0, transform: `translateY(${enterDistance}px)` }
        ] : [
          { opacity: 1, transform: 'translateY(0)' },
          { opacity: 0, transform: 'translateY(4px)' }
        ], {
          duration: direction === 'back' ? enterDuration : 110,
          easing: direction === 'back' ? 'cubic-bezier(0.7, 0, 0.84, 0)' : 'cubic-bezier(0.4, 0, 1, 1)',
          fill: 'forwards'
        }).finished;
      } catch (e) {
        // 动画被浏览器取消时继续导航，不阻塞页面切换。
      }
    }

    startProgress();

    try {
      const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const htmlText = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(htmlText, 'text/html');

      const newRoot = doc.getElementById('fluent-app-root');
      if (newRoot) {
        window.scrollTo(0, 0);

        // 关键防护：在插入新 DOM 前，把新 DOM 里的侧边栏类同步为用户本地记忆的折叠态
        const newSidebar = newRoot.querySelector('#main-sidebar');
        if (newSidebar) {
          newSidebar.classList.toggle('collapsed', sidebarCollapsed);
        }

        rootContainer.innerHTML = newRoot.innerHTML;
        document.title = doc.title;

        // 同步顶栏品牌区与视图操作区（包含用户头像与登录状态）
        const newBrand = doc.getElementById('navbar-brand-wrapper') || doc.querySelector('.navbar-brand-wrapper');
        const curBrand = document.getElementById('navbar-brand-wrapper') || doc.querySelector('.navbar-brand-wrapper');
        if (newBrand && curBrand) {
          curBrand.innerHTML = newBrand.innerHTML;
        }
        const newActions = doc.querySelector('.nav-actions');
        const curActions = document.querySelector('.nav-actions');
        if (newActions && curActions) {
          curActions.innerHTML = newActions.innerHTML;
        }

        if (pushState) {
          window.history.pushState({ pjax: true, url: url }, '', url);
          window._hasPjaxNavigated = true;
          try {
            const count = parseInt(sessionStorage.getItem('site_nav_depth') || '0', 10);
            sessionStorage.setItem('site_nav_depth', (count + 1).toString());
          } catch (err) {}
        }

        rootContainer.classList.remove('fluent-page-enter');
        void rootContainer.offsetWidth;
        rootContainer.classList.add('fluent-page-enter');

        // 重新初始化页面组件
        initPageComponents();
      } else {
        window.location.href = url;
      }
    } catch (e) {
      console.warn('PJAX load failed, falling back to full navigation:', e);
      window.location.href = url;
    } finally {
      finishProgress();
    }
  }

  // 全局返回上一级路由方法（优先真实后退上一页，支持深层访问安全回退至主页）
  window.navigateBack = function (e) {
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
      e.stopPropagation();
    }
    const referrer = document.referrer || '';
    const sameOrigin = referrer.includes(window.location.host) || referrer.startsWith('/') || (window.location.origin && referrer.startsWith(window.location.origin));
    let navDepth = 0;
    try {
      navDepth = parseInt(sessionStorage.getItem('site_nav_depth') || '0', 10);
    } catch (err) {}

    const hasHistory = (window.history.length > 1 && (sameOrigin || window._hasPjaxNavigated || navDepth > 0));

    if (hasHistory) {
      try {
        if (navDepth > 0) {
          sessionStorage.setItem('site_nav_depth', Math.max(0, navDepth - 1).toString());
        }
      } catch (err) {}
      window.history.back();
    } else {
      loadPagePjax('?r=home', true, 'back');
    }
  };

  // 监听浏览器前进/后退
  window.addEventListener('popstate', (e) => {
    if (e.state && e.state.pjax) {
      loadPagePjax(e.state.url, false, 'back');
    } else {
      loadPagePjax(window.location.href, false, 'back');
    }
  });

  // 全局事件委托：拦截站内可 PJAX 的链接 & 支持整张卡片任意位置点击跳转
  document.addEventListener('click', (e) => {
    // 仅在存在 #fluent-app-root 前台 SPA 容器时才处理 PJAX 路由
    if (!document.getElementById('fluent-app-root')) return;

    // 1. 优先处理整卡或卡片获取按钮点击 (触发物理 FLIP 图标与文字飞向详情页转场)
    const card = e.target.closest('.software-card[data-href]');
    if (card) {
      const cardHref = card.getAttribute('data-href');
      e.preventDefault();
      e.stopPropagation();
      morphCardToDetail(card, cardHref);
      return;
    }

    // 2. 拦截站内 a 标签
    const link = e.target.closest('a');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href) return;

    // 排除外链、锚点、在新标签打开、下载以及后台管理、个人中心、用户认证等独立功能页
    if (
      link.target === '_blank' ||
      link.hasAttribute('download') ||
      href.startsWith('#') ||
      href.startsWith('javascript:') ||
      href.startsWith('mailto:') ||
      href.startsWith('tel:') ||
      href.includes('r=admin') ||
      href.includes('r=user') ||
      href.includes('r=auth')
    ) {
      return;
    }

    // 仅拦截本站以 ?r= 或相对路径开头的站内页面
    const isInternal = href.startsWith('?') || href.startsWith('/') || href.startsWith('./') || href.startsWith(window.location.origin);
    if (!isInternal) return;

    e.preventDefault();
    if (link.classList.contains('back-nav-btn') || link.id === 'back-nav-btn' || link.title?.includes('返回上一级')) {
      window.navigateBack(e);
      return;
    }
    const isBackBtn = link.title?.includes('返回') || 
                      (document.querySelector('.ms-store-layout') && (href === '?r=home' || href.includes('r=home')));
    loadPagePjax(href, true, isBackBtn ? 'back' : 'forward');
  });



  // =========================================================================
  // 6. 实时搜索与下拉补全
  // =========================================================================
  function initSearch() {
    const searchInput = document.getElementById('main-search-input');
    const searchDropdown = document.getElementById('search-dropdown');
    let searchTimer = null;

    if (searchInput && searchDropdown && !searchInput._bound) {
      searchInput._bound = true;
      searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        clearTimeout(searchTimer);
        if (query.length === 0) {
          searchDropdown.style.display = 'none';
          searchDropdown.innerHTML = '';
          return;
        }

        searchTimer = setTimeout(() => {
          fetch('?r=api/search&q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
              const list = data.results || [];
              if (list.length === 0) {
                searchDropdown.innerHTML = '<div style="padding: 12px; font-size: 0.8125rem; color: var(--text-muted); text-align: center;">未找到匹配软件</div>';
              } else {
                searchDropdown.innerHTML = list.map(item => `
                  <a href="${escapeHtml(item.url)}" class="search-result-item">
                    <img src="${escapeHtml(item.icon_url)}" class="search-result-icon" alt="" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22><rect width=%2220%22 height=%2220%22 fill=%22%23e2e8f0%22/></svg>'">
                    <div style="min-width: 0; flex: 1;">
                      <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);">${escapeHtml(item.name)}</div>
                      <div style="font-size: 0.75rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(item.excerpt || '')}</div>
                    </div>
                    <span class="badge" style="font-size: 0.6875rem;">${escapeHtml(item.category_name || '')}</span>
                  </a>
                `).join('');
              }
              searchDropdown.style.display = 'block';
            })
            .catch(() => {
              searchDropdown.style.display = 'none';
            });
        }, 200);
      });

      document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
          searchDropdown.style.display = 'none';
        }
      });
    }
  }

  // =========================================================================
  // 7. 通用模态框与详情页交互 (评分、举报、提交等)
  // =========================================================================
  window.openModal = function (modalId) {
    // 先收起其它已开弹窗，避免 PJAX 残留的同名旧弹窗叠层导致"关不掉"
    document.querySelectorAll('.modal-overlay.active').forEach(m => {
      if (m.id !== modalId) m.classList.remove('active');
    });
    const modal = document.getElementById(modalId);
    if (modal) {
      if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
      }
      modal.classList.add('active');
      // 锁定背景滚动，杜绝弹窗内滚动到尽头后带动详情页滚动
      document.body.classList.add('modal-open');
      // 弹窗打开时彻底隐藏吸顶条，绝不遮挡弹窗
      const bar = document.getElementById('ms-sticky-bar');
      if (bar) bar.classList.remove('visible');
      // 懒初始化弹窗内 Markdown 编辑器（覆盖 morph/PJAX 路径）
      if (window.FluentMarkdown && window.FluentMarkdown.scan) {
        setTimeout(() => window.FluentMarkdown.scan(modal), 20);
      }
    }
  };

  window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      // 仅当没有任何弹窗处于打开态时才解锁背景滚动
      if (!document.querySelector('.modal-overlay.active')) {
        document.body.classList.remove('modal-open');
      }
      const bar = document.getElementById('ms-sticky-bar');
      if (bar && window._stickyScrollHandler) {
        window._stickyScrollHandler();
      }
    }
  };

  // 检查详情页“说明”卡片内容是否真实溢出 (防短文本多余留白与展开按钮)
  window.checkDescOverflow = function () {
    const card = document.getElementById('desc-card');
    const wrapper = document.getElementById('desc-wrapper');
    if (!card || !wrapper) return;
    const body = wrapper.querySelector('.ms-desc-body');
    if (!body) return;

    // 当内容高度超过阈值 (约 280px) 时才需要折叠与显示“阅读更多信息”
    const threshold = 280;
    const isOverflowing = body.scrollHeight > threshold + 10;

    if (isOverflowing) {
      card.classList.add('has-overflow');
      wrapper.classList.add('has-overflow');
      if (!wrapper.classList.contains('expanded')) {
        wrapper.classList.add('collapsed');
      }
      const btn = card.querySelector('.ms-read-more-btn');
      if (btn && !wrapper.classList.contains('expanded')) {
        btn.classList.remove('expanded');
        const span = btn.querySelector('span:not(.chevron-icon)');
        if (span) span.textContent = '阅读更多信息';
      }
    } else {
      card.classList.remove('has-overflow');
      wrapper.classList.remove('has-overflow');
      wrapper.classList.remove('collapsed');
      wrapper.classList.remove('expanded');
    }
  };

  // 折叠“阅读更多信息”展开/收起
  window.toggleReadMore = function (wrapperId, btn) {
    const el = document.getElementById(wrapperId);
    if (!el) return;
    const isCollapsed = el.classList.contains('collapsed');
    if (isCollapsed) {
      el.classList.remove('collapsed');
      el.classList.add('expanded');
      if (btn) {
        btn.classList.add('expanded');
        const span = btn.querySelector('span:not(.chevron-icon)');
        if (span) span.textContent = '收起信息';
      }
    } else {
      el.classList.add('collapsed');
      el.classList.remove('expanded');
      if (btn) {
        btn.classList.remove('expanded');
        const span = btn.querySelector('span:not(.chevron-icon)');
        if (span) span.textContent = '阅读更多信息';
      }
    }
  };

  // 展开/收起评价表单
  window.toggleReviewForm = function () {
    const form = document.getElementById('comment-form');
    if (form) {
      if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        form.style.display = 'none';
      }
    }
  };

  // 展开/收起单条长评论
  window.toggleCommentExpand = function (btn) {
    const content = btn.previousElementSibling;
    if (!content) return;
    const isExpanded = content.classList.contains('expanded');
    if (isExpanded) {
      content.classList.remove('expanded');
      btn.textContent = '展开全文';
    } else {
      content.classList.add('expanded');
      btn.textContent = '收起全文';
    }
  };

  // 展开/收起更多评论列表
  window.toggleMoreReviews = function (btn) {
    const hiddenReviews = document.querySelectorAll('.ms-reviews-list .ms-review-card.ms-review-hidden');
    const isExpanded = btn.classList.contains('expanded');
    if (isExpanded) {
      hiddenReviews.forEach(r => r.style.display = 'none');
      btn.classList.remove('expanded');
      const total = document.querySelectorAll('.ms-reviews-list .ms-review-card').length;
      btn.textContent = '阅读更多信息 (共 ' + total + ' 条评价)';
      const reviewsCard = document.getElementById('reviews-card');
      if (reviewsCard) {
        reviewsCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    } else {
      hiddenReviews.forEach(r => r.style.display = 'block');
      btn.classList.add('expanded');
      btn.textContent = '收起更多评价';
    }
  };

  // 评价点赞/点踩
  window.voteComment = function (id, type, btn) {
    if (btn && btn.classList.contains('voted')) return;
    const params = new URLSearchParams();
    params.append('comment_id', id);
    params.append('type', type);

    fetch('?r=software/voteComment', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params.toString()
    })
      .then(res => res.json())
      .then(data => {
        if (data.success && btn) {
          btn.classList.add('voted');
          const span = btn.querySelector('span');
          if (span) {
            span.textContent = type === 'like' ? data.likes : data.dislikes;
          }
        }
      })
      .catch(() => {});
  };


  function initModals() {
    // PJAX/morph 换页后清理残留：仅当同 ID 新弹窗已在 app-root 内时，
    // body 层的旧弹窗才是上一页孤儿，可移除（页脚提交弹窗只存在于 body 层，须保留）
    const appRoot = document.getElementById('fluent-app-root');
    document.querySelectorAll('body > .modal-overlay').forEach(orphan => {
      const fresh = appRoot ? appRoot.querySelector('[id="' + orphan.id + '"]') : null;
      if (fresh && fresh !== orphan) {
        orphan.remove();
      }
    });
    // 换页后复位所有弹窗打开态与滚动锁
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      if (!overlay._bound) {
        overlay._bound = true;
        overlay.addEventListener('click', function (e) {
          if (e.target === this) {
            this.classList.remove('active');
          }
        });
      }
    });

    // 评价星级
    const starInput = document.getElementById('comment-rating-input');
    const starContainer = document.getElementById('star-picker');
    if (starContainer && starInput && !starContainer._bound) {
      starContainer._bound = true;
      const stars = starContainer.querySelectorAll('.star-pick');
      stars.forEach(star => {
        star.addEventListener('click', function () {
          const val = this.getAttribute('data-val');
          starInput.value = val;
          stars.forEach(s => {
            if (parseInt(s.getAttribute('data-val')) <= parseInt(val)) {
              s.style.color = '#ea580c';
            } else {
              s.style.color = 'var(--text-muted)';
            }
          });
        });
      });
    }

    // 评价表单提交
    const commentForm = document.getElementById('comment-form');
    if (commentForm && !commentForm._bound) {
      commentForm._bound = true;
      commentForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch('?r=software/comment', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            window.FluentMessage('评价发表成功！', 'success');
            loadPagePjax(window.location.href, false);
          } else {
            window.FluentMessage(data.message || '评价发表失败', 'error');
            if (submitBtn) submitBtn.disabled = false;
          }
        })
        .catch(() => {
          window.FluentMessage('网络通信异常，请稍后再试', 'error');
          if (submitBtn) submitBtn.disabled = false;
        });
      });
    }

    // 举报表单提交
    const reportForm = document.getElementById('report-form');
    if (reportForm && !reportForm._bound) {
      reportForm._bound = true;
      reportForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('?r=software/report', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            window.FluentMessage(data.message || '反馈已提交', 'success');
            closeModal('report-modal');
            this.reset();
          } else {
            window.FluentMessage(data.message || '提交失败', 'error');
          }
        })
        .catch(() => {
          window.FluentMessage('提交出错，请稍后重试', 'error');
        });
      });
    }

    // 软件补充/修改纠错表单提交 (PJAX 全局委托支持)
    const correctionForm = document.getElementById('software-correction-form');
    if (correctionForm && !correctionForm._bound) {
      correctionForm._bound = true;
      correctionForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch('?r=software/submitCorrection', {
          method: 'POST',
          body: new FormData(this),
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            window.FluentMessage(data.message || '修改信息已提交审核', 'success');
            this.reset();
            closeModal('software-correction-modal');
          } else {
            window.FluentMessage(data.message || '提交失败', 'error');
          }
        })
        .catch(() => window.FluentMessage('通信异常，请稍后重试', 'error'))
        .finally(() => {
          if (submitBtn) submitBtn.disabled = false;
        });
      });
    }

    // 分享视频教程或第三方资源表单
    const addResForm = document.getElementById('add-resource-form');
    if (addResForm && !addResForm._bound) {
      addResForm._bound = true;
      addResForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch('?r=software/addResource', {
          method: 'POST',
          body: new FormData(this),
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            window.FluentMessage(data.message || '资源分享成功', 'success');
            this.reset();
            closeModal('add-resource-modal');
            loadPagePjax(window.location.href, false);
          } else {
            window.FluentMessage(data.message || '提交失败', 'error');
          }
        })
        .catch(() => window.FluentMessage('通信异常，请稍后重试', 'error'))
        .finally(() => {
          if (submitBtn) submitBtn.disabled = false;
        });
      });
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // =========================================================================
  // 8. 详情页吸顶条随滚动显示/隐藏 (用户滑过下载按钮才出现，平滑滑入滑出动效)
  // =========================================================================
  function initStickyBar() {
    // 检查并清理孤立在 body 上的旧吸顶条
    const bodyOldBar = document.body.querySelector(':scope > #ms-sticky-bar');
    const root = document.getElementById('fluent-app-root');
    const rootBar = root ? root.querySelector('#ms-sticky-bar') : null;

    if (!rootBar && !bodyOldBar) {
      if (window._stickyScrollHandler) {
        window.removeEventListener('scroll', window._stickyScrollHandler);
        window._stickyScrollHandler = null;
      }
      return;
    }

    if (!rootBar && bodyOldBar) {
      bodyOldBar.remove();
      if (window._stickyScrollHandler) {
        window.removeEventListener('scroll', window._stickyScrollHandler);
        window._stickyScrollHandler = null;
      }
      return;
    }

    if (bodyOldBar && bodyOldBar !== rootBar) {
      bodyOldBar.remove();
    }

    const bar = rootBar || bodyOldBar;
    if (!bar) return;

    // 挂载到 body 根层，防止容器 transform/filter 干扰 fixed 布局与裁切
    if (bar.parentElement !== document.body) {
      document.body.appendChild(bar);
    }

    const downloadBtn = document.getElementById('hero-download-btn') || document.querySelector('.btn-store-hero-download');

    function checkSticky() {
      // 若当前有激活的模态窗，立即隐藏吸顶条，杜绝遮挡
      if (document.querySelector('.modal-overlay.active')) {
        bar.classList.remove('visible');
        return;
      }

      let shouldShow = false;
      if (downloadBtn) {
        const rect = downloadBtn.getBoundingClientRect();
        // 用户滑过下载按钮：下载按钮顶部接近顶栏以下或整体滑过，或向下滚动超过 130px
        shouldShow = rect.top < 110 || window.scrollY > 130;
      } else {
        shouldShow = window.scrollY > 130;
      }

      if (shouldShow) {
        bar.classList.add('visible');
      } else {
        bar.classList.remove('visible');
      }
    }

    if (window._stickyScrollHandler) {
      window.removeEventListener('scroll', window._stickyScrollHandler);
    }
    window._stickyScrollHandler = checkSticky;
    window.addEventListener('scroll', window._stickyScrollHandler, { passive: true });
    checkSticky();
    setTimeout(checkSticky, 120);
  }
  window.initStickyBar = initStickyBar;

  // =========================================================================
  // 8.5 用户顶栏个人中心与下拉菜单
  // =========================================================================
  window.toggleUserDropdown = function (e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    const menu = document.getElementById('user-dropdown-menu');
    if (menu) {
      menu.classList.toggle('active');
    }
  };

  document.addEventListener('click', function (e) {
    const wrapper = document.getElementById('user-menu-wrapper');
    const menu = document.getElementById('user-dropdown-menu');
    if (menu && menu.classList.contains('active')) {
      if (!wrapper || !wrapper.contains(e.target)) {
        menu.classList.remove('active');
      }
    }
  });

  // =========================================================================
  // 8.6 用户登录/注册 Modal 控制与第三方快捷登录 (Microsoft, Google, GitHub)
  // =========================================================================
  window.openAuthModal = function (tab = 'login') {
    switchAuthTab(tab);
    window.openModal('auth-modal');
    const errEl = document.getElementById('auth-error-msg');
    const succEl = document.getElementById('auth-success-msg');
    if (errEl) errEl.style.display = 'none';
    if (succEl) succEl.style.display = 'none';
  };

  window.closeAuthModal = function () {
    window.closeModal('auth-modal');
  };

  window.switchAuthTab = function (tab) {
    const loginTab = document.getElementById('auth-tab-login');
    const regTab = document.getElementById('auth-tab-register');
    const forgotTab = document.getElementById('auth-tab-forgot');
    const loginForm = document.getElementById('auth-login-form');
    const regForm = document.getElementById('auth-register-form');
    const forgotForm = document.getElementById('auth-forgot-form');
    const oauthSec = document.getElementById('auth-oauth-section');
    const titleText = document.getElementById('auth-modal-title-text');
    const errEl = document.getElementById('auth-error-msg');
    const succEl = document.getElementById('auth-success-msg');

    if (errEl) errEl.style.display = 'none';
    if (succEl) succEl.style.display = 'none';

    [loginTab, regTab, forgotTab].forEach(t => t && t.classList.remove('active'));
    [loginForm, regForm, forgotForm].forEach(f => f && (f.style.display = 'none'));

    if (tab === 'register') {
      if (regTab) regTab.classList.add('active');
      if (regForm) regForm.style.display = 'block';
      if (oauthSec) oauthSec.style.display = 'block';
      if (titleText) titleText.textContent = '新用户注册';
      const userInp = document.getElementById('reg-username');
      if (userInp) userInp.focus();
    } else if (tab === 'forgot') {
      if (forgotTab) forgotTab.classList.add('active');
      if (forgotForm) forgotForm.style.display = 'block';
      if (oauthSec) oauthSec.style.display = 'none';
      if (titleText) titleText.textContent = '找回密码';
      const pInp = document.getElementById('forgot-email-prefix');
      if (pInp) pInp.focus();
    } else {
      if (loginTab) loginTab.classList.add('active');
      if (loginForm) loginForm.style.display = 'block';
      if (oauthSec) oauthSec.style.display = 'block';
      if (titleText) titleText.textContent = '登录账号';
      const accInp = document.getElementById('login-account');
      if (accInp) accInp.focus();
    }
  };

  window.sendAuthCode = async function (btn, action, prefix) {
    if (btn.disabled) return;
    const prefixInput = document.getElementById(prefix + '-email-prefix');
    const suffixSelect = document.getElementById(prefix + '-email-suffix');
    if (!prefixInput || !prefixInput.value.trim()) {
      window.FluentMessage('请先输入邮箱前缀', 'warning');
      prefixInput && prefixInput.focus();
      return;
    }

    const emailPrefix = prefixInput.value.trim();
    const emailSuffix = suffixSelect ? suffixSelect.value : '@qq.com';

    const form = btn.closest('form');
    const captchaInput = form ? form.querySelector('input[name="captcha_math_input"]') : null;
    const captchaName = form ? form.querySelector('input[name="captcha_name"]') : null;

    if (captchaInput && !captchaInput.value.trim()) {
      window.FluentMessage('请先计算并填写安全验证结果', 'warning');
      captchaInput.focus();
      return;
    }

    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '发送中...';

    const body = new URLSearchParams();
    body.append('email_prefix', emailPrefix);
    body.append('email_suffix', emailSuffix);
    body.append('action', action);
    if (captchaInput) body.append('captcha_math_input', captchaInput.value.trim());
    if (captchaName) body.append('captcha_name', captchaName.value.trim());

    try {
      const resp = await fetch('?r=auth/sendVerifyCode', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
      });
      const data = await resp.json();

      if (data.success) {
        let left = 60;
        btn.textContent = `${left}s`;
        const timer = setInterval(() => {
          left--;
          if (left <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = origText;
          } else {
            btn.textContent = `${left}s`;
          }
        }, 1000);
      } else {
        window.FluentMessage(data.error || '验证码发送失败', 'error');
        btn.disabled = false;
        btn.textContent = origText;
      }
    } catch (e) {
      window.FluentMessage('发送失败，请检查网络', 'error');
      btn.disabled = false;
      btn.textContent = origText;
    }
  };

  window.handleAuthForgot = async function (e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('btn-forgot-submit');
    const errEl = document.getElementById('auth-error-msg');
    const succEl = document.getElementById('auth-success-msg');

    if (errEl) errEl.style.display = 'none';
    if (succEl) succEl.style.display = 'none';

    const p1 = document.getElementById('forgot-password')?.value;
    const p2 = document.getElementById('forgot-password-confirm')?.value;
    if (p1 !== p2) {
      if (errEl) {
        errEl.textContent = '两次输入的新密码不一致';
        errEl.style.display = 'block';
      }
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>重置中...</span>';
    }

    try {
      const formData = new FormData(form);
      const resp = await fetch('?r=auth/forgotPassword', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      const data = await resp.json();

      if (data.success) {
        if (succEl) {
          succEl.textContent = '密码重置成功！请使用新密码登录';
          succEl.style.display = 'block';
        }
        setTimeout(() => {
          switchAuthTab('login');
        }, 1200);
      } else {
        if (errEl) {
          errEl.textContent = data.error || '重置失败，请核对信息';
          errEl.style.display = 'block';
        }
      }
    } catch (err) {
      if (errEl) {
        errEl.textContent = '网络异常，请重试';
        errEl.style.display = 'block';
      }
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>重置密码并登录</span>';
      }
    }
  };

  // 处理账号密码登录
  window.handleAuthLogin = async function (e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('btn-login-submit');
    const errEl = document.getElementById('auth-error-msg');
    const succEl = document.getElementById('auth-success-msg');

    if (errEl) errEl.style.display = 'none';
    if (succEl) succEl.style.display = 'none';

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>登录中...</span>';
    }

    try {
      const formData = new FormData(form);
      const resp = await fetch('?r=auth/login', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      const data = await resp.json();

      if (data.success) {
        if (succEl) {
          succEl.textContent = '登录成功！正在跳转...';
          succEl.style.display = 'block';
        }
        setTimeout(() => {
          closeAuthModal();
          window.location.reload();
        }, 600);
      } else {
        if (errEl) {
          errEl.textContent = data.error || '登录失败，请核对信息';
          errEl.style.display = 'block';
        }
      }
    } catch (err) {
      if (errEl) {
        errEl.textContent = '网络异常，请重试';
        errEl.style.display = 'block';
      }
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>登录</span>';
      }
    }
  };

  // 处理用户注册
  window.handleAuthRegister = async function (e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('btn-register-submit');
    const errEl = document.getElementById('auth-error-msg');
    const succEl = document.getElementById('auth-success-msg');

    if (errEl) errEl.style.display = 'none';
    if (succEl) succEl.style.display = 'none';

    const p1 = document.getElementById('reg-password')?.value;
    const p2 = document.getElementById('reg-password-confirm')?.value;
    if (p1 !== p2) {
      if (errEl) {
        errEl.textContent = '两次输入的密码不一致';
        errEl.style.display = 'block';
      }
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>注册中...</span>';
    }

    try {
      const formData = new FormData(form);
      const resp = await fetch('?r=auth/register', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      const data = await resp.json();

      if (data.success) {
        if (succEl) {
          succEl.textContent = '注册成功并已自动登录！正在跳转...';
          succEl.style.display = 'block';
        }
        setTimeout(() => {
          closeAuthModal();
          window.location.reload();
        }, 600);
      } else {
        if (errEl) {
          errEl.textContent = data.error || '注册失败，请核对信息';
          errEl.style.display = 'block';
        }
      }
    } catch (err) {
      if (errEl) {
        errEl.textContent = '网络异常，请重试';
        errEl.style.display = 'block';
      }
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>注册并登录</span>';
      }
    }
  };

  // 发起第三方 OAuth 登录 (支持 JS 居中弹窗与 postMessage 监听)
  window.startOAuthLogin = function (provider) {
    const width = 540;
    const height = 660;
    const left = Math.max(0, (window.screen.width - width) / 2);
    const top = Math.max(0, (window.screen.height - height) / 2);
    const url = '?r=auth/oauth-login&provider=' + encodeURIComponent(provider);

    const popup = window.open(
      url,
      'oauth_window_' + provider,
      `width=${width},height=${height},top=${top},left=${left},status=no,resizable=yes,scrollbars=yes`
    );

    if (popup) {
      popup.focus();
    } else {
      // 弹窗被拦截，降级为常规页面跳转
      window.location.href = url;
    }
  };

  // 监听来自 OAuth 弹窗的 postMessage 授权结果
  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin) return;
    if (event.data && event.data.type === 'oauth_result') {
      if (event.data.success) {
        closeAuthModal();
        window.location.reload();
      } else {
        const errEl = document.getElementById('auth-error-msg');
        if (errEl) {
          errEl.textContent = event.data.message || '第三方登录失败';
          errEl.style.display = 'block';
        }
      }
    }
  });

  // Google Identity Services (GIS) 一键登录凭证响应处理
  window.onGoogleCredentialResponse = async function (response) {
    if (!response || !response.credential) return;
    try {
      const resp = await fetch('?r=auth/google-one-tap', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credential: response.credential })
      });
      const data = await resp.json();
      if (data.success) {
        closeAuthModal();
        window.location.reload();
      } else {
        const errEl = document.getElementById('auth-error-msg');
        if (errEl) {
          errEl.textContent = data.error || 'Google 快捷登录验证失败';
          errEl.style.display = 'block';
        }
      }
    } catch (e) {
      console.warn('Google credential response error:', e);
    }
  };

  // Google One Tap：进站在角落弹一张账号卡（和论坛类站点那种一样）。
  // 下面每一道抑制都是必要的 —— 弹窗功能最容易变成骚扰，且第三方 Cookie 被挡时必须留下证据。
  function initGoogleOneTap() {
    const cfg = window.SOFTNAV_GOOGLE;
    if (!cfg || !cfg.oneTap || !cfg.clientId) return;
    const gsi = (window.google && window.google.accounts && window.google.accounts.id) || null;
    if (gsi && gsi.__softnavInit) return;                       // 同一页面只注册一次
    try {
      if (sessionStorage.getItem('softnav_onetap') === 'shown') return;   // 本会话只弹一次
      if (/(?:^|;\s*)softnav_onetap=off/.test(document.cookie)) return;   // 用户关掉过，7 天内不再打扰
    } catch (e) { /* 无痕模式下 sessionStorage 会抛，当作不抑制 */ }
    if (!gsi) {
      // gsi/client 是 async 加载的：没到就每秒再试一次，最多 6 次，避免静默不弹
      cfg.__tries = (cfg.__tries || 0) + 1;
      if (cfg.__tries <= 6) setTimeout(initGoogleOneTap, 1000);
      return;
    }
    if (document.querySelector('.modal-overlay.active')) return;          // 我们自己的登录框开着时不抢

    gsi.initialize({
      client_id: cfg.clientId,
      auto_select: false,
      cancel_on_tap_outside: true,
      callback: window.onGoogleCredentialResponse
    });
    gsi.__softnavInit = true;

    setTimeout(function () {
      if (document.querySelector('.modal-overlay.active')) return;
      gsi.prompt(function (notif) {
        try { sessionStorage.setItem('softnav_onetap', 'shown'); } catch (e) {}
        if (!notif || typeof notif.getNotDisplayedReason !== 'function') return;
        if (notif.isNotDisplayed && notif.isNotDisplayed()) {
          console.info('[One Tap] 未显示:', notif.getNotDisplayedReason(),
            '（常见原因：域名没加进 Google 控制台「已授权的 JavaScript 来源」、浏览器挡了第三方 Cookie）');
        }
        if (notif.isSkippedMoment && notif.isSkippedMoment()) {
          console.info('[One Tap] 本次被跳过:', notif.getNotDisplayedReason());
        }
        if (notif.isDismissedMoment && notif.isDismissedMoment()) {
          document.cookie = 'softnav_onetap=off; path=/; max-age=' + (7 * 86400) + '; SameSite=Lax';
        }
      });
    }, 1200);
  }

  // =========================================================================
  // 9. 统一页面组件初始化入口 (初次加载及 PJAX 切页后触发)
  // =========================================================================
  function initPageComponents() {
    initTheme();
    initSidebar();
    initSearch();
    initModals();
    applyHeroIconSampleGradient();
    applyCardIconSample();
    initIconColorSampling();
    initStickyBar();
    initGoogleOneTap();
    if (window.checkDescOverflow) {
      window.checkDescOverflow();
    }
  }

  // DOM 就绪时初始化
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageComponents);
  } else {
    initPageComponents();
  }

})();
