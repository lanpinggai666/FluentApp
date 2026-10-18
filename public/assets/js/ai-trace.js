/**
 * 后台「AI 智能补全」的实时对话面板。
 *
 * 点下去之后以前只有一个转圈圈：AI 那 20 秒里到底在换线路、在搜索、还是在读网页，
 * 全看不见，只能猜。这里把同一轮补全的事件流（core/AiTrace）每 600ms 拉一次增量，
 * 渲染成「系统 / AI / 工具 / 结果」几类气泡。
 *
 * 只做展示，不改语义：真正跑补全的仍然是那一个 POST。JS 不在、fetch 失败、
 * 浏览器太旧，都原样退回默认的表单提交（后台功能不依赖这段脚本）。
 *
 * 两个入口的接法不一样，是有意的：
 *   - 【全部软件】的 AI / 覆盖：表单没有页面级脚本，这里直接接管 submit 事件；
 *   - 【待审核】的「AI 补全后发布」：页面自己已经有确认框，且最后调的是 form.submit()
 *     （程序化提交不会再触发 submit 事件），所以由那边显式调 SoftnavAi.run()，
 *     免得两边各提交一次。
 */
(function () {
  'use strict';

  var KIND_LABEL = {
    start: ['系统', 'sys'], plan: ['系统', 'sys'], switch: ['系统', 'sys'],
    request: ['系统', 'sys'], assistant: ['AI', 'ai'], answer: ['AI', 'ai'],
    tool: ['工具', 'tool'], retry: ['提示', 'warn'], applied: ['结果', 'ok'],
    fallback: ['提示', 'warn'], error: ['失败', 'err'], done: ['结果', 'ok']
  };

  var POLL_MS = 600;
  // 轮询自己的兜底时限：比 ai_total_timeout 的默认 20s 宽得多，
  // 只用来兜「POST 一开局就死了、事件文件永远等不到」这种情况，别真的把面板吊在那里
  var POLL_DEADLINE_MS = 75000;
  var active = null;

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = String(str == null ? '' : str);
    return d.innerHTML;
  }

  /** 轮询地址从表单 action 换一段：伪静态与 ?r= 两种形态都能对上 admin/aiTrace */
  function traceUrl(action) {
    return String(action).replace(/(?:aiRetry|review)(?=$|[?&#/])/, 'aiTrace');
  }

  function newRun() {
    var c = window.crypto || window.msCrypto;
    if (!c || !c.getRandomValues) { return ''; }
    var b = new Uint8Array(12);
    c.getRandomValues(b);
    var out = '';
    for (var i = 0; i < b.length; i++) { out += ('0' + b[i].toString(16)).slice(-2); }
    return out;
  }

  function chips(meta) {
    var out = [];
    if (meta.fields && meta.fields.length) { out.push('待补：' + meta.fields.join('、')); }
    if (meta.providers && meta.providers.length) { out.push('线路：' + meta.providers.join(' → ')); }
    if (meta.args) { out.push(String(meta.args)); }
    if (meta.tokens) { out.push(meta.tokens + ' tokens'); }
    if (meta.chars) { out.push(meta.chars + ' 字'); }
    if (meta.filled && meta.filled.length) { out.push('已写：' + meta.filled.join('、')); }
    if (meta.reason) { out.push(String(meta.reason)); }
    if (meta.reasons && meta.reasons.length) { out.push(meta.reasons.join('；')); }
    if (meta.seconds) { out.push('耗时 ' + meta.seconds + 's'); }
    return out.map(function (t) { return '<span class="ai-trace-chip">' + esc(t) + '</span>'; }).join('');
  }

  function append(p, ev) {
    var map = KIND_LABEL[ev.kind] || ['系统', 'sys'];
    var row = document.createElement('div');
    row.className = 'ai-trace-row ai-trace-' + map[1];
    row.innerHTML = '<span class="ai-trace-who">' + esc(map[0]) + '</span>' +
      '<span class="ai-trace-text">' + esc(ev.text) + '</span>' + chips(ev.meta || {});
    p.body.appendChild(row);
    p.body.scrollTop = p.body.scrollHeight;
  }

  function buildPanel(title) {
    var wrap = document.createElement('div');
    wrap.className = 'ai-trace-overlay';
    wrap.innerHTML =
      '<div class="ai-trace-card" role="dialog" aria-live="polite" aria-label="AI 补全过程">' +
      '  <div class="ai-trace-head">' +
      '    <span class="ai-trace-dot"></span>' +
      '    <strong class="ai-trace-title">' + esc(title) + '</strong>' +
      '    <span class="ai-trace-clock">0.0s</span>' +
      '    <button type="button" class="ai-trace-close" title="收起面板（后台仍在继续，不会中断）">×</button>' +
      '  </div>' +
      '  <div class="ai-trace-body"></div>' +
      '  <div class="ai-trace-foot">' +
      '    <span class="ai-trace-hint">正在等待线路应答，可随时收起，不影响结果</span>' +
      '    <button type="button" class="ai-trace-refresh" hidden>刷新列表</button>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(wrap);
    return {
      wrap: wrap,
      body: wrap.querySelector('.ai-trace-body'),
      clock: wrap.querySelector('.ai-trace-clock'),
      dot: wrap.querySelector('.ai-trace-dot'),
      hint: wrap.querySelector('.ai-trace-hint'),
      refresh: wrap.querySelector('.ai-trace-refresh'),
      close: wrap.querySelector('.ai-trace-close')
    };
  }

  function stop(state) {
    if (state.timer) { clearInterval(state.timer); state.timer = null; }
  }

  function openPanel(title, action, run) {
    if (active) {
      stop(active);
      if (active.p.wrap.parentNode) { active.p.wrap.remove(); }
    }
    var p = buildPanel(title);
    var url = traceUrl(action);
    var state = { p: p, next: 0, timer: null, closed: false, done: false, t0: Date.now() };
    active = state;

    (function tick() {
      if (state.done || state.closed) { return; }
      p.clock.textContent = ((Date.now() - state.t0) / 1000).toFixed(1) + 's';
      requestAnimationFrame(tick);
    })();

    var poll = function () {
      fetch(url + '?run=' + encodeURIComponent(run) + '&after=' + state.next, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { events: [], done: true, stale: true }; })
        .then(function (data) {
          (data.events || []).forEach(function (ev) {
            if (ev.seq > state.next) { append(p, ev); }
          });
          if (data.next) { state.next = data.next; }
          if (data.done || data.stale) {
            state.done = true;
            stop(state);
            p.dot.classList.add(data.done ? 'is-idle' : 'is-stale');
            if (data.stale && !data.done) {
              p.hint.textContent = '这一轮没有回音（可能被 PHP 执行时限掐断）。稍后刷新列表确认结果。';
            }
          } else if (Date.now() - state.t0 > POLL_DEADLINE_MS) {
            state.done = true;
            stop(state);
            p.dot.classList.add('is-stale');
            p.hint.textContent = '面板先停了（超过 75 秒没等到结论）。结果以刷新列表为准。';
          }
        })
        .catch(function () { /* 单次轮询失败不终止，下一轮接着问 */ });
    };
    state.timer = setInterval(poll, POLL_MS);
    poll();

    p.close.addEventListener('click', function () {
      state.closed = true;
      stop(state);
      p.wrap.remove();
      if (active === state) { active = null; }
    });
    p.refresh.addEventListener('click', function () { location.reload(); });
    return state;
  }

  function markResult(state, ok, note) {
    var p = state.p;
    if (!state.closed) {
      append(p, { kind: ok ? 'done' : 'error', text: note || (ok ? '补全完成' : '补全失败') });
    }
    p.hint.textContent = note || '已结束';
    p.refresh.hidden = false;
    p.dot.classList.add(ok ? 'is-ok' : 'is-err');
  }

  /**
   * 发起一次「带对话面板」的补全提交。
   * @param {HTMLFormElement} form  后台那张 AI 表单（action 指向 admin/aiRetry 或 admin/review）
   * @param {string} title          面板标题
   * @param {object} [opts]         { actionValue: 程序化提交要显式带上的 action 字段,
   *                                 submitter: 用户点的那颗提交按钮 }
   * @returns {boolean} 接管成功返回 true；环境不支持就 false，调用方回退到原生提交
   */
  function run(form, title, opts) {
    opts = opts || {};
    if (!window.fetch || !window.FormData) { return false; }
    var runId = newRun();
    if (runId === '' || !form.getAttribute('action')) { return false; }

    // FormData(form) 不含「被点的那颗按钮」，而【覆盖】这颗偏偏带着 overwrite_ai=1 ——
    // 漏掉它就会在用户以为在「覆盖重生成」时，实际跑成只补空缺。
    var fd = new FormData(form);
    if (opts.submitter && opts.submitter.name) {
      fd.append(opts.submitter.name, opts.submitter.value || '1');
    }
    fd.append('ai_trace', runId);
    if (opts.actionValue) { fd.set('action', opts.actionValue); }

    var state = openPanel(title, form.getAttribute('action'), runId);

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (res) { markResult(state, res.success !== false, res.note); })
      .catch(function () {
        markResult(state, false, '请求没能回来（网络或执行时限）。后台可能仍在跑，稍后刷新列表确认。');
      });
    return true;
  }

  window.SoftnavAi = { run: run };

  // 【全部软件】的 AI / 覆盖：表单没有别的页面脚本，直接接管提交。
  // 必须拿到 event.submitter —— 否则「覆盖」那颗按钮的 overwrite_ai 值会被 FormData 丢掉。
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.getAttribute) { return; }
    if (!/aiRetry/.test(form.getAttribute('action') || '')) { return; }
    if (!e.submitter) { return; }                 // 老浏览器：交给原生提交
    var overwrite = e.submitter.name === 'overwrite_ai';
    e.preventDefault();
    if (run(form, overwrite ? 'AI 覆盖重生成' : 'AI 智能补全', { submitter: e.submitter })) { return; }
    form.submit();
  }, true);
})();
