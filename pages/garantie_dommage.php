<?php
$title = 'Garantie Dommage';
$active = 'garantie';
view('layout/header', compact('title', 'active'));
$ck = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
?>
<section class="gd">
  <div class="container gd-wrap">
    <div class="gd-hero">
      <div class="gd-hero-txt">
        <h1><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg> Garantie Dommage</h1>
        <i class="gd-swoosh"></i>
        <p><b>Une protection optionnelle pour vos services à domicile</b> (ménage, aide à domicile et pressing).</p>
      </div>
      <img src="/assets/img/gd-shield.jpg" alt="" width="509" height="308">
    </div>

    <h2 class="gd-h2">Ce que couvre la garantie</h2>
    <p class="gd-sub">Elle vous indemnise en cas de dommage survenu pendant la prestation.</p>
    <div class="gd-cols">
      <div class="gd-card gd-green">
        <div class="gd-card-h"><span class="gd-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 3l-6 9M9.5 12.5l4 2.5-2 6c-2-.5-5.5-2.5-7-5l5-3.5z"/><path d="M18 14l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6z"/></svg></span><b>Ménage &amp; Aide à domicile</b></div>
        <ul><li><?= $ck ?><span>Couvre les <b>dommages matériels</b> (casse, détérioration) survenus lors de la prestation.</span></li><li><?= $ck ?><span>Couvre le <b>vol</b> d’un bien dans le cadre de la prestation.</span></li></ul>
      </div>
      <div class="gd-card gd-blue">
        <div class="gd-card-h"><span class="gd-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="13" r="4.5"/><path d="M7 6h.01M10 6h.01"/></svg></span><b>Pressing &amp; Repassage</b></div>
        <ul><li><?= $ck ?><span>Couvre la <b>détérioration des vêtements</b> (brûlé, déchiré ou abîmé) pendant le lavage, le séchage ou le repassage.</span></li></ul>
      </div>
    </div>

    <div class="gd-plaf">
      <div class="gd-plaf-h"><svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="9" cy="6" rx="6" ry="2.5"/><path d="M3 6v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V6M3 10v4c0 1.4 2.7 2.5 6 2.5"/><ellipse cx="15" cy="14" rx="6" ry="2.5"/><path d="M9 14v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4"/></svg>
        <div><b>Plafonds d’indemnisation</b><small>L’indemnisation est accordée dans la limite des plafonds suivants :</small></div></div>
      <div class="gd-plaf-cols">
        <div><span class="gd-plaf-t gd-t-green"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20H3z"/><path d="M10 20v-5h4v5"/></svg>Ménage &amp; Aide à domicile</span><em class="gd-pill gd-p-green">Jusqu’à 150 000 FCFA<br>par prestation</em></div>
        <div><span class="gd-plaf-t"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1677C9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="13" r="4.5"/><path d="M7 6h.01M10 6h.01"/></svg>Pressing &amp; Repassage</span><em class="gd-pill gd-p-blue">Jusqu’à 100 000 FCFA<br>par prestation</em></div>
      </div>
    </div>

    <h2 class="gd-h2">Comment ça marche ?</h2>
    <div class="gd-steps">
      <div class="gd-step"><span class="gd-n">1</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg><p>Je coche la Garantie Dommage lors de ma réservation.</p></div>
      <div class="gd-step"><span class="gd-n">2</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l4-4 4 2 4-2 4 2 4 4"/><path d="M7 13l3 3a1.5 1.5 0 0 0 2-2M10 16l2 2a1.5 1.5 0 0 0 2-2l-3-3M14 18a1.5 1.5 0 0 0 2-2l-2-2M16 16a1.5 1.5 0 0 0 2-2l-4-4"/></svg><p>Le service est réalisé normalement par le prestataire.</p></div>
      <div class="gd-step"><span class="gd-n">3</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h6M8 17h3"/><path d="m15 19 5-5 1.5 1.5-5 5H15z"/></svg><p>En cas de problème, j’ouvre une réclamation depuis « Mes réservations ».</p></div>
      <div class="gd-step"><span class="gd-n">4</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg><p>ChapTarif examine ma demande et, si elle est validée, je suis indemnisé(e) selon les plafonds.</p></div>
    </div>

    <div class="gd-info">
      <div class="gd-info-h"><span>i</span><b>Bon à savoir</b></div>
      <ul><li><?= $ck ?><span>La Garantie Dommage est <b>optionnelle</b> et <b>payante</b>.</span></li><li><?= $ck ?><span>Elle doit être souscrite au <b>moment de la réservation</b>.</span></li><li><?= $ck ?><span>Elle est disponible sur les services Ménage &amp; Aide à domicile et <b>Pressing &amp; Repassage</b>.</span></li><li><?= $ck ?><span>Toute réclamation est traitée par l’équipe ChapTarif, via « Mes réservations » → Réclamation.</span></li></ul>
    </div>

    <div class="gd-cta"><a class="btn btn-primary btn-lg" href="/service-a-la-personne">Réserver un service</a></div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
