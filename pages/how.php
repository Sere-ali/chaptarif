<?php
$title = 'Comment ça marche';
$active = 'how';
view('layout/header', compact('title', 'active'));
?>
<section class="page-hero" style="--c:#2E7D5B;--hero:url('<?= e(img('hero-abidjan', 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container"><nav class="crumbs"><a href="/">Accueil</a> › Comment ça marche</nav><h1>Comparez. Réservez. Payez en sécurité.</h1><p>ChapTarif met fin aux prix opaques, aux négociations interminables et aux arnaques.</p></div>
</section>
<section class="section">
  <div class="container narrow">
    <div class="how">
      <div class="how-step"><span>1</span><div><h3>Comparez les prix</h3><p>Choisissez un univers (VTC, ménage, pressing, livraison, cars, immobilier). Les tarifs sont affichés à l'avance : par course, à la séance, au forfait, par zone ou à la nuitée.</p></div></div>
      <div class="how-step"><span>2</span><div><h3>Identifiez-vous par SMS</h3><p>Pas de mot de passe à retenir. Votre numéro de téléphone et un code à 6 chiffres suffisent.</p></div></div>
      <div class="how-step"><span>3</span><div><h3>Payez via Mobile Money</h3><p>Wave, Orange Money, MTN MoMo ou Moov. Votre argent est reçu par ChapTarif et <b>bloqué sous séquestre</b>. Le prestataire est notifié que sa mission est garantie.</p></div></div>
      <div class="how-step"><span>4</span><div><h3>Le service est réalisé</h3><p>Le prestataire intervient. Pour les cars, vous présentez votre E-billet QR code à l'embarquement.</p></div></div>
      <div class="how-step"><span>5</span><div><h3>Vous validez, il est payé</h3><p>Cliquez sur « Confirmer la fin du travail » ou remettez votre code de validation au prestataire. ChapTarif verse automatiquement sa part au prestataire.</p></div></div>
      <div class="how-step warn"><span>!</span><div><h3>Un problème ?</h3><p>Absence, travail non conforme : ouvrez une réclamation depuis « Mes réservations ». Le paiement est gelé et notre équipe arbitre. Si le service n'a pas été rendu, vous êtes remboursé.</p></div></div>
    </div>
    <div class="center mt"><a class="btn btn-primary btn-lg" href="/#univers">Commencer maintenant</a></div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
