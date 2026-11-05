<?php
require_super();
if (is_post()) {
    $act = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($act === 'create') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['code'] ?? '')));
        $kind = ($_POST['kind'] ?? '') === 'amount' ? 'amount' : 'percent';
        $val = (int) ($_POST['value'] ?? 0);
        $exp = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['expires_at'] ?? '')) ? $_POST['expires_at'] : null;
        $max = ($_POST['max_uses'] ?? '') !== '' ? max(1, (int) $_POST['max_uses']) : null;
        if (strlen($code) < 3) flash('error', 'Le code doit contenir au moins 3 caractères (lettres ou chiffres).');
        elseif ($kind === 'percent' && ($val < 1 || $val > 90)) flash('error', 'Pourcentage : entre 1 et 90.');
        elseif ($kind === 'amount' && $val < 100) flash('error', 'Montant minimum : 100 F.');
        elseif (val('SELECT 1 FROM discount_codes WHERE code = ?', [$code])) flash('error', 'Ce code existe déjà.');
        else {
            insert('discount_codes', ['code' => $code, 'kind' => $kind, 'value' => $val, 'active' => 1, 'expires_at' => $exp, 'max_uses' => $max, 'uses' => 0, 'created_at' => now()]);
            audit('code_reduction.creation', $code, ['kind' => $kind, 'value' => $val]);
            flash('success', 'Code « ' . $code . ' » créé.');
        }
    } elseif ($act === 'toggle' && $id) {
        q('UPDATE discount_codes SET active = 1 - active WHERE id = ?', [$id]);
        audit('code_reduction.statut', (string) $id);
    } elseif ($act === 'delete' && $id) {
        q('DELETE FROM discount_codes WHERE id = ?', [$id]);
        audit('code_reduction.suppression', (string) $id);
    }
    redirect('/admin/codes');
}
$rows = all('SELECT * FROM discount_codes ORDER BY id DESC');
$page = 'Codes de réduction';
$nav = 'codes';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel">
  <div class="panel-h"><h2>Créer un code</h2><span class="muted small">La réduction est prise sur la part de ChapTarif : le prestataire reçoit toujours son montant complet.</span></div>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label class="fld"><span>Code</span><input name="code" required maxlength="30" placeholder="BIENVENUE10"></label>
    <label class="fld"><span>Type</span><select name="kind"><option value="percent">Pourcentage (%)</option><option value="amount">Montant fixe (F CFA)</option></select></label>
    <label class="fld"><span>Valeur</span><input name="value" required inputmode="numeric" placeholder="10"></label>
    <label class="fld"><span>Valable jusqu'au (facultatif)</span><input type="date" name="expires_at"></label>
    <label class="fld"><span>Utilisations max. (facultatif)</span><input name="max_uses" inputmode="numeric" placeholder="100"></label>
    <button class="btn btn-primary">Créer</button>
  </form>
</div>
<div class="panel"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Code</th><th>Réduction</th><th>Expire le</th><th>Utilisé</th><th>Statut</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $c): ?>
    <tr><td><b><?= e($c['code']) ?></b></td>
    <td><?= $c['kind'] === 'percent' ? (int) $c['value'] . ' %' : fcfa($c['value']) ?></td>
    <td><?= $c['expires_at'] ? e(fmt_date($c['expires_at'])) : '—' ?></td>
    <td><?= (int) $c['uses'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?></td>
    <td><?= $c['active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Désactivé</span>' ?></td>
    <td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <button class="btn btn-soft btn-xs" name="action" value="toggle"><?= $c['active'] ? 'Désactiver' : 'Activer' ?></button>
      <button class="btn btn-ghost btn-xs" name="action" value="delete" onclick="return confirm('Supprimer ce code ?')">Supprimer</button></form></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">Aucun code pour l'instant.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php view('admin/footer');
