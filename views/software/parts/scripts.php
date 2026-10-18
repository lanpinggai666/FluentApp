    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
      form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
}
window.toggleReviewForm = toggleReviewForm;

// 展开/收起单条长评论
function toggleCommentExpand(btn) {
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
}
window.toggleCommentExpand = toggleCommentExpand;

// 展开/收起更多评论列表
function toggleMoreReviews(btn) {
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
}
window.toggleMoreReviews = toggleMoreReviews;

// 标签异步提交
const addTagForm = document.getElementById('add-tag-form');
if (addTagForm) {
  addTagForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('<?= Url::route('software/addTag') ?>', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.tags) {
        const container = document.getElementById('software-tags-container');
        if (container) {
          const btn = container.querySelector('button');
          container.innerHTML = data.tags.map(t => `<span class="tag-pill">${escapeHtml(t)}</span>`).join(' ');
          if (btn) container.appendChild(btn);
        }
        closeModal('add-tag-modal');
      } else {
        window.FluentMessage(data.message || '添加失败', 'error');
      }
    })
    .catch(() => window.FluentMessage('通信异常', 'error'));
  });
}

// 软件补充信息：动态添加和删除下载渠道
const correctionChannelList = document.getElementById('correction-channel-list');
const addCorrectionChannelButton = document.getElementById('add-correction-channel');

function createCorrectionChannelRow() {
  const row = document.createElement('div');
  row.className = 'correction-channel-row';
  
  row.innerHTML = `
    <select name="channel_type[]" class="form-select" aria-label="渠道类型">
      <option value="Github">Github</option>
      <option value="Microsoft Store">Microsoft Store</option>
      <option value="第三方下载站" selected>第三方下载站</option>
    </select>
    <input type="text" name="channel_name[]" class="form-input" maxlength="100" placeholder="渠道名称">
    <input type="url" name="channel_url[]" class="form-input" maxlength="500" placeholder="https://">
    <button type="button" class="btn-store-secondary remove-correction-channel" style="padding:6px 10px;" aria-label="删除下载渠道">删除</button>
  `;
  return row;
}

if (addCorrectionChannelButton && correctionChannelList) {
  addCorrectionChannelButton.addEventListener('click', function () {
    correctionChannelList.appendChild(createCorrectionChannelRow());
  });

  correctionChannelList.addEventListener('click', function (event) {
    const removeButton = event.target.closest('.remove-correction-channel');
    if (!removeButton) return;

    const row = removeButton.closest('.correction-channel-row');
    if (!row) return;

    if (correctionChannelList.querySelectorAll('.correction-channel-row').length === 1) {
      row.querySelectorAll('input').forEach(input => {
        input.value = input.name === 'channel_type[]' ? '第三方下载' : '';
      });
      return;
    }

    row.remove();
  });
}

// 提交软件补充信息
const softwareCorrectionForm = document.getElementById('software-correction-form');
if (softwareCorrectionForm) {
  softwareCorrectionForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const submitButton = this.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;

    fetch('<?= Url::route('software/submitCorrection') ?>', {
      method: 'POST',
      body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.FluentMessage(data.message || '修改信息已提交审核，审核结果将通过邮箱通知。', 'success');
        this.reset();
        closeModal('software-correction-modal');
      } else {
        window.FluentMessage(data.message || '提交失败', 'error');
      }
    })
    .catch(() => window.FluentMessage('通信异常，请稍后重试', 'error'))
    .finally(() => {
      if (submitButton) submitButton.disabled = false;
    });
  });
}

// 后端随错误回了一张新题时，把 widget 真正渲染出来。
// innerHTML 插进去的 <script> 不会执行，而 Turnstile / hCaptcha 只在首次加载时扫描 DOM，
// 所以动态插入的 .cf-turnstile 必须显式 render，否则用户看到的只是一个空框。
function renderInjectedCaptchaWidget(slot) {
  const host = slot.querySelector('.cf-turnstile, .h-captcha');
  if (!host) return;                                        // local 算术题是纯 HTML，不用管
  const api = host.classList.contains('h-captcha') ? window.hcaptcha : window.turnstile;
  if (!api || typeof api.render !== 'function') return;      // 供应商脚本还没回来，刷新页面时自然会渲染
  if (host.dataset.widgetId && typeof api.refresh === 'function') {
    api.refresh(host.dataset.widgetId);
    return;
  }
  const sitekey = host.getAttribute('data-sitekey');
  if (sitekey) {
    api.render(host, { sitekey: sitekey, theme: 'auto' });
  }
}

// 提交第三方视频教程与资源
const addResourceForm = document.getElementById('add-resource-form');
if (addResourceForm) {
  addResourceForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('<?= Url::route('software/addResource') ?>', {
      method: 'POST',
      body: formData
    })
    .then(async res => {
      // 先拿文本再自己解析：CSRF 闸门或 PHP 报错回的是 HTML，直接 res.json() 会抛错，
      // 结果被 catch 成一句「通信异常」，和真实原因差了十万八千里
      const text = await res.text();
      let payload = null;
      try { payload = JSON.parse(text); } catch (err) {
        console.error('[资源提交] 期望 JSON 却拿到 HTTP ' + res.status + ' / '
          + (res.headers.get('content-type') || '未知类型') + '：' + text.slice(0, 200));
      }
      return { status: res.status, payload };
    })
    .then(({ status, payload }) => {
      const data = payload || {};
      if (data.success) {
        window.FluentMessage(data.message || '提交成功！', 'success');
        location.reload();
        return;
      }
      // 算术题是一次性消费的：后端会随错误回一张新题，不换掉用户对着旧题算永远过不了
      const slot = this.querySelector('[data-captcha-slot="resource"]');
      if (slot && typeof data.captchaHtml === 'string' && data.captchaHtml !== '') {
        slot.innerHTML = data.captchaHtml;
        renderInjectedCaptchaWidget(slot);
      }
      window.FluentMessage(data.message || (status >= 500 ? '服务器处理失败，请稍后重试' : '提交失败（HTTP ' + status + '）'), 'error');
    })
    .catch(() => window.FluentMessage('网络通信异常，请检查网络后重试', 'error'));
  });
}

// 吸顶悬浮栏初始化
if (typeof window.initStickyBar === 'function') {
  window.initStickyBar();
}

// 检测说明文本是否溢出以显示“阅读更多信息”
if (typeof window.checkDescOverflow === 'function') {
  window.checkDescOverflow();
}
</script>

