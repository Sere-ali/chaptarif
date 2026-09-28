<?php
$u = require_client();
$b = one("SELECT * FROM bookings WHERE ref = ? AND universe = 'cars'", [(string) ($_GET['ref'] ?? '')]);
if (!$b || ((int) $b['user_id'] !== (int) $u['id'] && !is_admin($u))) not_found();
if (!in_array($b['status'], ['BLOQUE', 'VALIDE'], true)) redirect('/compte');
$d = json_decode((string) $b['details'], true) ?: [];
$seats = $d['seats'] ?? [$b['seat_no']];
$title = 'E-billet ' . $b['ref'];
$qr = true;
$scripts = '<script>document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("qr");if(window.QRCode){new QRCode(el,{text:el.dataset.payload,width:210,height:210,correctLevel:QRCode.CorrectLevel.M});}});</script>';
view('layout/header', compact('title'));
?>
<section class="section-tight">
  <div class="container">
    <div class="ticket">
      <div class="tk-head">
        <div><small>E-BILLET OFFICIEL</small><b><?= e($d['company'] ?? '') ?> · <?= e($d['class'] ?? '') ?></b></div>
        <img src="/assets/img/logo.svg" width="36" alt="">
      </div>
      <div class="tk-route">
        <div><small>Départ</small><b><?= e($d['from'] ?? '') ?></b><span><?= e($d['time'] ?? '') ?></span></div>
        <div class="tk-bus">🚌<i></i><small><?= e($d['duration'] ?? '') ?></small></div>
        <div class="right"><small>Arrivée</small><b><?= e($d['to'] ?? '') ?></b><span><?= fmt_date($b['service_date']) ?></span></div>
      </div>
      <div class="tk-grid">
        <div><small>Passager</small><b><?= e(($d['passenger_name'] ?? '') ?: ($u['name'] ?: fmt_phone($u['phone']))) ?></b></div>
        <div><small>Siège(s)</small><b>N° <?= e(implode(', ', array_filter($seats))) ?></b></div>
        <div><small>Gare</small><b><?= e($d['station'] ?? '') ?></b></div>
        <div><small>Passagers</small><b><?= (int) ($d['passengers'] ?? 1) ?></b></div>
      </div>
      <div class="tk-cut"></div>
      <div class="tk-qr">
        <div id="qr" data-payload="<?= e(ticket_payload($b)) ?>"></div>
        <b class="mono"><?= e($b['ref']) ?></b>
        <?= $b['status'] === 'VALIDE' ? '<span class="badge badge-gray">Déjà embarqué</span>' : '<span class="badge badge-green">✓ Valable à l\'embarquement</span>' ?>
        <small class="muted">Présentez ce QR code au contrôleur. Code de secours : <b class="mono"><?= e(ticket_sig($b['ref'])) ?></b></small>
      </div>
    </div>
    <div class="center mt"><button class="btn btn-soft" onclick="window.print()">🖨️ Imprimer / Enregistrer en PDF</button> <a class="btn btn-ghost" href="/compte">Mes réservations</a></div>
  </div>
</section>
<?php view('layout/footer', compact('qr', 'scripts'));
