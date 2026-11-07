<?php
// Demande de devis : maquillage effets spéciaux / artistique (maquette maquilleuses).
$types = [
    'artistique' => ['Artistique', 'shooting, clip, scène, etc.'],
    'sfx' => ['Effets spéciaux', 'blessure, cicatrice, vieillissement, etc.'],
    'thematique' => ['Thématique', 'cinéma, cosplay, Halloween, etc.'],
    'autre' => ['Autre', 'précisez'],
];
$heures = array_map(fn($i) => sprintf('%02d:00', $i), range(7, 23));
$u = current_user();
if (!$u) {
    flash('info', 'Connectez-vous pour envoyer votre demande de devis.');
    redirect('/connexion?next=' . urlencode('/devis-maquillage'));
}

if (is_post()) {
    csrf_check();
    if (!empty($_POST['website'])) redirect('/maquilleuse');
    if (too_many_attempts('devis:' . client_ip(), 5, 60)) {
        flash('error', 'Trop de demandes envoyées. Réessayez plus tard.');
        redirect('/devis-maquillage');
    }
    $sel = array_values(array_intersect(array_keys($types), (array) ($_POST['types'] ?? [])));
    $autre = mb_substr(trim((string) ($_POST['autre'] ?? '')), 0, 200);
    $pers = max(1, min(50, (int) ($_POST['personnes'] ?? 1)));
    $date = (string) ($_POST['date'] ?? '');
    $heure = in_array($_POST['heure'] ?? '', $heures, true) ? (string) $_POST['heure'] : '';
    $lieu = mb_substr(trim((string) ($_POST['lieu'] ?? '')), 0, 200);
    $projet = mb_substr(trim((string) ($_POST['projet'] ?? '')), 0, 1500);
    $err = null;
    if (!$sel) $err = 'Cochez au moins un type de maquillage.';
    elseif (in_array('autre', $sel, true) && $autre === '') $err = 'Précisez le type de maquillage « Autre ».';
    elseif (!valid_future_date($date, 365)) $err = 'Choisissez une date valide.';
    elseif ($heure === '') $err = 'Choisissez une heure.';
    elseif ($lieu === '') $err = 'Indiquez le lieu de la prestation.';
    elseif (mb_strlen($projet) < 10) $err = 'Décrivez votre projet en quelques mots.';
    $imgs = [];
    if (!$err && !empty($_FILES['refs']['name']) && is_array($_FILES['refs']['name'])) {
        $n = count(array_filter($_FILES['refs']['name'], fn($x) => $x !== ''));
        if ($n > 5) $err = '5 images maximum.';
        else {
            [$imgs, $upErr] = cld_upload_many($_FILES['refs'], 'chaptarif/devis');
            if ($upErr && !$imgs) $err = 'Images non envoyées : ' . $upErr[0];
        }
    }
    if ($err) {
        flash('error', $err);
        redirect('/devis-maquillage');
    }
    record_attempt('devis:' . client_ip());
    $lab = array_map(fn($k) => $types[$k][0] . ($k === 'autre' ? ' : ' . $autre : ''), $sel);
    $msg = "DEMANDE DE DEVIS MAQUILLAGE — EFFETS SPÉCIAUX / ARTISTIQUE\n"
        . 'Type de maquillage : ' . implode(', ', $lab) . "\n"
        . "Nombre de personnes : $pers\n"
        . 'Date et heure : ' . fmt_date($date) . " à $heure\n"
        . "Lieu : $lieu\n"
        . "Projet : $projet\n"
        . 'Références : ' . ($imgs ? implode(' ', $imgs) : '—');
    insert('contact_messages', ['name' => (string) ($u['name'] ?: 'Client'), 'phone' => (string) $u['phone'], 'email' => '', 'message' => $msg, 'handled' => 0, 'created_at' => now()]);
    flash('success', 'Demande de devis envoyée. ChapTarif vous propose un devis adapté dans les plus brefs délais.');
    redirect('/maquilleuse');
}

$title = 'Demande de devis : effets spéciaux / artistique';
$bodyClass = 'theme-beaute';
view('layout/header', compact('title', 'bodyClass'));
?>
<section class="section-tight">
  <div class="container narrow">
    <form class="card form-card dv" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="dv-head">
        <a href="/maquilleuse" class="dv-back" aria-label="Retour"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg></a>
        <span class="mq-ico mq-ico-lg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.8L18 9.5l-4.2 1.8L12 16l-1.8-4.7L6 9.5l4.2-1.7L12 3zM19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2zM5 15l.6 1.5L7 17l-1.4.6L5 19l-.6-1.4L3 17l1.4-.5L5 15z"/></svg></span>
        <div><h1>Demande de devis</h1><p>Effets spéciaux / Artistique</p></div>
      </div>
      <p class="dv-intro">Précisez votre besoin pour recevoir une proposition de devis personnalisée de la part de ChapTarif.</p>

      <b class="dv-lab">Type de maquillage souhaité <i>*</i></b>
      <div class="dv-box">
        <?php foreach ($types as $k => [$t, $d]): ?>
          <label class="dv-check"><input type="checkbox" name="types[]" value="<?= e($k) ?>" <?= $k === 'autre' ? 'data-dv-autre' : '' ?>> <span><b><?= e($t) ?></b> (<?= e($d) ?>)</span></label>
        <?php endforeach; ?>
        <input name="autre" maxlength="200" placeholder="Précisez le type de maquillage" data-dv-autre-txt hidden>
      </div>

      <div class="dv-row">
        <div><b class="dv-lab">Nombre de personnes <i>*</i></b>
          <div class="mq-step"><button type="button" data-dv-minus aria-label="Moins">−</button><input name="personnes" value="1" inputmode="numeric" readonly data-dv-n><button type="button" data-dv-plus aria-label="Plus">+</button></div></div>
        <label><b class="dv-lab">Date de la prestation <i>*</i></b><input type="date" name="date" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+365 days')) ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></label>
      </div>
      <label class="dv-half"><b class="dv-lab">Heure souhaitée <i>*</i></b><select name="heure" required><?php foreach ($heures as $h): ?><option <?= $h === '09:00' ? 'selected' : '' ?>><?= $h ?></option><?php endforeach; ?></select></label>
      <label><b class="dv-lab">Lieu de la prestation <i>*</i></b><input name="lieu" required maxlength="200" placeholder="Ex. Cocody Angré, immeuble Les Palmiers"></label>
      <label><b class="dv-lab">Décrivez votre projet <i>*</i></b><textarea name="projet" required minlength="10" maxlength="1500" rows="3" placeholder="Ex. : maquillage pour un tournage, un défilé, un thème spécifique, effets particuliers souhaités, etc."></textarea></label>

      <b class="dv-lab">Ajoutez des références <span>(facultatif)</span></b>
      <div class="dv-refs">
        <label class="dv-drop"><input type="file" name="refs[]" accept="image/*" multiple data-dv-files hidden>
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/></svg>
          <span data-dv-count>Ajouter des images<br>(max. 5)</span></label>
        <p class="dv-info"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7.5v.5"/></svg>Vous pouvez ajouter des photos d'inspiration (ex. : modèle de maquillage, thème, personnage, etc.).</p>
      </div>
      <div class="dv-thumbs" data-dv-thumbs></div>
      <p class="fine dv-err" data-dv-err hidden></p>

      <button class="btn btn-primary btn-block btn-lg dv-send"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg> Envoyer ma demande de devis</button>
      <p class="dv-note">Votre demande sera transmise à ChapTarif.<br>Nous vous proposerons un devis adapté dans les plus brefs délais.</p>
    </form>
  </div>
</section>
<script>
(function () {
  var f = document.querySelector('form.dv'); if (!f) return;
  var n = f.querySelector('[data-dv-n]'), err = f.querySelector('[data-dv-err]');
  f.querySelector('[data-dv-minus]').onclick = function () { n.value = Math.max(1, (+n.value || 1) - 1); };
  f.querySelector('[data-dv-plus]').onclick = function () { n.value = Math.min(50, (+n.value || 1) + 1); };
  var au = f.querySelector('[data-dv-autre]'), at = f.querySelector('[data-dv-autre-txt]');
  au.onchange = function () { at.hidden = !au.checked; at.required = au.checked; if (au.checked) at.focus(); };
  var fi = f.querySelector('[data-dv-files]'), th = f.querySelector('[data-dv-thumbs]'), cnt = f.querySelector('[data-dv-count]');
  fi.onchange = function () {
    th.innerHTML = ''; err.hidden = true;
    if (fi.files.length > 5) { err.textContent = '5 images maximum : choisissez-en moins.'; err.hidden = false; fi.value = ''; cnt.innerHTML = 'Ajouter des images<br>(max. 5)'; return; }
    [].forEach.call(fi.files, function (file) { var i = document.createElement('img'); i.src = URL.createObjectURL(file); th.appendChild(i); });
    cnt.innerHTML = fi.files.length ? fi.files.length + ' image' + (fi.files.length > 1 ? 's' : '') + '<br>ajoutée' + (fi.files.length > 1 ? 's' : '') : 'Ajouter des images<br>(max. 5)';
  };
  f.addEventListener('submit', function (e) {
    if (!f.querySelector('input[name="types[]"]:checked')) { e.preventDefault(); err.textContent = 'Cochez au moins un type de maquillage.'; err.hidden = false; err.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  });
})();
</script>
<?php view('layout/footer'); ?>
