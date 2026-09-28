<?php
$title = 'ChapTarif';
$active = 'home';
$U = universes();
$zones = zones_list();
$cities = array_column(all('SELECT DISTINCT to_city FROM trips WHERE active = 1 AND from_city = ? ORDER BY to_city', ['Abidjan']), 'to_city');
$demoFrom = one("SELECT * FROM zones WHERE name = 'Cocody St-Jean'");
$demoTo = one("SELECT * FROM zones WHERE name = 'Yopougon Siporex'");
$demo = ($demoFrom && $demoTo) ? vtc_estimate($demoFrom, $demoTo) : null;
$counts = [
    'providers' => (int) val("SELECT COUNT(*) FROM providers WHERE active = 1 AND kyc_status = 'verified'"),
    'trips' => (int) val('SELECT COUNT(*) FROM trips WHERE active = 1'),
    'props' => (int) val('SELECT COUNT(*) FROM properties WHERE active = 1 AND certified = 1'),
];
view('layout/header', compact('title', 'active'));
?>
<section class="hero">
  <div class="hero-bg" style="--hero:url('<?= e(img('hero-abidjan', 'w_1800,h_1000,c_fill,q_auto,f_auto')) ?>')"></div>
  <div class="container hero-grid">
    <div class="hero-copy reveal">
      <span class="pill"><span class="bolt">⚡</span> Le comparateur n°1 en Côte d'Ivoire</span>
      <h1>Où &amp; que souhaitez-vous <span class="grad">aujourd'hui&nbsp;?</span></h1>
      <p class="lead">Comparez les prix, réservez en 3 clics et payez en toute sécurité. Votre argent reste <strong>bloqué</strong> jusqu'à ce que le service soit bien rendu.</p>

      <div class="quick" data-tabs>
        <div class="quick-tabs" role="tablist">
          <?php $i = 0; foreach ($U as $k => $x): ?>
            <button role="tab" class="qt <?= $i++ === 0 ? 'on' : '' ?>" data-tab="<?= $k ?>" style="--c:<?= $x['color'] ?>"><span><?= $x['emoji'] ?></span><?= e($x['short']) ?></button>
          <?php endforeach; ?>
        </div>
        <form class="quick-pane on" data-pane="vtc" action="/vtc" method="get">
          <label class="fld"><span>🟢 Départ</span><select name="from" required><option value="">Choisir…</option><?= zone_options($demoFrom ? (int) $demoFrom['id'] : null) ?></select></label>
          <label class="fld"><span>🔴 Arrivée</span><select name="to" required><option value="">Choisir…</option><?= zone_options($demoTo ? (int) $demoTo['id'] : null) ?></select></label>
          <button class="btn btn-primary btn-lg">Comparer les prix</button>
        </form>
        <form class="quick-pane" data-pane="menage" action="/menage" method="get">
          <label class="fld"><span>📍 Votre commune</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les aides ménagères</button>
        </form>
        <form class="quick-pane" data-pane="pressing" action="/pressing" method="get">
          <label class="fld"><span>📍 Collecte à</span><select name="commune"><option value="">Toutes les communes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-lg">Voir les pressings</button>
        </form>
        <form class="quick-pane" data-pane="livreur" action="/livreur" method="get">
          <label class="fld"><span>📦 Ramassage</span><select name="from" required><option value="">Choisir…</option><?= zone_options() ?></select></label>
          <label class="fld"><span>🏁 Livraison</span><select name="to" required><option value="">Choisir…</option><?= zone_options() ?></select></label>
          <button class="btn btn-primary btn-lg">Calculer le tarif</button>
        </form>
        <form class="quick-pane" data-pane="cars" action="/cars" method="get">
          <input type="hidden" name="from" value="Abidjan">
          <label class="fld"><span>🚌 Abidjan →</span><select name="to"><?php foreach ($cities as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <label class="fld"><span>📅 Date</span><input type="date" name="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-primary btn-lg">Voir les départs</button>
        </form>
        <form class="quick-pane" data-pane="immobilier" action="/immobilier" method="get">
          <label class="fld"><span>🏙️ Commune</span><select name="commune"><option value="">Toutes</option><?php foreach (communes() as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
          <label class="fld"><span>📅 Arrivée</span><input type="date" name="checkin" min="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-primary btn-lg">Trouver un meublé</button>
        </form>
      </div>

      <ul class="trust">
        <li><span>🛡️</span> Prestataires CNI vérifiés</li>
        <li><span>🔒</span> Paiement sous séquestre</li>
        <li><span>⚡</span> Réservation en 3 clics</li>
      </ul>
    </div>

    <div class="hero-visual reveal" aria-hidden="true">
      <div class="phone">
        <div class="phone-notch"></div>
        <div class="phone-screen">
          <div class="ps-head"><img src="/assets/img/logo.svg" width="26" alt=""> <b>ChapTarif</b> <span class="ps-loc">📍 Abidjan</span></div>
          <div class="ps-route"><div><i class="g"></i> Cocody St-Jean</div><div><i class="r"></i> Yopougon Siporex</div></div>
          <div class="ps-label">Prix constatés du marché</div>
          <?php if ($demo): foreach (array_slice($demo['market'], 0, 2) as $m): ?>
            <div class="ps-row"><span><?= e($m['name']) ?><small>Estimation</small></span><b><?= fcfa($m['price']) ?></b></div>
          <?php endforeach; ?>
            <div class="ps-row best"><span>⭐ Tarif ChapTarif<small>Chauffeur à 3 min</small></span><b><?= fcfa($demo['price']) ?><em>-<?= fcfa($demo['saving']) ?></em></b></div>
          <?php endif; ?>
          <div class="ps-btn">Commander via Wave</div>
          <div class="ps-foot">✓ Tarif garanti · Aucun surcoût embouteillage</div>
        </div>
      </div>
      <div class="float f1"><span class="fi">🔒</span><div><b>5 000 F bloqués</b><small>Versés après votre validation</small></div></div>
      <div class="float f2"><span class="fi">🎫</span><div><b>E-billet UTB</b><small>Siège 14 · QR code</small></div></div>
      <div class="float f3"><span class="fi">✅</span><div><b>Awa K. · CNI vérifiée</b><small>★ 4,9 · Cocody Angré</small></div></div>
    </div>
  </div>
</section>

<section class="section" id="univers">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow">Univers disponibles</span>
      <h2>Tout votre quotidien, <span class="grad">un seul tarif clair</span></h2>
      <p>Six services essentiels, des prix affichés à l'avance et zéro négociation.</p>
    </div>
    <div class="uni-grid">
      <?php foreach ($U as $k => $x): ?>
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
    <div class="paybar reveal">🔒 <strong>Paiement In-App sécurisé :</strong> <span class="chip chip-wave">Wave</span><span class="chip chip-om">Orange Money</span><span class="chip chip-mtn">MTN MoMo</span><span class="chip chip-moov">Moov</span></div>
  </div>
</section>

<section class="section section-dark" id="sequestre">
  <div class="container">
    <div class="sec-head light">
      <span class="eyebrow">Tiers de confiance</span>
      <h2>Votre argent est protégé <span class="grad-light">jusqu'au bout</span></h2>
      <p>ChapTarif garde les fonds sous séquestre. Le prestataire n'est payé qu'une fois le travail confirmé.</p>
    </div>
    <ol class="steps">
      <li class="reveal"><span class="step-n">1</span><h4>Vous réservez</h4><p>Choisissez votre prestataire ou formule, prix affiché à l'avance.</p></li>
      <li class="reveal"><span class="step-n">2</span><h4>Vous payez</h4><p>Wave, Orange Money, MTN ou Moov. Les fonds sont <b>bloqués</b>.</p></li>
      <li class="reveal"><span class="step-n">3</span><h4>Le service est rendu</h4><p>Ménage fait, colis livré, clés remises : le prestataire intervient.</p></li>
      <li class="reveal"><span class="step-n">4</span><h4>Vous validez</h4><p>« Confirmer la fin du travail » ou code remis au prestataire.</p></li>
      <li class="reveal"><span class="step-n">5</span><h4>Paiement libéré</h4><p>Le prestataire est payé automatiquement. Un souci&nbsp;? Ouvrez un litige.</p></li>
    </ol>
  </div>
</section>

<section class="section">
  <div class="container split">
    <div class="reveal">
      <span class="eyebrow">Comparateur VTC</span>
      <h2>Voyez l'écart <span class="grad">avant de payer</span></h2>
      <p class="lead-sm">ChapTarif estime en direct le prix des grandes applications et des taxis compteurs selon la distance, puis vous propose le tarif garanti de son réseau de chauffeurs affiliés.</p>
      <ul class="checks">
        <li>Estimation selon distance réelle entre quartiers</li>
        <li>Tarif fixé avant la course, sans surcoût embouteillage</li>
        <li>Chauffeurs vérifiés : CNI, permis, véhicule</li>
      </ul>
      <a class="btn btn-primary btn-lg" href="/vtc">Comparer un trajet</a>
    </div>
    <div class="compare-card reveal">
      <?php if ($demo): ?>
      <div class="cc-head"><span>🟢 Cocody St-Jean</span><span class="cc-arrow">→</span><span>🔴 Yopougon Siporex</span></div>
      <div class="cc-meta"><?= e($demo['km']) ?> km · ~<?= (int) $demo['minutes'] ?> min</div>
      <?php $max = max(array_column($demo['market'], 'price')); foreach ($demo['market'] as $m): ?>
        <div class="bar-row"><span><?= e($m['name']) ?></span><div class="bar"><i style="width:<?= round($m['price'] / $max * 100) ?>%"></i></div><b><?= fcfa($m['price']) ?></b></div>
      <?php endforeach; ?>
      <div class="bar-row ours"><span>⭐ ChapTarif</span><div class="bar"><i style="width:<?= round($demo['price'] / $max * 100) ?>%"></i></div><b><?= fcfa($demo['price']) ?></b></div>
      <p class="cc-note">Prix du marché : estimations indicatives calculées par ChapTarif.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section stats-band">
  <div class="container stats">
    <div class="stat reveal"><b data-count="<?= $counts['providers'] ?>">0</b><span>prestataires vérifiés</span></div>
    <div class="stat reveal"><b data-count="<?= count($zones) ?>">0</b><span>quartiers couverts à Abidjan</span></div>
    <div class="stat reveal"><b data-count="<?= $counts['trips'] ?>">0</b><span>départs de cars quotidiens</span></div>
    <div class="stat reveal"><b>100%</b><span>des paiements sous séquestre</span></div>
  </div>
</section>

<section class="section">
  <div class="container split split-rev">
    <div class="app-visual reveal">
      <img src="<?= e(img('app-user', 'w_900,h_700,c_fill,g_face,q_auto,f_auto')) ?>" alt="Cliente utilisant ChapTarif sur son téléphone" loading="lazy" width="900" height="700">
      <div class="float f4"><span class="fi">📲</span><div><b>Code reçu par SMS</b><small>Connexion sans mot de passe</small></div></div>
    </div>
    <div class="reveal">
      <span class="eyebrow">Pensé pour Abidjan</span>
      <h2>Léger, rapide, <span class="grad">même en 3G</span></h2>
      <p class="lead-sm">Pas de compte à créer pour comparer. Votre numéro de téléphone suffit au moment de payer : un code SMS et c'est parti.</p>
      <div class="features">
        <div><span>📶</span><b>Ultra léger</b><small>Chargement rapide sur réseau mobile</small></div>
        <div><span>📱</span><b>Installable</b><small>Ajoutez ChapTarif à votre écran d'accueil</small></div>
        <div><span>🎫</span><b>E-billets QR</b><small>Montrez votre téléphone à l'embarquement</small></div>
        <div><span>💬</span><b>Support local</b><small>Une équipe à Abidjan 7j/7</small></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="pro-cta reveal">
      <div>
        <span class="eyebrow light">Prestataires & partenaires</span>
        <h2>Développez votre activité avec ChapTarif</h2>
        <p>Aides ménagères, pressings, coursiers, chauffeurs, compagnies de cars, bailleurs : recevez des clients qui ont déjà payé. Paiement garanti après chaque mission.</p>
        <div class="pro-points">
          <span>✓ Paiement garanti sur Wave / OM</span><span>✓ Inscription gratuite</span><span>✓ Badge « Recommandé » : <?= fcfa(setting('sponsor_price', 5000)) ?>/mois</span>
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
