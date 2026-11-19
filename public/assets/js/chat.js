/* Messagerie : envoi, réponses, émojis, pièces jointes, notes vocales, coches de lecture */
(function () {
  'use strict';
  var box = document.querySelector('[data-chat]');
  if (!box) return;
  var q = function (a) { return box.querySelector(a); };
  var log = q('[data-chat-log]'), form = q('[data-chat-form]'), ta = form.elements.body;
  var fileIn = q('[data-chat-file]'), camIn = q('[data-chat-camfile]'), recBox = q('[data-chat-rec]'), main = q('[data-chat-main]');
  var replyId = q('[data-reply-id]'), replyBar = q('[data-reply-bar]'), panel = q('[data-emoji-panel]');
  var last = parseInt(box.dataset.last, 10) || 0, url = box.dataset.url, sep = url.indexOf('?') > -1 ? '&' : '?';
  var seen = {};
  log.querySelectorAll('.wa-msg').forEach(function (m) { seen[m.dataset.id] = 1; });
  var down = function () { log.scrollTop = log.scrollHeight; };

  // --- réactions et fils
  var csrf = form.elements._csrf.value, thId = 0, rxCache = {};
  var th = q('[data-th]'), thLog = q('[data-th-log]'), thForm = q('[data-th-form]'), pick = q('[data-rx-pick]'), pickFor = 0;
  var setRx = function (box, list) {
    box.textContent = '';
    (list || []).forEach(function (r) {
      var b = el('button', 'rx-chip' + (r.mine ? ' mine' : '')); b.type = 'button'; b.dataset.react = r.e; b.title = (r.who || []).join(', ');
      b.appendChild(document.createTextNode(r.e + ' ')); b.appendChild(el('b', '', String(r.n))); box.appendChild(b);
    });
    var a = el('button', 'rx-add', '☺︎+'); a.type = 'button'; a.dataset.rxAdd = '1'; a.setAttribute('aria-label', 'Ajouter une réaction'); box.appendChild(a);
  };
  var setRc = function (b, n) {
    b.hidden = !n; b.textContent = '🧵 ' + n + ' réponse' + (n > 1 ? 's' : '');
  };
  var applyMeta = function (j) {
    var rx = j.rx || {}, rc = j.rc || {};
    box.querySelectorAll('.wa-log .wa-msg[data-id]').forEach(function (m) {
      var id = m.dataset.id, key = JSON.stringify(rx[id] || []);
      if (rxCache[id] !== key) { rxCache[id] = key; box.querySelectorAll('[data-rx="' + id + '"]').forEach(function (x) { setRx(x, rx[id] || []); }); }
      var tb = m.querySelector('.wa-thread'); if (tb) { var n = rc[id] || 0; if (tb.dataset.n !== String(n)) { tb.dataset.n = n; setRc(tb, n); } }
    });
  };
  var clean = function (n) { var c = n.cloneNode(true); c.classList.remove('flash'); c.querySelectorAll('.wa-reply,.wa-thread' + (n.dataset.replyTo ? ',.wa-quote' : '')).forEach(function (x) { x.remove(); }); return c; };
  var showThread = function (id, keepBottom) {
    var p = log.querySelector('.wa-msg[data-id="' + id + '"]'); if (!p) return;
    thId = +id; thLog.textContent = '';
    var pc = clean(p); pc.classList.add('wa-th-parent'); thLog.appendChild(pc);
    var reps = log.querySelectorAll('.wa-msg[data-reply-to="' + id + '"]');
    thLog.appendChild(el('div', 'wa-th-sep', reps.length ? reps.length + ' réponse' + (reps.length > 1 ? 's' : '') : 'Aucune réponse pour l\'instant'));
    reps.forEach(function (r) { thLog.appendChild(clean(r)); });
    th.hidden = false; box.classList.add('th-open');
    thLog.scrollTop = keepBottom || reps.length ? thLog.scrollHeight : 0;
  };
  var hideThread = function () { thId = 0; th.hidden = true; box.classList.remove('th-open'); };
  var react = function (id, emoji) {
    var d = new FormData(); d.append('_csrf', csrf); d.append('action', 'react'); d.append('id', id); d.append('emoji', emoji);
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: d }).then(function () { poll(true); }).catch(function () {});
  };
  var openPick = function (id, anchor) {
    pickFor = +id; pick.hidden = false;
    var r = anchor.getBoundingClientRect(), bx = box.getBoundingClientRect();
    var left = Math.max(6, Math.min(bx.width - pick.offsetWidth - 6, r.left - bx.left));
    var top = r.top - bx.top - pick.offsetHeight - 6; if (top < 60) top = r.bottom - bx.top + 6;
    pick.style.left = left + 'px'; pick.style.top = top + 'px';
  };
  box.addEventListener('click', function (e) {
    var t;
    if ((t = e.target.closest('.rx-chip'))) { react(t.closest('[data-rx]').dataset.rx, t.dataset.react); return; }
    if ((t = e.target.closest('[data-rx-add]'))) { e.stopPropagation(); openPick(t.closest('[data-rx]').dataset.rx, t); return; }
    if ((t = e.target.closest('[data-pick]'))) { if (pickFor) react(pickFor, t.dataset.pick); pick.hidden = true; return; }
    if (e.target.closest('[data-pk-reply]')) { var m = log.querySelector('.wa-msg[data-id="' + pickFor + '"]'); pick.hidden = true; if (m) setReply(m); return; }
    if (e.target.closest('[data-pk-thread]')) { pick.hidden = true; showThread(pickFor); thForm.elements[0].focus(); return; }
    if ((t = e.target.closest('[data-thread]'))) { showThread(t.dataset.thread); return; }
    if (e.target.closest('[data-th-x]')) { hideThread(); return; }
    if (!e.target.closest('[data-rx-pick]')) pick.hidden = true;
  });
  // appui long (mobile) ou clic droit : réactions
  var lp = null;
  log.addEventListener('touchstart', function (e) { var m = e.target.closest('.wa-msg'); if (!m || e.target.closest('a,button,audio')) return; lp = setTimeout(function () { lp = null; if (navigator.vibrate) navigator.vibrate(15); openPick(m.dataset.id, m); }, 480); }, { passive: true });
  ['touchend', 'touchmove', 'touchcancel'].forEach(function (ev) { log.addEventListener(ev, function () { if (lp) { clearTimeout(lp); lp = null; } }, { passive: true }); });
  log.addEventListener('contextmenu', function (e) { var m = e.target.closest('.wa-msg'); if (!m || e.target.closest('a,img,audio')) return; e.preventDefault(); openPick(m.dataset.id, m); });
  // réponse dans le fil
  thForm.addEventListener('submit', function (e) {
    e.preventDefault(); var ta = thForm.elements[0], v = ta.value.trim(); if (!v || !thId) return;
    var d = new FormData(); d.append('_csrf', csrf); d.append('body', v); d.append('reply_to', thId); ta.value = '';
    send(d, function () { ta.value = v; });
  });
  thForm.elements[0].addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 900) { e.preventDefault(); thForm.requestSubmit(); } });

  // --- traduction (back-office uniquement)
  var trOn = box.dataset.tr === '1';
  var trPost = function (data) { data.append('_csrf', csrf); return fetch('/admin/traduire', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: data }).then(function (r) { return r.json(); }); };
  var LC = { 'français': 'FR', 'anglais': 'EN', 'espagnol': 'ES', 'portugais': 'PT', 'arabe': 'AR', 'chinois': 'ZH', 'allemand': 'DE', 'italien': 'IT' };
  var lc = function (n) { return LC[n] || String(n || '').slice(0, 2).toUpperCase(); };
  // petite icône 🌐 discrète, dans la ligne de l'heure
  var addTrBtn = function (msg) {
    if (!trOn || !msg.querySelector('.wa-txt') || msg.querySelector('.wa-trb')) return;
    var b = el('button', 'wa-trb', '🌐'); b.type = 'button'; b.title = 'Traduire (français ↔ anglais)'; b.setAttribute('aria-label', 'Traduire');
    var meta = msg.querySelector('.wa-meta'); if (meta) meta.insertBefore(b, meta.firstChild); else msg.appendChild(b);
  };
  if (trOn) {
    box.addEventListener('click', function (e) {
      var b = e.target.closest('.wa-trb, .wa-trans'); if (!b) return;
      var msg = b.closest('.wa-msg'), ex = msg.querySelector('.wa-trans'), ic = msg.querySelector('.wa-trb');
      if (ex) { ex.hidden = !ex.hidden; ic.classList.toggle('on', !ex.hidden); return; }
      ic.disabled = true; ic.classList.add('busy');
      var d = new FormData(); d.append('kind', 'chat'); d.append('id', msg.dataset.id);
      trPost(d).then(function (j) {
        ic.disabled = false; ic.classList.remove('busy');
        if (!j.ok) { alert(j.error || 'Traduction impossible.'); return; }
        var t = el('div', 'wa-trans'); t.title = 'Toucher pour masquer la traduction';
        t.appendChild(el('small', '', lc(j.from) + ' → ' + lc(j.into))); t.appendChild(el('span', '', j.text));
        var tx = msg.querySelector('.wa-txt'); tx.insertAdjacentElement('afterend', t); ic.classList.add('on');
      }).catch(function () { ic.disabled = false; ic.classList.remove('busy'); alert('Connexion perdue.'); });
    });
    var trMenu = q('[data-tr-menu]'), trBtn = q('[data-tr-draft]');
    trBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!ta.value.trim()) { alert('Écrivez d\'abord votre message, puis touchez 🌐 pour le traduire.'); ta.focus(); return; }
      trMenu.hidden = !trMenu.hidden;
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('[data-tr-menu]')) trMenu.hidden = true; });
    trMenu.addEventListener('click', function (e) {
      var o = e.target.closest('[data-tr-to]'); if (!o) return;
      trMenu.hidden = true; var orig = ta.value, d = new FormData(); d.append('kind', 'text'); d.append('text', orig); d.append('to', o.dataset.trTo);
      trBtn.disabled = true; trBtn.textContent = '⏳';
      trPost(d).then(function (j) {
        trBtn.disabled = false; trBtn.textContent = '🌐';
        if (!j.ok) { alert(j.error || 'Traduction impossible.'); return; }
        ta.value = j.text; sync(); ta.focus();
      }).catch(function () { trBtn.disabled = false; trBtn.textContent = '🌐'; alert('Connexion perdue.'); });
    });
  }

  // --- affichage d'un message reçu
  var el = function (tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt) n.textContent = txt; return n; };
  var add = function (m) {
    if (seen[m.id]) return; seen[m.id] = 1;
    var emp = q('[data-chat-empty]'); if (emp) emp.remove();
    var d = el('div', 'wa-msg ' + (m.me ? 'me' : 'them')); d.dataset.id = m.id; if (m.reply_to) d.dataset.replyTo = m.reply_to;
    if (m.about) { var ab = el(m.about_uid ? 'a' : 'div', 'wa-about', '✉️ ' + m.about + (m.about_uid ? ' →' : '')); if (m.about_uid) ab.href = '/admin/chat?u=' + m.about_uid; d.appendChild(ab); }
    if (m.quote) { var qq = el('div', 'wa-quote'); qq.dataset.goto = m.quote.id; qq.appendChild(el('b', '', m.quote.who)); qq.appendChild(el('span', '', m.quote.text)); d.appendChild(qq); }
    if (m.att) {
      if (m.att.type === 'image') { var a = el('a'); a.href = m.att.url; a.target = '_blank'; a.rel = 'noopener'; var im = el('img', 'wa-img'); im.src = m.att.img || m.att.url; a.appendChild(im); d.appendChild(a); }
      else if (m.att.type === 'audio') { var au = el('audio'); au.controls = true; au.preload = 'none'; au.src = m.att.url; d.appendChild(au); }
      else { var f = el('a', 'wa-file', '📎 ' + (m.att.name || 'Fichier')); f.href = m.att.url; f.target = '_blank'; f.rel = 'noopener'; d.appendChild(f); }
    }
    if (m.body) d.appendChild(el('span', 'wa-txt', m.body));
    var meta = el('span', 'wa-meta', m.time);
    if (m.me) { var t = el('i', 'wa-tick' + (m.read ? ' read' : ''), '✓✓'); meta.appendChild(document.createTextNode(' ')); meta.appendChild(t); }
    d.appendChild(meta);
    var rxd = el('div', 'rx'); rxd.dataset.rx = m.id; d.appendChild(rxd); setRx(rxd, m.rx || []);
    var tb = el('button', 'wa-thread'); tb.type = 'button'; tb.dataset.thread = m.id; d.appendChild(tb); setRc(tb, m.rc || 0);
    var rb = el('button', 'wa-reply', '↩'); rb.type = 'button'; rb.title = 'Répondre'; d.appendChild(rb);
    log.appendChild(d); addTrBtn(d); last = Math.max(last, m.id); down();
    if (m.reply_to && m.reply_to === thId) showThread(thId, true);
  };
  var poll = function (force) {
    if (document.hidden && force !== true) return;
    fetch(url + sep + 'poll=1&after=' + last, { credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (j) {
        (j.messages || []).forEach(add);
        (j.reads || []).forEach(function (id) { var t = log.querySelector('.wa-msg[data-id="' + id + '"] .wa-tick'); if (t) t.classList.add('read'); });
        applyMeta(j);
        try { box.dispatchEvent(new CustomEvent('chat:poll', { detail: j })); } catch (e) {}
      }).catch(function () {});
  };
  var send = function (data, onFail) {
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: data })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) { alert(j.error || 'Message non envoyé.'); if (onFail) onFail(); } poll(); })
      .catch(function () { alert('Connexion perdue : message non envoyé.'); if (onFail) onFail(); });
  };
  var base = function () { var d = new FormData(); d.append('_csrf', form.elements._csrf.value); if (replyId.value) d.append('reply_to', replyId.value); return d; };
  if (trOn) log.querySelectorAll('.wa-msg').forEach(addTrBtn);
  down(); setInterval(poll, 1000);
  log.querySelectorAll('.wa-thread').forEach(function (b) { b.dataset.n = (b.querySelector('span') || {}).textContent || '0'; }); document.addEventListener('visibilitychange', poll);

  // --- répondre à un message
  var setReply = function (msg) {
    replyId.value = msg.dataset.id;
    var who = msg.classList.contains('me') ? 'Vous' : (q('.wa-who b').textContent);
    var txt = (msg.querySelector('.wa-txt') || {}).textContent || (msg.querySelector('audio') ? '🎤 Message vocal' : msg.querySelector('img') ? '📷 Photo' : '📎 Fichier');
    q('[data-reply-who]').textContent = who; q('[data-reply-text]').textContent = txt.slice(0, 100);
    replyBar.hidden = false; ta.focus();
  };
  var clearReply = function () { replyId.value = ''; replyBar.hidden = true; };
  q('[data-reply-x]').addEventListener('click', clearReply);
  log.addEventListener('click', function (e) {
    var rb = e.target.closest('.wa-reply'); if (rb) { setReply(rb.parentNode); return; }
    var qt = e.target.closest('.wa-quote');
    if (qt) { var t = log.querySelector('.wa-msg[data-id="' + qt.dataset.goto + '"]'); if (t) { t.scrollIntoView({ block: 'center', behavior: 'smooth' }); t.classList.add('flash'); setTimeout(function () { t.classList.remove('flash'); }, 1300); } }
  });
  // glisser vers la droite (mobile) pour répondre
  var sx = 0, sy = 0, sm = null;
  log.addEventListener('touchstart', function (e) { var m = e.target.closest('.wa-msg'); sm = m; sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
  log.addEventListener('touchend', function (e) {
    if (!sm) return; var dx = e.changedTouches[0].clientX - sx, dy = Math.abs(e.changedTouches[0].clientY - sy);
    if (dx > 70 && dy < 40) setReply(sm); sm = null;
  }, { passive: true });

  // --- émojis
  q('[data-emoji-btn]').addEventListener('click', function () { panel.hidden = !panel.hidden; });
  panel.addEventListener('click', function (e) {
    var b = e.target.closest('[data-emoji]'); if (!b) return;
    var s = ta.selectionStart || ta.value.length; ta.value = ta.value.slice(0, s) + b.dataset.emoji + ta.value.slice(ta.selectionEnd || s); ta.focus(); ta.selectionStart = ta.selectionEnd = s + b.dataset.emoji.length; sync();
  });

  // --- zone de texte : bouton micro <-> envoyer
  var micI = q('[data-main-mic]'), sndI = q('[data-main-send]');
  var sync = function () {
    var has = ta.value.trim() !== ''; micI.hidden = has; sndI.hidden = !has;
    main.setAttribute('aria-label', has ? 'Envoyer' : 'Message vocal');
    ta.style.height = 'auto'; ta.style.height = Math.min(120, ta.scrollHeight) + 'px';
  };
  ta.addEventListener('input', sync);
  if (ta.value.trim() !== '') sync(); // message pré-rempli
  var submitText = function () {
    var v = ta.value.trim(); if (!v) return;
    var d = base(); d.append('body', v); ta.value = ''; sync(); panel.hidden = true;
    send(d, function () { ta.value = v; sync(); }); clearReply();
  };
  form.addEventListener('submit', function (e) { e.preventDefault(); submitText(); });
  ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 900) { e.preventDefault(); submitText(); } });

  // --- pièces jointes / photo
  var sendFile = function (f) {
    if (!f) return;
    if (f.size > 8 * 1024 * 1024) { alert('Fichier trop lourd (8 Mo maximum).'); return; }
    var d = base(); d.append('body', ta.value.trim()); d.append('file', f, f.name); ta.value = ''; sync(); send(d); clearReply();
  };
  q('[data-chat-attach]').addEventListener('click', function () { fileIn.click(); });
  q('[data-chat-cam]').addEventListener('click', function () { camIn.click(); });
  fileIn.addEventListener('change', function () { sendFile(fileIn.files[0]); fileIn.value = ''; });
  camIn.addEventListener('change', function () { sendFile(camIn.files[0]); camIn.value = ''; });

  // --- note vocale
  var rec = null, chunks = [], timer = null, t0 = 0, cancelled = false, stream = null;
  var stopUI = function () { clearInterval(timer); recBox.hidden = true; if (stream) stream.getTracks().forEach(function (t) { t.stop(); }); };
  var startRec = function () {
    if (!navigator.mediaDevices || !window.MediaRecorder) { alert('Le message vocal n\'est pas disponible sur ce navigateur.'); return; }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      stream = s; chunks = []; cancelled = false; rec = new MediaRecorder(s);
      rec.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
      rec.onstop = function () {
        stopUI(); if (cancelled || !chunks.length) return;
        var type = (rec.mimeType || 'audio/webm').split(';')[0];
        var ext = type.indexOf('mp4') > -1 ? 'm4a' : type.indexOf('ogg') > -1 ? 'ogg' : 'webm';
        var blob = new Blob(chunks, { type: type });
        if (blob.size > 8 * 1024 * 1024) { alert('Message vocal trop long.'); return; }
        var d = base(); d.append('voice', '1'); d.append('file', blob, 'vocal.' + ext); send(d); clearReply();
      };
      rec.start(); t0 = Date.now(); recBox.hidden = false; q('[data-rec-time]').textContent = '0:00';
      timer = setInterval(function () {
        var n = Math.floor((Date.now() - t0) / 1000);
        q('[data-rec-time]').textContent = Math.floor(n / 60) + ':' + ('0' + (n % 60)).slice(-2);
        if (n >= 120 && rec.state === 'recording') rec.stop();
      }, 500);
    }).catch(function () { alert('Autorisez le micro pour envoyer un message vocal.'); });
  };
  main.addEventListener('click', function () { if (ta.value.trim() !== '') submitText(); else startRec(); });
  q('[data-rec-send]').addEventListener('click', function () { if (rec && rec.state === 'recording') rec.stop(); });
  q('[data-rec-cancel]').addEventListener('click', function () { cancelled = true; if (rec && rec.state === 'recording') rec.stop(); });
})();
