<?php
$title = 'Garantie Dommage';
$active = 'garantie';
view('layout/header', compact('title', 'active'));
$ck = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
?>
<section class="gd">
  <div class="container gd-wrap">
    <div class="gd-hero2">
      <div>
        <h1><svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#123E8C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg><span>Garantie Dommage</span></h1>
        <i class="gd-swoosh"></i>
        <p>Une protection optionnelle pour vos services à domicile (ménage, aide à domicile et pressing).</p>
      </div>
      <img src="/assets/img/gd-shield2.jpg" alt="" width="404" height="300">
    </div>

    <h2 class="gd-h2">Ce que couvre la garantie</h2>
    <p class="gd-sub">Elle vous indemnise en cas de dommage survenu pendant la prestation.</p>
    <div class="gd-cols">
      <div class="gd-card gd-green">
        <div class="gd-card-h"><span class="gd-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#0F1B2D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 3l-6 9M9.5 12.5l4 2.5-2 6c-2-.5-5.5-2.5-7-5l5-3.5z"/><path d="M18 14l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6z"/></svg></span><b>Ménage &amp; Aide à domicile</b></div>
        <ul><li><?= $ck ?><span>Couvre les <b>dommages matériels</b> (casse, détérioration) survenus lors de la prestation.</span></li><li><?= $ck ?><span>Couvre le <b>vol</b> d’un bien dans le cadre de la prestation.</span></li></ul>
      </div>
      <div class="gd-card gd-blue">
        <div class="gd-card-h"><span class="gd-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#0F1B2D" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7a2 2 0 1 1 2-2c0 1-2 1.5-2 3v1L3 15c-1 .6-.6 2 .6 2h16.8c1.2 0 1.6-1.4.6-2L12 9"/></svg></span><b>Pressing &amp; Repassage</b></div>
        <ul><li><?= $ck ?><span>Couvre la <b>détérioration des vêtements</b> (brûlé, déchiré ou abîmé) pendant le lavage, le séchage ou le repassage.</span></li></ul>
      </div>
    </div>

    <div class="gd-plaf">
      <div class="gd-plaf-h"><svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="9" cy="6" rx="6" ry="2.5"/><path d="M3 6v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V6M3 10v4c0 1.4 2.7 2.5 6 2.5"/><ellipse cx="15" cy="14" rx="6" ry="2.5"/><path d="M9 14v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4"/></svg>
        <div><b>Plafonds d’indemnisation</b><small>L’indemnisation est accordée dans la limite des plafonds suivants :</small></div></div>
      <div class="gd-plaf-cols">
        <div><span class="gd-plaf-t gd-t-green"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5V20H3z"/><path d="M10 20v-5h4v5"/></svg>Ménage &amp; Aide à domicile</span><em class="gd-pill gd-p-green">Jusqu’à 150 000 FCFA<br>par prestation</em></div>
        <div><span class="gd-plaf-t"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0F1B2D" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7a2 2 0 1 1 2-2c0 1-2 1.5-2 3v1L3 15c-1 .6-.6 2 .6 2h16.8c1.2 0 1.6-1.4.6-2L12 9"/></svg>Pressing &amp; Repassage</span><em class="gd-pill gd-p-blue">Jusqu’à 100 000 FCFA<br>par prestation</em></div>
      </div>
      <p class="gd-vet"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E7A30" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/></svg><span><b>Vétusté :</b> <mark>10 %</mark> par année d’ancienneté, plafonnée à <mark>50 %</mark>.</span></p>
    </div>

    <h2 class="gd-h2"><svg hidden width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0F1B2D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg> Comment ça marche ?</h2>
    <div class="gd-steps">
      <div class="gd-step"><span class="gd-n">1</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg><p>Je coche la Garantie Dommage lors de ma réservation.</p></div>
      <div class="gd-step"><span class="gd-n">2</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l4-4 4 2 4-2 4 2 4 4"/><path d="M7 13l3 3a1.5 1.5 0 0 0 2-2M10 16l2 2a1.5 1.5 0 0 0 2-2l-3-3M14 18a1.5 1.5 0 0 0 2-2l-2-2M16 16a1.5 1.5 0 0 0 2-2l-4-4"/></svg><p>Le service est réalisé normalement par le prestataire.</p></div>
      <div class="gd-step"><span class="gd-n">3</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h6M8 17h3"/><path d="m15 19 5-5 1.5 1.5-5 5H15z"/></svg><p>En cas de problème, j’ouvre une réclamation depuis « Mes réservations ».</p></div>
      <div class="gd-step"><span class="gd-n">4</span><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1E9E3E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg><p>ChapTarif examine ma demande et, si elle est validée, je suis indemnisé(e) selon les plafonds.</p></div>
    </div>

    <div class="gd-two">
      <div class="gd-excl">
        <h3><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg> Exclusions (non couvertes)</h3>
        <ul><li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span>L’usure normale des biens ou des vêtements.</span></li><li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span>Les dommages antérieurs à la prestation.</span></li><li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span>Les taches difficiles ou non détachables liées à la nature du tissu.</span></li><li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span>Les pertes non déclarées dans les délais requis.</span></li><li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span>Les dommages causés en dehors du cadre de la prestation.</span></li><li><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E53935" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span><b>Les objets précieux (tels que l’or, les bijoux, les œuvres d’art, les tableaux).</b></span></li></ul>
      </div>
      <div class="gd-delai">
        <h3><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1E7A30" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Délai de déclaration</h3>
        <p>Toute réclamation doit être effectuée dans un délai de <mark>24 heures (24 h)</mark> après la prestation et la réception (du service ou des vêtements).</p>
      </div>
    </div>

    <div class="gd-cta"><a class="btn btn-primary btn-lg" href="/service-a-la-personne">Réserver un service</a></div>
  </div>
</section>
<?php view('layout/footer', compact('active'));
