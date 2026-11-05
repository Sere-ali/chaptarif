<?php
// Formulaire « Recherche appartement » : type de logement + commune (choix dans la liste ou saisie libre).
// Variables attendues : $type (type sélectionné), $commune (commune saisie).
?>
<form class="immo-form" method="get" action="/immobilier">
  <input type="hidden" name="cat" value="appart">
  <select name="type" aria-label="Type de logement" required>
    <option value="">Sélection…</option>
    <?php foreach (PROPERTY_TYPES as $t): ?><option value="<?= e($t) ?>" <?= ($type ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
  </select>
  <input name="commune" list="immo-communes" value="<?= e((string) ($commune ?? '')) ?>" placeholder="Commune, exemple : Cocody" aria-label="Commune" maxlength="60" autocomplete="off">
  <datalist id="immo-communes"><?php foreach (communes() as $cm): ?><option value="<?= e($cm) ?>"><?php endforeach; ?></datalist>
  <button class="btn btn-primary btn-block">Recherche appartement</button>
</form>
