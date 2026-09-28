<?php
$u = require_client();

if (is_post()) {
    $b = one('SELECT * FROM bookings WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $u['id']]);
    $act = $_POST['action'] ?? '';
    if ($act === 'profile') {
        q('UPDATE users SET name = ? WHERE id = ?', [mb_substr(trim((string) $_POST['name']), 0, 80), $u['id']]);
        flash('success', 'Profil mis à jour.');
        redirect('/compte');
    }
    if (!$b) redirect('/compte');
    if ($act === 'confirm' && booking_release($b, 'client')) {
        flash('success', 'Merci ! Le prestataire a été payé. Votre avis compte : à bientôt sur ChapTarif.');
    } elseif ($act === 'dispute') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 10) flash('error', 'Décrivez le problème en quelques mots (10 caractères minimum).');
        elseif (booking_dispute($b, $reason)) flash('warn', 'Réclamation enregistrée. Le paiement est gelé : notre équipe vous contacte sous 24h.');
    } elseif ($act === 'pay' && $b['status'] === 'EN_ATTENTE_PAIEMENT') {
        $init = payment_init($b, $u);
        if ($init['ok']) redirect($init['url']);
        flash('error', $init['error']);
    } elseif ($act === 'cancel' && booking_cancel($b)) {
        flash('info', 'Réservation annulée.');
    }
    redirect('/compte?ref=' . urlencode($b['ref']));
}

$bookings = all("SELECT b.*, p.name AS provider_name, p.phone AS provider_phone FROM bookings b LEFT JOIN providers p ON p.id = b.provider_id WHERE b.user_id = ? AND b.status <> 'ANNULE' ORDER BY b.id DESC LIMIT 100", [$u['id']]);
$hl = (string) ($_GET['ref'] ?? '');
$title = 'Mes réservations';
$active = 'account';
view('layout/header', compact('title', 'active'));
?>
<section class="section-tight">
  <div class="container narrow-2">
    <?php if (isset($_GET['paid'])): ?>
      <div class="paid-banner reveal"><span>🎉</span><div><b>Paiement reçu — fonds sécurisés !</b><p>Votre argent est bloqué sur le compte séquestre ChapTarif. Il ne sera versé qu'après votre confirmation.</p></div></div>
    <?php endif; ?>
    <div class="acc-head">
      <div><h1 class="h2">Bonjour <?= e($u['name'] ?: '') ?> 👋</h1><p class="muted"><?= e(fmt_phone($u['phone'])) ?></p></div>
      <form method="post" action="/deconnexion"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Se déconnecter</button></form>
    </div>

    <?php if (!$bookings): ?>
      <div class="empty"><div class="empty-ico">🧾</div><h2 class="h3">Aucune réservation pour l'instant</h2><p>Comparez et réservez votre premier service en 3 clics.</p><a class="btn btn-primary" href="/#univers">Découvrir les services</a></div>
    <?php endif; ?>

    <div class="bk-list">
    <?php foreach ($bookings as $b): $x = universe($b['universe']); $d = json_decode((string) $b['details'], true) ?: []; ?>
      <article class="bk card <?= $hl === $b['ref'] ? 'hl' : '' ?>" id="<?= e($b['ref']) ?>" style="--c:<?= $x['color'] ?>;--bg:<?= $x['bg'] ?>">
        <div class="bk-top">
          <span class="sum-ico"><?= $x['emoji'] ?></span>
          <div class="bk-title"><small><?= e($x['name']) ?> · <span class="mono"><?= e($b['ref']) ?></span></small><b><?= e($b['title']) ?></b><small>📅 <?= fmt_date($b['service_date']) ?><?= !empty($d['time']) ? ' à ' . e($d['time']) : '' ?></small></div>
          <div class="bk-amt"><b><?= fcfa($b['total']) ?></b><?= status_badge($b['status']) ?></div>
        </div>

        <?php if ($b['status'] === 'BLOQUE'): ?>
          <div class="bk-escrow">
            <div class="timeline"><span class="t-done">Payé</span><span class="t-done">Fonds bloqués</span><span class="t-on"><?= $b['universe'] === 'cars' ? 'Embarquement' : 'Service en cours' ?></span><span>Paiement libéré</span></div>
            <?php if ($b['universe'] === 'cars'): ?>
              <a class="btn btn-primary" href="/billet?ref=<?= urlencode($b['ref']) ?>">🎫 Afficher mon E-billet</a>
            <?php else: ?>
              <div class="code-box"><span>Code de validation à remettre au prestataire <em>uniquement une fois le service terminé</em></span><b class="mono"><?= e($b['release_code']) ?></b></div>
              <?php if (!empty($b['provider_name'])): ?><p class="muted small">Prestataire : <b><?= e($b['provider_name']) ?></b><?= $b['provider_phone'] ? ' · <a href="tel:' . e($b['provider_phone']) . '">' . e(fmt_phone($b['provider_phone'])) . '</a>' : '' ?></p><?php endif; ?>
              <div class="bk-actions">
                <form method="post" data-confirm="Confirmez-vous que le service a bien été réalisé ? Le prestataire sera payé immédiatement."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button name="action" value="confirm" class="btn btn-primary">✓ Confirmer la fin du travail</button></form>
                <button class="btn btn-ghost" data-toggle="#dsp<?= (int) $b['id'] ?>">⚠️ Signaler un problème</button>
              </div>
            <?php endif; ?>
            <form method="post" class="dispute" id="dsp<?= (int) $b['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <label class="fld"><span>Que s'est-il passé ?</span><textarea name="reason" rows="3" required minlength="10" placeholder="Ex. le prestataire ne s'est pas présenté, travail non conforme…"></textarea></label>
              <button name="action" value="dispute" class="btn btn-danger">Ouvrir une réclamation (gèle le paiement)</button>
            </form>
          </div>
        <?php elseif ($b['status'] === 'EN_ATTENTE_PAIEMENT'): ?>
          <div class="bk-actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button name="action" value="pay" class="btn btn-primary">Finaliser le paiement</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button name="action" value="cancel" class="btn btn-ghost">Annuler</button></form>
          </div>
        <?php elseif ($b['status'] === 'EN_LITIGE'): ?>
          <div class="alert alert-error small">Réclamation en cours d'examen : « <?= e($b['dispute_reason']) ?> ». Le paiement reste gelé jusqu'à la décision de notre équipe.</div>
        <?php elseif ($b['status'] === 'VALIDE' && $b['universe'] === 'cars'): ?>
          <a class="btn btn-soft btn-sm" href="/billet?ref=<?= urlencode($b['ref']) ?>">Voir le billet</a>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    </div>

    <details class="card mt"><summary><b>Mon profil</b></summary>
      <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <label class="fld"><span>Nom et prénom</span><input name="name" value="<?= e($u['name']) ?>" maxlength="80"></label>
        <button class="btn btn-soft">Enregistrer</button>
      </form>
    </details>
  </div>
</section>
<?php view('layout/footer', compact('active'));
