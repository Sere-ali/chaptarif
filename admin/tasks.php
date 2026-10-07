<?php
/**
 * Tâches internes : le Super Admin crée des tâches, les classe par catégorie
 * (une catégorie par « bouton » du site : Transport, Immobilier, Beauté,
 * Service à la personne, ou Général) et les attribue à un administrateur précis.
 * Chaque administrateur ne voit et ne traite que ses propres tâches ; le Super
 * Admin voit, filtre et pilote l'ensemble.
 */
$me = require_admin();
$isSuper = is_super($me);
$admins = all("SELECT id, name, email, role FROM users WHERE role IN ('admin','super_admin') AND active = 1 ORDER BY role DESC, name");
$cats = task_categories();

if (is_post()) {
    $act = $_POST['action'] ?? '';

    // ---- Création (Super Admin uniquement) ----
    if ($act === 'create') {
        if (!$isSuper) { flash('error', 'Seul le Super Admin peut créer et attribuer une tâche.'); redirect('/admin/taches'); }
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160);
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 2000);
        $cat = (string) ($_POST['category'] ?? 'general');
        if (!isset($cats[$cat])) $cat = 'general';
        $prio = (string) ($_POST['priority'] ?? 'normale');
        if (!isset(task_priorities()[$prio])) $prio = 'normale';
        $toId = (int) ($_POST['assigned_to'] ?? 0);
        $toUser = one("SELECT * FROM users WHERE id = ? AND role IN ('admin','super_admin') AND active = 1", [$toId]);
        $due = trim((string) ($_POST['due_date'] ?? ''));
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
        $ref = mb_substr(trim((string) ($_POST['booking_ref'] ?? '')), 0, 30);
        if (!$title || !$toUser) {
            flash('error', 'Le titre et l\'administrateur assigné sont obligatoires.');
            redirect('/admin/taches');
        }
        insert('tasks', [
            'title' => $title, 'description' => $desc, 'category' => $cat, 'priority' => $prio,
            'status' => 'a_faire', 'assigned_to' => $toUser['id'], 'assigned_by' => $me['id'],
            'booking_ref' => $ref ?: null, 'due_date' => $due,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        audit('task.creation', $title, ['assigne_a' => $toUser['email'], 'categorie' => $cat, 'priorite' => $prio]);
        flash('success', 'Tâche créée et attribuée à ' . ($toUser['name'] ?: $toUser['email']) . '.');
        redirect('/admin/taches');
    }

    $t = one('SELECT * FROM tasks WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$t) redirect('/admin/taches');
    $mine = (int) $t['assigned_to'] === (int) $me['id'];
    if (!$isSuper && !$mine) { flash('error', 'Cette tâche ne vous est pas attribuée.'); redirect('/admin/taches'); }

    // ---- Changement de statut (l'administrateur assigné ou le Super Admin) ----
    if ($act === 'status') {
        $st = (string) ($_POST['status'] ?? '');
        if (!isset(task_statuses()[$st])) redirect('/admin/taches');
        update('tasks', (int) $t['id'], [
            'status' => $st, 'updated_at' => now(),
            'completed_at' => $st === 'termine' ? now() : null,
        ]);
        audit('task.statut', $t['title'], ['statut' => $st]);
        flash('success', 'Statut de la tâche mis à jour.');
        redirect('/admin/taches');
    }

    // ---- Réattribution à un autre administrateur (Super Admin uniquement) ----
    if ($act === 'reassign') {
        if (!$isSuper) redirect('/admin/taches');
        $toId = (int) ($_POST['assigned_to'] ?? 0);
        $toUser = one("SELECT * FROM users WHERE id = ? AND role IN ('admin','super_admin') AND active = 1", [$toId]);
        if ($toUser) {
            update('tasks', (int) $t['id'], ['assigned_to' => $toUser['id'], 'status' => 'a_faire', 'updated_at' => now()]);
            audit('task.reattribution', $t['title'], ['nouvel_assigne' => $toUser['email']]);
            flash('success', 'Tâche réattribuée à ' . ($toUser['name'] ?: $toUser['email']) . '.');
        }
        redirect('/admin/taches');
    }

    // ---- Suppression (Super Admin uniquement) ----
    if ($act === 'delete') {
        if (!$isSuper) redirect('/admin/taches');
        q('DELETE FROM tasks WHERE id = ?', [(int) $t['id']]);
        audit('task.suppression', $t['title']);
        flash('success', 'Tâche supprimée.');
        redirect('/admin/taches');
    }
}

// ---- Filtres & liste ----
$fStatus = (string) ($_GET['status'] ?? '');
$fCat = (string) ($_GET['category'] ?? '');
$fTo = (int) ($_GET['assigned_to'] ?? 0);
$where = ['1=1'];
$p = [];
if (!$isSuper) { $where[] = 't.assigned_to = ?'; $p[] = $me['id']; }
elseif ($fTo) { $where[] = 't.assigned_to = ?'; $p[] = $fTo; }
if ($fStatus !== '' && isset(task_statuses()[$fStatus])) { $where[] = 't.status = ?'; $p[] = $fStatus; }
if ($fCat !== '' && isset($cats[$fCat])) { $where[] = 't.category = ?'; $p[] = $fCat; }
$w = implode(' AND ', $where);
$rows = all("SELECT t.*, ua.name AS a_name, ua.email AS a_email, ub.name AS b_name, ub.email AS b_email
             FROM tasks t
             LEFT JOIN users ua ON ua.id = t.assigned_to
             LEFT JOIN users ub ON ub.id = t.assigned_by
             WHERE $w
             ORDER BY CASE t.status WHEN 'a_faire' THEN 0 WHEN 'en_cours' THEN 1 WHEN 'termine' THEN 2 ELSE 3 END,
                      CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date, t.id DESC", $p);

$today = date('Y-m-d');
$openTotal = (int) val("SELECT COUNT(*) FROM tasks WHERE status NOT IN ('termine','annule')");
$lateTotal = (int) val("SELECT COUNT(*) FROM tasks WHERE status NOT IN ('termine','annule') AND due_date IS NOT NULL AND due_date < ?", [$today]);
$myOpen = (int) val("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('termine','annule')", [$me['id']]);

$page = 'Tâches';
$nav = 'tasks';
view('admin/header', compact('page', 'nav'));
?>
<?php if ($isSuper): ?>
<div class="kpis">
  <div class="kpi"><span class="kpi-ico">📌</span><small>Tâches ouvertes</small><b><?= $openTotal ?></b><em>Toutes équipes confondues</em></div>
  <div class="kpi"><span class="kpi-ico">⏰</span><small>En retard</small><b><?= $lateTotal ?></b><em>Échéance dépassée</em></div>
  <div class="kpi"><span class="kpi-ico">👤</span><small>Vos tâches</small><b><?= $myOpen ?></b><em>Assignées à vous-même</em></div>
  <div class="kpi"><span class="kpi-ico">🧑‍💼</span><small>Administrateurs</small><b><?= count($admins) ?></b><em><a href="/admin/administrateurs">Gérer l'équipe →</a></em></div>
</div>
<div class="panel">
  <div class="panel-h"><h2>Attribuer une nouvelle tâche</h2></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="row2">
      <label class="fld"><span>Titre de la tâche</span><input name="title" required maxlength="160" placeholder="Ex. Vérifier le KYC de 3 nouvelles coiffeuses"></label>
      <label class="fld"><span>Attribuer à</span>
        <select name="assigned_to" required>
          <option value="">— Choisir un administrateur —</option>
          <?php foreach ($admins as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $me['id'] ? 'selected' : '' ?>><?= e($a['name'] ?: $a['email']) ?><?= $a['role'] === 'super_admin' ? ' (Super Admin)' : '' ?></option>
          <?php endforeach; ?>
        </select></label>
    </div>
    <label class="fld"><span>Description / instructions</span><textarea name="description" rows="3" maxlength="2000" placeholder="Détails, contexte, ce qui est attendu…"></textarea></label>
    <div class="row2">
      <label class="fld"><span>Catégorie (le bouton du site concerné)</span>
        <select name="category">
          <?php foreach ($cats as $ck => $c): ?><option value="<?= e($ck) ?>"><?= $c['emoji'] ?> <?= e($c['name'] ?? $c['short']) ?></option><?php endforeach; ?>
        </select></label>
      <label class="fld"><span>Priorité</span>
        <select name="priority">
          <?php foreach (task_priorities() as $pk => [$pl]): ?><option value="<?= $pk ?>" <?= $pk === 'normale' ? 'selected' : '' ?>><?= e($pl) ?></option><?php endforeach; ?>
        </select></label>
    </div>
    <div class="row2">
      <label class="fld"><span>Échéance (optionnel)</span><input type="date" name="due_date"></label>
      <label class="fld"><span>Réservation liée (optionnel)</span><input name="booking_ref" maxlength="30" placeholder="Ex. CT-2026-00123"></label>
    </div>
    <button class="btn btn-primary mt">Créer et attribuer la tâche</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-h"><h2><?= $isSuper ? 'Toutes les tâches' : 'Mes tâches' ?></h2><span class="muted small"><?= count($rows) ?> tâche(s)</span></div>
  <form class="filters" method="get">
    <?php if ($isSuper): ?>
    <label class="fld"><span>Administrateur</span><select name="assigned_to"><option value="">Tous</option><?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $fTo === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name'] ?: $a['email']) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label class="fld"><span>Catégorie</span><select name="category"><option value="">Toutes</option><?php foreach ($cats as $ck => $c): ?><option value="<?= e($ck) ?>" <?= $fCat === $ck ? 'selected' : '' ?>><?= $c['emoji'] ?> <?= e($c['name'] ?? $c['short']) ?></option><?php endforeach; ?></select></label>
    <label class="fld"><span>Statut</span><select name="status"><option value="">Tous</option><?php foreach (task_statuses() as $sk => [$sl]): ?><option value="<?= $sk ?>" <?= $fStatus === $sk ? 'selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Filtrer</button>
  </form>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Tâche</th><th>Catégorie</th><th>Attribuée à</th><th>Priorité</th><th>Échéance</th><th>Statut</th><th class="num">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): $c = task_category($t['category']); $late = $t['due_date'] && $t['due_date'] < $today && !in_array($t['status'], ['termine', 'annule'], true); ?>
      <tr>
        <td><b><?= e($t['title']) ?></b>
          <?php if ($t['description']): ?><small><?= e(mb_strimwidth($t['description'], 0, 90, '…')) ?></small><?php endif; ?>
          <?php if ($t['booking_ref']): $bk = one('SELECT id FROM bookings WHERE ref = ?', [$t['booking_ref']]); ?>
            <small><?= $bk ? '<a href="/admin/reservation?id=' . (int) $bk['id'] . '">🧾 ' . e($t['booking_ref']) . '</a>' : '🧾 ' . e($t['booking_ref']) ?></small>
          <?php endif; ?>
          <small class="muted">Créée par <?= e($t['b_name'] ?: $t['b_email'] ?: '—') ?> le <?= fmt_date($t['created_at']) ?></small>
        </td>
        <td><span class="uni-dot" style="--c:<?= $c['color'] ?>"><i></i><?= $c['emoji'] ?> <?= e($c['short'] ?? $c['name']) ?></span></td>
        <td><?= e($t['a_name'] ?: $t['a_email'] ?: '—') ?></td>
        <td><?= task_priority_badge($t['priority']) ?></td>
        <td><?= $t['due_date'] ? fmt_date($t['due_date']) : '—' ?><?php if ($late): ?><br><span class="badge badge-red">⏰ En retard</span><?php endif; ?></td>
        <td><?= task_status_badge($t['status']) ?></td>
        <td><div class="acts">
          <?php if ($isSuper || (int) $t['assigned_to'] === (int) $me['id']): ?>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <select name="status" onchange="this.form.submit()">
              <?php foreach (task_statuses() as $sk => [$sl]): ?><option value="<?= $sk ?>" <?= $t['status'] === $sk ? 'selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?>
            </select>
          </form>
          <?php endif; ?>
          <?php if ($isSuper): ?>
          <details class="adm-menu">
            <summary class="btn btn-ghost btn-xs">Réattribuer</summary>
            <form method="post" style="padding:10px;min-width:220px">
              <?= csrf_field() ?><input type="hidden" name="action" value="reassign"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <select name="assigned_to"><?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $t['assigned_to'] ? 'selected' : '' ?>><?= e($a['name'] ?: $a['email']) ?></option><?php endforeach; ?></select>
              <button class="btn btn-primary btn-xs mt">Valider</button>
            </form>
          </details>
          <form method="post" data-confirm="Supprimer définitivement la tâche « <?= e($t['title']) ?> » ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-ghost btn-xs">🗑️</button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted"><?= $isSuper ? 'Aucune tâche pour le moment.' : 'Aucune tâche ne vous est attribuée pour le moment. 🎉' ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php view('admin/footer');
