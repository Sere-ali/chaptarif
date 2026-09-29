</main>
<footer class="footer">
  <div class="container footer-grid">
    <div>
      <a class="brand brand-light" href="/"><img src="/assets/img/logo.svg" alt="" width="36" height="36"><span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span></a>
      <p class="muted-light">Le comparateur n°1 des services du quotidien en Côte d'Ivoire. Prix transparents, prestataires vérifiés, paiement protégé sous séquestre.</p>
      <div class="pay-chips">
        <span class="chip chip-wave">Wave</span><span class="chip chip-om">Orange Money</span><span class="chip chip-mtn">MTN MoMo</span><span class="chip chip-moov">Moov</span>
      </div>
    </div>
    <div>
      <h4>Services</h4>
      <?php foreach (nav_entries() as $x): ?><a href="<?= $x['url'] ?>"><?= e($x['name']) ?></a><?php endforeach; ?>
    </div>
    <div>
      <h4>ChapTarif</h4>
      <a href="/comment-ca-marche">Comment ça marche</a>
      <a href="/garantie-dommage">🛡️ Garantie Dommage</a>
      <a href="/devenir-prestataire">Devenir prestataire</a>
      <a href="/prestataire/valider">Valider une mission (prestataires)</a>
      <a href="/contact">Contact & réclamations</a>
      <a href="/mentions-legales">Mentions légales & CGU</a>
    </div>
    <div>
      <h4>Nous joindre</h4>
      <a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('support_phone'))) ?>">📞 <?= e(setting('support_phone')) ?></a>
      <a href="mailto:<?= e(setting('support_email')) ?>">✉️ <?= e(setting('support_email')) ?></a>
      <span class="muted-light">📍 Abidjan, Côte d'Ivoire</span>
      <span class="muted-light">🕗 7j/7 · 7h – 22h</span>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> ChapTarif · Tous droits réservés</span>
    <span>🔒 Paiements sécurisés sous séquestre</span>
  </div>
</footer>

<nav class="tabbar" aria-label="Navigation mobile">
  <a href="/" class="<?= ($active ?? '') === 'home' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Accueil</a>
  <a href="/cars" class="<?= ($active ?? '') === 'cars' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/></svg>Billets</a>
  <a href="/covoiturage" class="<?= ($active ?? '') === 'covoiturage' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>Trajets</a>
  <a href="/compte" class="<?= ($active ?? '') === 'account' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Compte</a>
</nav>

<script src="/assets/js/app.js?v=3" defer></script>
<?php if (!empty($leaflet)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
<?php endif; ?>
<?php if (!empty($qr)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<?= $scripts ?? '' ?>
</body>
</html>
