/* Aperçu des PDF dans la page (les téléphones Android ne savent pas afficher un PDF dans un cadre).
 * window.ctPdf(source, conteneur) : source = File/Blob, ArrayBuffer ou adresse du fichier. Renvoie une promesse. */
(function () {
  var lib = null;
  function load() {
    if (lib) return lib;
    lib = new Promise(function (ok, ko) {
      if (window.pdfjsLib) return ok(window.pdfjsLib);
      var s = document.createElement('script'); s.src = '/assets/vendor/pdfjs/pdf.min.js?v=3.11.174';
      s.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/vendor/pdfjs/pdf.worker.min.js?v=3.11.174'; ok(window.pdfjsLib); };
      s.onerror = function () { lib = null; ko(new Error('pdf.js')); };
      document.head.appendChild(s);
    });
    return lib;
  }
  function bytes(src) {
    if (src instanceof ArrayBuffer) return Promise.resolve(src);
    if (window.Blob && src instanceof Blob) return src.arrayBuffer ? src.arrayBuffer() : new Promise(function (ok) { var r = new FileReader(); r.onload = function () { ok(r.result); }; r.readAsArrayBuffer(src); });
    return fetch(src, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.arrayBuffer(); });
  }
  window.ctPdf = function (src, box) {
    box.innerHTML = ''; box.classList.remove('pdfv-zoom');
    if (!box._z) { box._z = 1; box.title = 'Toucher pour agrandir / réduire'; box.addEventListener('click', function (e) { if (e.target.tagName === 'CANVAS') box.classList.toggle('pdfv-zoom'); }); }
    return Promise.all([load(), bytes(src)]).then(function (a) {
      return a[0].getDocument({ data: new Uint8Array(a[1]) }).promise;
    }).then(function (pdf) {
      var w = Math.max(600, box.clientWidth || 600), ratio = Math.min(Math.max(window.devicePixelRatio || 1, 1.5), 2), n = Math.min(pdf.numPages, 20), chain = Promise.resolve();
      for (var i = 1; i <= n; i++) (function (i) {
        chain = chain.then(function () { return pdf.getPage(i); }).then(function (page) {
          var v0 = page.getViewport({ scale: 1 }), vp = page.getViewport({ scale: (w / v0.width) * ratio });
          var c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height; c.className = 'pdfv-page';
          box.appendChild(c);
          return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
        });
      })(i);
      return chain.then(function () { if (pdf.numPages > n) { var p = document.createElement('p'); p.className = 'pdfv-more'; p.textContent = '… ' + (pdf.numPages - n) + ' page(s) de plus'; box.appendChild(p); } });
    });
  };
})();
