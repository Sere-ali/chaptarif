<?php
$me = require_super();
// Bouton rapide (bandeau du back-office) : ouvrir / fermer le site
if (is_post() && in_array($_POST['quick'] ?? '', ['open', 'close'], true)) {
    $m = $_POST['quick'] === 'close' ? '1' : '0';
    if (setting('maintenance') !== $m) { setting_set('maintenance', $m); audit('parametres.modification', '', ['maintenance' => [$m === '1' ? '0' : '1', $m]]); }
    flash('success', $m === '1' ? 'Site fermé au public (maintenance). Le back-office reste accessible.' : 'Site rouvert : il est de nouveau accessible à tous.');
    $back = (string) ($_POST['back'] ?? '/admin');
    redirect(str_starts_with($back, '/admin') ? $back : '/admin');
}
$groups = [
    'Commissions (%)' => [
        'commission_menage' => 'Ménage & Aide', 'commission_pressing' => 'Pressing & Linge',
        'commission_immobilier' => 'Immobilier', 'commission_cars' => 'Cars (sur le prix du billet)',
        'commission_covoiturage' => 'Covoiturage (sur le prix de la place)', 'commission_location_car' => 'Location de car',
        'commission_location_camion' => 'Location de camion', 'commission_location_vehicule' => 'Location de véhicule', 'commission_chauffeur' => 'Louer un chauffeur', 'commission_coiffeuse' => 'Coiffeuse à domicile',
        'commission_maquilleuse' => 'Maquilleuse à domicile', 'commission_onglerie' => 'Onglerie à domicile',
    ],
    'Frais & abonnements (F CFA)' => ['dossier_fee' => 'Frais de dossier (automatique sur chaque réservation)', 'cars_service_fee' => 'Frais de service par billet de car', 'sponsor_price' => 'Abonnement « Recommandé » / mois'],
    'Garantie Dommage (option payante)' => ['garantie_dommage_pct' => 'Garantie ménage et pressing, en % du prix de la prestation', 'service_fee_menage_pct' => 'Frais de service ménage, en % du prix de la prestation'],
    'Support client' => ['support_phone' => 'Téléphone', 'support_email' => 'E-mail'],
    'Paiement manuel · numéros de réception Mobile Money' => [
        'pay_wave_number' => 'Numéro Wave', 'pay_orange_number' => 'Numéro Orange Money',
        'pay_mtn_number' => 'Numéro MTN MoMo', 'pay_moov_number' => 'Numéro Moov Money', 'pay_djamo_number' => 'Numéro Djamo',
    ],
];
$textFields = ['support_phone', 'support_email', 'pay_wave_number', 'pay_orange_number', 'pay_mtn_number', 'pay_moov_number', 'pay_djamo_number'];
if (is_post()) {
    // Numéros de réception des paiements modifiés : double validation obligatoire.
    foreach (['pay_wave_number', 'pay_orange_number', 'pay_mtn_number', 'pay_moov_number', 'pay_djamo_number'] as $pk) {
        if (isset($_POST[$pk]) && trim((string) $_POST[$pk]) !== (string) setting($pk)) { stepup_require($me, '/admin/parametres'); break; }
    }
    $changes = [];
    foreach ($groups as $fields) {
        foreach ($fields as $k => $label) {
            if (!isset($_POST[$k])) continue;
            $v = trim((string) $_POST[$k]);
            if (!in_array($k, $textFields, true)) {
                $v = str_replace(',', '.', $v);
                if (!is_numeric($v) || (float) $v < 0 || (str_starts_with($k, 'commission_') && (float) $v > 50)) { flash('error', "Valeur invalide pour « $label »."); redirect('/admin/parametres'); }
            }
            if ((string) setting($k) !== $v) { $changes[$k] = [setting($k), $v]; setting_set($k, $v); }
        }
    }
    $m = ($_POST['site_state'] ?? 'open') === 'closed' ? '1' : '0';
    if (setting('maintenance') !== $m) { $changes['maintenance'] = [setting('maintenance'), $m]; setting_set('maintenance', $m); }
    if ($changes) audit('parametres.modification', '', $changes);
    flash('success', $changes ? count($changes) . ' paramètre(s) mis à jour.' : 'Aucune modification.');
    redirect('/admin/parametres');
}
$page = 'Paramètres de la plateforme';
$nav = 'settings';
view('admin/header', compact('page', 'nav'));
?>
<form method="post" data-stepup-if="pay_wave_number,pay_orange_number,pay_mtn_number,pay_moov_number,pay_djamo_number"><?= csrf_field() ?>
  <div class="grid2e">
    <?php foreach ($groups as $title => $fields): ?>
      <div class="panel"><h2 class="h4"><?= e($title) ?></h2>
        <?php foreach ($fields as $k => $label): ?>
          <?php $defaults = ['pay_wave_number' => '0100354093', 'garantie_dommage_pct' => '5']; ?>
          <label class="fld"><span><?= e($label) ?></span><input name="<?= $k ?>" value="<?= e(setting($k, $defaults[$k] ?? '')) ?>"></label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <div class="panel"><h2 class="h4">Environnement</h2>
      <p class="h5" style="margin:0 0 .5rem">État du site public</p>
      <label class="check"><input type="radio" name="site_state" value="open" <?= setting('maintenance') !== '1' ? 'checked' : '' ?>> 🟢 <b>Site ouvert</b> — accessible à tous</label>
      <label class="check"><input type="radio" name="site_state" value="closed" <?= setting('maintenance') === '1' ? 'checked' : '' ?>> 🔴 <b>Site fermé</b> (maintenance) — les visiteurs voient « On revient très vite », le back-office reste accessible</label>
      <dl class="kv mt">
        <dt>Base de données</dt><dd><?= e(db_driver() === 'pgsql' ? 'PostgreSQL' : 'SQLite (local)') ?></dd>
        <dt>Paiements</dt><dd><?= payment_mode() === 'paydunya' ? '<span class="badge badge-green">PayDunya (réel)</span>' : (payment_mode() === 'cinetpay' ? '<span class="badge badge-green">CinetPay (réel)</span>' : (payment_mode() === 'manuel' ? '<span class="badge badge-green">Manuel · validation admin</span>' : '<span class="badge badge-amber">Simulation</span>')) ?></dd>
        <dt>Stockage des fichiers</dt><dd><?= files_ready() ? '<span class="badge badge-green">Disque Render · ' . e(files_root()) . '</span> <a href="/admin/transfert-fichiers">Transfert Cloudinary</a>' : (cld_config() ? '<span class="badge badge-green">Connecté · ' . e(cld_cloud()) . ' (temporaire) · <a href="/admin/transfert-fichiers">Quitter Cloudinary</a></span>' : '<span class="badge badge-red">Non configuré</span>') ?></dd>
        <dt>SMS OTP</dt><dd><?= (env('SMS_API_URL') || env('INFOBIP_API_KEY')) ? '<span class="badge badge-green">Passerelle configurée</span>' : '<span class="badge badge-amber">Aucune passerelle' . (sms_demo_mode() ? ' · code affiché (démo)' : '') . '</span>' ?></dd>
      </dl>
      <p class="fine">Ces éléments se configurent via les variables d'environnement Render (voir README).</p>
    </div>
  </div>
  <button class="btn btn-primary btn-lg">Enregistrer les paramètres</button>
</form>
<?php view('admin/footer');
