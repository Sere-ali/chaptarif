<?php
$types = ['Nettoyage de fin de chantier', 'Nettoyage après déménagement'];
$batiments = ['Appartement', 'Maison / Villa', 'Immeuble', 'Bureau / Local professionnel', 'Autre'];
$extras = ['Élimination de poussière de chantier', 'Nettoyage des traces de peinture', 'Nettoyage des vitres', 'Évacuation de gravats ou déchets', 'Nettoyage complet après déménagement', 'Autre besoin'];
$u = current_user();

if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) redirect('/menage');
    if (too_many_attempts('devis:' . client_ip(), 5, 60)) {
        flash('error', 'Trop de demandes envoyées. Réessayez plus tard.');
        redirect('/devis-menage');
    }
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
    $phone = mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 30);
    $type = (string) ($_POST['type'] ?? '');
    $bat = (string) ($_POST['batiment'] ?? '');
    $clean = fn(string $k) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 200);
    if ($name === '' || $phone === '' || !in_array($type, $types, true) || !in_array($bat, $batiments, true)) {
        flash('error', 'Merci de renseigner votre nom, votre téléphone, le type de nettoyage et le type de bâtiment.');
        redirect('/devis-menage');
    }
    record_attempt('devis:' . client_ip());
    $date = (string) ($_POST['date'] ?? '');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? fmt_date($date) : 'Non précisée';
    $ex = array_values(array_intersect($extras, (array) ($_POST['extras'] ?? [])));
    $msg = "DEMANDE DE DEVIS MÉNAGE\n"
        . "Type de nettoyage : $type\n"
        . "Type de bâtiment : $bat\n"
        . 'Commune : ' . ($clean('commune') ?: '—') . "\n"
        . 'Quartier : ' . ($clean('quartier') ?: '—') . "\n"
        . 'Adresse / indication : ' . ($clean('adresse') ?: '—') . "\n"
        . "Date souhaitée : $date" . (!empty($_POST['flexible']) ? ' (date flexible)' : '') . "\n"
        . 'Besoins particuliers : ' . ($ex ? implode(', ', $ex) : '—') . "\n"
        . 'Précisions : ' . (mb_substr(trim((string) ($_POST['precisions'] ?? '')), 0, 1000) ?: '—');
    insert('contact_messages', ['name' => $name, 'phone' => $phone, 'email' => '', 'message' => $msg, 'handled' => 0, 'created_at' => now()]);
    flash('success', 'Demande de devis envoyée. Nous vous recontactons rapidement.');
    redirect('/menage');
}

$title = 'Devis nettoyage fin de chantier ou déménagement';
$preType = (string) ($_GET['type'] ?? '');
view('layout/header', compact('title'));
?>
<section class="section-tight">
  <div class="container narrow">
    <nav class="crumbs dark"><a href="/">Accueil</a> › <a href="/menage">Ménage</a> › Devis</nav>
    <h1 class="h2">Nettoyage de fin de chantier ou grand nettoyage</h1>
    <p class="muted">Proposition sur devis : cochez simplement vos choix, nous revenons vers vous avec une offre.</p>
    <form class="card form-card" method="post"><?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="row2">
        <label class="fld"><span>Nom</span><input name="name" required maxlength="80" value="<?= e((string) ($u['name'] ?? '')) ?>"></label>
        <label class="fld"><span>Téléphone</span><input name="phone" required inputmode="tel" maxlength="30" value="<?= e((string) ($u['phone'] ?? '')) ?>"></label>
      </div>

      <h2 class="h4">1. Quel type de nettoyage souhaitez-vous ?</h2>
      <?php foreach ($types as $i => $t): ?>
        <label class="check"><input type="radio" name="type" value="<?= e($t) ?>" required <?= ($preType === 'chantier' && $i === 0) || ($preType === 'demenagement' && $i === 1) ? 'checked' : '' ?>> <?= e($t) ?></label>
      <?php endforeach; ?>

      <h2 class="h4 mt">2. Quel type de bâtiment faut-il nettoyer ?</h2>
      <?php foreach ($batiments as $b): ?>
        <label class="check"><input type="radio" name="batiment" value="<?= e($b) ?>" required> <?= e($b) ?></label>
      <?php endforeach; ?>

      <h2 class="h4 mt">3. Où se situe le bâtiment ?</h2>
      <div class="row2">
        <label class="fld"><span>Commune</span><input name="commune" maxlength="100"></label>
        <label class="fld"><span>Quartier</span><input name="quartier" maxlength="100"></label>
      </div>
      <label class="fld"><span>Adresse ou indication complémentaire</span><input name="adresse" maxlength="200"></label>
      <input type="hidden" name="lat" data-geo-lat><input type="hidden" name="lng" data-geo-lng>
      <button type="button" class="btn btn-soft btn-block" data-geo="adresse" style="margin:-4px 0 6px"> Activer ma localisation</button><p class="fine" data-geo-msg style="margin:0 0 10px" hidden></p>

      <h2 class="h4 mt">4. À quelle date souhaitez-vous le nettoyage ?</h2>
      <label class="fld"><span>Date souhaitée</span><input type="date" name="date" min="<?= date('Y-m-d') ?>"></label>
      <label class="check"><input type="checkbox" name="flexible" value="1"> Date flexible</label>

      <h2 class="h4 mt">5. Informations complémentaires (facultatif)</h2>
      <p class="muted">Précisez les éventuels besoins particuliers :</p>
      <?php foreach ($extras as $x): ?>
        <label class="check"><input type="checkbox" name="extras[]" value="<?= e($x) ?>"> <?= e($x) ?></label>
      <?php endforeach; ?>
      <label class="fld mt"><span>Précisions supplémentaires</span><textarea name="precisions" rows="3" maxlength="1000"></textarea></label>

      <button class="btn btn-primary btn-block btn-lg">Envoyer ma demande de devis</button>
    </form>
  </div>
</section>
<?php view('layout/footer'); ?>
