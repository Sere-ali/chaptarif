<?php
// Centre de sécurité (Super Admin) : état des protections, alertes, sauvegardes, plan de réaction.
$me = require_super();

if (is_post()) {
    $act = (string) ($_POST['action'] ?? '');
    if ($act === 'seen') {
        q("UPDATE security_events SET seen = 1 WHERE seen = 0");
        flash('success', 'Alertes marquées comme lues.');
    } elseif ($act === 'backup') {
        stepup_require($me, '/admin/securite');
        $r = backup_run('manuel · ' . ($me['email'] ?? ''));
        $r['ok'] ? flash('success', 'Sauvegarde chiffrée créée et envoyée hors du serveur (' . round($r['bytes'] / 1024) . ' Ko).') : flash('error', 'Sauvegarde impossible : ' . $r['error']);
    } elseif ($act === 'restore_test') {
        $r = backup_test_restore();
        $r['ok'] ? flash('success', 'Test de restauration réussi : sauvegarde du ' . fmt_date($r['created'], true) . ' lue et déchiffrée (' . $r['tables'] . ' tables, ' . $r['rows'] . ' lignes).') : flash('error', 'Test de restauration échoué : ' . $r['error']);
    }
    redirect('/admin/securite');
}

$ev = all('SELECT e.*, u.email, u.phone FROM security_events e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.id DESC LIMIT 60');
$admins = all("SELECT name, email, totp_enabled, active FROM users WHERE role IN ('admin','super_admin') ORDER BY id");
$no2fa = array_filter($admins, fn($a) => (int) $a['active'] && empty($a['totp_enabled']));
$lastBk = (string) setting('backup_last', '');
$lastTest = (string) setting('backup_test_last', '');
$bkOk = $lastBk !== '' && strtotime($lastBk) > time() - 2 * 86400;
$https = IS_HTTPS;
$pm = payment_mode();
$checks = [
    ['Site', 'HTTPS forcé + HSTS (navigateur obligé d\'utiliser HTTPS)', $https],
    ['Site', 'En-têtes de sécurité (CSP, anti-clickjacking, anti-sniffing, permissions)', true],
    ['Site', 'Protection CSRF sur tous les formulaires, requêtes SQL préparées, échappement anti-XSS', true],
    ['Site', 'Pare-feu applicatif (WAF) + anti-DDoS (Cloudflare) · IP réelle des visiteurs', via_cloudflare() ? true : null, via_cloudflare() ? 'Actif — votre IP vue par le site : ' . client_ip() : 'Pas encore actif : cette page n\'est pas passée par Cloudflare'],
    ['Comptes', 'Mots de passe hachés (bcrypt)', true],
    ['Comptes', 'Double authentification obligatoire pour les admins', !$no2fa, $no2fa ? count($no2fa) . ' admin(s) ne l\'ont pas encore activée' : ''],
    ['Comptes', 'Blocage après tentatives de connexion échouées', true],
    ['Comptes', 'Alerte en cas de connexion depuis un nouvel appareil', true],
    ['Paiements', 'Paiement vérifié côté serveur auprès de PayDunya (jamais sur la seule base du navigateur)', $pm !== 'simulation', $pm === 'simulation' ? 'Mode simulation actif' : ''],
    ['Paiements', 'Webhooks signés et revérifiés · anti double paiement', true],
    ['Paiements', 'PIN / code OTP jamais stockés', true],
    ['Paiements', 'Rapprochement quotidien réservations ↔ grand livre ↔ sommes reçues', ($rc = json_decode((string) setting('reconcile_result', ''), true)) !== null && empty($rc['anomalies']), setting('reconcile_last') ? 'Dernier : ' . fmt_date((string) setting('reconcile_last'), true) . (!empty($rc['anomalies']) ? ' — ' . count($rc['anomalies']) . ' anomalie(s), voir Finances' : '') : 'Pas encore lancé (Finances › Rapprochement)'],
    ['Paiements', 'Remboursements, virements et coordonnées de paiement : double validation (code 2FA) + journal', true],
    ['Documents', 'Pièces d\'identité stockées en privé, accessibles aux seuls admins connectés, chiffrées au repos (AES-256)', true],
    ['Documents', 'Durée de conservation : suppression à la suppression du prestataire, 2 ans après un refus', true],
    ['Administration', 'Comptes individuels, rôles Admin / Super Admin, journal d\'audit', true],
    ['Incidents', 'Sauvegarde quotidienne chiffrée stockée hors du serveur', $bkOk, $lastBk ? 'Dernière : ' . fmt_date($lastBk, true) : 'Aucune encore'],
    ['Incidents', 'Test de restauration', $lastTest !== '' && strtotime($lastTest) > time() - 35 * 86400, $lastTest ? 'Dernier test : ' . fmt_date($lastTest, true) : 'À faire une fois par mois'],
    ['Incidents', 'Alertes de sécurité (SMS au numéro de support pour les alertes critiques)', true],
    ['Lancement', 'Audit de sécurité indépendant + test d\'intrusion', null, 'À commander auprès d\'un expert tiers avant l\'ouverture au public'],
];
$lvl = ['info' => ['Info', 'badge-blue'], 'warn' => ['Attention', 'badge-amber'], 'critical' => ['Critique', 'badge-red']];
$page = 'Centre de sécurité';
$nav = 'security';
view('admin/header', compact('page', 'nav'));
?>
<div class="sec-grid">
  <div class="panel">
    <div class="panel-h"><h2>État des protections</h2></div>
    <ul class="sec-checks">
      <?php foreach ($checks as $c): [$grp, $txt, $ok] = $c; $note = $c[3] ?? ''; ?>
        <li class="<?= $ok === true ? 'ok' : ($ok === false ? 'ko' : 'todo') ?>"><i><?= $ok === true ? '✓' : ($ok === false ? '!' : '…') ?></i><span><small><?= e($grp) ?></small><?= e($txt) ?><?php if ($note): ?><em><?= e($note) ?></em><?php endif; ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div>
    <div class="panel">
      <div class="panel-h"><h2>Sauvegardes</h2></div>
      <p class="muted small">Chaque nuit, toute la base est exportée, <b>chiffrée (AES-256)</b> et envoyée sur un stockage séparé du serveur. Les 30 dernières sont conservées.</p>
      <p>Dernière sauvegarde : <b><?= $lastBk ? fmt_date($lastBk, true) : '—' ?></b><br>Dernier test de restauration : <b><?= $lastTest ? fmt_date($lastTest, true) : '—' ?></b></p>
      <div class="acts">
        <form method="post" data-stepup><?= csrf_field() ?><button class="btn btn-primary btn-sm" name="action" value="backup">Sauvegarder maintenant</button></form>
        <form method="post"><?= csrf_field() ?><button class="btn btn-soft btn-sm" name="action" value="restore_test">Tester la restauration</button></form>
      </div>
    </div>
    <div class="panel">
      <div class="panel-h"><h2>Double authentification</h2></div>
      <?php foreach ($admins as $a): ?>
        <p class="sec-adm"><span><?= e($a['name'] ?: $a['email']) ?><small><?= e($a['email']) ?></small></span><?= !empty($a['totp_enabled']) ? '<span class="badge badge-green">2FA ✓</span>' : '<span class="badge badge-amber">À activer</span>' ?></p>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <div class="panel-h"><h2>Plan de réaction en cas d'attaque</h2></div>
      <ol class="sec-plan">
        <li><b>Isoler</b> : activer le mode maintenance (Paramètres), désactiver le compte admin suspect, réinitialiser sa 2FA.</li>
        <li><b>Analyser</b> : consulter les alertes ci-dessous et le Journal d'audit (qui, quoi, quand, adresse IP).</li>
        <li><b>Corriger</b> : changer les mots de passe et les clés (PayDunya, Cloudinary, APP_KEY) dans Render, corriger la faille.</li>
        <li><b>Restaurer</b> : repartir de la dernière sauvegarde saine si des données ont été modifiées.</li>
        <li><b>Communiquer</b> : prévenir les clients et prestataires concernés, et l'ARTCI en cas de fuite de données personnelles.</li>
      </ol>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-h"><h2>Alertes de sécurité</h2><?php if ($ev): ?><form method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" name="action" value="seen">Tout marquer comme lu</button></form><?php endif; ?></div>
  <?php if (!$ev): ?><p class="muted">Aucune alerte pour le moment.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Date</th><th>Niveau</th><th>Événement</th><th>Détail</th><th>IP</th></tr></thead>
    <tbody><?php foreach ($ev as $x): [$ll, $lc] = $lvl[$x['level']] ?? [$x['level'], 'badge-gray']; ?>
      <tr class="<?= (int) $x['seen'] ? '' : 'sec-new' ?>"><td><?= fmt_date($x['created_at'], true) ?></td><td><span class="badge <?= $lc ?>"><?= e($ll) ?></span></td><td><b><?= e($x['kind']) ?></b></td><td><?= e($x['detail']) ?></td><td class="mono small"><?= e($x['ip']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php view('admin/footer'); ?>
