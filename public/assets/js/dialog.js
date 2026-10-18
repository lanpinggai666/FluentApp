/**
 * 全局 fetch 包装：给同源的状态变更请求补上 X-CSRF-Token。
 * Router 对所有 POST/PUT/PATCH/DELETE 统一校验「表单隐藏域 或 本请求头」，
 * 所以这里集中注入一次即可，25 个调用点无需各自记得带 token。
 */
(function () {
  if (window.__csrfFetchInstalled || !window.fetch) return;
  var meta = document.querySelector('meta[name="csrf-token"]');
  var token = meta ? meta.getAttribute('content') : '';
  if (!token) return;

  var UNSAFE = { POST: 1, PUT: 1, PATCH: 1, DELETE: 1 };
  var nativeFetch = window.fetch.bind(window);

  window.fetch = function (input, init) {
    var opts = init || {};
    var method = String(opts.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (!UNSAFE[method]) return nativeFetch(input, opts);

    var headers = new Headers(opts.headers || (input instanceof Request ? input.headers : {}));
    if (!headers.has('x-csrf-token')) headers.set('x-csrf-token', token);

    if (typeof input === 'string' || input instanceof URL) {
      return nativeFetch(input, Object.assign({}, opts, { method: method, headers: headers }));
    }
    return nativeFetch(new Request(input, { method: method, headers: headers }), opts);
  };
  window.__csrfFetchInstalled = true;
})();

/**
 * 蓝屏钙软件导航网 - 自定义确认框、消息提示与输入框 (Fluent UI)
 *
 * 彻底取代浏览器原生 alert() / confirm() / prompt()：
 *  - FluentConfirm(options)  → Promise<boolean>，用于操作二次确认
 *  - FluentMessage(text, tone) → Promise<void>，用于结果提示
 *  - FluentPrompt(options)   → Promise<string|null>，用于接收输入
 *  - FluentToast(text, tone)   → 自动渐隐浮动通知
 *  - data-confirm / data-confirm-title 属性声明式绑定确认
 *  - 全局拦截并替换 window.alert / window.confirm / window.prompt
 *
 * 遵循 Microsoft Fluent 设计语言：毛玻璃遮罩、圆角微动效与规范色彩。
 */
(function () {
  'use strict';

  var HOST_ID = 'fluent-dialog-host';
  var ICONS = {
    info: '<path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 3.25a.9.9 0 0 1 .9.9v4.4a.9.9 0 0 1-1.8 0v-4.4a.9.9 0 0 1 .9-.9Zm0 7.25a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1Z"/>',
    success: '<path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm3.78 6.28-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06L8.75 11.19l3.97-3.97a.75.75 0 1 1 1.06 1.06Z"/>',
    warning: '<path d="M8.68 2.79a1.5 1.5 0 0 1 2.64 0l6 10.5A1.5 1.5 0 0 1 16 15.5H4a1.5 1.5 0 0 1-1.32-2.21l6-10.5ZM10 6.5a.85.85 0 0 0-.85.9l.25 3.2a.6.6 0 0 0 1.2 0l.25-3.2A.85.85 0 0 0 10 6.5Zm0 6a1.05 1.05 0 1 0 0 2.1 1.05 1.05 0 0 0 0-2.1Z"/>',
    error: '<path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm2.53 5.03L11.06 8.5l1.47 1.47a.75.75 0 1 1-1.06 1.06L10 9.56 8.53 11.03a.75.75 0 0 1-1.06-1.06L8.94 8.5 7.47 7.03a.75.75 0 1 1 1.06-1.06L10 7.44l1.47-1.41a.75.75 0 0 1 1.06 1Z"/>'
  };

  var TONES = {
    info: '#0284c7',
    success: '#059669',
    warning: '#d97706',
    error: '#dc2626',
    danger: '#dc2626'
  };

  function host() {
    var el = document.getElementById(HOST_ID);
    if (!el) {
      el = document.createElement('div');
      el.id = HOST_ID;
      var parent = document.body || document.documentElement;
      parent.appendChild(el);
    }
    return el;
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /**
   * 打开模态对话框。
   * @param {Object} options
   * @returns {Promise<boolean>} 确定返回 true，取消/关闭返回 false
   */
  function open(options) {
    var opts = options || {};
    var tone = TONES[opts.tone] ? opts.tone : 'info';
    var accent = TONES[tone];
    var isSingleBtn = opts.singleButton === true;
    var icon = opts.showIcon === false ? '' :
      '<svg class="fd-icon" viewBox="0 0 20 20" fill="' + accent + '" aria-hidden="true">'
      + (ICONS[tone] || ICONS.info) + '</svg>';

    return new Promise(function (resolve) {
      var settled = false;
      var overlay = document.createElement('div');
      overlay.className = 'fluent-dialog-overlay';
      overlay.setAttribute('role', 'presentation');

      var footerButtons = '';
      if (!isSingleBtn) {
        footerButtons += '<button type="button" class="fluent-dialog-btn fluent-dialog-btn-cancel" data-role="cancel">'
          + escapeHtml(opts.cancelText || '取消') + '</button>';
      }
      footerButtons += '<button type="button" class="fluent-dialog-btn '
        + (tone === 'danger' || tone === 'error' ? 'fluent-dialog-btn-danger' : 'fluent-dialog-btn-primary')
        + '" data-role="ok">' + escapeHtml(opts.confirmText || (isSingleBtn ? '知道了' : '确定')) + '</button>';

      overlay.innerHTML =
        '<div class="fluent-dialog' + (opts.size === 'wide' ? ' fluent-dialog-wide' : '') + '" role="alertdialog" aria-modal="true">'
        + '<div class="fluent-dialog-body">'
        + icon
        + '<div class="fluent-dialog-text">'
        + '<h3 class="fluent-dialog-title">' + escapeHtml(opts.title || (isSingleBtn ? '提示' : '请确认')) + '</h3>'
        + (opts.html
          ? '<div class="fluent-dialog-message">' + opts.html + '</div>'
          : '<p class="fluent-dialog-message">' + escapeHtml(opts.message || '').replace(/\n/g, '<br>') + '</p>')
        + '</div>'
        + '</div>'
        + '<div class="fluent-dialog-footer">'
        + footerButtons
        + '</div>'
        + '</div>';

      var previousFocus = document.activeElement;

      function close(result) {
        if (settled) return;
        settled = true;
        overlay.classList.add('fd-closing');
        document.removeEventListener('keydown', onKeydown, true);
        setTimeout(function () {
          if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 140);
        if (previousFocus && previousFocus.focus) {
          try { previousFocus.focus(); } catch (e) { /* noop */ }
        }
        resolve(result);
      }

      function onKeydown(event) {
        if (!overlay.isConnected) return;
        if (event.key === 'Escape') {
          event.preventDefault();
          event.stopPropagation();
          close(false);
        } else if (event.key === 'Tab') {
          var focusables = overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
          if (!focusables.length) return;
          var first = focusables[0];
          var last = focusables[focusables.length - 1];
          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
          }
        } else if (event.key === 'Enter') {
          var targetTag = event.target ? event.target.tagName : '';
          if (targetTag !== 'TEXTAREA') {
            event.preventDefault();
            close(true);
          }
        }
      }

      overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
          close(false);
          return;
        }
        var role = event.target.closest ? event.target.closest('[data-role]') : null;
        if (role) {
          close(role.getAttribute('data-role') === 'ok');
        }
      });

      host().appendChild(overlay);
      document.addEventListener('keydown', onKeydown, true);

      var focusInput = overlay.querySelector('input, textarea');
      if (focusInput) {
        focusInput.focus();
        if (typeof focusInput.select === 'function') focusInput.select();
      } else {
        var okBtn = overlay.querySelector('[data-role="ok"]');
        if (okBtn) okBtn.focus();
      }
    });
  }

  /**
   * 自定义确认框（替代原生 window.confirm）。
   * @param {Object|string} options
   * @returns {Promise<boolean>}
   */
  window.FluentConfirm = function (options) {
    var opts = typeof options === 'string' ? { message: options } : (options || {});
    return open(Object.assign({
      title: '请确认',
      message: '确定要执行该操作吗？',
      tone: 'warning',
      confirmText: '确定',
      cancelText: '取消'
    }, opts));
  };

  /**
   * 自定义消息框（替代原生 window.alert）。
   * @param {string} text
   * @param {string} tone info | success | warning | error
   * @returns {Promise<void>}
   */
  window.FluentMessage = function (text, tone) {
    var key = TONES[tone] ? tone : 'info';
    var titles = { info: '提示', success: '操作成功', warning: '提示', error: '操作失败', danger: '警告' };
    return open({
      title: titles[key] || '提示',
      message: String(text == null ? '' : text),
      tone: key,
      confirmText: '知道了',
      singleButton: true,
      size: 'wide'
    }).then(function () {});
  };

  /**
   * 自定义输入框（替代原生 window.prompt）。
   * @param {Object|string} options
   * @param {string} [defaultVal]
   * @returns {Promise<string|null>}
   */
  window.FluentPrompt = function (options, defaultVal) {
    var opts = typeof options === 'string' ? { message: options, defaultValue: defaultVal } : (options || {});
    var inputId = 'fluent-prompt-input-' + Math.random().toString(36).substring(2, 9);
    var inputType = opts.type || 'text';
    var val = escapeHtml(opts.defaultValue != null ? opts.defaultValue : '');
    var placeholder = escapeHtml(opts.placeholder || '');
    var inputHtml = '<div style="margin-top: 10px;">'
      + '<input id="' + inputId + '" type="' + inputType + '" class="form-input" style="width: 100%; box-sizing: border-box; height: 36px; padding: 0 10px; font-size: 0.875rem;" value="' + val + '" placeholder="' + placeholder + '">'
      + '</div>';

    return open({
      title: opts.title || '请输入',
      message: opts.message || '',
      html: inputHtml,
      tone: opts.tone || 'info',
      confirmText: opts.confirmText || '确定',
      cancelText: opts.cancelText || '取消'
    }).then(function (ok) {
      if (!ok) return null;
      var el = document.getElementById(inputId);
      return el ? el.value : '';
    });
  };

  /** 非阻塞浮层提示，用于轻量通知。 */
  window.FluentToast = function (text, tone) {
    var key = TONES[tone] ? tone : 'info';
    var el = document.createElement('div');
    el.className = 'fluent-toast fluent-toast-' + key;
    el.innerHTML = '<svg class="fluent-icon fluent-icon-sm" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
      + (ICONS[key] || ICONS.info) + '</svg><span>' + escapeHtml(text) + '</span>';
    (document.body || document.documentElement).appendChild(el);
    setTimeout(function () {
      el.classList.add('fluent-toast-out');
      setTimeout(function () {
        if (el.parentNode) el.parentNode.removeChild(el);
      }, 260);
    }, 2600);
  };

  /* -----------------------------------------------------------------------
   * 全局劫持原生浏览器的 alert / confirm / prompt
   * 确保整站任何地方触发对话框/确认框均使用统一 Fluent 风格
   * --------------------------------------------------------------------- */
  window.alert = function (message) {
    return window.FluentMessage(message, 'info');
  };

  window.confirm = function (message) {
    return window.FluentConfirm(message);
  };

  window.prompt = function (message, defaultVal) {
    return window.FluentPrompt(message, defaultVal);
  };

  /* -----------------------------------------------------------------------
   * 声明式确认：任何带 data-confirm 的元素或含有 confirm 的行内元素被触发时拦截
   * --------------------------------------------------------------------- */
  function onDocumentClick(event) {
    var target = event.target;
    var trigger = target.closest ? target.closest('[data-confirm]') : null;

    // 兼顾容错：若有历史或动态未迁移的 inline onclick="return confirm(...)"
    if (!trigger) {
      var inlineEl = target.closest ? target.closest('[onclick*="confirm("]') : null;
      if (inlineEl) {
        if (inlineEl._fluentConfirmed) {
          delete inlineEl._fluentConfirmed;
          return;
        }
        var rawOnclick = inlineEl.getAttribute('onclick') || '';
        var msgMatch = rawOnclick.match(/confirm\(\s*(['"])(.*?)\1\s*\)/);
        var extractedMsg = msgMatch ? msgMatch[2] : '确定要执行该操作吗？';

        event.preventDefault();
        event.stopPropagation();

        window.FluentConfirm({
          message: extractedMsg,
          tone: 'warning'
        }).then(function (ok) {
          if (!ok) return;
          inlineEl._fluentConfirmed = true;
          inlineEl.removeAttribute('onclick');
          inlineEl.click();
          inlineEl.setAttribute('onclick', rawOnclick);
        });
        return;
      }
      return;
    }

    if (trigger._fluentConfirmed) {
      delete trigger._fluentConfirmed;
      return;
    }

    var message = trigger.getAttribute('data-confirm') || '确定要执行该操作吗？';
    event.preventDefault();
    event.stopPropagation();

    window.FluentConfirm({
      message: message,
      title: trigger.getAttribute('data-confirm-title') || '请确认',
      confirmText: trigger.getAttribute('data-confirm-ok') || '确定',
      cancelText: trigger.getAttribute('data-confirm-cancel') || '取消',
      tone: trigger.getAttribute('data-confirm-tone') || 'warning'
    }).then(function (ok) {
      if (!ok) return;

      var action = trigger.getAttribute('data-confirm-action');
      if (action && typeof window[action] === 'function') {
        window[action](trigger);
        return;
      }

      if (trigger.tagName === 'A' && trigger.getAttribute('href')) {
        window.location.href = trigger.getAttribute('href');
        return;
      }

      if (trigger.tagName === 'FORM') {
        trigger._fluentConfirmed = true;
        if (typeof trigger.requestSubmit === 'function') {
          trigger.requestSubmit();
        } else {
          trigger.submit();
        }
        return;
      }

      if (trigger.tagName === 'BUTTON' || trigger.tagName === 'INPUT') {
        var form = trigger.closest('form');
        trigger._fluentConfirmed = true;
        if (form && trigger.type === 'submit') {
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(trigger);
          } else {
            form.submit();
          }
        } else {
          trigger.click();
        }
      }
    });
  }

  function onDocumentSubmit(event) {
    var form = event.target;
    if (!form || form.tagName !== 'FORM') return;
    if (form._fluentConfirmed) {
      delete form._fluentConfirmed;
      return;
    }
    var message = form.getAttribute('data-confirm');
    if (!message) return;

    event.preventDefault();
    event.stopPropagation();

    window.FluentConfirm({
      message: message,
      title: form.getAttribute('data-confirm-title') || '请确认',
      confirmText: form.getAttribute('data-confirm-ok') || '确定',
      cancelText: form.getAttribute('data-confirm-cancel') || '取消',
      tone: form.getAttribute('data-confirm-tone') || 'warning'
    }).then(function (ok) {
      if (!ok) return;
      form._fluentConfirmed = true;
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
  }

  document.addEventListener('click', onDocumentClick, true);
  document.addEventListener('submit', onDocumentSubmit, true);

  window.FluentDialog = { open: open };
})();
