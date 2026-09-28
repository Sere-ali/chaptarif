<?php
$title = 'Garantie Dommage';
$active = 'garantie';
$pct = (float) setting('garantie_dommage_pct', 5);
view('layout/header', compact('title', 'active'));
?>
<section class="page-hero" style="--c:#B45309;--hero:url('<?= e(img('hero-abidjan', 'w_1600,h_600,c_fill,q_auto,f_auto')) ?>')">
  <div class="container"><nav class="crumbs"><a href="/">Accueil</a> › Garantie Dommage</nav><h1>🛡️ La Garantie Dommage</h1><p>Une assurance interne ChapTarif, en option, pour les services à domicile : ménage, aide à domicile, pressing, location de car et de camion.</p></div>
</section>
<section class="section">
  <div class="container narrow">
    <div class="alert alert-success" style="margin-bottom:20px"><b>En bref :</b> pour +<?= e((string) $pct) ?>% du prix du service, si la prestation est mal réalisée ou qu'un objet est endommagé, ChapTarif vous indemnise ou vous propose une nouvelle prestation gratuite.</div>

    <h2 class="h4">Comment ça marche&nbsp;?</h2>
    <div class="how">
      <div class="how-step"><span>1</span><div><h3>Vous cochez l'option au moment de la réservation</h3><p>Sur les formulaires Ménage & Aide, Pressing & Linge, Location de car et Location de camion, cochez « 🛡️ Garantie Dommage » avant de payer. Son coût (+<?= e((string) $pct) ?>%) s'ajoute au total, affiché avant paiement.</p></div></div>
      <div class="how-step"><span>2</span><div><h3>Le service est réalisé</h3><p>Le prestataire intervient normalement. La garantie ne change rien à son travail : elle vous protège en cas de problème.</p></div></div>
      <div class="how-step"><span>3</span><div><h3>Un souci&nbsp;? Ouvrez une réclamation</h3><p>Depuis « Mes réservations », signalez le problème (ménage mal fait, vêtement brûlé au repassage, objet cassé…). Le paiement du prestataire est gelé pendant l'examen.</p></div></div>
      <div class="how-step"><span>4</span><div><h3>ChapTarif tranche</h3><p>Notre équipe étudie votre réclamation et, si la garantie a été souscrite, décide soit de vous <b>indemniser</b>, soit de vous proposer <b>une nouvelle prestation gratuite</b>.</p></div></div>
    </div>

    <h2 class="h4 mt">Exemples concrets</h2>
    <ul class="checks">
      <li><b>Ménage :</b> la femme de ménage n'a pas nettoyé correctement, avec beaucoup de manquements → indemnisation du ménage non effectué ou nouveau ménage offert.</li>
      <li><b>Pressing :</b> un vêtement est brûlé pendant le repassage → indemnisation si la garantie avait été prise pour cette réservation.</li>
      <li><b>Location de camion :</b> un meuble est endommagé pendant un déménagement → réclamation possible avec la garantie souscrite.</li>
    </ul>

    <h2 class="h4 mt">Bon à savoir</h2>
    <ul class="checks">
      <li>La Garantie Dommage est <b>optionnelle et payante</b> : elle n'est pas incluse automatiquement, il faut la cocher avant de payer.</li>
      <li>Elle ne peut être demandée qu'<b>au moment de la réservation</b>, pas après.</li>
      <li>Elle est disponible sur : Ménage & Aide, Pressing & Linge, Location de car, Location de camion.</li>
      <li>Toute réclamation passe par le même circuit que les autres litiges (« Mes réservations » → Réclamation), examinée par l'équipe ChapTarif.</li>
    </ul>

    <div class="center mt">
      <a class="btn btn-primary btn-lg" href="/#univers">Réserver un service</a>
    </div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
