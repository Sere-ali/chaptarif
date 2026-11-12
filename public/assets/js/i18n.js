/* Site bilingue : français (d'origine) <-> anglais, sans recharger la page.
   Le choix est gardé dans un cookie « ct_lang » ; le dictionnaire anglais n'est chargé qu'en anglais. */
(function () {
  'use strict';
  var root = document.documentElement, lang = 'fr', done = [], ATTR = ['placeholder', 'aria-label', 'title', 'alt'];
  var NUM = /\d+(?:[ .]\d{3})*(?:,\d+)?/g;
  var norm = function (s) { return s.replace(/’/g, "'").replace(/[\s  ]+/g, ' ').trim(); };
  var DICT = function () { return window.CT_EN || { d: {}, c: {}, m: {} }; };

  function look(k) {
    var E = DICT(), d = E.d;
    if (Object.prototype.hasOwnProperty.call(d, k)) return d[k];
    var nums = k.match(NUM);
    if (nums) {
      var i = 0, tk = k.replace(NUM, function () { return '{' + (i++) + '}'; });
      if (Object.prototype.hasOwnProperty.call(d, tk)) return d[tk].replace(/\{(\d+)\}/g, function (_, n) { return nums[+n]; });
    }
    var m;
    if ((m = k.match(/^(\S+ \+\d+ · )(.+)$/)) && E.c[m[2]]) return m[1] + E.c[m[2]];
    if ((m = k.match(/^(.+) : document obligatoire\.$/))) return (look(m[1]) || m[1]) + ': required document.';
    if ((m = k.match(/^(.+) · ChapTarif$/)) && (m = look(m[1]))) return m + ' · ChapTarif';
    if ((m = k.match(/^(.*?)\s*:$/)) && m[1] && Object.prototype.hasOwnProperty.call(d, m[1])) return d[m[1]] + ':';
    if ((m = k.match(/^Code appliqué : vous économisez (.+)\.?$/))) return 'Code applied: you save ' + m[1].replace(/\.$/, '') + '.';
    if ((m = k.match(/^Indiquez le nombre de personnes pour « (.+) »\.$/))) return 'Enter the number of people for “' + (look(m[1]) || m[1]) + '”.';
    if ((m = k.match(/^Un code à (\d) chiffres vient d'être envoyé au (.+?)\.?$/))) return 'A ' + m[1] + '-digit code has just been sent to ' + m[2] + '.';
    if ((m = k.match(/^N° client (.+)$/))) return 'Client No. ' + m[1];
    if ((m = k.match(/^N° prestataire (.+)$/))) return 'Provider No. ' + m[1];
    if ((m = k.match(/^Motif : (.+)$/))) return 'Reason: ' + m[1];
    if ((m = k.match(/^Gare (.+)$/))) return m[1] + ' station';
    if ((m = k.match(/^E-billet (.+)$/))) return 'E-ticket ' + m[1];
    if ((m = k.match(/^le (\d{1,2}\/\d{1,2}\/\d{4})(?: à (.+))?$/))) return 'on ' + m[1] + (m[2] ? ' at ' + m[2] : '');
    if ((m = k.match(/^Paiement déclaré : notre équipe vérifie la réception sur (.+) et confirmera votre réservation très bientôt\.$/))) return 'Payment declared: our team is checking receipt on ' + m[1] + ' and will confirm your booking very soon.';
    if ((m = k.match(/^Réclamation en cours d'examen : « (.+) »\. Le paiement reste gelé jusqu'à la décision de notre équipe\.$/))) return 'Claim under review: “' + m[1] + '”. The payment stays frozen until our team decides.';
    if ((m = k.match(/^(.+) ·$/)) && (m = look(m[1]))) return m + ' ·';
    if ((m = k.match(/^· (.+)$/)) && (m = look(m[1]))) return '· ' + m;
    // texte composé « A · B · C » : chaque morceau traduit séparément
    if (k.indexOf(' · ') > 0) {
      var parts = k.split(' · '), hit = false;
      parts = parts.map(function (p) { var x = p.trim() ? look(p.trim()) : null; if (x !== null) { hit = true; return x; } return p; });
      if (hit) return parts.join(' · ');
    }
    // dates « 12 nov. 2026 » dans un texte libre
    if (/\d{1,2} (janv\.|févr\.|mars|avr\.|mai|juin|juil\.|août|sept\.|oct\.|nov\.|déc\.)/.test(k)) {
      return k.replace(/(\d{1,2}) (janv\.|févr\.|mars|avr\.|mai|juin|juil\.|août|sept\.|oct\.|nov\.|déc\.)/g, function (_, j, mo) { return j + ' ' + E.m[mo]; }).replace(/ à (\d{1,2}[:h]\d{2})/g, ' at $1');
    }
    return null;
  }
  function tr(s) {
    if (!s || !/\p{L}/u.test(s)) return null;
    var k = norm(s); if (!k) return null;
    var e = look(k); if (e === null || e === k) return null;
    var a = s.match(/^\s*/)[0], b = s.match(/\s*$/)[0];
    return a + e + b;
  }
  window.ctT = function (s) { return lang === 'en' ? (tr(String(s)) || s) : s; };

  function doText(n) {
    if (n.parentNode && n.parentNode.tagName === 'TEXTAREA') return; // texte saisi : jamais traduit
    if (n._en !== undefined && n.nodeValue === n._en) return;
    var t = tr(n.nodeValue); if (t === null) return;
    n._fr = n.nodeValue; n._en = t; n.nodeValue = t; done.push(n);
  }
  function doEl(el) {
    for (var i = 0; i < ATTR.length; i++) {
      var a = ATTR[i], v = el.getAttribute(a); if (!v) continue;
      var key = 'fr' + i; if (el['_' + key] !== undefined && v === el['_en' + i]) continue;
      var t = tr(v); if (t === null) continue;
      el['_' + key] = v; el['_en' + i] = t; el.setAttribute(a, t); done.push(el);
    }
    if ((el.type === 'submit' || el.type === 'button') && el.tagName === 'INPUT' && el.value) {
      var tv = tr(el.value); if (tv !== null) { el._frv = el.value; el.value = tv; done.push(el); }
    }
  }
  var skip = function (n) { return n.closest && n.closest('script,style,[data-no-tr],.wa-txt'); };
  function walk(node) {
    if (node.nodeType === 3) { if (node.parentNode && !skip(node.parentNode)) doText(node); return; }
    if (node.nodeType !== 1 || skip(node)) return;
    doEl(node);
    var w = document.createTreeWalker(node, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
      acceptNode: function (x) { return x.nodeType === 1 && /^(SCRIPT|STYLE)$/.test(x.tagName) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT; }
    }), n;
    while ((n = w.nextNode())) { if (n.nodeType === 3) { if (!skip(n.parentNode)) doText(n); } else if (!skip(n)) doEl(n); }
  }
  function restore() {
    done.forEach(function (n) {
      if (n.nodeType === 3) { if (n.nodeValue === n._en) n.nodeValue = n._fr; delete n._fr; delete n._en; return; }
      for (var i = 0; i < ATTR.length; i++) if (n['_fr' + i] !== undefined) { if (n.getAttribute(ATTR[i]) === n['_en' + i]) n.setAttribute(ATTR[i], n['_fr' + i]); delete n['_fr' + i]; delete n['_en' + i]; }
      if (n._frv !== undefined) { n.value = n._frv; delete n._frv; }
    });
    done = [];
  }
  var titleFr = null;
  function doTitle() { var t = tr(document.title); if (t) { if (titleFr === null) titleFr = document.title; document.title = t; } }
  var obs = new MutationObserver(function (list) {
    if (lang !== 'en') return;
    list.forEach(function (m) {
      if (m.type === 'characterData') { if (m.target.parentNode && !skip(m.target.parentNode)) doText(m.target); }
      else if (m.type === 'attributes') { if (!skip(m.target)) doEl(m.target); }
      else m.addedNodes.forEach(walk);
    });
  });
  // Messages d'alerte et de confirmation traduits aussi
  var oa = window.alert, oc = window.confirm;
  window.alert = function (s) { return oa.call(window, window.ctT(s)); };
  window.confirm = function (s) { return oc.call(window, window.ctT(s)); };

  function ui() {
    document.querySelectorAll('[data-lang]').forEach(function (b) { b.classList.toggle('on', b.dataset.lang === lang); });
    document.querySelectorAll('[data-lang-toggle]').forEach(function (b) {
      b.textContent = lang === 'en' ? 'EN' : 'FR'; // langue affichée = langue du site
      b.setAttribute('aria-label', 'Langue / Language'); b.setAttribute('aria-haspopup', 'menu');
      b.title = b.getAttribute('aria-label');
    });
  }
  function apply() {
    root.lang = lang;
    if (lang === 'en') {
      walk(document.body); doTitle();
      obs.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTR });
    } else { obs.disconnect(); restore(); if (titleFr !== null) { document.title = titleFr; titleFr = null; } }
    ui();
    root.classList.remove('i18n-wait');
  }
  function load(cb) {
    if (window.CT_EN) return cb();
    var s = document.createElement('script'); s.src = '/assets/js/i18n-en.js?v=' + (root.dataset.i18n || '1');
    s.onload = cb; s.onerror = function () { lang = 'fr'; apply(); }; document.head.appendChild(s);
  }
  function set(l) {
    lang = l === 'en' ? 'en' : 'fr';
    document.cookie = 'ct_lang=' + lang + ';path=/;max-age=31536000;samesite=lax';
    try { localStorage.setItem('ct_lang', lang); } catch (e) {}
    if (lang === 'en') load(apply); else apply();
  }
  // Menu de choix de la langue (s'ouvre sous le bouton FR / EN)
  var menu = null;
  function closeMenu() { if (menu) { menu.remove(); menu = null; document.querySelectorAll('[data-lang-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); } }
  function openMenu(btn) {
    closeMenu();
    menu = document.createElement('div'); menu.className = 'lang-menu'; menu.setAttribute('role', 'menu'); menu.setAttribute('data-no-tr', '');
    [['fr', '🇫🇷', 'Français'], ['en', '🇬🇧', 'English']].forEach(function (o) {
      var it = document.createElement('button'); it.type = 'button'; it.setAttribute('role', 'menuitemradio');
      it.setAttribute('aria-checked', o[0] === lang ? 'true' : 'false'); it.dataset.pick = o[0];
      it.innerHTML = '<span class="lm-flag">' + o[1] + '</span><span>' + o[2] + '</span><i>' + (o[0] === lang ? '✓' : '') + '</i>';
      if (o[0] === lang) it.className = 'on';
      menu.appendChild(it);
    });
    document.body.appendChild(menu);
    var r = btn.getBoundingClientRect(), w = menu.offsetWidth;
    menu.style.top = (r.bottom + 8) + 'px';
    menu.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w)) + 'px';
    btn.setAttribute('aria-expanded', 'true');
  }
  document.addEventListener('click', function (e) {
    var p = e.target.closest('.lang-menu [data-pick]');
    if (p) { e.preventDefault(); var l = p.dataset.pick; closeMenu(); if (l !== lang) set(l); return; }
    var t = e.target.closest('[data-lang-toggle]');
    if (t) { e.preventDefault(); e.stopPropagation(); if (menu) closeMenu(); else openMenu(t); return; }
    if (menu && !e.target.closest('.lang-menu')) closeMenu();
    var b = e.target.closest('[data-lang]'); if (!b) return;
    e.preventDefault(); set(b.dataset.lang);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
  window.addEventListener('scroll', closeMenu, { passive: true });
  window.addEventListener('resize', closeMenu);
  var start = function () {
    var c = document.cookie.match(/(?:^|; )ct_lang=(\w+)/);
    if (c && c[1] === 'en') { lang = 'en'; load(apply); } else { ui(); root.classList.remove('i18n-wait'); }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
