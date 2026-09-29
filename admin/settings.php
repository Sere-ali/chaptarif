<?php
require_super();
$groups = [
    'Commissions (%)' => [
        'commission_menage' => 'Ménage & Aide', 'commission_pressing' => 'Pressing & Linge',
        'commission_immobilier' => 'Immobilier', 'commission_cars' => 'Cars (sur le prix du billet)',
        'commission_covoiturage' => 'Covoiturage (sur le prix de la place)', 'commission_location_car' => 'Location de car',
        'commission_location_camion' => 'Location de camion', 'commission_coiffeuse' => 'Coiffeuse à domicile',
        'commission_maquilleuse' => 'Maquilleuse à domicile', 'commission_onglerie' => 'Onglerie à domicile',
    ],
    'Frais & abonnements (F CFA)' => ['cars_service_fee' => 'Frais de service par billet de car', 'sponsor_price' => 'Abonnement « Recommandé » / mois'],
    'Garantie Dommage (option payante)' => ['garantie_dommage_pct' => 'Prix de la garantie, en % du prix du service'],
    'Support client' => ['support_phone' => 'Téléphone', 'support_email' => 'E-mail'],
    'Paiement manuel · numéros de réception Mobile Money' => [
        'pay_wave_number' => 'Numéro Wave', 'pay_orange_number' => 'Numéro Orange Money',
        'pay_mtn_number' => 'Numéro MTN MoMo', 'pay_moov_number' => 'Numéro Moov Money',
    ],
];
$textFields = ['support_phone', 'support_email', 'pay_wave_number', 'pay_orange_number', 'pay_mtn_number', 'pay_moov_number'];
if (is_post()) {
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
    $m = isset($_POST['maintenance']) ? '1' : '0';
    if (setting('maintenance') !== $m) { $changes['maintenance'] = [setting('maintenance'), $m]; setting_set('maintenance', $m); }
    if ($changes) audit('parametres.modification', '', $changes);
    flash('success', $changes ? count($changes) . ' paramètre(s) mis à jour.' : 'Aucune modification.');
    redirect('/admin/parametres');
}
$page = 'Paramètres de la plateforme';
$nav = 'settings';
view('admin/header', compact('page', 'nav'));
?>
<form method="post"><?= csrf_field() ?>
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
      <label class="check"><input type="checkbox" name="maintenance" <?= setting('maintenance') === '1' ? 'checked' : '' ?>> Mode maintenance (site public fermé, back-office accessible)</label>
      <dl class="kv mt">
        <dt>Base de données</dt><dd><?= e(db_driver() === 'pgsql' ? 'PostgreSQL' : 'SQLite (local)') ?></dd>
        <dt>Paiements</dt><dd><?= payment_mode() === 'cinetpay' ? '<span class="badge badge-green">CinetPay (réel)</span>' : (payment_mode() === 'manuel' ? '<span class="badge badge-green">Manuel · validation admin</span>' : '<span class="badge badge-amber">Simulation</span>') ?></dd>
        <dt>Cloudinary</dt><dd><?= cld_config() ? '<span class="badge badge-green">Connecté · ' . e(cld_cloud()) . '</span>' : '<span class="badge badge-red">Non configuré</span>' ?></dd>
        <dt>SMS OTP</dt><dd><?= env('SMS_API_URL') ? '<span class="badge badge-green">Passerelle configurée</span>' : '<span class="badge badge-amber">Aucune passerelle' . (sms_demo_mode() ? ' · code affiché (démo)' : '') . '</span>' ?></dd>
      </dl>
      <p class="fine">Ces éléments se configurent via les variables d'environnement Render (voir README).</p>
    </div>
  </div>
  <button class="btn btn-primary btn-lg">Enregistrer les paramètres</button>
</form>
<?php view('admin/footer');
