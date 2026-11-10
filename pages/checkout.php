<?php
if (is_post()) {
    $uk = (string) ($_POST['universe'] ?? '');
    $r = build_quote($uk, $_POST);
    if (!$r['ok']) {
        flash('error', $r['error']);
        redirect($_SERVER['HTTP_REFERER'] ?? (universes()[$uk]['url'] ?? '/'));
    }
    $_SESSION['checkout'] = ['quote' => $r['quote'], 'input' => array_diff_key($_POST, ['_csrf' => 1, 'code' => 1])];
    $postedCode = trim((string) ($_POST['code'] ?? ''));
    if ($postedCode !== '') {
        $dr = discount_apply($r['quote'], $postedCode);
        if ($dr['ok']) $_SESSION['checkout']['code'] = $dr['quote']['discount_code'];
        else flash('error', $dr['error']);
    }
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
$codeApplied = '';
if (!empty($_SESSION['checkout']['code'])) {
    $dr = discount_apply($q, (string) $_SESSION['checkout']['code']);
    if ($dr['ok']) { $q = $dr['quote']; $codeApplied = $q['discount_code']; }
    else { unset($_SESSION['checkout']['code']); flash('error', $dr['error']); }
}
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
    <?php $prov = (!empty($q['provider_id']) && !in_array($q['universe'], ['cars', 'covoiturage'], true)) ? one('SELECT * FROM providers WHERE id = ?', [(int) $q['provider_id']]) : null; ?>
    <?php if ($prov): ?>
      <div class="card prov-pick" id="provPick">
        <div class="prov-pick-top">
          <?= avatar($prov, 'lg') ?>
          <div><small class="prov-dispo">Prestataire disponible</small><b><?= e($prov['name']) ?></b><span><?= e($x['name']) ?><?= !empty($prov['commune']) ? ' · ' . e($prov['commune']) : '' ?></span>
            <span class="prov-pick-meta"><?= stars((float) $prov['rating']) ?> · <?= (int) $prov['missions'] ?> missions <?= kyc_badge($prov['kyc_status'], $prov) ?></span></div>
        </div>
        <a class="prov-pick-link" href="/profil-prestataire?id=<?= (int) $prov['id'] ?>">Voir son profil</a>
        <button type="button" class="btn btn-primary btn-block btn-lg" data-pick>Sélectionner ce prestataire</button>
      </div>
    <?php endif; ?>
    <div class="two-col checkout-cols" data-steps="<?= $prov ? 1 : 0 ?>">
      <div>
        <?php if (!$u): ?>
          <div class="card form-card">
            <h2 class="h3">Connectez-vous pour réserver</h2>
            <p class="muted">Votre compte protège vos réservations et vos paiements. Connectez-vous avec votre numéro et votre mot de passe, ou créez un compte en 1 minute.</p>
            <a class="btn btn-primary btn-block btn-lg" href="/connexion?next=%2Freserver%23sel">Se connecter</a>
            <a class="btn btn-soft btn-block btn-lg" style="margin-top:10px" href="/connexion?mode=signup&amp;next=%2Freserver%23sel">Créer un compte</a>
          </div>
        <?php else: ?>
          <form class="card form-card" method="post" action="/paiement" id="payForm">
            <?= csrf_field() ?>
            <p class="muted">Connecté en tant que <b><?= e($u['name'] ?: fmt_phone($u['phone'])) ?></b> · <?= e(fmt_phone($u['phone'])) ?></p>
            <?php if (in_array($q['universe'], ['cars', 'covoiturage'], true)): $np = (int) ($q['details']['passengers'] ?? 1); $pn = $q['details']['passenger_names'] ?? []; ?>
              <h2 class="h3"> <?= $np > 1 ? 'Nom de chaque passager' : 'Nom du passager' ?></h2>
              <p class="muted">Le nom de chaque passager figurera sur son billet.</p>
              <?php for ($i = 0; $i < $np; $i++): ?>
                <label class="fld"><span>Passager <?= $i + 1 ?></span><input name="passenger_names[]" maxlength="60" required autocomplete="off" placeholder="Nom et prénom" value="<?= e($pn[$i] ?? ($i === 0 && $np === 1 ? ($u['name'] ?? '') : '')) ?>"></label>
              <?php endfor; ?>
            <?php endif; ?>
            <h2 class="h3"> Choisissez votre moyen de paiement</h2>
            <div class="pay-methods">
              <?php $i = 0; foreach (payment_methods() as $k => [$label, $color, $hint]): ?>
                <label class="pm" style="--pm:<?= $color ?>">
                  <input type="radio" name="method" value="<?= $k ?>" required <?= $i++ === 0 ? 'checked' : '' ?>>
                  <?php $lg = pay_logo($k); ?><span class="pm-logo<?= $lg ? ' has-img' : '' ?>"><?= $lg ? '<img src="' . e($lg) . '" alt="' . e($label) . '">' : e(mb_substr($label, 0, 1)) ?></span>
                  <span><b><?= e($label) ?></b><small><?= e($hint) ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
            <label class="check" data-after-sum><input type="checkbox" required form="payForm"> J'accepte les <a href="/mentions-legales" target="_blank">conditions générales</a> et le paiement sous séquestre.</label>
            <button class="btn btn-primary btn-block btn-lg" form="payForm" data-after-sum>Payer <?= fcfa($q['total']) ?> en toute sécurité</button>
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
          <?php $gar = (int) ($q['garantie_fee'] ?? 0); $fraisOnly = (int) $q['service_fee'] - $gar; ?>
          <?php if ($gar): ?><div class="sum-row"><span>Garantie dommage (<?= e(str_replace('.', ',', (string) (float) setting('garantie_dommage_pct', 6.72))) ?>% du montant de la formule)</span><b><?= fcfa($gar) ?></b></div><?php endif; ?>
          <?php if ($fraisOnly > 0 || isset($q['garantie_fee'])): ?><div class="sum-row"><span>Frais de service</span><b><?= $fraisOnly > 0 ? fcfa($fraisOnly) : 'Offerts' ?></b></div><?php endif; ?>
          <?php if (!empty($q['discount'])): ?><div class="sum-row"><span>Code de réduction (<?= e($q['discount_code']) ?>)</span><b>− <?= fcfa($q['discount']) ?></b></div><?php endif; ?>
          <form method="post" action="/reserver/code" class="promo-sum">
            <?= csrf_field() ?>
            <?php if (!empty($q['discount'])): ?>
              <button class="btn btn-ghost btn-xs" name="action" value="remove">Retirer le code de réduction</button>
            <?php else: ?>
              <label class="fld" style="margin:0"><span>Code de réduction</span>
                <div class="promo-row"><input name="code" maxlength="30" autocomplete="off" autocapitalize="characters" placeholder="Votre code"><button class="btn btn-soft" name="action" value="apply">Appliquer</button></div></label>
            <?php endif; ?>
          </form>
          <div class="sum-row total"><span>Total à payer</span><b><?= fcfa($q['total']) ?></b></div>
          <div class="escrow-note"> <b>Paiement protégé.</b> Les fonds restent bloqués chez ChapTarif et ne sont versés qu'après la bonne exécution du service.</div>
        </div>
      </aside>
    </div>
  </div>
</section>
<script>
(function () {
  var w = document.querySelector('.checkout-cols'); if (!w) return;
  var main = w.firstElementChild, aside = w.querySelector('aside'), pick = document.getElementById('provPick');
  var form = document.getElementById('payForm'), moved = false;
  var after = form ? Array.prototype.slice.call(form.querySelectorAll('[data-after-sum]')) : [];
  function showPay() {
    main.hidden = false;
    if (!form) aside.hidden = false;
    if (pick) { var b = pick.querySelector('[data-pick]'); b.textContent = '✓ Prestataire sélectionné'; b.disabled = true; pick.classList.add('picked'); }
  }
  function showSum() {
    if (!form) return;
    if (!moved) { form.parentNode.insertBefore(aside, form.nextSibling); var box = document.createElement('div'); box.className = 'after-sum'; after.forEach(function (n) { box.appendChild(n); }); aside.appendChild(box); moved = true; }
    aside.hidden = false; after.forEach(function (n) { n.hidden = false; });
  }
  if (pick) main.hidden = true;
  if (pick || form) aside.hidden = true;
  if (form) {
    after.forEach(function (n) { n.hidden = true; });
    form.querySelectorAll('input[name=method]').forEach(function (r) {
      r.checked = false;
      r.addEventListener('change', function () { showSum(); setTimeout(function () { aside.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60); });
    });
  }
  if (pick) pick.querySelector('[data-pick]').addEventListener('click', function () { showPay(); setTimeout(function () { main.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60); });
  if (!pick) showPay();
  if (location.hash === '#sel' || location.hash === '#pay') showPay();
  if (location.hash === '#pay') showSum();
})();
</script>
<?php view('layout/footer');
