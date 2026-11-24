<?php
// Transfert des fichiers Cloudinary vers le disque Render (réservé au super-administrateur).
// Peut être relancé autant de fois que nécessaire : ce qui est déjà copié est ignoré.
$me = require_super();
$nav = 'settings';
$cfg = cld_config();
$ready = files_ready();
$report = null;

function mf_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return ($b !== false && $code === 200 && $b !== '') ? $b : null;
}

/** Colonnes texte de la base (nom de table, colonne). */
function mf_columns(): array
{
    $out = [];
    if (db_driver() === 'pgsql') {
        foreach (all("SELECT table_name t, column_name c FROM information_schema.columns WHERE table_schema = 'public' AND data_type IN ('text','character varying')") as $r) $out[$r['t']][] = $r['c'];
    } else {
        foreach (all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $t) {
            foreach (all('PRAGMA table_info(' . $t['name'] . ')') as $c) if (stripos((string) $c['type'], 'INT') === false && stripos((string) $c['type'], 'REAL') === false) $out[$t['name']][] = $c['name'];
        }
    }
    unset($out['settings'], $out['audit_log'], $out['security_events']);
    return $out;
}

/** Copie un fichier Cloudinary vers le disque ; renvoie la nouvelle adresse /f/... ou null. */
function mf_copy_url(string $url, array &$st): ?string
{
    $u = str_replace('\\/', '/', $url);
    if (!preg_match('#/(image|raw|video)/(upload|authenticated)/(?:[^/]+/)*?(chaptarif/[^?\s"\'<>]+)$#', $u, $m)) { $st['skip']++; return null; }
    $id = $m[3];
    if (!preg_match('/\.[a-z0-9]{2,5}$/i', $id)) $id .= '.jpg';
    if (!files_id_ok($id)) { $st['skip']++; return null; }
    if (!files_exists($id, false)) {
        $b = mf_get(preg_replace('#/(upload|authenticated)/(?:(?!chaptarif/)[^/]+/)*(chaptarif/)#', '/upload/$2', $u));
        if ($b === null && $m[2] === 'authenticated') $b = null;
        if ($b === null) { $st['fail'][] = $id; return null; }
        files_put($id, $b, false);
        $st['files']++;
    }
    return '/f/' . $id;
}

if (is_post() && $ready && $cfg) {
    @set_time_limit(120); ignore_user_abort(true);
    $deadline = time() + 80;
    $st = ['files' => 0, 'rows' => 0, 'skip' => 0, 'fail' => [], 'docs' => 0, 'site' => 0, 'left' => false];

    // 1. Images du site référencées par img()
    foreach (['hero-abidjan', 'univers-immobilier'] as $sid) {
        $id = 'chaptarif/' . $sid . '.jpg';
        if (files_exists($id, false) || is_file(ROOT . '/public/assets/img/' . $sid . '.jpg')) continue;
        $b = mf_get('https://res.cloudinary.com/' . $cfg['cloud'] . '/image/upload/' . $id);
        if ($b !== null) { files_put($id, $b, false); $st['site']++; } else $st['fail'][] = $id;
    }

    // 2. Pièces des prestataires (privées ou publiques, sans adresse dans la base)
    foreach (all('SELECT * FROM providers') as $p) {
        foreach (provider_docs($p) as $k => $did) {
            if (!is_string($did) || !str_starts_with($did, 'chaptarif/') || !files_id_ok($did)) continue;
            if (files_exists($did, true) || files_exists($did, false)) continue;
            if (time() > $deadline) { $st['left'] = true; break 2; }
            $pub = str_starts_with($did, 'chaptarif/prestataires/');
            $urls = $pub ? ['https://res.cloudinary.com/' . $cfg['cloud'] . '/image/upload/' . $did] : [cld_private_url($did), 'https://res.cloudinary.com/' . $cfg['cloud'] . '/image/upload/' . $did];
            $b = null;
            foreach ($urls as $u) { $b = mf_get($u); if ($b !== null) break; }
            if ($b === null && str_ends_with(strtolower($did), '.pdf')) {
                $raw = 'https://res.cloudinary.com/' . $cfg['cloud'] . '/raw/upload/' . $did; $b = mf_get($raw);
            }
            if ($b === null) { $st['fail'][] = $did; continue; }
            files_put($did, $b, !$pub);
            $st['docs']++;
        }
    }

    // 3. Adresses Cloudinary écrites dans la base (photos, pièces jointes de chat, annonces...)
    foreach (mf_columns() as $t => $cols) {
        $hasId = false; foreach ($cols as $c) if ($c === 'id') $hasId = true;
        if (!$hasId) continue;
        foreach ($cols as $c) {
            if ($c === 'id') continue;
            $like = '%res.cloudinary.com%';
            $rows = all("SELECT id, $c AS v FROM $t WHERE $c LIKE ?", [$like]);
            foreach ($rows as $r) {
                if (time() > $deadline) { $st['left'] = true; break 3; }
                $v = (string) $r['v']; $new = $v;
                if (!preg_match_all('#https:(?:\\\\?/){2}res\.cloudinary\.com(?:\\\\?/)[^"\'\s<>,\)\]]+#', $v, $mm)) continue;
                foreach (array_unique($mm[0]) as $url) {
                    $to = mf_copy_url($url, $st);
                    if ($to === null) continue;
                    $new = str_replace($url, str_contains($url, '\\/') ? str_replace('/', '\\/', $to) : $to, $new);
                }
                if ($new !== $v) { q("UPDATE $t SET $c = ? WHERE id = ?", [$new, $r['id']]); $st['rows']++; }
            }
        }
    }
    $st['left'] = $st['left'] || false;
    audit('stockage.migration', 'Cloudinary → disque', ['fichiers' => $st['files'] + $st['docs'] + $st['site'], 'lignes' => $st['rows'], 'echecs' => count($st['fail'])]);
    $report = $st;
}

// État actuel : combien d'adresses Cloudinary restent dans la base ?
$remaining = 0;
foreach (mf_columns() as $t => $cols) {
    if (!in_array('id', $cols, true)) continue;
    foreach ($cols as $c) { if ($c === 'id') continue; $remaining += (int) (one("SELECT COUNT(*) n FROM $t WHERE $c LIKE ?", ['%res.cloudinary.com%'])['n'] ?? 0); }
}
$nb = ['pub' => count(glob(files_root() . '/public/chaptarif/*/*') ?: []), 'priv' => count(glob(files_root() . '/private/chaptarif/*/*') ?: [])];

$page = 'Transfert des fichiers';
view('admin/header', compact('page', 'nav'));
?>
<div class="panel" style="max-width:760px">
  <h2 style="margin-top:0">Quitter Cloudinary : transfert des fichiers</h2>
  <p><b>Disque Render :</b> <?= $ready ? '<span class="badge badge-green">actif · ' . e(files_root()) . '</span>' : '<span class="badge badge-red">pas encore branché</span>' ?>
     &nbsp; <b>Cloudinary :</b> <?= $cfg ? '<span class="badge badge-green">connecté</span>' : '<span class="badge badge-amber">non configuré</span>' ?></p>
  <p>Fichiers sur le disque : <b><?= $nb['pub'] ?></b> publics, <b><?= $nb['priv'] ?></b> privés. Adresses Cloudinary encore dans la base : <b><?= $remaining ?></b>.</p>
  <?php if (!$ready): ?>
    <div class="warn-box">Ajoutez d'abord un disque au service dans Render (Disks, chemin <code>/var/data</code>), puis revenez ici.</div>
  <?php elseif (!$cfg): ?>
    <div class="warn-box">CLOUDINARY_URL n'est plus configurée : il n'y a plus rien à copier.</div>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><button class="btn btn-primary">Copier mes fichiers depuis Cloudinary</button></form>
    <p class="muted" style="margin-top:10px">Peut prendre une minute. Si « il reste des fichiers » s'affiche, cliquez à nouveau.</p>
  <?php endif; ?>
  <?php if ($report): ?>
    <div class="<?= $report['fail'] ? 'warn-box' : 'ok-box' ?>" style="margin-top:14px">
      Copiés : <?= $report['files'] ?> fichiers de la base, <?= $report['docs'] ?> pièces de prestataires, <?= $report['site'] ?> images du site. Lignes mises à jour : <?= $report['rows'] ?>.
      <?php if ($report['left']): ?><br><b>Il reste des fichiers : cliquez à nouveau.</b><?php endif; ?>
      <?php if ($report['fail']): ?><br>Non récupérés (<?= count($report['fail']) ?>) : <?= e(implode(', ', array_slice($report['fail'], 0, 15))) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($ready && $remaining === 0 && $nb['pub'] + $nb['priv'] > 0): ?>
    <p class="ok-box" style="margin-top:14px">Tout est sur le disque. Vous pouvez supprimer la variable <code>CLOUDINARY_URL</code> dans Render.</p>
  <?php endif; ?>
</div>
<?php view('admin/footer');
