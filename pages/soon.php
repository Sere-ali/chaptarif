<?php
// Service pas encore ouvert au public.
$x = universe($soonUk ?? '');
$title = ($x['name'] ?? 'Service') . ' · Bientôt disponible';
$noindex = true; header('X-Robots-Tag: noindex, nofollow');
http_response_code(200);
view('layout/header', compact('title', 'noindex') + ['active' => '']);
?>
<section class="section auth-wrap">
  <div class="auth-card card sec-card" style="text-align:center">
    <div style="font-size:54px">⏳</div>
    <h1 class="h2 center"><?= e($x['name'] ?? 'Ce service') ?></h1>
    <p class="center"><b>Bientôt disponible</b></p>
    <p class="center muted">Nous finalisons l'ouverture de ce service. Revenez très vite : nous vous préviendrons dès qu'il sera disponible.</p>
    <a class="btn btn-primary btn-block btn-lg" href="/transport">Voir les services disponibles</a>
    <a class="btn btn-ghost btn-block btn-lg" style="margin-top:10px" href="/">Retour à l'accueil</a>
  </div>
</section>
<?php view('layout/footer', ['active' => '']);
