/* Page « Devenir prestataire » : traduction français <-> anglais (sans recharger la page). */
(function () {
  'use strict';
  var D = {
    // En-tête, menu, barre du bas
    'Aller au contenu': 'Skip to content', 'Télécharger': 'Download', 'Services': 'Services', 'Transports': 'Transport',
    'Cars, location & chauffeur': 'Coaches, rentals & drivers', 'Immobilier': 'Real estate', 'Studios, villas & appartements': 'Studios, villas & apartments',
    'Service à la personne': 'Home services', 'Ménage & pressing': 'Cleaning & laundry', 'Beauté': 'Beauty', 'Beauté à domicile': 'Beauty at home',
    'Coiffure & maquillage': 'Hair & make-up', 'Comment ça marche': 'How it works', 'Garantie Dommage': 'Damage Guarantee',
    'Devenir prestataire': 'Become a provider', 'Se connecter': 'Log in', 'Mon espace prestataire': 'My provider area',
    'Valider une mission': 'Validate a job', 'Contact & aide': 'Contact & help', 'Retour · Accueil': 'Back · Home',
    'Accueil': 'Home', 'Réservations': 'Bookings', 'Profil': 'Profile', 'Mes réservations': 'My bookings', 'Mon compte': 'My account',
    // Colonne de présentation
    'Espace partenaires': 'Partner area', 'Rejoignez le réseau': 'Join', 'Plus de missions, plus de clients, plus de DJÈ !': 'More jobs, more clients, more money!',
    'Paiement garanti': 'Guaranteed payment',
    'Le client paie à la réservation, vous êtes payé sur Wave ou Orange Money dès la validation.': 'The client pays when booking; you get paid on Wave or Orange Money as soon as the job is validated.',
    'Plus de clients': 'More clients', 'Visibilité dans votre commune auprès de milliers d’Abidjanais.': 'Get seen in your area by thousands of people in Abidjan.',
    'Badge « Recommandé »': '“Recommended” badge', 'Commission claire': 'Clear commission',
    'Le client paie': 'The client pays', 'Vous recevez': 'You receive',
    // Onglets et connexion
    'Créer mon compte': 'Create my account', 'Ravi de vous revoir !': 'Welcome back!',
    'Accédez à votre espace pour gérer vos annonces, vos missions et vos paiements.': 'Access your area to manage your listings, jobs and payments.',
    'Numéro de téléphone': 'Phone number', 'Mot de passe': 'Password', 'Me connecter': 'Log in',
    'Pas encore prestataire ?': 'Not a provider yet?', 'Mot de passe oublié ?': 'Forgot your password?', 'Déjà prestataire ?': 'Already a provider?',
    // Inscription
    'Devenez prestataire': 'Become a provider', 'Inscription gratuite, en 3 étapes. Notre équipe valide votre dossier rapidement.': 'Free sign-up in 3 steps. Our team reviews your application quickly.',
    'Activité': 'Activity', 'Compte': 'Account', 'Documents': 'Documents', 'Votre activité': 'Your activity',
    'Cars Voyage': 'Intercity coaches', 'Louer un car': 'Rent a coach', 'Location de véhicule': 'Vehicle rental', 'Louer un chauffeur': 'Hire a driver',
    'Location de gros camion': 'Truck rental', 'Covoiturage': 'Car-sharing', 'Ménage ou aide à domicile': 'Cleaning or home help',
    'Pressing & repassage': 'Laundry & ironing', 'Coiffeuse à domicile': 'Hairdresser at home', 'Maquilleuse à domicile': 'Make-up artist at home',
    'Onglerie à domicile': 'Nail technician at home', 'Choisir…': 'Choose…',
    'Nom complet ou nom de l\'entreprise': 'Full name or company name', 'Téléphone': 'Phone', 'Commune': 'Area (commune)', 'Véhicule': 'Vehicle',
    '📱 Indiquez un numéro sur lequel on peut vous joindre : vous y recevrez le': '📱 Enter a number where you can be reached: you will receive the',
    'code de confirmation par SMS': 'SMS confirmation code', 'et les nouvelles réservations.': 'and your new bookings on it.',
    'Présentez votre activité': 'Describe your activity', 'E-mail (facultatif)': 'Email (optional)', 'Recevoir mes paiements sur': 'Receive my payments on',
    'Numéro de paiement': 'Payment number', 'Mot de passe (8 caractères min.)': 'Password (8 characters min.)', 'Confirmer le mot de passe': 'Confirm password',
    'Vérification de votre numéro': 'Verify your number', 'Nous envoyons un code par SMS au numéro indiqué à l\'étape 1.': 'We send a code by SMS to the number entered in step 1.',
    'Recevoir le code': 'Get the code', 'Renvoyer le code': 'Resend the code', 'Envoi…': 'Sending…',
    // Documents
    'Documents à fournir': 'Documents to provide', 'Choisissez d’abord votre activité à l’étape 1.': 'First choose your activity in step 1.',
    'Pièce d’identité — recto': 'ID document — front', 'Pièce d’identité — verso': 'ID document — back',
    '(face avant : CNI, passeport ou titre de séjour)': '(front side: national ID card, passport or residence permit)', '(face arrière)': '(back side)', '(face avant)': '(front side)',
    'Photo d’identité': 'ID photo', 'Casier judiciaire': 'Criminal record certificate', '(moins de 3 mois)': '(less than 3 months old)',
    'Permis de conduire valide': 'Valid driving licence', 'Carte grise du véhicule': 'Vehicle registration certificate', 'Assurance du véhicule valide': 'Valid vehicle insurance',
    'Extrait du RCCM': 'Business registration (RCCM) extract', '(entreprise de transport ou de location)': '(transport or rental company)', 'Statuts de la société': 'Company articles of association',
    'Pièce d’identité du représentant légal — recto': 'Legal representative’s ID — front', 'Pièce d’identité du représentant légal — verso': 'Legal representative’s ID — back',
    'Titre de propriété ou attestation de mise à disposition du bien': 'Title deed or property availability certificate', '(contrat de gestion, mandat, etc.)': '(management contract, mandate, etc.)',
    'Pièce d’identité du propriétaire — recto': 'Owner’s ID — front', 'Pièce d’identité du propriétaire — verso': 'Owner’s ID — back',
    'À retenir : les documents doivent être lisibles, valides et à jour (JPG, PNG ou PDF).': 'Please note: documents must be readable, valid and up to date (JPG, PNG or PDF).',
    '🔒 Vos documents sont stockés de façon privée et consultés uniquement par l\'équipe de vérification.': '🔒 Your documents are stored privately and only viewed by our verification team.',
    '← Retour': '← Back', 'Continuer →': 'Continue →', 'Envoyer ma candidature': 'Submit my application',
    // Messages
    'Choisissez votre activité.': 'Choose your activity.', 'Complétez ce champ.': 'Please fill in this field.',
    'Les deux mots de passe ne sont pas identiques.': 'The two passwords do not match.',
    'Indiquez votre nom complet ou celui de votre entreprise.': 'Enter your full name or your company name.',
    'Numéro de téléphone invalide.': 'Invalid phone number.', 'Une candidature existe déjà pour ce numéro.': 'An application already exists for this number.',
    'Choisissez un mot de passe d\'au moins 8 caractères.': 'Choose a password of at least 8 characters.',
    'Trop de candidatures depuis cette connexion. Réessayez plus tard.': 'Too many applications from this connection. Please try again later.',
    'Code de vérification SMS incorrect ou expiré.': 'Incorrect or expired SMS verification code.',
    'Trop de demandes. Réessayez dans 30 minutes.': 'Too many requests. Try again in 30 minutes.',
    'Le code n\'a pas pu être envoyé. Réessayez plus tard.': 'The code could not be sent. Please try again later.',
    'Candidature envoyée ! Connectez-vous juste à côté avec votre numéro et votre mot de passe pour présenter vos services. Notre équipe vérifie votre dossier (pièces demandées) et vous appelle sous 30 minutes.': 'Application sent! Log in with your number and password to present your services. Our team checks your documents and will call you within 30 minutes.',
    'Candidature envoyée ! Ce numéro a déjà un compte : connectez-vous (code ou mot de passe existant) pour accéder à votre espace. Notre équipe vérifie votre dossier sous 30 minutes.': 'Application sent! This number already has an account: log in (code or existing password) to access your area. Our team checks your application within 30 minutes.',
    // Champs (placeholders, infobulles)
    'Ex. Koné Aminata ou Clean Services CI': 'E.g. Koné Aminata or Clean Services CI', 'Ex. Minibus 18 places climatisé': 'E.g. Air-conditioned 18-seat minibus',
    'Expérience, spécialités, horaires…': 'Experience, specialities, working hours…', 'vous@exemple.com': 'you@example.com', 'Si différent': 'If different',
    'Code à 6 chiffres': '6-digit code', 'Voir le document': 'View the document', 'Fermer': 'Close', 'Aperçu du document': 'Document preview',
    'Afficher le mot de passe': 'Show password', 'Indicatif du pays': 'Country code', 'Étapes': 'Steps', 'Pays': 'Country', 'Menu': 'Menu'
  };
  var C = { 'Sénégal': 'Senegal', 'Guinée': 'Guinea', 'Bénin': 'Benin', 'Cameroun': 'Cameroon', 'RD Congo': 'DR Congo', 'Maroc': 'Morocco', 'Tunisie': 'Tunisia',
    'Belgique': 'Belgium', 'Suisse': 'Switzerland', 'Royaume-Uni': 'United Kingdom', 'Allemagne': 'Germany', 'Italie': 'Italy', 'Espagne': 'Spain',
    'États-Unis / Canada': 'United States / Canada', 'Émirats': 'UAE', 'Liban': 'Lebanon', 'Chine': 'China', "Côte d'Ivoire": "Côte d'Ivoire" };
  var R = [
    [/^En tête de liste pour (.+) \/ mois \(optionnel\)\.$/, function (m) { return 'Top of the list for ' + m[1] + ' / month (optional).'; }],
    [/^(\d+) % sur les services à domicile\. Inscription gratuite\.$/, function (m) { return m[1] + '% on home services. Free sign-up.'; }],
    [/^Exemple sur un ménage à (.+)$/, function (m) { return 'Example: a cleaning job at ' + m[1]; }],
    [/^Commission \((\d+) %\)$/, function (m) { return 'Commission (' + m[1] + '%)'; }],
    [/^Code envoyé au (.+)\.$/, function (m) { return 'Code sent to ' + m[1] + '.'; }],
    [/^Renvoyer \((\d+) s\)$/, function (m) { return 'Resend (' + m[1] + ' s)'; }],
    [/^(.+) : document obligatoire\.$/, function (m) { return (D[m[1]] || m[1]) + ': required document.'; }],
    [/^(\S+ \+\d+ · )(.+)$/, function (m) { return m[1] + (C[m[2]] || m[2]); }]
  ];
  function en(s) {
    var k = s.trim(); if (!k) return null;
    var e = Object.prototype.hasOwnProperty.call(D, k) ? D[k] : null;
    if (e === null) for (var i = 0; i < R.length; i++) { var m = k.match(R[i][0]); if (m) { e = R[i][1](m); break; } }
    if (e === null || e === k) return null;
    return s.replace(k, e);
  }
  var lang = 'fr', done = [], ATTR = ['placeholder', 'aria-label', 'title', 'alt'];
  function doText(n) {
    if (n._fr !== undefined && n.nodeValue === n._en) return;
    var t = en(n.nodeValue); if (t === null) return;
    n._fr = n.nodeValue; n._en = t; n.nodeValue = t; done.push(n);
  }
  function doEl(el) {
    ATTR.forEach(function (a) {
      var v = el.getAttribute(a); if (!v) return;
      var k = 'i18n' + a.replace('-', ''), t = en(v); if (t === null) return;
      el.dataset[k] = v; el.setAttribute(a, t); if (done.indexOf(el) < 0) done.push(el);
    });
  }
  function walk(root) {
    if (root.nodeType === 3) { if (!root.parentNode || !root.parentNode.closest('script,style')) doText(root); return; }
    if (root.nodeType !== 1 || root.closest('script,style')) return;
    doEl(root);
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT), n;
    while ((n = w.nextNode())) { if (n.nodeType === 3) { if (!n.parentNode.closest('script,style')) doText(n); } else doEl(n); }
  }
  function restore() {
    done.forEach(function (n) {
      if (n.nodeType === 3) { if (n.nodeValue === n._en) n.nodeValue = n._fr; delete n._fr; delete n._en; return; }
      ATTR.forEach(function (a) { var k = 'i18n' + a.replace('-', ''); if (n.dataset[k] !== undefined) { n.setAttribute(a, n.dataset[k]); delete n.dataset[k]; } });
    });
    done = [];
  }
  var obs = new MutationObserver(function (list) {
    if (lang !== 'en') return;
    list.forEach(function (m) {
      if (m.type === 'characterData') doText(m.target);
      else m.addedNodes.forEach(walk);
    });
  });
  function set(l) {
    lang = l;
    document.documentElement.lang = l;
    document.querySelectorAll('[data-lang]').forEach(function (b) { b.classList.toggle('on', b.dataset.lang === l); });
    if (l === 'en') { walk(document.body); obs.observe(document.body, { childList: true, subtree: true, characterData: true }); document.title = 'Become a ChapTarif provider'; }
    else { obs.disconnect(); restore(); document.title = 'Devenir prestataire ChapTarif'; }
    try { localStorage.setItem('ct_lang', l); } catch (e) {}
    document.cookie = 'ct_lang=' + l + ';path=/;max-age=31536000;samesite=lax';
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-lang]'); if (b) set(b.dataset.lang); });
  var saved = null; try { saved = localStorage.getItem('ct_lang'); } catch (e) {}
  if (!saved) { var m = document.cookie.match(/(?:^|; )ct_lang=(\w+)/); saved = m ? m[1] : null; }
  if (saved === 'en') set('en');
})();
