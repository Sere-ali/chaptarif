</main>
<?php if (!empty($providerMode)): ?>
<footer class="footer ps-foot"><div class="container footer-bottom"><span>© <?= date('Y') ?> ChapTarif · Espace prestataire</span><span>Besoin d'aide ? <a href="/chat?pro=1"> Chat avec l'équipe</a></span></div></footer>
<?php else: ?>


<?php $__cu = current_user(); $chatBadge = $__cu ? (int) val("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = 'admin' AND read_by_user = 0", [$__cu['id']]) : 0; ?>
<nav class="tabbar" aria-label="Navigation mobile">
  <a href="/" class="<?= ($active ?? '') === 'home' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Accueil</a>
  <a href="/compte" class="<?= ($active ?? '') === 'account' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>Réservations</a>
  <a href="/chat" class="<?= ($active ?? '') === 'chat' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>Chat<?php if (!empty($chatBadge)): ?><i class="tab-dot"><?= (int) $chatBadge ?></i><?php endif; ?></a>
  <a href="/compte"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Profil</a>
</nav>

<?php endif; ?>
<script src="/assets/js/app.js?v=20" defer></script>
<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("[class*=ico],[class*=emoji],.fi,.step-ico,.sum-ico").forEach(function(e){if(!e.children.length&&!e.textContent.trim()&&e.tagName!=="IMG"&&e.tagName!=="svg")e.style.display="none";});});</script>
<script src="/assets/js/polish.js?v=1" defer></script>
<script src="/assets/js/motion.js?v=1" defer></script>
<?php if (!empty($leaflet)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
<?php endif; ?>
<?php if (!empty($qr)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<?= $scripts ?? '' ?>
</body>
</html>
