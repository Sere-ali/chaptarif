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

  // --- affichage d'un message reçu
  var el = function (tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt) n.textContent = txt; return n; };
  var add = function (m) {
    if (seen[m.id]) return; seen[m.id] = 1;
    var emp = q('[data-chat-empty]'); if (emp) emp.remove();
    var d = el('div', 'wa-msg ' + (m.me ? 'me' : 'them')); d.dataset.id = m.id;
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
    var rb = el('button', 'wa-reply', '↩'); rb.type = 'button'; rb.title = 'Répondre'; d.appendChild(rb);
    log.appendChild(d); last = Math.max(last, m.id); down();
  };
  var poll = function () {
    if (document.hidden) return;
    fetch(url + sep + 'poll=1&after=' + last, { credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (j) {
        (j.messages || []).forEach(add);
        (j.reads || []).forEach(function (id) { var t = log.querySelector('.wa-msg[data-id="' + id + '"] .wa-tick'); if (t) t.classList.add('read'); });
      }).catch(function () {});
  };
  var send = function (data, onFail) {
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }, body: data })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) { alert(j.error || 'Message non envoyé.'); if (onFail) onFail(); } poll(); })
      .catch(function () { alert('Connexion perdue : message non envoyé.'); if (onFail) onFail(); });
  };
  var base = function () { var d = new FormData(); d.append('_csrf', form.elements._csrf.value); if (replyId.value) d.append('reply_to', replyId.value); return d; };
  down(); setInterval(poll, 4000); document.addEventListener('visibilitychange', poll);

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
