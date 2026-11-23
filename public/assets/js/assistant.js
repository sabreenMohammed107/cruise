/* Safer Cruise chat assistant (resources/views/partials/assistant-widget.blade.php).
 * Sends the visitor's message to POST /assistant/message and shows the reply. The conversation itself lives on the
 * server (AssistantController keeps it in the session); this script only keeps what is on screen.
 *
 * What is on screen is also kept in localStorage, so it comes back after a page load or on the next page, for as long
 * as the server says it is the same chat (data-chat, AssistantController::chatId: a new chat, signing in or out → a
 * different id → the old messages are dropped). The panel is open, minimized (title bar only) or closed, remembered too.
 */
(function () {
  'use strict';

  var root = document.getElementById('sc-assistant');
  if (!root) return;

  var panel = root.querySelector('#sc-assistant-panel');
  var openBtn = root.querySelector('[data-assistant-open]');
  var log = root.querySelector('[data-assistant-log]');
  var form = root.querySelector('[data-assistant-form]');
  var input = form.querySelector('textarea');
  var sendBtn = form.querySelector('button[type="submit"]');
  var suggestions = root.querySelector('[data-assistant-suggestions]');
  var welcome = log.innerHTML;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var busy = false;
  var t = {};
  try { t = JSON.parse(root.dataset.strings || '{}'); } catch (e) { t = {}; }
  var ar = document.documentElement.lang === 'ar';
  var minBtn = root.querySelector('[data-assistant-min]');
  var CHAT_KEY = 'sc-assistant-chat';
  var STATE_KEY = 'sc-assistant-state';
  var chatId = root.dataset.chat || '';
  var history = [];
  var restoring = false;
  // Photos (data-images: the longest side to shrink to; only for the accounts allowed to send them). The photo is
  // sent with the next message and never kept, here or on the server.
  var maxSide = parseInt(root.dataset.images || '0', 10);
  var fileInput = root.querySelector('[data-assistant-file]');
  var preview = root.querySelector('[data-assistant-preview]');
  var pendingImage = null;

  function storeGet(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }
  function storeSet(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* full or blocked: only this page keeps it */ } }
  function storeDel(key) { try { window.localStorage.removeItem(key); } catch (e) { /* blocked */ } }

  // One thing shown in the chat: {k: 'user'|'bot'|'report'|'choices'|'booking', text?, error?, ui?, done?}
  function remember(item) {
    if (restoring) return;
    history.push(item);
    if (chatId) storeSet(CHAT_KEY, JSON.stringify({ chat: chatId, items: history.slice(-80) }));
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text != null) node.textContent = text;
    return node;
  }

  function scroll() { log.scrollTop = log.scrollHeight; }

  // Buttons (one answer) or checkboxes + confirm (several answers), from the offer_choices tool
  function renderChoices(ui) {
    remember({ k: 'choices', ui: ui });
    var box = el('div', 'grid gap-2');
    box.dataset.choices = '1';
    if (ui.question) box.appendChild(el('div', 'sc-msg sc-msg-bot', ui.question));
    var wrap = el('div', 'sc-choices');
    if (ui.multiple) {
      ui.options.forEach(function (o) {
        var label = el('label', 'sc-check');
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = o;
        cb.checked = (ui.selected || []).indexOf(o) !== -1;
        label.appendChild(cb);
        label.appendChild(document.createTextNode(o));
        wrap.appendChild(label);
      });
      var ok = el('button', 'sc-choice', t.confirm || 'OK');
      ok.type = 'button';
      ok.style.background = '#0e5a85';
      ok.style.color = '#fff';
      ok.addEventListener('click', function () {
        var picked = [].slice.call(wrap.querySelectorAll('input:checked')).map(function (c) { return c.value; });
        if (picked.length) send(picked.join(ar ? '، ' : ', '));
      });
      wrap.appendChild(ok);
    } else {
      ui.options.forEach(function (o) {
        var b = el('button', 'sc-choice', o);
        b.type = 'button';
        b.addEventListener('click', function () { send(o); });
        wrap.appendChild(b);
      });
    }
    box.appendChild(wrap);
    log.appendChild(box);
  }

  // Older buttons stop working once the visitor has answered
  function freezeChoices() {
    [].forEach.call(log.querySelectorAll('[data-choices] button, [data-choices] input'), function (c) { c.disabled = true; });
  }

  function renderReport(ui) {
    remember({ k: 'report', ui: ui });
    var card = el('div', 'sc-report');
    var head = el('div', 'sc-report-head');
    head.appendChild(el('p', 'font-bold text-sm', ui.title));
    head.appendChild(el('p', 'text-xs text-slate-500', ui.count_label + ' · ' + (t.usd || 'USD')));
    card.appendChild(head);

    if (!ui.count) {
      card.appendChild(el('p', 'p-3 text-sm text-slate-500', t.empty || '—'));
    } else {
      var scrollBox = el('div', 'sc-report-scroll');
      var table = document.createElement('table');
      var num = function (c) { return c.type === 'money' || c.type === 'int'; };
      var tr = document.createElement('tr');
      ui.columns.forEach(function (c) { tr.appendChild(el('th', num(c) ? 'num' : '', c.label)); });
      var thead = document.createElement('thead');
      thead.appendChild(tr);
      table.appendChild(thead);
      var tbody = document.createElement('tbody');
      ui.rows.forEach(function (row) {
        var r = document.createElement('tr');
        row.forEach(function (v, i) { var td = el('td', num(ui.columns[i]) ? 'num' : '', v); td.dir = 'auto'; r.appendChild(td); });
        tbody.appendChild(r);
      });
      if (ui.totals.some(function (v) { return v !== ''; })) {
        var tot = el('tr', 'total');
        ui.totals.forEach(function (v, i) { tot.appendChild(el('td', v !== '' ? 'num' : '', v !== '' ? v : (i === 0 ? (t.total || 'Total') : ''))); });
        tbody.appendChild(tot);
      }
      table.appendChild(tbody);
      scrollBox.appendChild(table);
      card.appendChild(scrollBox);
      if (ui.note) card.appendChild(el('p', 'px-3 py-2 text-xs text-slate-500', ui.note));
    }

    var actions = el('div', 'sc-report-actions');
    var excel = el('a', 'primary', t.excel || 'Excel');
    excel.href = ui.excel_url;
    var pdf = el('a', '', t.pdf || 'PDF');
    pdf.href = ui.print_url + '?autoprint=1';
    pdf.target = '_blank';
    pdf.rel = 'noopener';
    var save = el('button', '', t.save || 'Save');
    save.type = 'button';
    save.addEventListener('click', function () {
      var name = window.prompt(t.savePrompt || 'Name:', ui.title.split(' · ')[0]);
      if (!name || !name.trim()) return;
      post(ui.template_url, { name: name.trim().slice(0, 80) })
        .then(function (res) { return res.json().catch(function () { return {}; }); })
        .then(function (data) { add('bot', data.message || root.dataset.error, data.ok ? '' : 'sc-msg-error'); })
        .catch(function () { add('bot', root.dataset.error, 'sc-msg-error'); });
    });
    actions.appendChild(excel);
    actions.appendChild(pdf);
    actions.appendChild(save);
    card.appendChild(actions);
    log.appendChild(card);
  }

  // Booking summary from the prepare_booking tool: only this button books (POST /assistant/booking)
  function renderBooking(ui, done) {
    var item = { k: 'booking', ui: ui, done: !!done };
    remember(item);
    var card = el('div', 'sc-report');
    var head = el('div', 'sc-report-head');
    head.appendChild(el('p', 'font-bold text-sm', ui.title));
    card.appendChild(head);
    var body = el('div', 'px-3 py-2 text-xs');
    (ui.lines || []).forEach(function (line) { var p = el('p', 'mb-1', line); p.dir = 'auto'; body.appendChild(p); });
    body.appendChild(el('p', 'font-bold text-sm mt-2', ui.total));
    body.appendChild(el('p', 'text-slate-500 mt-1', ui.terms));
    card.appendChild(body);
    var actions = el('div', 'sc-report-actions');
    var ok = el('button', 'primary', ui.confirm);
    ok.type = 'button';
    ok.disabled = !!done;
    ok.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      ok.disabled = true;
      sendBtn.disabled = true;
      freezeChoices();
      var thinking = add('bot', root.dataset.thinking, '', false);
      thinking.classList.add('animate-pulse');
      post(ui.confirm_url, { id: ui.id })
        .then(function (res) {
          if (res.status === 429) return { reply: root.dataset.tooMany, error: true };
          return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok) { data.error = true; data.reply = data.reply || root.dataset.error; }
            return data;
          });
        })
        .catch(function () { return { reply: root.dataset.error, error: true }; })
        .then(function (data) {
          thinking.remove();
          // Pressed once, whatever happened: a summary books at most once (a new one is needed to try again)
          history.forEach(function (h) { if (h.k === 'booking' && h.ui && h.ui.id === ui.id) h.done = true; });
          if (chatId) storeSet(CHAT_KEY, JSON.stringify({ chat: chatId, items: history.slice(-80) }));
          add('bot', data.reply || root.dataset.error, data.error ? 'sc-msg-error' : '');
          scroll();
        })
        .finally(function () {
          busy = false;
          sendBtn.disabled = false;
        });
    });
    actions.appendChild(ok);
    card.appendChild(actions);
    log.appendChild(card);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function link(url, text) {
    var external = url.indexOf(location.origin) !== 0;
    return '<a href="' + url + '"' + (external ? ' target="_blank" rel="noopener"' : '') + '>' + text + '</a>';
  }

  // Text from the assistant → safe HTML: escaped, then **bold**, [text](https://…) and bare https:// links.
  function format(text) {
    var links = [];
    var html = escapeHtml(text)
      .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (_, label, url) {
        links.push(link(url, label));
        return '\u0000' + (links.length - 1) + '\u0000';
      })
      .replace(/https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"]/g, function (url) {
        links.push(link(url, url.replace(/^https?:\/\//, '').slice(0, 48) + (url.length > 56 ? '…' : '')));
        return '\u0000' + (links.length - 1) + '\u0000';
      })
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/^#{1,4}\s*/gm, '');
    return html.replace(/\u0000(\d+)\u0000/g, function (_, i) { return links[+i]; });
  }

  function add(role, text, extraClass, keep) {
    if (keep !== false) remember({ k: role, text: text, error: extraClass === 'sc-msg-error' });
    var el = document.createElement('div');
    el.className = 'sc-msg ' + (role === 'user' ? 'sc-msg-user' : 'sc-msg-bot') + (extraClass ? ' ' + extraClass : '');
    if (role === 'user') el.textContent = text; else el.innerHTML = format(text);
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  // 'open' | 'min' (title bar only, the chat kept) | 'closed' (the round button)
  function setState(state, focus) {
    var shown = state !== 'closed';
    panel.hidden = !shown;
    panel.classList.toggle('sc-min', state === 'min');
    openBtn.hidden = shown;
    openBtn.setAttribute('aria-expanded', state === 'open' ? 'true' : 'false');
    minBtn.setAttribute('aria-expanded', state === 'open' ? 'true' : 'false');
    var fab = document.getElementById('wa-fab');
    if (fab) fab.style.visibility = shown && window.innerWidth < 640 ? 'hidden' : '';
    storeSet(STATE_KEY, state);
    if (state === 'open') { scroll(); if (focus !== false) setTimeout(function () { input.focus(); }, 50); }
  }
  function setOpen(open) { setState(open ? 'open' : 'closed'); }

  // The chat of this visit from an earlier page, if it is still the same chat on the server
  function restore() {
    var saved = null;
    try { saved = JSON.parse(storeGet(CHAT_KEY) || 'null'); } catch (e) { saved = null; }
    if (!saved || !chatId || saved.chat !== chatId || !Array.isArray(saved.items) || !saved.items.length) {
      storeDel(CHAT_KEY);
      return;
    }
    if (suggestions) suggestions.remove();
    suggestions = null;
    history = saved.items;
    restoring = true;
    try {
      history.forEach(function (item, i) {
        if (item.k === 'report' && item.ui) renderReport(item.ui);
        else if (item.k === 'booking' && item.ui) renderBooking(item.ui, item.done);
        else if (item.k === 'choices' && item.ui) {
          // Only the last question can still be answered
          if (i !== history.length - 1) freezeChoices();
          renderChoices(item.ui);
          if (i !== history.length - 1) freezeChoices();
        } else if (item.k === 'user' || item.k === 'bot') add(item.k, item.text || '', item.error ? 'sc-msg-error' : '');
      });
    } finally {
      restoring = false;
    }
    scroll();
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    });
  }

  // The chosen photo, shrunk so its longest side is at most maxSide, as a JPEG data URL
  function shrink(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        var scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        resolve(canvas.toDataURL('image/jpeg', 0.85));
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(); };
      img.src = url;
    });
  }

  function setImage(dataUrl) {
    pendingImage = dataUrl;
    if (!preview) return;
    preview.hidden = !dataUrl;
    preview.querySelector('[data-assistant-preview-img]').src = dataUrl || '';
  }

  function send(text) {
    text = (text || '').trim();
    var image = pendingImage;
    if ((!text && !image) || busy) return;
    busy = true;
    sendBtn.disabled = true;
    if (suggestions) suggestions.remove();
    suggestions = null;
    freezeChoices();
    // The photo itself isn't shown again (nor kept): a label stands for it
    add('user', image ? '📷 ' + (t.imageSent || 'Photo') + (text ? '\n' + text : '') : text);
    input.value = '';
    setImage(null);
    resize();
    var thinking = add('bot', root.dataset.thinking, '', false);
    thinking.classList.add('animate-pulse');

    post(root.dataset.url, image ? { message: text, image: image } : { message: text })
      .then(function (res) {
        if (res.status === 429) return { reply: root.dataset.tooMany, error: true };
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok && !data.reply) data = { reply: root.dataset.error, error: true };
          return data;
        });
      })
      .catch(function () { return { reply: root.dataset.error, error: true }; })
      .then(function (data) {
        thinking.remove();
        if (data.chat && data.chat !== chatId) {
          // The first answer of a chat names it: keep what is already on screen under that name
          chatId = data.chat;
          var shown = history;
          history = [];
          shown.forEach(remember);
        }
        var ui = Array.isArray(data.ui) ? data.ui : [];
        ui.filter(function (u) { return u.type === 'report'; }).forEach(renderReport);
        if (data.reply || !ui.length) add('bot', data.reply || root.dataset.error, data.error ? 'sc-msg-error' : '');
        ui.filter(function (u) { return u.type === 'booking'; }).forEach(function (u) { renderBooking(u); });
        ui.filter(function (u) { return u.type === 'choices'; }).forEach(renderChoices);
        scroll();
      })
      .finally(function () {
        busy = false;
        sendBtn.disabled = false;
        input.focus();
      });
  }

  function resize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 128) + 'px';
  }

  function bindSuggestions() {
    if (!suggestions) return;
    suggestions.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (b) send(b.textContent);
    });
  }

  openBtn.addEventListener('click', function () { setOpen(true); });
  root.querySelector('[data-assistant-close]').addEventListener('click', function () { setOpen(false); openBtn.focus(); });
  minBtn.addEventListener('click', function () { setState(panel.classList.contains('sc-min') ? 'open' : 'min'); });
  root.querySelector('[data-assistant-title]').addEventListener('click', function () {
    if (panel.classList.contains('sc-min')) setState('open');
  });
  root.querySelector('[data-assistant-new]').addEventListener('click', function () {
    if (busy) return;
    post(root.dataset.resetUrl).finally(function () {
      history = [];
      chatId = '';
      storeDel(CHAT_KEY);
      log.innerHTML = welcome;
      suggestions = log.querySelector('[data-assistant-suggestions]');
      bindSuggestions();
      input.focus();
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hidden && !panel.classList.contains('sc-min')) setState('min');
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); }
  });
  input.addEventListener('input', resize);
  if (maxSide && fileInput) {
    root.querySelector('[data-assistant-attach]').addEventListener('click', function () { if (!busy) fileInput.click(); });
    var useFile = function (file) {
      if (!file || busy) return;
      shrink(file).then(function (dataUrl) {
        setImage(dataUrl);
        input.focus();
      }, function () {
        add('bot', t.imageInvalid || root.dataset.error, 'sc-msg-error');
        scroll();
      });
    };
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      fileInput.value = '';
      useFile(file);
    });
    // A copied photo pasted in the message box (Ctrl+V): it becomes the photo to send; pasted text stays text
    input.addEventListener('paste', function (e) {
      var items = (e.clipboardData && e.clipboardData.items) || [];
      for (var i = 0; i < items.length; i++) {
        if (items[i].kind === 'file' && /^image\//.test(items[i].type)) {
          e.preventDefault();
          useFile(items[i].getAsFile());
          return;
        }
      }
    });
    // Or dropped on the chat
    panel.addEventListener('dragover', function (e) {
      if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) e.preventDefault();
    });
    panel.addEventListener('drop', function (e) {
      var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (file && /^image\//.test(file.type)) {
        e.preventDefault();
        useFile(file);
      }
    });
    root.querySelector('[data-assistant-remove-image]').addEventListener('click', function () { setImage(null); input.focus(); });
  }
  bindSuggestions();
  restore();
  var state = storeGet(STATE_KEY);
  if (state === 'open' || state === 'min') setState(state, false);
})();
