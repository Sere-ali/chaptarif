<?php
$title = 'ChapTarif';
$active = 'home';
$U = universes();
$N = nav_entries();
$zones = zones_list();
$cities = array_column(all('SELECT DISTINCT to_city FROM trips WHERE active = 1 AND universe = \'cars\' AND from_city = ? ORDER BY to_city', ['Abidjan']), 'to_city');
$covCities = array_column(all('SELECT DISTINCT to_city FROM trips WHERE active = 1 AND universe = \'covoiturage\' AND from_city = ? ORDER BY to_city', ['Abidjan']), 'to_city');
$c = one("SELECT
    (SELECT COUNT(*) FROM providers WHERE active = 1 AND kyc_status = 'verified') AS providers,
    (SELECT COUNT(*) FROM trips WHERE active = 1) AS trips,
    (SELECT COUNT(*) FROM properties WHERE active = 1 AND certified = 1) AS props
");
$counts = ['providers' => (int) $c['providers'], 'trips' => (int) $c['trips'], 'props' => (int) $c['props']];
// Offres populaires du moment (prix réels, pour l'aperçu de l'accueil)
$popular = [
    ['label' => 'Cars voyages', 'sub' => 'Lignes directes toutes villes', 'price' => (int) val("SELECT MIN(price) FROM trips WHERE active = 1 AND universe = 'cars'")],
    ['label' => 'Covoiturage', 'sub' => 'Trajets économiques partagés', 'price' => (int) val("SELECT MIN(price) FROM trips WHERE active = 1 AND universe = 'covoiturage'")],
    ['label' => 'Coiffeuse à domicile', 'sub' => 'Tresses & soins sans déplacement', 'price' => (int) val("SELECT MIN(price) FROM offers WHERE active = 1 AND universe = 'coiffeuse'")],
];
$immoFrom = (int) val("SELECT MIN(price_night) FROM properties WHERE active = 1");
$bodyClass = 'home-m';
// Icônes de l'accueil mobile : couleur et taille écrites en dur dans le SVG
// (pas seulement via CSS) pour qu'elles s'affichent toujours correctement,
// même si la feuille de style met du temps à charger sur une connexion lente.
$mCatIcon = function (string $key, string $c): string {
    $icons = [
        'transport' => '<path d="M9 26c0-2 1-3 2-3l2-7c1-3 4-5 11-5s10 2 11 5l2 7c1 0 2 1 2 3v7a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-1H15v1a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z" fill="%C"/><circle cx="16" cy="33" r="3" fill="#1C2733"/><circle cx="32" cy="33" r="3" fill="#1C2733"/><rect x="15" y="16" width="18" height="7" rx="2" fill="#fff"/>',
        'immobilier' => '<circle cx="34" cy="16" r="5" fill="%C" opacity=".55"/><rect x="32" y="20" width="4" height="6" fill="%C" opacity=".55"/><path d="M11 25 24 13l13 12v12a2 2 0 0 1-2 2H13a2 2 0 0 1-2-2z" fill="%C"/><rect x="20" y="27" width="8" height="10" fill="#fff"/><rect x="16" y="29" width="4" height="4" fill="#fff"/>',
        'service-a-la-personne' => '<path d="M29 9 16 32" stroke="%C" stroke-width="3.2" stroke-linecap="round"/><path d="M12 36c1-6 5-8 8-8l5 3c-1 5-6 7-10 7z" fill="%C"/><rect x="25" y="32" width="13" height="10" rx="2" fill="%C" opacity=".5"/><path d="M27 32c0-2.2 1.3-4 4.5-4s4.5 1.8 4.5 4" stroke="%C" stroke-width="2" fill="none"/>',
        'beaute' => '<path d="M24 10c7.5 0 13 5.8 13 13 0 4.2-1.1 7.9-2.7 10.5l-3.2-1.4c1.3-2.8 2.1-5.8 2.1-8.7 0-5.9-4-10.4-9.2-10.4s-9.2 4.5-9.2 10.4c0 2.9.8 5.9 2.1 8.7l-3.2 1.4C12.1 30.9 11 27.2 11 23c0-7.2 5.5-13 13-13z" fill="%C"/><circle cx="24" cy="24" r="6.5" fill="%C" opacity=".5"/>',
    ];
    $svg = $icons[$key] ?? '';
    return '<svg width="28" height="28" viewBox="0 0 48 48" fill="none">' . str_replace('%C', $c, $svg) . '</svg>';
};
view('layout/header', compact('title', 'active', 'bodyClass'));
?>
<section class="m-home">
  <div class="m-hero">
  <div class="container m-home-top">
    <a class="m-brand" href="/" aria-label="ChapTarif, accueil">
      <img src="/assets/img/logo.svg" alt="" width="34" height="34">
      <span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span>
    </a>
    <span class="m-loc"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>Côte d'Ivoire<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
    <button class="m-burger" type="button" aria-label="Ouvrir le menu" aria-controls="m-drawer" aria-expanded="false" data-drawer-open><span></span><span></span><span></span></button>
  </div>
  <div class="container m-hero-in">
    <h1 class="sr-only">ChapTarif : réservez vos services en toute sécurité</h1>
    <div class="m-trio" aria-label="Nos engagements">
      <div><svg viewBox="0 0 48 48" fill="none" stroke="#0F1B2D" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M24 5 9 10.5v11.6c0 9.4 6.3 17 15 20.4 8.7-3.4 15-11 15-20.4V10.5z"/><path d="m16.5 24 5.5 5.5 10-11"/></svg><b>Fiable</b></div>
      <div><svg viewBox="0 0 48 48" fill="none" stroke="#F0A020" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M27 4 11 26h11l-3 18 18-24H26z"/></svg><b>Rapide</b></div>
      <div><svg viewBox="0 0 48 48" fill="none" stroke="#0F1B2D" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="21" width="30" height="22" rx="5"/><path d="M15 21v-6a9 9 0 0 1 18 0v6"/><circle cx="24" cy="31" r="2.6" fill="#0F1B2D"/><path d="M24 33.5V37"/></svg><b>Sécurisé</b></div>
    </div>
    <?php
      $kw = ['cars' => 'bus billet billets voyage interurbain gare utb ticket', 'covoiturage' => 'covoiturage covoit trajet voiture partagee partager place', 'location_car' => 'car minibus groupe evenement louer un car', 'location_vehicule' => 'voiture vehicule 4x4 suv location louer auto', 'chauffeur' => 'chauffeur conducteur louer un chauffeur', 'location_camion' => 'camion demenagement demenager fret transport charge', 'immobilier' => 'logement appartement studio villa maison chambre salon louer immobilier', 'menage' => 'menage nettoyage aide menagere femme de menage', 'pressing' => 'pressing linge lessive repassage laver blanchisserie', 'coiffeuse' => 'coiffure coiffeuse tresse tresses tissage perruque cheveux nattes', 'maquilleuse' => 'maquillage maquilleuse mariage evenement', 'onglerie' => 'ongle ongles manucure vernis faux ongles gel'];
      $alias = ['cars' => 'car cars bus autocar billet billets voyage', 'covoiturage' => 'covoiturage covoit trajet', 'location_car' => 'minibus louer un car location car location de car', 'location_vehicule' => 'voiture voitures vehicule vehicules 4x4 suv auto location voiture', 'chauffeur' => 'chauffeur chauffeurs', 'location_camion' => 'camion camions demenagement fret', 'immobilier' => 'immobilier logement logements appartement appartements maison maisons villa villas', 'menage' => 'menage menagere nettoyage', 'pressing' => 'pressing linge lessive repassage', 'coiffeuse' => 'coiffure coiffeuse tresse tresses tissage perruque', 'maquilleuse' => 'maquillage maquilleuse', 'onglerie' => 'ongle ongles manucure vernis'];
      $sidx = [];
      foreach (universes() as $uk2 => $ux) {
          if (empty($ux['url'])) continue;
          $sidx[] = ['t' => $ux['emoji'] . ' ' . $ux['name'], 'u' => $ux['url'], 'a' => $alias[$uk2] ?? '', 'k' => ($ux['name'] . ' ' . ($ux['short'] ?? '') . ' ' . ($ux['sub'] ?? '') . ' ' . ($kw[$uk2] ?? ''))];
      }
      foreach (PROPERTY_TYPES as $pt) {
          $sidx[] = ['t' => ' ' . $pt, 'u' => '/immobilier?cat=appart&type=' . urlencode($pt), 'a' => ($pt === 'Studio' ? 'studio studios' : mb_strtolower($pt) . ' ' . str_replace('chambre ', 'chambres ', mb_strtolower($pt))), 'k' => $pt . ' appartement logement louer immobilier' . ($pt === 'Studio' ? ' studios' : ' chambres chambre salon')];
      }
      $sidx[] = ['t' => ' Chat service client', 'u' => '/chat', 'k' => 'chat aide contact service client support whatsapp'];
      $sidx[] = ['t' => ' Mes réservations', 'u' => '/compte', 'k' => 'reservation reservations commande billet compte profil'];
    ?>
    <form class="m-search" action="#" data-search='<?= e(json_encode($sidx, JSON_UNESCAPED_UNICODE)) ?>' autocomplete="off" style="position:relative">
      <span class="m-search-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0F1B2D" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg></span>
      <input type="search" name="q" placeholder="Quel service recherchez-vous ?" aria-label="Rechercher un service">
      <a class="m-filter" href="/#m-cats" aria-label="Voir tous les services"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><path d="M4 8h16M4 16h16"/><circle cx="9" cy="8" r="2.4" fill="#3A7FC1"/><circle cx="15" cy="16" r="2.4" fill="#3A7FC1"/></svg></a>
      <ul class="m-sugg" hidden></ul>
    </form>
    <script>
    (function () {
      var f = document.querySelector('[data-search]'); if (!f) return;
      var idx = JSON.parse(f.getAttribute('data-search')), inp = f.querySelector('input'), ul = f.querySelector('.m-sugg');
      var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
      var find = function (q) {
        q = norm(q).trim(); if (!q) return [];
        var words = q.split(/\s+/);
        return idx.map(function (e) { var k = norm(e.t + ' ' + e.k), sc = 0; words.forEach(function (w) { if (k.indexOf(w) > -1) sc += (norm(e.t).indexOf(w) > -1 ? 2 : 1); else sc -= 100; }); return { e: e, s: sc }; })
          .filter(function (x) { return x.s > 0; }).sort(function (a, b) { return b.s - a.s; }).map(function (x) { return x.e; }).slice(0, 6);
      };
      var render = function () {
        var r = find(inp.value);
        if (!inp.value.trim()) { ul.hidden = true; return r; }
        ul.innerHTML = r.length ? r.map(function (e) { var a = document.createElement('a'); a.href = e.u; a.textContent = e.t; return '<li><a href="' + e.u + '">' + a.innerHTML + '</a></li>'; }).join('') : '<li><a href="/chat">Aucun résultat — écrivez-nous via le Chat</a></li>';
        ul.hidden = false; return r;
      };
      var exact = function (q) {
        q = norm(q).trim(); if (!q) return null;
        for (var i = 0; i < idx.length; i++) { if (!idx[i].a) continue; if (idx[i].a.split(' ').indexOf(q) > -1 || norm(idx[i].a).indexOf(q) > -1 && q.length > 3 && q === norm(idx[i].a).split(' ').slice(0, 1)[0]) return idx[i]; }
        for (var j = 0; j < idx.length; j++) { var al = norm(idx[j].a || ''); if (al && (' ' + al + ' ').indexOf(' ' + q + ' ') > -1) return idx[j]; }
        return null;
      };
      var tm;
      inp.addEventListener('input', function () {
        var r = render(); clearTimeout(tm);
        var q = inp.value.trim(), ex = exact(q);
        if (ex) { tm = setTimeout(function () { location.href = ex.u; }, 350); return; }
        if (q.length >= 4 && r.length) tm = setTimeout(function () { var best = find(inp.value); if (best.length && (best.length === 1 || norm(best[0].t).indexOf(norm(inp.value.trim())) > -1)) location.href = best[0].u; }, 700);
      });
      f.addEventListener('submit', function (ev) { ev.preventDefault(); var ex = exact(inp.value), r = find(inp.value); location.href = ex ? ex.u : (r.length ? r[0].u : '/chat'); });
      document.addEventListener('click', function (ev) { if (!f.contains(ev.target)) ul.hidden = true; });
    })();
    </script>
  </div>
  </div>
  <div class="container m-body">
    <div class="m-grid" id="m-cats">
      <?php
        $mAcc = ['transport' => ['#2C7BC8', '#E4EFF9', 'home-transport'], 'immobilier' => ['#F0A020', '#FBF1DE', 'home-immo'], 'service-a-la-personne' => ['#6A3FC0', '#EFEAFB', 'home-service'], 'beaute' => ['#E8408A', '#FCE6EF', 'home-beaute']];
        foreach ($N as $k => $x):
          $ac = $mAcc[$k] ?? [$x['color'], $x['bg'], null];
      ?>
        <a class="m-cat reveal" href="<?= $x['url'] ?>" style="--c:<?= $ac[0] ?>;--t:<?= $ac[1] ?>">
          <?php if ($ac[2]): ?><span class="m-cat-photo"><img src="/assets/img/<?= $ac[2] ?>.jpg" alt="" loading="lazy" width="390" height="190"></span><?php endif; ?>
          <span class="m-cat-ico"><?= $mCatIcon($k, $ac[0]) ?></span>
          <b><?= e($x['short']) ?></b>
          <span class="m-cat-arrow"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
      <?php endforeach; ?>
    </div>
    <button class="m-install" type="button" data-install hidden><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M12 8v6M9.5 11.8 12 14.3l2.5-2.5"/></svg>Télécharger l'application</button>
  </div>
  <div class="m-overlay" data-drawer-close hidden></div>
  <aside class="m-drawer" id="m-drawer" aria-label="Menu" hidden>
    <div class="m-drawer-head">
      <span class="m-brand"><img src="/assets/img/logo.svg" alt="" width="38" height="38"><span class="brand-txt"><span class="b1">Chap</span><span class="b2">Tarif</span></span></span>
      <button type="button" class="m-drawer-x" aria-label="Fermer le menu" data-drawer-close><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    </div>
    <button class="m-drawer-install" type="button" data-install hidden><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M12 8v6M9.5 11.8 12 14.3l2.5-2.5"/></svg>Télécharger l'application</button>
    <p class="m-install-help" data-install-help hidden></p>
    <nav class="m-drawer-links">
      <?php if (current_user()): ?>
        <a href="/compte">Mes réservations</a>
      <?php else: ?>
        <a href="/connexion">Se connecter</a>
      <?php endif; ?>
      <a href="/comment-ca-marche">Comment ça marche</a>
      <a href="/garantie-dommage">Garantie Dommage</a>
      <a href="/devenir-prestataire">Devenir prestataire</a>
      <a href="/chat">Chat service client</a>
      <a href="/contact">Contact &amp; aide</a>
      <a href="/mentions-legales">Mentions légales</a>
    </nav>
  </aside>
  <script>
  (function () {
    var d = document.getElementById('m-drawer'), ov = document.querySelector('.m-overlay'), bu = document.querySelector('[data-drawer-open]');
    if (!d || !bu) return;
    var set = function (o) {
      d.hidden = !o; ov.hidden = !o; bu.setAttribute('aria-expanded', o ? 'true' : 'false');
      document.body.style.overflow = o ? 'hidden' : '';
      requestAnimationFrame(function () { d.classList.toggle('open', o); ov.classList.toggle('open', o); });
    };
    bu.addEventListener('click', function () { set(true); });
    [].forEach.call(document.querySelectorAll('[data-drawer-close]'), function (x) { x.addEventListener('click', function () { set(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    // Installation de l'application
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var btns = [].slice.call(document.querySelectorAll('[data-install]')), help = document.querySelector('[data-install-help]');
    if (!standalone) btns.forEach(function (b) { b.hidden = false; });
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        var p = window.__deferredInstall;
        if (p) { p.prompt(); p.userChoice.finally(function () { window.__deferredInstall = null; }); return; }
        help.textContent = ios
          ? 'Sur iPhone : touchez le bouton Partager (carré avec une flèche) en bas de Safari, puis « Sur l\'écran d\'accueil ».'
          : 'Ouvrez le menu de votre navigateur (⋮ en haut à droite), puis touchez « Installer l\'application » ou « Ajouter à l\'écran d\'accueil ».';
        help.hidden = false; set(true);
      });
    });
    window.addEventListener('appinstalled', function () { btns.forEach(function (b) { b.hidden = true; }); });
  })();
  </script>
</section>
<section class="hero">
  <div class="hero-bg" style="--hero:url('<?= e(img('hero-abidjan', 'w_1800,h_1000,c_fill,q_auto,f_auto')) ?>')"></div>
  <div class="container hero-grid">
    <div class="hero-copy reveal">
      <span class="pill"><span class="bolt"></span> Le comparateur n°1 en Côte d'Ivoire</span>
      <h1>Où &amp; que souhaitez-vous <span class="grad">aujourd'hui&nbsp;?</span></h1>
      <p class="lead">Comparez les prix, réservez en 3 clics et payez en toute sécurité. Votre argent reste <strong>bloqué</strong> jusqu'à ce que le service soit bien rendu.</p>

      <div class="quick" data-tabs>
        <div class="quick-tabs" role="tablist">
          <?php $i = 0; foreach ($U as $k => $x): ?>
            <button role="tab" class="qt <?= $i++ === 0 ? 'on' : '' ?>" data-tab="<?= $k ?>" style="--c:<?= $x['color'] ?>"><span><?= $x['emoji'] ?></span><?= e($x['short']) ?></button>
          <?php endforeach; ?>
        </div>
        <form class="quick-pane on" data-pane="cars" action="/cars" method="get">
          <input type="hidden" name="from" value="Abidjan">
          <label class="fld"><span> Abidjan →</span><select name="to"><?php foreach ($cities as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <label class="fld"><span> Date</span><input type="date" name="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-primary btn-lg">Voir les départs</button>
        </form>
        <form class="quick-pane" data-pane="location_car" action="/location-car" method="get">
          <label class="fld"><span> Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les loueurs de car</button>
        </form>
        <form class="quick-pane" data-pane="location_camion" action="/location-camion" method="get">
          <label class="fld"><span> Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les loueurs de camion</button>
        </form>
        <form class="quick-pane" data-pane="covoiturage" action="/covoiturage" method="get">
          <input type="hidden" name="from" value="Abidjan">
          <label class="fld"><span> Abidjan →</span><select name="to"><?php foreach ($covCities as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les trajets</button>
        </form>
        <form class="quick-pane" data-pane="immobilier" action="/immobilier" method="get">
          <label class="fld"><span> Commune</span><select name="commune"><option value="">Toutes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <label class="fld"><span> Arrivée</span><input type="date" name="checkin" min="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-primary btn-lg">Trouver un logement</button>
        </form>
        <form class="quick-pane" data-pane="menage" action="/menage" method="get">
          <label class="fld"><span> Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les aides ménagères</button>
        </form>
        <form class="quick-pane" data-pane="pressing" action="/pressing" method="get">
          <label class="fld"><span> Collecte à</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les pressings</button>
        </form>
        <form class="quick-pane" data-pane="coiffeuse" action="/coiffeuse" method="get">
          <label class="fld"><span> Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les coiffeuses</button>
        </form>
        <form class="quick-pane" data-pane="maquilleuse" action="/maquilleuse" method="get">
          <label class="fld"><span> Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les maquilleuses</button>
        </form>
        <form class="quick-pane" data-pane="onglerie" action="/onglerie" method="get">
          <label class="fld"><span> Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les onglerie</button>
        </form>
      </div>

      <ul class="trust">
        <li><span></span> Prestataires CNI vérifiés</li>
        <li><span></span> Paiement sous séquestre</li>
        <li><span></span> Réservation en 3 clics</li>
      </ul>
    </div>

    <div class="hero-visual reveal" aria-hidden="true">
      <div class="phone">
        <div class="phone-notch"></div>
        <div class="phone-screen">
          <div class="ps-head"><img src="/assets/img/logo.svg" width="26" alt=""> <b>ChapTarif</b> <span class="ps-loc"> Côte d'Ivoire</span></div>
          <div class="ps-label">Offres populaires du moment</div>
          <?php foreach ($popular as $po): ?>
            <div class="ps-row"><span><?= e($po['label']) ?><small><?= e($po['sub']) ?></small></span><b><?= $po['price'] ? 'Dès ' . fcfa($po['price']) : '—' ?></b></div>
          <?php endforeach; ?>
          <div class="ps-row best"><span> Location de maisons<small>Villas & appartements certifiés</small></span><b><?= $immoFrom ? 'Dès ' . fcfa($immoFrom) : 'Tarifs réels' ?></b></div>
          <div class="ps-btn">Réserver via Wave</div>
          <div class="ps-foot">✓ Tarifs affichés à l'avance · Paiement sécurisé</div>
        </div>
      </div>
      <div class="float f1"><span class="fi"></span><div><b>5 000 F bloqués</b><small>Versés après votre validation</small></div></div>
      <div class="float f2"><span class="fi"></span><div><b>E-billet UTB</b><small>Siège 14 · QR code</small></div></div>
      <div class="float f3"><span class="fi"></span><div><b>Awa K. · CNI vérifiée</b><small>★ 4,9 · Cocody Angré</small></div></div>
    </div>
  </div>
</section>

<section class="section" id="univers">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow">Univers disponibles</span>
      <h2>Tout votre quotidien, <span class="grad">un seul tarif clair</span></h2>
      <p>Des prix affichés à l'avance et zéro négociation.</p>
    </div>
    <div class="uni-grid">
      <?php foreach ($N as $k => $x): ?>
        <a class="uni reveal" href="<?= $x['url'] ?>" style="--c:<?= $x['color'] ?>;--bg:<?= $x['bg'] ?>">
          <div class="uni-img"><img src="<?= e(img($x['img'], 'w_640,h_420,c_fill,q_auto,f_auto')) ?>" alt="<?= e($x['name']) ?>" loading="lazy" width="640" height="420"></div>
          <div class="uni-body"><span class="uni-emoji"><?= $x['emoji'] ?></span>
            <h3><?= e($x['name']) ?></h3>
            <p class="uni-sub"><?= e($x['sub']) ?></p>
            <p><?= e($x['pitch']) ?></p>
            <span class="uni-cta">Comparer & réserver <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="paybar reveal"> <strong>Paiement In-App sécurisé :</strong> <span class="chip chip-wave">Wave</span><span class="chip chip-om">Orange Money</span><span class="chip chip-mtn">MTN MoMo</span><span class="chip chip-moov">Moov</span></div>
  </div>
</section>

<section class="section section-dark" id="sequestre">
  <div class="container">
    <div class="sec-head light">
      <span class="eyebrow">Tiers de confiance</span>
    </div>
    <ol class="steps">
      <li class="reveal"><span class="step-n">1</span><h4>Vous réservez</h4><p>Sélectionnez votre prestation ou formule au tarif transparent et fixé à l'avance.</p></li>
      <li class="reveal"><span class="step-n">2</span><h4>Vous payez</h4><p>Réglez via Wave, Orange Money, MTN ou Moov. Vos règlements sont conservés sur un compte sécurisé.</p></li>
      <li class="reveal"><span class="step-n">3</span><h4>Le service est rendu</h4><p>Le prestataire effectue la mission commandée</p></li>
      <li class="reveal"><span class="step-n">4</span><h4>Vous validez</h4><p>Confirmez la bonne fin de la mission directement depuis l'application ou transmettez votre code de validation au prestataire.</p></li>
      <li class="reveal"><span class="step-n">5</span><h4>Paiement libéré</h4><p>Le règlement est automatiquement versé au prestataire. En cas de réclamation, vous pouvez déclarer un litige pour geler les fonds.</p></li>
    </ol>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="pro-cta reveal">
      <div>
        <span class="eyebrow light">Prestataires & partenaires</span>
        <h2>Développez votre activité avec ChapTarif</h2>
        <p>Transports, immobilier, Ménage, pressing, beauté: trouvez de nouveaux clients grâce à ChapTarif.</p>
        <div class="pro-points">
          <span>✓ Paiements sécurisés via mobile money</span><span>✓ Inscription gratuite</span><span>✓ Badge « Recommandé » : 9.999 FCFA/mois, en option</span>
        </div>
      </div>
      <a class="btn btn-white btn-lg" href="/devenir-prestataire">Devenir prestataire</a>
    </div>
  </div>
</section>

<section class="section" id="faq">
  <div class="container narrow">
    <div class="sec-head"><span class="eyebrow">Questions fréquentes</span><h2>Tout savoir avant de réserver</h2></div>
    <div class="faq">
      <details><summary>Comment mon argent est-il protégé&nbsp;?</summary><p>Lorsque vous payez, les fonds sont conservés sur le compte séquestre de ChapTarif. Ils ne sont reversés au prestataire qu'après votre confirmation (bouton « Confirmer la fin du travail » ou code de validation remis au prestataire). En cas de problème, vous ouvrez une réclamation et le paiement est gelé jusqu'à l'arbitrage de notre équipe.</p></details>
      <details><summary>Dois-je créer un compte pour comparer&nbsp;?</summary><p>Non. Vous comparez librement. Au moment de payer, il suffit d'entrer votre numéro : vous recevez un code par SMS pour vous identifier.</p></details>
      <details><summary>Quels moyens de paiement sont acceptés&nbsp;?</summary><p>Wave, Orange Money, MTN Mobile Money et Moov Money, directement dans l'application.</p></details>
      <details><summary>Comment les prestataires sont-ils vérifiés&nbsp;?</summary><p>Chaque prestataire fournit sa pièce d'identité (CNI) contrôlée par notre équipe. Les chauffeurs fournissent aussi leur permis, les bailleurs leur titre ou mandat. Les logements sont inspectés physiquement.</p></details>
      <details><summary>Que se passe-t-il si le prestataire ne vient pas&nbsp;?</summary><p>Ouvrez un litige depuis « Mes réservations ». Le paiement est gelé et, si l'absence est confirmée, vous êtes intégralement remboursé.</p></details>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
