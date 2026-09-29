# ChapTarif — Plateforme multi-services (Côte d'Ivoire)

Comparateur + réservation + paiement sous séquestre pour 9 univers, groupés en 4 domaines :
**Transports** (Cars Voyage · Louer un car · Location de gros camion · Covoiturage) · **Immobilier** (Location d'appartements, villas & studios) · **Service à la personne** (Ménage ou aide à domicile · Pressing & repassage) · **Beauté à domicile** (Coiffeuse · Maquilleuse · Onglerie).

Stack : **HTML / CSS / JavaScript + PHP 8.3** (sans framework), PostgreSQL sur **Render**, images sur **Cloudinary**.

---

## 1. Fonctionnalités

### Site public
- Accueil avec recherche rapide par univers, cartes univers groupées par domaine, explication du séquestre, page Garantie Dommage, FAQ.
- **Ménage / Pressing / Coiffeuse / Maquilleuse / Onglerie** : formules à prix fixe, prestataires vérifiés filtrables par commune, badge « Recommandé » en tête de liste.
- **Cars** : départs par ligne et par date, places restantes, **E-billet QR code signé (HMAC)**, impression PDF.
- **Immobilier** : annonces avec galerie Cloudinary, filtre par type (appartement/villa/studio) et par commune, calcul nuitée / semaine, contrôle des disponibilités.
- **Covoiturage** : un chauffeur vérifié (univers « Covoiturage » dans Prestataires) publie son trajet depuis **Admin → Trajets covoiturage** (ville + précision de départ/arrivée, heure, prix par place) ; le client réserve sa place, paiement sous séquestre, E-billet QR comme pour les Cars.
- **Location de car / Location de camion** : mêmes mécanismes que Ménage/Pressing (prestataires + formules créées dans **Admin → Prestataires** et **Admin → Formules & tarifs**) — location de car pour sorties de groupe, camion pour déménagement/fret.
- **Garantie Dommage** : option payante (+X % du prix, réglable dans **Admin → Paramètres**, 5 % par défaut) proposée sur Ménage, Pressing, Location de car et Location de camion. En cas de réclamation, elle est signalée à l'équipe (badge dans les litiges) qui décide d'indemniser ou de refaire la prestation. Page d'explication publique : `/garantie-dommage`.
- Connexion client **sans mot de passe par OTP SMS** (au moment du paiement uniquement).
- Paiement Wave / Orange Money / MTN / Moov → **fonds BLOQUÉS** → le client clique « Confirmer la fin du travail » **ou** remet un code à 4 chiffres au prestataire → **VALIDÉ** (commission retenue, reversement automatique). Réclamation → **EN LITIGE** (paiement gelé).
- Espace prestataire public `/prestataire/valider` (saisie du code client pour être payé).
- Candidature prestataire avec envoi de la CNI (stockée **en privé** sur Cloudinary).
- PWA installable (manifest + service worker), responsive mobile avec barre d'onglets.

### Back-office `/admin`
| Fonction | Admin | Super Admin |
|---|:-:|:-:|
| Tableau de bord (revenus, volume, séquestre, litiges, graphiques) | ✓ | ✓ |
| Réservations : débloquer, rembourser, annuler, notes, export CSV | ✓ | ✓ |
| Arbitrage des litiges | ✓ | ✓ |
| Finances : grand livre, file des virements à effectuer, export CSV | ✓ | ✓ |
| Contrôle des E-billets (scan caméra ou saisie) | ✓ | ✓ |
| Prestataires : KYC, suspension, badge Recommandé (5 000 F/mois), photos Cloudinary | ✓ | ✓ |
| Formules, départs de cars, logements (upload photos), zones GPS | ✓ | ✓ |
| Clients, messages de contact | ✓ | ✓ |
| **Gestion des administrateurs** (création, rôle, désactivation, réinit. mot de passe) | — | ✓ |
| **Paramètres** (commissions par univers, frais, Garantie Dommage, maintenance) | — | ✓ |
| **Journal d'audit** de toutes les actions sensibles | — | ✓ |

Sécurité : CSRF sur tous les formulaires, requêtes préparées (PDO), mots de passe bcrypt, blocage après 5 échecs, session admin expirée après 2h d'inactivité, mot de passe provisoire à changer à la 1re connexion, au moins un Super Admin toujours actif, en-têtes de sécurité (HSTS, X-Frame-Options…), seul `public/` est exposé.

---

## 2. Déploiement sur Render (≈ 10 minutes)

1. **Mettez le code sur GitHub** (nouveau dépôt privé) :
   ```bash
   git init && git add . && git commit -m "ChapTarif v1"
   git remote add origin https://github.com/VOTRE_COMPTE/chaptarif.git
   git push -u origin main
   ```
2. Sur **dashboard.render.com** → **New → Blueprint** → choisissez le dépôt. Render lit `render.yaml` et crée :
   - le service web Docker `chaptarif` (PHP 8.3 + Apache),
   - la base PostgreSQL `chaptarif-db` (reliée automatiquement via `DATABASE_URL`).
3. Renseignez les variables demandées :
   | Variable | Valeur |
   |---|---|
   | `CLOUDINARY_URL` | Cloudinary → Settings → API Keys : `cloudinary://API_KEY:API_SECRET@epxlbn9z` |
   | `SUPERADMIN_EMAIL` | votre e-mail de Super Admin |
   | `SUPERADMIN_PASSWORD` | un mot de passe fort (10+ caractères) |
   | `APP_URL` | l'URL Render, ex. `https://chaptarif.onrender.com` |
4. Déployez. Les tables et les données de démonstration sont créées automatiquement au premier démarrage.
5. Connectez-vous sur `/admin/login`, puis **remplacez les données de démonstration** (prestataires, formules, départs, logements) par vos vrais partenaires.

> Le plan **free** de Render met le site en veille après 15 min sans visite (réveil ≈ 30 s) et la base Postgres gratuite expire au bout de 30 jours. Pour la production, passez le service et la base en plan **Starter**.

### Nom de domaine
Render → service → **Settings → Custom Domains** → ajoutez `chaptarif.ci` et suivez les enregistrements DNS indiqués.

---

## 3. Passer en paiements réels

Par défaut `PAYMENT_MODE=simulation` : un écran de test remplace la passerelle (aucun argent débité).

### Option A — Paiement manuel (sans API, recommandé pour démarrer)

`PAYMENT_MODE=manuel` : le client envoie lui-même l'argent sur votre numéro Mobile Money (Wave, Orange Money, MTN MoMo ou Moov), puis vous (Super Admin) validez la réception dans le back-office.

1. Sur Render, ajoutez la variable `PAYMENT_MODE=manuel`.
2. Dans **Admin → Paramètres → Paiement manuel**, renseignez vos numéros de réception (le numéro Wave est déjà pré-rempli avec `01 00 35 40 93`, modifiable à tout moment).
3. Quand un client commande, il voit le numéro à qui envoyer l'argent, puis clique « J'ai envoyé le paiement ». La réservation passe en attente de validation.
4. Vous recevez l'alerte dans **Admin → Réservations → 🕐 paiement(s) manuel(s) à valider**. Après avoir vérifié la réception sur votre compte Wave/Orange/MTN/Moov, ouvrez la réservation et cliquez **« Confirmer la réception du paiement »** : les fonds passent alors sous séquestre ChapTarif comme pour tout paiement, avec le code de validation à 4 chiffres généré pour le client.

Aucune clé API n'est nécessaire pour ce mode.

### Option B — CinetPay (paiement automatique par API)

1. Ouvrez un compte marchand **CinetPay** (couvre Wave, Orange Money, MTN, Moov en CI).
2. Ajoutez `CINETPAY_APIKEY`, `CINETPAY_SITE_ID`, puis `PAYMENT_MODE=cinetpay`.
3. Dans CinetPay, l'URL de notification est `https://VOTRE_DOMAINE/webhook/cinetpay` (déjà transmise automatiquement à chaque paiement).
4. Faites un test réel à 100 F.

Chaque paiement est **revérifié côté serveur** auprès de CinetPay avant de bloquer les fonds.
Les reversements prestataires et remboursements passent en statut **A_VIRER / A_REMBOURSER** dans *Finances* : l'équipe effectue le transfert puis clique « Marquer payé ». Pour les automatiser, branchez l'API de transfert CinetPay / Wave Business dans `app/payment.php` → `payout_send()`.

## 4. Brancher les SMS (OTP)

Sans passerelle, avec `OTP_DEMO=1`, le code s'affiche à l'écran (tests uniquement).
Pour la production : `SMS_API_URL` + `SMS_API_TOKEN` (POST JSON `{to, from, message}` — Orange SMS API, Infobip, Twilio… via un petit adaptateur), puis `OTP_DEMO=0`.

---

## 5. Développement local
```bash
cp .env.example .env        # APP_ENV=local, SQLite automatique
php -S localhost:8000 -t public router.php
```
Super Admin par défaut en local (si `SUPERADMIN_*` non définis) : `superadmin@chaptarif.ci` / `ChapTarif@2026` — changement obligatoire à la 1re connexion.

## 6. Arborescence
```
app/        cœur : bdd, auth, tarification, séquestre, paiement, Cloudinary, SMS
pages/      pages publiques
admin/      back-office (Admin / Super Admin)
views/      gabarits (en-tête, pied de page)
public/     seul dossier exposé : index.php (routeur), CSS, JS, images, PWA
docker/     configuration Apache/PHP pour Render
render.yaml blueprint Render · Dockerfile
```

## 7. Images
Les visuels du site sont hébergés sur Cloudinary (cloud `epxlbn9z`, dossier `chaptarif/`). Les photos ajoutées depuis le back-office (prestataires, logements) sont envoyées dans `chaptarif/prestataires` et `chaptarif/immobilier` ; les pièces d'identité dans `chaptarif/kyc` en accès **authentifié** (non publiques).

Note légale : complétez `/mentions-legales` (raison sociale, RCCM, etc.) avant l'ouverture au public.
