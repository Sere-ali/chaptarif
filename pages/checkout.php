<?php
if (is_post()) {
    $uk = (string) ($_POST['universe'] ?? '');
    $r = build_quote($uk, $_POST);
    if (!$r['ok']) {
        flash('error', $r['error']);
        redirect($_SERVER['HTTP_REFERER'] ?? (universes()[$uk]['url'] ?? '/'));
    }
    $_SESSION['checkout'] = ['quote' => $r['quote'], 'input' => array_diff_key($_POST, ['_csrf' => 1])];
    redirect('/reserver');
}

$co = $_SESSION['checkout'] ?? null;
if (!$co || time() - $co['quote']['created'] > 3600) {
    unset($_SESSION['checkout']);
    flash('warn', 'Votre sélection a expiré. Merci de recommencer.');
    redirect('/');
}
// Re-vérification des disponibilités et du prix
$r = build_quote($co['quote']['universe'], $co['input']);
if (!$r['ok']) {
    unset($_SESSION['checkout']);
    flash('error', $r['error']);
    redirect(universes()[$co['quote']['universe']]['url'] ?? '/');
}
$q = $r['quote'];
$_SESSION['checkout']['quote'] = $q;
$x = universe($q['universe']);
$u = current_user();
$title = 'Finaliser ma réservation';
$active = '';
view('layout/header', compact('title', 'active'));
?>
<section class="section-tight">
  <div class="container narrow-2">
    <ol class="progress">
      <li class="done">Sélection</li>
      <li class="<?= $u ? 'done' : 'on' ?>">Identification</li>
      <li class="<?= $u ? 'on' : '' ?>">Paiement</li>
      <li>Confirmation</li>
    </ol>
    <div class="two-col">
      <div>
        <?php if (!$u): ?>
          <div class="card form-card">
            <h2 class="h3">📱 Votre numéro de téléphone</h2>
            <p class="muted">Pas de mot de passe : nous vous envoyons un code par SMS pour sécuriser votre réservation.</p>
            <form method="post" action="/connexion">
              <?= csrf_field() ?>
              <input type="hidden" name="step" value="send"><input type="hidden" name="next" value="/reserver">
              <label class="fld"><span>Nom et prénom</span><input name="name" maxlength="80" autocomplete="name" placeholder="Ex. Koné Aminata" required></label>
              <label class="fld"><span>Téléphone</span><div class="tel"><span>🇨🇮 +225</span><input name="phone" inputmode="tel" autocomplete="tel" placeholder="07 00 00 00 00" required></div></label>
              <button class="btn btn-primary btn-block btn-lg">Recevoir mon code</button>
            </form>
          </div>
        <?php else: ?>
          <form class="card form-card" method="post" action="/paiement">
            <?= csrf_field() ?>
            <h2 class="h3">💳 Choisissez votre moyen de paiement</h2>
            <p class="muted">Connecté en tant que <b><?= e($u['name'] ?: fmt_phone($u['phone'])) ?></b> · <?= e(fmt_phone($u['phone'])) ?></p>
            <div class="pay-methods">
              <?php $i = 0; foreach (payment_methods() as $k => [$label, $color, $hint]): ?>
                <label class="pm" style="--pm:<?= $color ?>">
                  <input type="radio" name="method" value="<?= $k ?>" <?= $i++ === 0 ? 'checked' : '' ?>>
                  <span class="pm-logo"><?= e(mb_substr($label, 0, 1)) ?></span>
                  <span><b><?= e($label) ?></b><small><?= e($hint) ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
            <label class="check"><input type="checkbox" required> J'accepte les <a href="/mentions-legales" target="_blank">conditions générales</a> et le paiement sous séquestre.</label>
            <button class="btn btn-primary btn-block btn-lg">Payer <?= fcfa($q['total']) ?> en toute sécurité</button>
            <?php if (payment_mode() === 'simulation'): ?><p class="fine">Mode test actif : aucun débit réel ne sera effectué.</p><?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
      <aside>
        <div class="card summary" style="--c:<?= $x['color'] ?>;--bg:<?= $x['bg'] ?>">
          <div class="sum-head"><span class="sum-ico"><?= $x['emoji'] ?></span><div><small><?= e($x['name']) ?></small><b><?= e($q['title']) ?></b></div></div>
          <?php foreach ($q['summary'] as [$k, $v]): ?>
            <div class="sum-row"><span><?= e($k) ?></span><b><?= e($v) ?></b></div>
          <?php endforeach; ?>
          <hr>
          <div class="sum-row"><span>Prestation</span><b><?= fcfa($q['amount']) ?></b></div>
          <?php if ($q['service_fee']): ?><div class="sum-row"><span>Frais de service</span><b><?= fcfa($q['service_fee']) ?></b></div><?php endif; ?>
          <div class="sum-row total"><span>Total à payer</span><b><?= fcfa($q['total']) ?></b></div>
          <div class="escrow-note">🔒 <b>Paiement protégé.</b> Les fonds restent bloqués chez ChapTarif et ne sont versés qu'après la bonne exécution du service.</div>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php view('layout/footer');
