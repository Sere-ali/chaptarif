<?php
$me = require_admin();
$result = null;
$b = null;
if (is_post()) {
    $payload = (string) ($_POST['payload'] ?? '');
    $b = ticket_verify($payload);
    if (!$b || !in_array($b['universe'], ['cars', 'covoiturage'], true)) $result = ['ko', '❌ Billet invalide ou falsifié', 'Ce QR code n\'a pas été émis par ChapTarif.'];
    elseif ($b['status'] === 'VALIDE') $result = ['warn', '⚠️ Billet déjà utilisé', 'Embarquement déjà enregistré le ' . fmt_date($b['validated_at'], true) . '.'];
    elseif ($b['status'] !== 'BLOQUE') $result = ['ko', '❌ Billet non valable', 'Statut : ' . (statuses()[$b['status']][0] ?? $b['status'])];
    elseif ($b['service_date'] !== date('Y-m-d') && !isset($_POST['force'])) $result = ['warn', '⚠️ Billet pour une autre date', 'Voyage prévu le ' . fmt_date($b['service_date']) . '.'];
    else {
        if (isset($_POST['board'])) {
            booking_release($b, 'contrôleur ' . ($me['name'] ?: $me['email']), 'Embarquement validé');
            audit('billet.embarquement', $b['ref']);
            $b = one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
            $result = ['ok', '✅ Embarquement validé', 'Passager enregistré, paiement compagnie débloqué.'];
        } else {
            $result = ['ok', '✅ Billet authentique', 'Valable pour ce départ. Validez l\'embarquement.'];
        }
    }
}
$page = 'Contrôle des billets';
$nav = 'control';
$scripts = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.getElementById('scan').addEventListener('click',function(){
  var r=document.getElementById('reader');r.hidden=false;this.hidden=true;
  if(!window.Html5Qrcode){alert('Scanner indisponible, saisissez la référence.');return;}
  var q=new Html5Qrcode('reader');
  q.start({facingMode:'environment'},{fps:10,qrbox:240},function(txt){q.stop();var f=document.getElementById('ctl');f.payload.value=txt;f.submit();},function(){}).catch(function(){alert('Accès caméra refusé.');});
});
</script>
HTML;
view('admin/header', compact('page', 'nav'));
?>
<div class="panel scan-box">
  <h2 class="h4">Scanner un E-billet</h2>
  <p class="muted">Scannez le QR code du passager avec la caméra, ou saisissez sa référence (ex. CT-BUS-XXXXXX).</p>
  <button class="btn btn-primary btn-block btn-lg" id="scan" type="button">📷 Ouvrir la caméra</button>
  <div id="reader" hidden></div>
  <form id="ctl" method="post" class="mt"><?= csrf_field() ?>
    <label class="fld"><span>Référence ou contenu du QR</span><input name="payload" class="mono" required value="<?= e($_POST['payload'] ?? '') ?>"></label>
    <button class="btn btn-soft btn-block">Vérifier</button>
  </form>
  <?php if ($result): [$cls, $h, $txt] = $result; ?>
    <div class="verdict <?= $cls ?>">
      <h3 class="h3"><?= e($h) ?></h3><p><?= e($txt) ?></p>
      <?php if ($b): $d = json_decode((string) $b['details'], true) ?: []; ?>
        <dl class="kv"><dt>Référence</dt><dd class="mono"><?= e($b['ref']) ?></dd><dt>Trajet</dt><dd><?= e(($d['company'] ?? '') . ' · ' . ($d['from'] ?? '') . ' → ' . ($d['to'] ?? '') . ' ' . ($d['time'] ?? '')) ?></dd><dt>Date</dt><dd><?= fmt_date($b['service_date']) ?></dd><dt>Siège(s)</dt><dd><?= e(implode(', ', $d['seats'] ?? [$b['seat_no']])) ?></dd><dt>Passagers</dt><dd><?= (int) ($d['passengers'] ?? 1) ?></dd></dl>
        <?php if ($b['status'] === 'BLOQUE' && $cls !== 'ko'): ?>
          <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="payload" value="<?= e($b['ref']) ?>"><input type="hidden" name="board" value="1"><?php if ($cls === 'warn'): ?><input type="hidden" name="force" value="1"><?php endif; ?>
            <button class="btn btn-primary btn-block">✓ Valider l'embarquement</button></form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php view('admin/footer', compact('scripts'));
