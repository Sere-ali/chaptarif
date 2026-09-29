<?php
$title = 'Mentions légales & CGU';
view('layout/header', compact('title'));
?>
<section class="section-tight">
  <div class="container narrow prose">
    <h1 class="h2">Mentions légales & Conditions générales d'utilisation</h1>
    <p class="muted">À compléter par l'éditeur avec ses informations légales (raison sociale, RCCM, siège, directeur de publication) avant la mise en production.</p>
    <h3>1. Objet</h3>
    <p>ChapTarif est une plateforme de comparaison et de mise en relation entre des usagers et des prestataires de services en Côte d'Ivoire (transport, immobilier, services à domicile et beauté à domicile).</p>
    <h3>2. Paiement sous séquestre</h3>
    <p>Les paiements effectués via ChapTarif sont encaissés sur un compte séquestre. Les fonds sont reversés au prestataire, déduction faite de la commission ChapTarif, après confirmation par l'usager de la bonne exécution du service (ou saisie par le prestataire du code de validation remis par l'usager). En cas de réclamation, le déblocage est suspendu jusqu'à l'arbitrage de ChapTarif.</p>
    <h3>3. Commissions et frais</h3>
    <p>Une commission est prélevée sur chaque prestation (<?= (int) setting('commission_menage') ?> % pour les services à domicile et la beauté à domicile, <?= (int) setting('commission_immobilier') ?> % pour l'immobilier). Des frais de service de <?= fcfa(setting('cars_service_fee')) ?> par billet s'appliquent aux cars. Les prix affichés au client sont les prix finaux.</p>
    <h3>4. Annulation et remboursement</h3>
    <p>Une réservation non payée peut être annulée à tout moment. Une réservation payée dont le service n'a pas été rendu fait l'objet d'un remboursement intégral après examen de la réclamation.</p>
    <h3>5. Données personnelles</h3>
    <p>Les données collectées (numéro de téléphone, nom, réservations, pièces d'identité des prestataires) sont utilisées uniquement pour le fonctionnement du service, conformément à la loi ivoirienne n° 2013-450 relative à la protection des données à caractère personnel. Vous pouvez demander l'accès ou la suppression de vos données à <?= e(setting('support_email')) ?>.</p>
  </div>
</section>
<?php view('layout/footer');
