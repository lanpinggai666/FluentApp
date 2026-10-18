/**
 * 蓝屏钙软件导航网 - 统一软件提交表单行为控制器
 *
 * 采用事件委托，同时驱动「独立提交页」与「全局提交弹窗」两份表单实例，
 * 保证两端交互逻辑完全一致，且无需重复绑定。
 */
(function () {
  'use strict';

  var CHANNEL_TYPES = [
    { value: 'Github', name: '渠道说明 (如：Release 直链)', url: 'https://github.com/owner/repo', hideName: true },
    { value: 'Microsoft Store', name: '商店名称或产品 ID (选填)', url: 'https://apps.microsoft.com/detail/…', hideName: false },
    { value: '第三方下载站', name: '下载方式名称 (如：蓝奏云、百度网盘)', url: '下载直链或分享网页链接 https://...', hideName: false }
  ];

  function q(el, selector) { return el.querySelector(selector); }
  function qa(el, selector) { return Array.prototype.slice.call(el.querySelectorAll(selector)); }

  /* ---------------------------------------------------------------------
   * 下载渠道编辑器
   * ------------------------------------------------------------------ */
  function channelTemplate() {
    var options = CHANNEL_TYPES.map(function (t) {
      return '<option value="' + t.value + '"' + (t.value === '第三方下载站' ? ' selected' : '') + '>' + t.value + '</option>';
    }).join('');

    return '<div class="channel-row" data-channel-row style="animation: fluentFadeIn 0.2s ease;">'
      + '<select name="channel_type[]" class="form-select channel-type-select" aria-label="渠道类型">' + options + '</select>'
      + '<input type="text" name="channel_name[]" class="form-input channel-name-input" maxlength="100"'
      + ' placeholder="下载方式名称 (如：蓝奏云、百度网盘)">'
      + '<input type="url" name="channel_url[]" class="form-input channel-url-input" maxlength="500"'
      + ' placeholder="下载直链或分享网页链接 https://...">'
      + '<button type="button" class="channel-remove" data-channel-remove title="删除该渠道" aria-label="删除该渠道">&times;</button>'
      + '</div>';
  }

  function applyChannelType(row) {
    var select = q(row, '.channel-type-select');
    var nameInput = q(row, '.channel-name-input');
    var urlInput = q(row, '.channel-url-input');
    if (!select) return;

    var meta = null;
    CHANNEL_TYPES.forEach(function (t) { if (t.value === select.value) meta = t; });
    if (!meta) return;

    if (nameInput) {
      nameInput.placeholder = meta.name;
      if (meta.hideName) {
        nameInput.value = '';
        nameInput.disabled = true;
      } else {
        nameInput.disabled = false;
      }
    }
    if (urlInput) {
      urlInput.placeholder = meta.url;
    }
  }

  function initChannels(scope) {
    qa(scope, '[data-channel-editor]').forEach(function (editor) {
      if (editor.__channelBound) return;
      editor.__channelBound = true;
      qa(editor, '[data-channel-row]').forEach(applyChannelType);
    });
  }

  function collectChannels(form) {
    var channels = [];
    qa(form, '[data-channel-editor] [data-channel-row]').forEach(function (row) {
      var typeSelect = q(row, '.channel-type-select');
      var nameInput = q(row, '.channel-name-input');
      var urlInput = q(row, '.channel-url-input');
      var url = urlInput ? urlInput.value.trim() : '';
      if (!url) return;
      channels.push({
        type: typeSelect ? typeSelect.value : '第三方下载站',
        name: (nameInput && !nameInput.disabled) ? nameInput.value.trim() : '',
        url: url
      });
    });
    return channels;
  }

  /** 把渠道写回表单字段，供 FormData 使用。 */
  function syncChannelFields(form) {
    var channels = collectChannels(form);
    // 清空上一轮生成的隐藏字段，避免重复提交
    qa(form, '[data-channel-hidden]').forEach(function (n) { n.parentNode.removeChild(n); });
    if (!channels.length) return;

    var holder = document.createElement('div');
    holder.setAttribute('data-channel-hidden', '1');
    holder.style.display = 'none';
    channels.forEach(function (c) {
      holder.appendChild(hiddenField('channel_type[]', c.type));
      holder.appendChild(hiddenField('channel_name[]', c.name));
      holder.appendChild(hiddenField('channel_url[]', c.url));
    });
    form.appendChild(holder);
  }

  function hiddenField(name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.setAttribute('data-channel-hidden', '1');
    input.name = name;
    input.value = value;
    return input;
  }

  /* ---------------------------------------------------------------------
   * 条件区块：开源协议 / 收费明细
   * ------------------------------------------------------------------ */
  function applyToggle(select) {
    var targetId = select.getAttribute('data-license-toggle');
    var group = targetId ? document.getElementById(targetId) : null;
    var licenseInput = document.querySelector('[data-license-input]');

    if (targetId) {
      var isOpen = select.value === '1';
      if (group) group.hidden = !isOpen;
      if (licenseInput) {
        licenseInput.disabled = !isOpen;
        if (!isOpen) licenseInput.value = '';
      }
      return;
    }

    targetId = select.getAttribute('data-pricing-toggle');
    group = targetId ? document.getElementById(targetId) : null;
    if (group) group.hidden = !(select.value === 'paid' || select.value === 'freemium');
  }

  /* ---------------------------------------------------------------------
   * 开源软件不收集 GitHub 地址：勾选后整组隐藏并禁用。
   * 用 disabled 而不是清空，是为了让这个字段「压根不提交」——
   * 服务端把「没提交」当作「保持库里原值」，用户勾一下开源不会把已登记的地址洗掉。
   * ------------------------------------------------------------------ */
  function applyOpenSourceRule(checkbox) {
    var form = checkbox.form || checkbox.closest('form');
    if (!form) return;
    qa(form, '[data-hide-if-open-source]').forEach(function (group) {
      var hide = !!checkbox.checked;
      group.hidden = hide;
      qa(group, 'input, textarea, select').forEach(function (field) {
        field.disabled = hide;
      });
    });
  }

  function initToggles(scope) {
    qa(scope, '[data-license-toggle], [data-pricing-toggle]').forEach(function (select) {
      if (select.__toggleBound) return;
      select.__toggleBound = true;
      applyToggle(select);
    });
    qa(scope, '[data-open-source-switch]').forEach(function (checkbox) {
      if (checkbox.__openSourceBound) return;
      checkbox.__openSourceBound = true;
      checkbox.addEventListener('change', function () { applyOpenSourceRule(checkbox); });
      applyOpenSourceRule(checkbox);
    });
  }

  /* ---------------------------------------------------------------------
   * 标签快捷选择
   * ------------------------------------------------------------------ */
  function addTag(input, tag) {
    if (!input) return;
    var current = input.value.trim();
    var arr = current ? current.split(/[,，\s]+/).filter(Boolean) : [];
    if (arr.indexOf(tag) !== -1) return;
    arr.push(tag);
    input.value = arr.slice(0, 8).join(',');
  }

  function initTagPickers(scope) {
    qa(scope, '[data-tag-picker]').forEach(function (picker) {
      if (picker.__tagBound) return;
      picker.__tagBound = true;
      picker.addEventListener('click', function (event) {
        var pill = event.target.closest('[data-tag-preset]');
        if (!pill) return;
        event.preventDefault();
        addTag(picker.parentNode.querySelector('[data-tag-input]'), pill.getAttribute('data-tag-preset'));
      });
    });
  }

  /* ---------------------------------------------------------------------
   * 客户端校验（与服务端规则保持一致，错误信息统一走自定义提示框）
   * ------------------------------------------------------------------ */
  function validate(form) {
    var name = q(form, '[name="name"]');
    var official = q(form, '[name="official_url"]');
    var icon = q(form, '[name="icon_url"]');
    var email = q(form, '[name="submitter_email"]');

    if (name && !name.value.trim()) {
      name.focus();
      return '请填写软件名称';
    }
    if (official && !validUrl(official.value)) {
      official.focus();
      return '请填写有效的官方网站地址（以 http:// 或 https:// 开头）';
    }
    if (icon && !validUrl(icon.value)) {
      icon.focus();
      return '请填写有效的软件图标直链（以 http:// 或 https:// 开头）';
    }
    if (email && email.type !== 'hidden') {
      var value = email.value.trim();
      if (!value) {
        email.focus();
        return '必须填写联系邮箱，用于接收审核结果通知';
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
        email.focus();
        return '请填写有效的邮箱地址';
      }
    }

    var invalidChannel = null;
    qa(form, '[data-channel-editor] .channel-url-input').forEach(function (input) {
      var raw = input.value.trim();
      if (raw && !validUrl(raw) && invalidChannel === null) {
        invalidChannel = input;
      }
    });
    if (invalidChannel) {
      invalidChannel.focus();
      return '下载渠道包含无效网址，请以 http:// 或 https:// 开头';
    }

    return null;
  }

  function validUrl(value) {
    var raw = (value || '').trim();
    if (!/^https?:\/\//i.test(raw)) return false;
    try {
      var url = new URL(raw);
      return !!url.hostname;
    } catch (e) {
      return false;
    }
  }

  /* ---------------------------------------------------------------------
   * 提交（弹窗走 AJAX，页面走原生跳转以保留人机验证回填）
   * ------------------------------------------------------------------ */
  function bindForms(scope) {
    qa(scope, 'form.softnav-submit-form').forEach(function (form) {
      if (form.__submitBound) return;
      form.__submitBound = true;

      initChannels(form);
      initToggles(form);
      initTagPickers(form);

      form.addEventListener('submit', function (event) {
        if (form.dataset.scope !== 'modal') {
          // 非弹窗场景仅做客户端拦截，服务端错误通过 URL 参数回显
          var error = validate(form);
          if (error) {
            event.preventDefault();
            window.FluentMessage(error, 'warning');
          }
          return;
        }

        event.preventDefault();

        var error = validate(form);
        if (error) {
          window.FluentMessage(error, 'warning');
          return;
        }

        syncChannelFields(form);

        var button = q(form, 'button[type="submit"]');
        var label = q(form, '[data-submit-label]');
        var originLabel = label ? label.textContent : '';
        if (button) button.disabled = true;
        if (label) label.textContent = '正在提交…';

        var endpoint = form.getAttribute('action');
        fetch(endpoint, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form)
        })
          .then(function (res) {
            // 先拿文本再自己解析：地址没被路由接住时（404/500/被重定向到登录页）回的是 HTML，
            // 直接 res.json() 只会抛一个看不懂的 SyntaxError，然后被下面的 catch 统一成
            // 「网络通信异常」——用户和运维都看不到真正的原因
            return res.text().then(function (text) {
              try {
                return JSON.parse(text);
              } catch (parseError) {
                var err = new Error('HTTP ' + res.status + ' 返回的不是 JSON（'
                  + (res.headers.get('content-type') || '未知类型') + '），目标 ' + endpoint);
                err.diagnostics = true;
                throw err;
              }
            });
          })
          .then(function (data) {
            if (data && data.success) {
              form.reset();
              qa(form, '[data-channel-editor] [data-channel-row]').forEach(applyChannelType);
              window.FluentMessage(data.message || '软件提交成功！已提交至待审核池，审核结果将通过邮件通知。', 'success');
              if (typeof window.closeModal === 'function') window.closeModal('submit-software-modal');
            } else {
              window.FluentMessage((data && data.message) || '提交失败，请稍后重试', 'error');
            }
          })
          .catch(function (err) {
            if (window.console) { console.error('[提交软件]', endpoint, err); }
            window.FluentMessage(err && err.diagnostics
              ? '提交失败：' + err.message
              : '网络通信异常，请稍后重试', 'error');
          })
          .finally(function () {
            if (button) button.disabled = false;
            if (label) label.textContent = originLabel;
          });
      });
    });
  }

  /** 供外部重置弹窗状态。 */
  function scan(scope) {
    var root = scope || document;
    initChannels(root);
    initToggles(root);
    initTagPickers(root);
    bindForms(root);
  }

  /* ---------------------------------------------------------------------
   * 全局事件委托
   * ------------------------------------------------------------------ */
  document.addEventListener('click', function (event) {
    var addButton = event.target.closest ? event.target.closest('[data-channel-add]') : null;
    if (addButton) {
      event.preventDefault();
      var editor = addButton.parentNode.querySelector('[data-channel-editor]');
      if (!editor) return;
      var row = document.createElement('div');
      row.innerHTML = channelTemplate();
      var node = row.firstElementChild;
      editor.appendChild(node);
      applyChannelType(node);
      var urlInput = q(node, '.channel-url-input');
      if (urlInput) urlInput.focus();
      return;
    }

    var removeButton = event.target.closest ? event.target.closest('[data-channel-remove]') : null;
    if (removeButton) {
      event.preventDefault();
      var targetRow = removeButton.closest('[data-channel-row]');
      if (!targetRow) return;
      var list = targetRow.parentNode;
      var rows = qa(list, '[data-channel-row]');
      if (rows.length > 1) {
        // 有实际内容的渠道需二次确认，避免误删已填写的下载来源
        var urlInput = q(targetRow, '.channel-url-input');
        var nameInput = q(targetRow, '.channel-name-input');
        var filled = (urlInput && urlInput.value.trim()) || (nameInput && nameInput.value.trim());
        if (filled) {
          window.FluentConfirm({
            title: '删除下载渠道',
            message: '该渠道已填写内容，确定要移除吗？',
            tone: 'warning',
            confirmText: '删除'
          }).then(function (ok) {
            if (ok && targetRow.parentNode) targetRow.parentNode.removeChild(targetRow);
          });
          return;
        }
        list.removeChild(targetRow);
      } else {
        var rowUrl = q(targetRow, '.channel-url-input');
        var rowName = q(targetRow, '.channel-name-input');
        if (rowUrl) rowUrl.value = '';
        if (rowName) rowName.value = '';
      }
      return;
    }

    var changeSelect = event.target.closest ? event.target.closest('.channel-type-select') : null;
    if (changeSelect) {
      var row = changeSelect.closest('[data-channel-row]');
      if (row) applyChannelType(row);
      return;
    }

    var cancelButton = event.target.closest ? event.target.closest('[data-submit-cancel]') : null;
    if (cancelButton) {
      event.preventDefault();
      if (typeof window.closeModal === 'function') window.closeModal('submit-software-modal');
    }
  });

  document.addEventListener('change', function (event) {
    var select = event.target.closest ? event.target.closest('[data-license-toggle], [data-pricing-toggle]') : null;
    if (select) applyToggle(select);
  });

  document.addEventListener('DOMContentLoaded', function () { scan(document); });
  scan(document);

  var originalOpenModal = window.openModal;
  if (typeof originalOpenModal === 'function') {
    window.openModal = function (modalId) {
      var result = originalOpenModal.apply(this, arguments);
      var modal = document.getElementById(modalId);
      if (modal) setTimeout(function () { scan(modal); }, 20);
      return result;
    };
  }

  window.SoftnavSubmitForm = {
    scan: scan,
    validate: validate,
    collectChannels: collectChannels
  };
})();
