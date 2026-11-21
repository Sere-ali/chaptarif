/* Clé de sécurité (Passkey / WebAuthn) : enregistrement et connexion. */
(function () {
  function b2u(b) { var s = ''; new Uint8Array(b).forEach(function (c) { s += String.fromCharCode(c); }); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function u2b(u) { u = u.replace(/-/g, '+').replace(/_/g, '/'); while (u.length % 4) u += '='; var s = atob(u), a = new Uint8Array(s.length); for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i); return a.buffer; }
  function csrf() { var m = document.querySelector('meta[name=csrf]'); return m ? m.content : ''; }
  function post(d) { return fetch('/passkey', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(d) }).then(function (r) { return r.json(); }); }
  function supported() { return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create); }
  function fail(e) {
    if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) return 'Opération annulée.';
    if (e && e.name === 'InvalidStateError') return 'Cette clé est déjà enregistrée sur cet appareil.';
    return (e && e.message) ? e.message : 'Une erreur est survenue.';
  }
  window.ctPasskey = {
    supported: supported,
    register: function () {
      return post({ a: 'reg_options' }).then(function (r) {
        if (!r.ok) throw new Error(r.error);
        var o = r.options; o.challenge = u2b(o.challenge); o.user.id = u2b(o.user.id);
        (o.excludeCredentials || []).forEach(function (c) { c.id = u2b(c.id); });
        return navigator.credentials.create({ publicKey: o });
      }).then(function (c) {
        return post({ a: 'reg_finish', id: c.id, clientDataJSON: b2u(c.response.clientDataJSON), attestationObject: b2u(c.response.attestationObject), label: (/Android/i.test(navigator.userAgent) ? 'Téléphone Android' : /iPhone|iPad/i.test(navigator.userAgent) ? 'iPhone / iPad' : 'Cet appareil') });
      }).then(function (r) { if (!r.ok) throw new Error(r.error); return r; });
    },
    login: function (next) {
      return post({ a: 'login_options' }).then(function (r) {
        var o = r.options; o.challenge = u2b(o.challenge);
        return navigator.credentials.get({ publicKey: o });
      }).then(function (c) {
        return post({ a: 'login_finish', next: next || '/compte', id: c.id, clientDataJSON: b2u(c.response.clientDataJSON), authenticatorData: b2u(c.response.authenticatorData), signature: b2u(c.response.signature) });
      }).then(function (r) { if (!r.ok) throw new Error(r.error); return r; });
    },
    fail: fail
  };
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-passkey-login]'); if (!b) return;
    e.preventDefault();
    var msg = document.querySelector('[data-passkey-msg]'); if (msg) msg.textContent = '';
    if (!supported()) { if (msg) msg.textContent = "Votre navigateur ne gère pas la clé de sécurité. Utilisez votre mot de passe."; return; }
    b.disabled = true;
    window.ctPasskey.login(b.getAttribute('data-next')).then(function (r) { location.href = r.next; }).catch(function (x) { b.disabled = false; if (msg) msg.textContent = fail(x); });
  });
})();
