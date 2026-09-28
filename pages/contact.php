<?php
if (is_post()) {
    $msg = trim((string) ($_POST['message'] ?? ''));
    if (mb_strlen($msg) < 10 || !empty($_POST['website'])) {
        flash('error', 'Votre message est trop court.');
        redirect('/contact');
    }
    if (too_many_attempts('contact', 5, 60)) {
        flash('error', 'Trop de messages envoyés. Réessayez plus tard.');
        redirect('/contact');
    }
    record_attempt('contact');
    insert('contact_messages', [
        'name' => mb_substr((string) $_POST['name'], 0, 80), 'phone' => mb_substr((string) $_POST['phone'], 0, 30),
        'email' => mb_substr((string) ($_POST['email'] ?? ''), 0, 120), 'message' => mb_substr($msg, 0, 3000), 'handled' => 0, 'created_at' => now(),
    ]);
    flash('success', 'Message reçu ! Nous vous répondons rapidement.');
    redirect('/contact');
}
$title = 'Contact & réclamations';
view('layout/header', compact('title'));
?>
<section class="section-tight">
  <div class="container two-col">
    <div>
      <h1 class="h2">Parlons-en</h1>
      <p class="muted">Une question, une réclamation, un partenariat ? Notre équipe basée à Abidjan vous répond 7j/7.</p>
      <div class="features features-col">
        <div><span>📞</span><b><?= e(setting('support_phone')) ?></b><small>Appel ou WhatsApp, 7h – 22h</small></div>
        <div><span>✉️</span><b><?= e(setting('support_email')) ?></b><small>Réponse sous 24h</small></div>
        <div><span>⚠️</span><b>Un problème sur une réservation ?</b><small>Ouvrez une réclamation depuis <a href="/compte">Mes réservations</a> : le paiement est gelé immédiatement.</small></div>
      </div>
    </div>
    <form class="card form-card" method="post"><?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="fld"><span>Nom</span><input name="name" required maxlength="80"></label>
      <div class="row2"><label class="fld"><span>Téléphone</span><input name="phone" required inputmode="tel"></label><label class="fld"><span>E-mail</span><input type="email" name="email"></label></div>
      <label class="fld"><span>Message</span><textarea name="message" rows="5" required minlength="10"></textarea></label>
      <button class="btn btn-primary btn-block btn-lg">Envoyer</button>
    </form>
  </div>
</section>
<?php view('layout/footer');
