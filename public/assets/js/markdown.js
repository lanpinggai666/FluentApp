/**
 * 蓝屏钙软件导航网 - Markdown 编辑器统一初始化
 *
 * 基于开源编辑器 EasyMDE (MIT License, https://github.com/Urar/ EasyMDE)，
 * 本地存放于 assets/vendor/easymde/，完全离线可用。
 *
 * 自动接管所有 <textarea class="markdown-editor">，支持：
 *  - 弹窗内懒初始化（打开弹窗时才挂载，避免首页加载卡顿）
 *  - PJAX 局部刷新后自动重扫
 *  - 与后端 Markdown 渲染语义保持一致
 */
(function () {
  'use strict';

  if (!window.EasyMDE) return;

  var instances = new WeakMap();

  var BASE_OPTIONS = {
    autoDownloadFontAwesome: false,
    autofocus: false,
    spellChecker: false,
    status: false,
    lineWrapping: true,
    placeholder: '支持 Markdown 语法…',
    forceSync: true,
    previewClass: 'md-preview',
    toolbarTips: true,
    toolbar: [
      { name: 'bold', className: 'fa fa-bold', title: '加粗 (Ctrl+B)', action: EasyMDE.toggleBold },
      { name: 'italic', className: 'fa fa-italic', title: '斜体 (Ctrl+I)', action: EasyMDE.toggleItalic },
      { name: 'strikethrough', className: 'fa fa-strikethrough', title: '删除线', action: EasyMDE.toggleStrikethrough },
      { name: 'heading', className: 'fa fa-header', title: '标题', action: EasyMDE.toggleHeadingSmaller },
      '|',
      { name: 'quote', className: 'fa fa-quote-left', title: '引用', action: EasyMDE.toggleBlockquote },
      { name: 'unordered-list', className: 'fa fa-list-ul', title: '无序列表', action: EasyMDE.toggleUnorderedList },
      { name: 'ordered-list', className: 'fa fa-list-ol', title: '有序列表', action: EasyMDE.toggleOrderedList },
      { name: 'horizontal-rule', className: 'fa fa-minus', title: '分割线', action: EasyMDE.drawHorizontalRule },
      '|',
      { name: 'link', className: 'fa fa-link', title: '链接', action: EasyMDE.drawLink },
      { name: 'image', className: 'fa fa-image', title: '图片', action: EasyMDE.drawImage },
      { name: 'code', className: 'fa fa-code', title: '代码', action: EasyMDE.toggleCodeBlock },
      { name: 'table', className: 'fa fa-table', title: '表格', action: EasyMDE.drawTable },
      '|',
      { name: 'preview', className: 'fa fa-eye', title: '实时预览', action: EasyMDE.togglePreview },
      { name: 'fullscreen', className: 'fa fa-arrows-alt', title: '全屏编辑', action: EasyMDE.toggleFullScreen },
      { name: 'side-by-side', className: 'fa fa-columns', title: '分栏预览', action: EasyMDE.toggleSideBySide },
      { name: 'undo', className: 'fa fa-undo', title: '撤销', action: EasyMDE.undo },
      { name: 'redo', className: 'fa fa-repeat', title: '重做', action: EasyMDE.redo },
      '|',
      { name: 'guide', className: 'fa fa-question-circle', title: 'Markdown 语法帮助', action: function (editor) { window.FluentMessage(MARKDOWN_GUIDE, 'info'); } }
    ],
    renderingConfig: {
      // 与后端 Markdown 渲染保持一致的安全输出
      html: false,
      breaks: true,
      linkify: false
    }
  };

  var MARKDOWN_GUIDE =
    '【标题】# 一级标题  ## 二级标题\n' +
    '【正文】**加粗** *斜体* ~~删除线~~ ==高亮== `行内代码`\n' +
    '【列表】- 无序项\n' +
    '        1. 有序项\n' +
    '【引用】> 引用内容\n' +
    '【代码】```\n        code block\n        ```\n' +
    '【链接】[名称](https://example.com)\n' +
    '【图片】![说明](https://example.com/a.png)\n' +
    '【分隔线】---';

  function isInsideHidden(textarea) {
    // offsetParent 为 null 说明任一祖先（如未打开的弹窗）处于 display:none，
    // 此时 CodeMirror 会以 0 宽度布局导致样式错乱，留待弹窗打开时再构建
    if (!textarea.offsetParent) return true;
    var node = textarea;
    while (node && node !== document.body) {
      if (node.style && node.style.display === 'none') return true;
      if (node.hasAttribute && node.hasAttribute('hidden')) return true;
      node = node.parentNode;
    }
    return false;
  }

  function build(textarea) {
    if (instances.has(textarea)) return instances.get(textarea);
    if (typeof EasyMDE === 'undefined') return null;

    var options = Object.assign({}, BASE_OPTIONS, {
      element: textarea,
      placeholder: textarea.getAttribute('placeholder') || BASE_OPTIONS.placeholder,
      minHeight: textarea.getAttribute('rows') ? (parseInt(textarea.getAttribute('rows'), 10) * 24 + 24) + 'px' : '220px',
      maxHeight: '520px'
    });

    var mde = new EasyMDE(options);
    instances.set(textarea, mde);
    textarea.setAttribute('data-md-ready', '1');
    return mde;
  }

  function scan(root) {
    var scope = root || document;
    var list = scope.querySelectorAll('textarea.markdown-editor');
    Array.prototype.forEach.call(list, function (textarea) {
      if (textarea.hasAttribute('data-md-ready')) return;
      if (isInsideHidden(textarea)) return;
      build(textarea);
    });
  }

  // 弹窗首次打开时再挂载编辑器，避免首屏加载不必要的重量
  var originalOpenModal = window.openModal;
  if (typeof originalOpenModal === 'function') {
    window.openModal = function (modalId) {
      var result = originalOpenModal.apply(this, arguments);
      var modal = document.getElementById(modalId);
      if (modal) {
        setTimeout(function () { scan(modal); }, 30);
      }
      return result;
    };
  }

  // PJAX 局部替换后重新扫描
  if (typeof window.__onPjaxRender === 'function') {
    var originalPjaxRender = window.__onPjaxRender;
    window.__onPjaxRender = function () {
      var result = originalPjaxRender.apply(this, arguments);
      setTimeout(function () { scan(document); }, 0);
      return result;
    };
  }

  document.addEventListener('DOMContentLoaded', function () { scan(document); });
  scan(document);

  // 暴露给需要主动调用的场景
  window.FluentMarkdown = {
    scan: scan,
    build: build,
    get: function (textarea) { return instances.get(textarea) || null; },
    guide: MARKDOWN_GUIDE
  };
})();
