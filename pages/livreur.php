<?php
$title = 'Livreur express & coursier moto à Abidjan';
$desc = 'Coursiers moto pour plis, colis urgents et courses au marché dans toutes les communes d\'Abidjan. Tarifs zonés et suivi en temps réel.';
$active = 'livreur';
$leaflet = true;
$x = universe('livreur');
$from = isset($_GET['from']) ? zone((int) $_GET['from']) : null;
$to = isset($_GET['to']) ? zone((int) $_GET['to']) : null;
$type = in_array($_GET['type'] ?? '', ['pli', 'colis', 'volumineux', 'courses'], true) ? $_GET['type'] : 'colis';
$est = ($from && $to) ? livreur_estimate($from, $to, $type) : null;
$types = ['pli' => ['✉️', 'Pli / document'], 'colis' => ['📦', 'Colis standard'], 'volumineux' => ['🧳', 'Volumineux (+1 000 F)'], 'courses' => ['🛒', 'Courses marché (+500 F)']];
$riders = all("SELECT * FROM providers WHERE universe = 'livreur' AND active = 1 AND kyc_status = 'verified' ORDER BY rating DESC LIMIT 3");
view('layout/header', compact('title', 'desc', 'active', 'leaflet'));
?>
<section class="page-hero" style="--c:<?= $x['color'] ?>;--hero:url('<?= e(img($x['img'], 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container">
    <nav class="crumbs"><a href="/">Accueil</a> › Livreur Express</nav>
    <h1><?= $x['emoji'] ?> Livreur Express</h1>
    <p><?= e($x['pitch']) ?></p>
  </div>
</section>

<section class="section-tight">
  <div class="container two-col">
    <div>
      <form class="card form-card" method="get" action="/livreur">
        <h2 class="h3">Calculez votre tarif</h2>
        <label class="fld"><span>📦 Ramassage</span><select name="from" required><option value="">Choisir…</option><?= zone_options($from ? (int) $from['id'] : null) ?></select></label>
        <label class="fld"><span>🏁 Livraison</span><select name="to" required><option value="">Choisir…</option><?= zone_options($to ? (int) $to['id'] : null) ?></select></label>
        <div class="seg">
          <?php foreach ($types as $k => [$ic, $l]): ?>
            <label><input type="radio" name="type" value="<?= $k ?>" <?= $type === $k ? 'checked' : '' ?>><span><?= $ic ?> <?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-primary btn-block btn-lg">Voir le tarif</button>
      </form>

      <?php if ($est): ?>
      <form class="card result-card" method="post" action="/reserver">
        <?= csrf_field() ?>
        <input type="hidden" name="universe" value="livreur">
        <input type="hidden" name="from" value="<?= (int) $from['id'] ?>">
        <input type="hidden" name="to" value="<?= (int) $to['id'] ?>">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="rc-head"><div><b><?= e($from['name']) ?> → <?= e($to['name']) ?></b><small><?= e($est['km']) ?> km · livraison ~<?= (int) $est['minutes'] ?> min</small></div><div class="big-price"><?= fcfa($est['price']) ?></div></div>
        <div class="row2">
          <label class="fld"><span>👤 Destinataire</span><input name="recipient" maxlength="80" placeholder="Nom du destinataire" required></label>
          <label class="fld"><span>📞 Téléphone</span><input name="recipient_phone" inputmode="tel" placeholder="07 00 00 00 00" required></label>
        </div>
        <label class="fld"><span>📝 Instructions</span><textarea name="note" rows="2" maxlength="300" placeholder="Ex. colis fragile, appeler avant d'arriver…"></textarea></label>
        <button class="btn btn-primary btn-block btn-lg">Commander un coursier (<?= fcfa($est['price']) ?>)</button>
        <p class="fine">📍 Suivi géolocalisé · 🔒 Paiement libéré à la livraison</p>
      </form>
      <?php endif; ?>
    </div>
    <div>
      <div class="card map-card"><div id="map" class="map" data-map <?php if ($est): ?>data-a="<?= e($from['lat'] . ',' . $from['lng']) ?>" data-b="<?= e($to['lat'] . ',' . $to['lng']) ?>" data-la="<?= e($from['name']) ?>" data-lb="<?= e($to['name']) ?>" data-moto="1"<?php endif; ?>></div></div>
      <div class="card">
        <h3 class="h4">Exemples de tarifs zonés</h3>
        <?php
        $ex = [['Cocody Centre', 'Plateau'], ['Marcory Zone 4', 'Treichville'], ['Yopougon Siporex', 'Adjamé'], ['Riviera 2', 'Port-Bouët']];
        foreach ($ex as [$a, $b]) {
            $za = one('SELECT * FROM zones WHERE name = ?', [$a]);
            $zb = one('SELECT * FROM zones WHERE name = ?', [$b]);
            if ($za && $zb) echo '<div class="price-row"><span>' . e($a) . ' → ' . e($b) . '</span><b>' . fcfa(livreur_estimate($za, $zb)['price']) . '</b></div>';
        }
        ?>
        <h3 class="h4 mt">Coursiers disponibles</h3>
        <?php foreach ($riders as $d): ?>
          <div class="prov-mini"><?= avatar($d) ?><div><b><?= e($d['name']) ?></b> <?= is_sponsored($d) ? '<span class="badge badge-gold">Recommandé</span>' : '' ?><small>📍 <?= e($d['commune']) ?> · <?= stars((float) $d['rating']) ?> · <?= (int) $d['missions'] ?> livraisons</small></div><?= kyc_badge($d['kyc_status']) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active', 'leaflet'));
