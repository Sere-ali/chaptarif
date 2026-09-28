<?php
/**
 * Données initiales — exécutées une seule fois à la création de la base.
 * Les prestataires / offres / biens ci-dessous sont des DONNÉES DE DÉMONSTRATION :
 * remplacez-les par vos vrais partenaires depuis le back-office.
 */

const CLD_BASE = 'https://res.cloudinary.com/epxlbn9z/image/upload/';

function seed_all(): void
{
    $now = date('Y-m-d H:i:s');

    // ---- Paramètres métier ----
    $settings = [
        'commission_menage'    => '15',
        'commission_pressing'  => '15',
        'commission_livreur'   => '15',
        'commission_vtc'       => '10',
        'commission_immobilier'=> '10',
        'commission_cars'      => '0',
        'cars_service_fee'     => '400',
        'sponsor_price'        => '5000',
        'vtc_base'             => '500',
        'vtc_per_km'           => '220',
        'vtc_min'              => '1000',
        'road_factor'          => '1.3',
        'livreur_base'         => '800',
        'livreur_per_km'       => '80',
        'livreur_min'          => '1000',
        'market_yango'         => '1.18',
        'market_uber'          => '1.25',
        'market_indrive'       => '1.08',
        'market_taxi'          => '1.12',
        'support_phone'        => '+225 07 00 00 00 00',
        'support_email'        => 'contact@chaptarif.ci',
        'maintenance'          => '0',
    ];
    foreach ($settings as $k => $v) q('INSERT INTO settings (key, value) VALUES (?, ?)', [$k, $v]);

    // ---- Super administrateur initial ----
    $email = strtolower((string) env('SUPERADMIN_EMAIL', 'superadmin@chaptarif.ci'));
    $pass  = (string) env('SUPERADMIN_PASSWORD', 'ChapTarif@2026');
    insert('users', [
        'role' => 'super_admin', 'name' => 'Super Administrateur', 'email' => $email,
        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
        'must_change_password' => env('SUPERADMIN_PASSWORD') ? 0 : 1,
        'active' => 1, 'created_at' => $now,
    ]);

    // ---- Zones / quartiers d'Abidjan (coordonnées approximatives) ----
    $zones = [
        ['Plateau', 'Plateau', 5.3230, -4.0197], ['Cocody Centre', 'Cocody', 5.3480, -3.9870],
        ['Cocody St-Jean', 'Cocody', 5.3380, -4.0000], ['Cocody Ambassades', 'Cocody', 5.3345, -3.9930],
        ['Cocody Angré', 'Cocody', 5.3920, -3.9870], ['Deux-Plateaux', 'Cocody', 5.3700, -3.9990],
        ['Riviera 2', 'Cocody', 5.3570, -3.9650], ['Riviera Palmeraie', 'Cocody', 5.3720, -3.9420],
        ['Adjamé', 'Adjamé', 5.3576, -4.0270], ['Yopougon Siporex', 'Yopougon', 5.3390, -4.0830],
        ['Yopougon Niangon', 'Yopougon', 5.3310, -4.1100], ['Treichville', 'Treichville', 5.2960, -4.0050],
        ['Marcory Zone 4', 'Marcory', 5.2940, -3.9780], ['Marcory Résidentiel', 'Marcory', 5.3030, -3.9820],
        ['Koumassi', 'Koumassi', 5.2980, -3.9480], ['Port-Bouët', 'Port-Bouët', 5.2560, -3.9260],
        ['Abobo', 'Abobo', 5.4240, -4.0200], ['Attécoubé', 'Attécoubé', 5.3350, -4.0410],
        ['Bingerville', 'Bingerville', 5.3550, -3.8850], ['Songon', 'Songon', 5.3130, -4.2560],
        ['Anyama', 'Anyama', 5.4940, -4.0520], ['Grand-Bassam', 'Grand-Bassam', 5.2110, -3.7390],
    ];
    foreach ($zones as [$n, $c, $la, $lo]) insert('zones', ['name' => $n, 'commune' => $c, 'lat' => $la, 'lng' => $lo, 'active' => 1]);

    // ---- Prestataires (démonstration) ----
    $sp = date('Y-m-d', strtotime('+25 days'));
    $prov = [
        ['menage', 'Awa K.', 'Cocody', 'Aide ménagère expérimentée, 6 ans. Repassage soigné.', 4.9, 132, $sp],
        ['menage', 'Mariam T.', 'Cocody', 'Spécialiste grand nettoyage & vitres.', 4.8, 87, null],
        ['menage', 'Fatou D.', 'Marcory', 'Ménage régulier, appartements & bureaux.', 4.7, 64, null],
        ['menage', 'Adjoua B.', 'Yopougon', 'Ménage, cuisine familiale et repassage.', 4.8, 51, null],
        ['pressing', 'Pressing Le Lys', 'Cocody', 'Collecte à domicile 7j/7, retour 24h.', 4.8, 410, $sp],
        ['pressing', 'Clean Express CI', 'Marcory', 'Lavage pro, détachage, costumes & bazin.', 4.6, 205, null],
        ['pressing', 'Pressing Étoile', 'Yopougon', 'Tarifs à la pièce, repassage vapeur.', 4.5, 150, null],
        ['livreur', 'Ibrahim S.', 'Cocody', 'Moto 125 cc, top-case sécurisé.', 4.9, 620, $sp],
        ['livreur', 'Moussa C.', 'Plateau', 'Plis administratifs & colis urgents.', 4.7, 380, null],
        ['livreur', 'Yao A.', 'Yopougon', 'Courses marché & livraisons express.', 4.6, 240, null],
        ['vtc', 'Kouassi M.', 'Cocody', 'Toyota Corolla climatisée.', 4.9, 1240, $sp],
        ['vtc', 'Serge B.', 'Marcory', 'Hyundai Elantra climatisée.', 4.8, 860, null],
        ['vtc', 'Didier O.', 'Yopougon', 'Kia Rio climatisée.', 4.7, 540, null],
    ];
    foreach ($prov as [$u, $n, $c, $b, $r, $m, $s]) {
        insert('providers', [
            'universe' => $u, 'name' => $n, 'phone' => '+22507' . random_int(10000000, 99999999), 'commune' => $c,
            'bio' => $b, 'rating' => $r, 'missions' => $m, 'kyc_status' => 'verified', 'sponsored_until' => $s,
            'payout_method' => 'wave', 'payout_number' => '+22507' . random_int(10000000, 99999999),
            'vehicle' => $u === 'vtc' ? $b : null, 'source' => 'admin', 'active' => 1, 'created_at' => $now,
        ]);
    }

    // ---- Formules ----
    $offers = [
        ['menage', 'Séance 4h — Studio / 2 pièces', 'Ménage complet, sols, cuisine, sanitaires', 5000, 'séance', 1],
        ['menage', 'Séance 4h — 3 / 4 pièces', 'Ménage complet d\'un appartement familial', 7000, 'séance', 0],
        ['menage', 'Forfait grand nettoyage', 'Vitres, placards, cuisine en profondeur', 6000, 'forfait', 0],
        ['menage', 'Repassage 3h', 'Linge repassé et plié chez vous', 3500, 'séance', 0],
        ['pressing', 'Formule Domicile', '5 chemises + 2 pantalons, collecte & retour', 4500, 'forfait', 1],
        ['pressing', 'Pack Famille 15 pièces', 'Linge mixte lavé, repassé, plié', 9000, 'forfait', 0],
        ['pressing', 'Costume complet', 'Nettoyage à sec veste + pantalon', 2500, 'pièce', 0],
        ['pressing', 'Grand boubou / Bazin', 'Lavage délicat & amidonnage', 2000, 'pièce', 0],
        ['pressing', 'Draps & housse (2 places)', 'Lavage & repassage', 1500, 'pièce', 0],
    ];
    foreach ($offers as $i => [$u, $t, $s, $p, $un, $pop]) {
        insert('offers', ['universe' => $u, 'title' => $t, 'subtitle' => $s, 'price' => $p, 'unit' => $un, 'popular' => $pop, 'active' => 1, 'sort' => $i]);
    }

    // ---- Départs Cars ----
    $trips = [
        ['UTB', 'VIP Climatisé', 'Abidjan', 'Yamoussoukro', '08:30', 'Gare Adjamé', '2h30', 5000],
        ['SBTA', 'Standard', 'Abidjan', 'Yamoussoukro', '09:00', 'Gare Yopougon', '2h45', 3500],
        ['AVS', 'Climatisé', 'Abidjan', 'Yamoussoukro', '14:00', 'Gare Adjamé', '2h40', 4000],
        ['UTB', 'VIP Climatisé', 'Abidjan', 'Bouaké', '07:00', 'Gare Adjamé', '5h00', 7000],
        ['CTE', 'Standard', 'Abidjan', 'Bouaké', '10:30', 'Gare Adjamé', '5h30', 6000],
        ['UTB', 'VIP Climatisé', 'Abidjan', 'Korhogo', '06:30', 'Gare Adjamé', '9h00', 12000],
        ['AVS', 'Climatisé', 'Abidjan', 'San Pedro', '08:00', 'Gare Yopougon', '6h00', 8000],
        ['SBTA', 'Standard', 'Abidjan', 'Daloa', '09:30', 'Gare Adjamé', '6h00', 7000],
        ['UTB', 'VIP Climatisé', 'Abidjan', 'Man', '07:30', 'Gare Adjamé', '8h00', 9000],
        ['UTB', 'VIP Climatisé', 'Yamoussoukro', 'Abidjan', '15:00', 'Gare Yamoussoukro', '2h30', 5000],
        ['UTB', 'VIP Climatisé', 'Bouaké', 'Abidjan', '13:00', 'Gare Bouaké', '5h00', 7000],
    ];
    foreach ($trips as [$c, $cl, $f, $t, $d, $s, $du, $p]) {
        insert('trips', ['company' => $c, 'class' => $cl, 'from_city' => $f, 'to_city' => $t, 'depart_time' => $d, 'station' => $s, 'duration' => $du, 'price' => $p, 'seats_total' => 70, 'active' => 1]);
    }

    // ---- Biens immobiliers ----
    $props = [
        ['Studio VIP — Cocody Angré 8e Tranche', 'Cocody', 'Angré 8e Tranche', 'Studio', 25000, 150000, 2,
            'Wifi haut débit,Smart TV,Climatisation,Groupe électrogène,Sécurité 24/7', ['univers-immobilier', 'immo-2']],
        ['Villa avec piscine — Riviera Palmeraie', 'Cocody', 'Riviera Palmeraie', 'Villa', 85000, 520000, 6,
            'Piscine,Wifi,Climatisation,Parking,Gardien,Groupe électrogène', ['immo-3', 'immo-2']],
        ['Appartement 2 chambres — Marcory Zone 4', 'Marcory', 'Zone 4', 'Appartement', 40000, 250000, 4,
            'Wifi,Climatisation,Cuisine équipée,Parking,Sécurité 24/7', ['immo-4', 'immo-2']],
        ['Chambre cosy — Deux-Plateaux Vallon', 'Cocody', 'Deux-Plateaux', 'Chambre', 18000, 110000, 2,
            'Wifi,Climatisation,Smart TV,Eau chaude', ['immo-2', 'univers-immobilier']],
    ];
    foreach ($props as [$t, $c, $qr, $ty, $pn, $pw, $cap, $am, $imgs]) {
        insert('properties', [
            'title' => $t, 'commune' => $c, 'quartier' => $qr, 'type' => $ty,
            'description' => "Logement meublé vérifié physiquement par l'équipe ChapTarif. Photos réelles, check-in avec remise des clés en main propre. Le paiement reste bloqué sous séquestre jusqu'à votre arrivée.",
            'price_night' => $pn, 'price_week' => $pw, 'capacity' => $cap, 'amenities' => $am,
            'images' => json_encode(array_map(fn($i) => CLD_BASE . "chaptarif/$i.jpg", $imgs)),
            'owner_name' => 'Bailleur certifié', 'owner_phone' => '', 'certified' => 1, 'active' => 1, 'created_at' => $now,
        ]);
    }
}
