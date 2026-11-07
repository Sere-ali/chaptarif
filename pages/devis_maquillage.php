<?php
// Demande de devis : maquillage effets spéciaux / artistique.
$styles = ['Shooting photo / vidéo', 'Scène / spectacle', 'Halloween / Carnaval', 'Cinéma / clip', 'Autre'];
$u = current_user();

if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) redirect('/maquilleuse');
    if (too_many_attempts('devis:' . client_ip(), 5, 60)) {
        flash('error', 'Trop de demandes envoyées. Réessayez plus tard.');
        redirect('/devis-maquillage');
    }
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
    $phone = mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 30);
    $style = (string) ($_POST['style'] ?? '');
    if ($name === '' || $phone === '' || !in_array($style, $styles, true)) {
        flash('error', 'Merci de renseigner votre nom, votre téléphone et le type de projet.');
        redirect('/devis-maquillage');
    }
    record_attempt('devis:' . client_ip());
    $date = (string) ($_POST['date'] ?? '');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? fmt_date($date) : 'Non précisée';
    $clean = fn(string $k, int $n = 200) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $n);
    $msg = "DEMANDE DE DEVIS MAQUILLAGE — EFFETS SPÉCIAUX / ARTISTIQUE\n"
        . "Type de projet : $style\n"
        . 'Nombre de personnes : ' . max(1, min(50, (int) ($_POST['personnes'] ?? 1))) . "\n"
        . "Date souhaitée : $date\n"
        . 'Adresse : ' . ($clean('adresse') ?: '—') . "\n"
        . 'Description du maquillage souhaité : ' . ($clean('precisions', 1000) ?: '—');
    insert('contact_messages', ['name' => $name, 'phone' => $phone, 'email' => '', 'message' => $msg, 'handled' => 0, 'created_at' => now()]);
    flash('success', 'Demande de devis envoyée. Une maquilleuse vous recontacte rapidement.');
    redirect('/maquilleuse');
}

$title = 'Devis maquillage effets spéciaux / artistique';
$bodyClass = 'theme-beaute';
view('layout/header', compact('title', 'bodyClass'));
?>
<section class="section-tight">
  <div class="container narrow">
    <h1 class="h2">Effets spéciaux / Artistique</h1>
    <p class="muted">Maquillage créatif (shooting, scène, etc.) : décrivez votre projet, nous revenons vers vous avec un devis.</p>
    <form class="card form-card" method="post"><?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="row2">
        <label class="fld"><span>Nom</span><input name="name" required maxlength="80" value="<?= e((string) ($u['name'] ?? '')) ?>"></label>
        <label class="fld"><span>Téléphone</span><input name="phone" required inputmode="tel" maxlength="30" value="<?= e((string) ($u['phone'] ?? '')) ?>"></label>
      </div>
      <h2 class="h4">Type de projet</h2>
      <?php foreach ($styles as $st): ?>
        <label class="check"><input type="radio" name="style" value="<?= e($st) ?>" required> <?= e($st) ?></label>
      <?php endforeach; ?>
      <div class="row2 mt">
        <label class="fld"><span>Date souhaitée</span><input type="date" name="date" min="<?= date('Y-m-d') ?>"></label>
        <label class="fld"><span>Nombre de personnes</span><input type="number" name="personnes" min="1" max="50" value="1"></label>
      </div>
      <label class="fld"><span>Adresse</span><input name="adresse" maxlength="200" placeholder="Ex. Cocody Angré"></label>
      <label class="fld"><span>Décrivez le maquillage souhaité</span><textarea name="precisions" rows="4" maxlength="1000" placeholder="Thème, personnage, photos de référence, durée…"></textarea></label>
      <button class="btn btn-primary btn-block btn-lg">Envoyer ma demande de devis</button>
    </form>
  </div>
</section>
<?php view('layout/footer'); ?>
