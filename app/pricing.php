<?php
/**
 * Moteur de tarification — tous les prix sont RECALCULÉS côté serveur,
 * jamais repris du navigateur.
 */

function round50(float $x): int
{
    return (int) (ceil($x / 50) * 50);
}

function zone(int $id): ?array
{
    return one('SELECT * FROM zones WHERE id = ? AND active = 1', [$id]);
}

function distance_km(array $a, array $b): float
{
    $R = 6371;
    $dLat = deg2rad($b['lat'] - $a['lat']);
    $dLng = deg2rad($b['lng'] - $a['lng']);
    $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;
    $straight = 2 * $R * asin(min(1, sqrt($h)));
    return max(1.5, $straight * (float) setting('road_factor', 1.3));
}

function commission_rate(string $universe): float
{
    return (float) setting('commission_' . $universe, 15);
}

function immo_price(array $p, int $nights): int
{
    if ($nights >= 7 && (int) $p['price_week'] > 0) {
        return intdiv($nights, 7) * (int) $p['price_week'] + ($nights % 7) * (int) $p['price_night'];
    }
    return $nights * (int) $p['price_night'];
}

function best_provider(string $universe, ?string $commune = null): ?array
{
    $today = date('Y-m-d');
    $sql = "SELECT * FROM providers WHERE universe = ? AND active = 1 AND kyc_status = 'verified'";
    $order = " ORDER BY CASE WHEN sponsored_until >= ? THEN 0 ELSE 1 END, rating DESC LIMIT 1";
    if ($commune) {
        $p = one($sql . ' AND commune = ?' . $order, [$universe, $commune, $today]);
        if ($p) return $p;
    }
    return one($sql . $order, [$universe, $today]);
}

/** Choisit la formule la plus adaptée à la demande (type de véhicule, nombre de places, taille du camion). */
function auto_offer(string $universe, array $in): ?array
{
    $offers = all('SELECT * FROM offers WHERE universe = ? AND active = 1 ORDER BY sort, price', [$universe]);
    if (!$offers) return null;
    $want = match ($universe) {
        'location_vehicule' => ($in['vtype'] ?? '') === '4x4' ? ['4x4', 'suv'] : [],
        'location_car' => [(string) (int) ($in['seats'] ?? 0)],
        'location_camion' => [($in['ttype'] ?? '') === 'gros' ? 'gros' : 'moyen'],
        default => [],
    };
    foreach ($offers as $o) {
        $hay = mb_strtolower($o['title'] . ' ' . $o['subtitle']);
        foreach ($want as $w) if ($w !== '' && $w !== '0' && str_contains($hay, $w)) return $o;
    }
    if ($universe === 'location_vehicule' && ($in['vtype'] ?? '') !== '4x4') {
        foreach ($offers as $o) if (!str_contains(mb_strtolower($o['title']), '4x4')) return $o;
    }
    return $offers[0];
}

/** Noms des passagers saisis avant paiement (cars, covoiturage). */
function passenger_names_in(array $in, int $n): array
{
    $raw = $in['passenger_names'] ?? [];
    if (!is_array($raw)) return [];
    $names = [];
    foreach (array_slice(array_values($raw), 0, $n) as $v) $names[] = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $v)), 0, 60);
    return $names;
}

function valid_future_date(?string $d, int $maxDays = 180): bool
{
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    return $d >= date('Y-m-d') && $d <= date('Y-m-d', strtotime("+$maxDays days"));
}

/**
 * Construit un devis à partir des champs du formulaire.
 * @return array{ok:bool, error?:string, quote?:array}
 */
function build_quote(string $universe, array $in): array
{
    $fail = fn(string $m) => ['ok' => false, 'error' => $m];
    $q = ['universe' => $universe, 'service_fee' => 0, 'provider_id' => null, 'item_type' => null, 'item_id' => null, 'details' => [], 'summary' => []];

    switch ($universe) {
        case 'menage':
        case 'pressing':
        case 'coiffeuse':
        case 'maquilleuse':
        case 'onglerie': {
            $prov = one("SELECT * FROM providers WHERE id = ? AND universe = ? AND active = 1 AND kyc_status = 'verified'", [(int) ($in['provider'] ?? 0), $universe]);
            $offer = null; $isListing = false;
            // Prix fixé par le prestataire : le client choisit une annonce validée.
            if (!empty($in['listing'])) {
                $ad = one("SELECT ps.* FROM provider_services ps JOIN providers p ON p.id = ps.provider_id WHERE ps.id = ? AND p.universe = ? AND p.active = 1 AND p.kyc_status = 'verified' AND ps.active = 1 AND ps.status = 'approved' AND ps.price IS NOT NULL AND ps.price > 0", [(int) $in['listing'], $universe]);
                if ($ad) {
                    $prov = one('SELECT * FROM providers WHERE id = ?', [(int) $ad['provider_id']]);
                    $offer = ['id' => (int) $ad['id'], 'title' => $ad['title'], 'price' => (int) $ad['price'], 'unit' => (string) ($ad['unit'] ?? '')];
                    $isListing = true;
                }
            }
            if (!$prov) $prov = best_provider($universe);
            if (!$offer) $offer = one('SELECT * FROM offers WHERE id = ? AND universe = ? AND active = 1', [(int) ($in['offer'] ?? 0), $universe]);
            $zone = zone((int) ($in['zone'] ?? 0));
            $addr0 = trim((string) ($in['address'] ?? ''));
            $lat0 = $in['lat'] ?? null; $lng0 = $in['lng'] ?? null;
            $gps0 = (is_numeric($lat0) && is_numeric($lng0) && abs((float) $lat0) <= 90 && abs((float) $lng0) <= 180) ? round((float) $lat0, 5) . ', ' . round((float) $lng0, 5) : '';
            if (!$prov) return $fail('Ce service ouvre bientôt. Écrivez-nous via l\'onglet Chat.');
            if (!$offer) return $fail('Choisissez une prestation.');
            if (!$zone && $addr0 === '' && $gps0 === '') return $fail('Indiquez votre lieu (activez la localisation ou saisissez votre adresse).');
            if (!valid_future_date($in['date'] ?? null, 60)) return $fail('Choisissez une date valide.');
            $time = preg_match('/^\d{2}:\d{2}$/', $in['time'] ?? '') ? $in['time'] : '09:00';
            $qty = $offer['unit'] === 'pièce' ? max(1, min(50, (int) ($in['qty'] ?? 1))) : 1;
            $amount = (int) $offer['price'] * $qty;
            $q += ['title' => $offer['title'] . ($qty > 1 ? " × $qty" : '') . ' · ' . $prov['name'], 'amount' => $amount, 'service_date' => $in['date']];
            $q['provider_id'] = (int) $prov['id'];
            $q['item_type'] = $isListing ? 'listing' : 'offer';
            $q['item_id'] = (int) $offer['id'];
            $q['details'] = ['offer' => $offer['title'], 'qty' => $qty, 'zone' => $zone['name'] ?? '', 'gps' => $gps0, 'time' => $time, 'address' => mb_substr((string) ($in['address'] ?? ''), 0, 200), 'provider' => $prov['name']];
            $q['summary'] = [['Prestataire', $prov['name']], ['Formule', $offer['title'] . ($qty > 1 ? " × $qty" : '')], ['Rendez-vous', fmt_date($in['date']) . ' à ' . $time], ['Lieu', trim(($zone['name'] ?? '') . ($addr0 !== '' ? ($zone ? ' · ' : '') . mb_substr($addr0, 0, 200) : '') . ($gps0 !== '' && $addr0 === '' && !$zone ? 'Position partagée' : ''))]];
            // Garantie Dommage (option payante, disponible pour les services à domicile/matériel)
            $q['garantie_fee'] = 0;
            if (in_array($universe, ['menage', 'pressing'], true)) {
                // Frais de service ChapTarif (réglage admin, 0 par défaut), distincts de la garantie.
                $q['service_fee'] += (int) setting('home_service_fee', 0);
                if (!empty($in['garantie'])) {
                    $pct = $universe === 'menage' ? (float) setting('garantie_menage_pct', 10) : (float) setting('garantie_dommage_pct', 6.72);
                    $fee = round50($amount * $pct / 100);
                    $q['garantie_fee'] = $fee;
                    $q['service_fee'] += $fee;
                    $q['details']['garantie_dommage'] = true;
                    $q['details']['garantie_dommage_montant'] = $fee;
                }
            }
            break;
        }
        case 'location_vehicule':
        case 'location_car':
        case 'chauffeur':
        case 'location_camion': {
            // Le client choisit l'annonce d'un prestataire (prix fixé par lui) ; à défaut, attribution automatique.
            $prov = null; $offer = null; $isListing = false;
            if (!empty($in['listing'])) {
                $ad = one("SELECT ps.* FROM provider_services ps JOIN providers p ON p.id = ps.provider_id WHERE ps.id = ? AND p.universe = ? AND p.active = 1 AND p.kyc_status = 'verified' AND ps.active = 1 AND ps.status = 'approved' AND ps.price IS NOT NULL AND ps.price > 0", [(int) $in['listing'], $universe]);
                if ($ad) {
                    $prov = one('SELECT * FROM providers WHERE id = ?', [(int) $ad['provider_id']]);
                    $offer = ['id' => (int) $ad['id'], 'title' => $ad['title'], 'price' => (int) $ad['price'], 'unit' => (string) ($ad['unit'] ?? '')];
                    $isListing = true;
                } else {
                    return $fail('Cette annonce n\'est plus disponible. Choisissez-en une autre.');
                }
            }
            if (!$prov) $prov = !empty($in['provider'])
                ? one("SELECT * FROM providers WHERE id = ? AND universe = ? AND active = 1 AND kyc_status = 'verified'", [(int) $in['provider'], $universe])
                : best_provider($universe);
            if (!$offer) $offer = !empty($in['offer'])
                ? one('SELECT * FROM offers WHERE id = ? AND universe = ? AND active = 1', [(int) $in['offer'], $universe])
                : auto_offer($universe, $in);
            if (!$prov || !$offer) return $fail('Ce service ouvre bientôt dans votre zone. Écrivez-nous via l\'onglet Chat : nous vous trouvons un loueur.');
            $lat = $in['lat'] ?? ''; $lng = $in['lng'] ?? '';
            $geo0 = (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) ? round((float) $lat, 5) . ', ' . round((float) $lng, 5) : '';
            $txt = fn(string $k, int $max = 200) => trim(mb_substr((string) ($in[$k] ?? ''), 0, $max));
            $dt = function (string $k) use ($in) {
                $v = (string) ($in[$k] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) return null;
                $t = strtotime(str_replace('T', ' ', $v));
                if (!$t || $t < time() - 3600 || $t > time() + 86400 * 90) return null;
                return $t;
            };
            $fdt = fn(int $t) => fmt_date(date('Y-m-d', $t)) . ' à ' . date('H:i', $t);
            $dest = ($in['dest'] ?? '') === 'hors' ? 'Hors Abidjan' : 'Abidjan';
            $days = 1;
            $d = [];
            $s = [['Prestataire', $prov['name']], ['Formule', $offer['title']]];
            $date = null;

            if ($universe === 'location_vehicule') {
                $pickup = $txt('pickup');
                $t1 = $dt('start'); $t2 = $dt('end');
                if ($pickup === '') return $fail('Indiquez la commune ou le lieu de prise en charge.');
                if (!$t1) return $fail('Indiquez une date et une heure de début valides.');
                if (!$t2 || $t2 <= $t1) return $fail('La date de retour doit être après le début.');
                $days = max(1, (int) ceil(($t2 - $t1) / 86400));
                if ($days > 60) return $fail('Location limitée à 60 jours.');
                $vt = ($in['vtype'] ?? '') === '4x4' ? '4×4' : 'Autre véhicule';
                $dr = ($in['driver'] ?? '') === 'avec' ? 'Avec chauffeur' : 'Sans chauffeur';
                $d = ['pickup' => $pickup, 'start' => date('d/m/Y H:i', $t1), 'end' => date('d/m/Y H:i', $t2), 'days' => $days, 'vehicle_type' => $vt, 'driver_option' => $dr];
                $s[] = ['Prise en charge', $pickup];
                $s[] = ['Début', $fdt($t1)];
                $s[] = ['Retour', $fdt($t2)];
                $s[] = ['Véhicule', $vt . ' · ' . $dr];
                $date = date('Y-m-d', $t1);
            } elseif ($universe === 'location_car') {
                $from = $txt('from_place');
                if ($from === '') return $fail('Indiquez le lieu de départ.');
                $dd = (string) ($in['date'] ?? '');
                if (!valid_future_date($dd, 90)) return $fail('Choisissez une date valide.');
                $days = (int) ($in['days'] ?? 1);
                if ($days < 1 || $days > 7) return $fail('La durée doit être de 1 à 7 jours.');
                $seats = (int) ($in['seats'] ?? 0);
                if (!in_array($seats, [30, 70], true)) return $fail('Choisissez 30 ou 70 places.');
                $motifs = ['mariage' => 'Mariage', 'excursion' => 'Excursion', 'sortie_scolaire' => 'Sortie scolaire', 'evenement' => 'Événement', 'autres' => 'Autres'];
                $mk = (string) ($in['motif'] ?? '');
                if (!isset($motifs[$mk])) return $fail('Indiquez le motif de la location.');
                $d = ['from_place' => $from, 'destination' => $dest, 'days' => $days, 'seats' => $seats, 'motif' => $motifs[$mk]];
                $s[] = ['Départ', $from];
                $s[] = ['Destination', $dest];
                $s[] = ['Date', fmt_date($dd)];
                $s[] = ['Durée', $days . ' jour' . ($days > 1 ? 's' : '')];
                $s[] = ['Places', (string) $seats];
                $s[] = ['Motif', $motifs[$mk]];
                $date = $dd;
            } elseif ($universe === 'chauffeur') {
                $pickup = $txt('pickup');
                if ($pickup === '') return $fail('Indiquez l\'adresse de prise en charge.');
                $t1 = $dt('start');
                if (!$t1) return $fail('Indiquez une date et une heure d\'arrivée valides.');
                $du = (string) ($in['duree'] ?? '');
                $map = ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, 'semaine' => 7, 'mois' => 30];
                if (!isset($map[$du])) return $fail('Choisissez la durée souhaitée.');
                $days = $map[$du];
                $dl = $du === 'semaine' ? '1 semaine' : ($du === 'mois' ? '1 mois' : $du . ' jour' . ((int) $du > 1 ? 's' : ''));
                $geo = $geo0;
                $gb = ['manuelle' => 'Manuelle', 'automatique' => 'Automatique'][(string) ($in['gearbox'] ?? '')] ?? 'Manuelle ou automatique';
                $d = ['pickup' => $pickup, 'gps' => $geo, 'destination' => $dest, 'arrival' => date('d/m/Y H:i', $t1), 'duration' => $dl, 'days' => $days, 'gearbox' => $gb];
                $s[] = ['Prise en charge', $pickup . ($geo ? ' (position partagée)' : '')];
                $s[] = ['Destination', $dest];
                $s[] = ['Arrivée du chauffeur', $fdt($t1)];
                $s[] = ['Boîte de vitesse', $gb];
                $s[] = ['Durée', $dl];
                $date = date('Y-m-d', $t1);
            } else { // location_camion
                $load = $txt('load'); $deliver = $txt('deliver');
                if ($load === '' || $deliver === '') return $fail('Indiquez l\'adresse de chargement et de livraison.');
                $t1 = $dt('start');
                if (!$t1) return $fail('Indiquez une date et une heure valides.');
                $hand = ($in['handling'] ?? '') === 'oui' ? 'Oui' : 'Non';
                $tt = ($in['ttype'] ?? '') === 'gros' ? 'Gros camion' : 'Camion moyen';
                $d = ['load' => $load, 'deliver' => $deliver, 'when' => date('d/m/Y H:i', $t1), 'handling' => $hand, 'truck_type' => $tt];
                $s[] = ['Chargement', $load];
                $s[] = ['Livraison', $deliver];
                $s[] = ['Date et heure', $fdt($t1)];
                $s[] = ['Camion', $tt . ' · Manutention : ' . $hand];
                $date = date('Y-m-d', $t1);
            }

            if ($geo0 !== '' && $universe !== 'chauffeur') { $d['gps'] = $geo0; $s[] = ['Position partagée', $geo0]; }
            $mult = $offer['unit'] === 'jour' ? $days : 1;
            $amount = (int) $offer['price'] * $mult;
            $q += ['title' => $offer['title'] . ($mult > 1 ? " × $mult j" : '') . ' · ' . $prov['name'], 'amount' => $amount, 'service_date' => $date];
            $q['provider_id'] = (int) $prov['id'];
            $q['item_type'] = $isListing ? 'listing' : 'offer';
            $q['item_id'] = (int) $offer['id'];
            $q['details'] = ['offer' => $offer['title'], 'provider' => $prov['name']] + $d;
            $q['summary'] = $s;
            if ($mult > 1) $q['summary'][] = ['Tarif', fcfa($offer['price']) . ' × ' . $mult . ' jours'];
            break;
        }
        case 'cars': {
            $trip = one('SELECT * FROM trips WHERE id = ? AND active = 1', [(int) ($in['trip'] ?? 0)]);
            if (!$trip) return $fail('Départ introuvable.');
            $date = $in['date'] ?? date('Y-m-d');
            if (!valid_future_date($date, 60)) return $fail('Date de voyage invalide.');
            if ($date === date('Y-m-d') && $trip['depart_time'] <= date('H:i')) return $fail('Ce départ est déjà passé aujourd\'hui. Choisissez une autre date.');
            $n = max(1, min(5, (int) ($in['passengers'] ?? 1)));
            $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe = 'cars' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$trip['id'], $date]);
            if ($sold + $n > (int) $trip['seats_total']) return $fail('Plus assez de places sur ce départ.');
            $fee = (int) setting('cars_service_fee', 400);
            $q += ['title' => "{$trip['company']} {$trip['from_city']} → {$trip['to_city']} · {$trip['depart_time']}", 'amount' => (int) $trip['price'] * $n, 'service_date' => $date];
            $q['service_fee'] = $fee * $n;
            $q['item_type'] = 'trip';
            $q['item_id'] = (int) $trip['id'];
            $q['details'] = ['company' => $trip['company'], 'class' => $trip['class'], 'from' => $trip['from_city'], 'to' => $trip['to_city'], 'time' => $trip['depart_time'], 'station' => $trip['station'], 'duration' => $trip['duration'], 'passengers' => $n, 'passenger_name' => (passenger_names_in($in, $n)[0] ?? '') ?: mb_substr((string) ($in['passenger_name'] ?? ''), 0, 80), 'passenger_names' => passenger_names_in($in, $n)];
            $q['summary'] = [['Compagnie', $trip['company'] . ' · ' . $trip['class']], ['Trajet', "{$trip['from_city']} → {$trip['to_city']}"], ['Départ', fmt_date($date) . ' à ' . $trip['depart_time'] . ' · ' . $trip['station']], ['Passagers', (string) $n . (passenger_names_in($in, $n) ? ' : ' . implode(', ', passenger_names_in($in, $n)) : '')]];
            break;
        }
        case 'covoiturage': {
            $trip = one("SELECT * FROM trips WHERE id = ? AND active = 1 AND universe = 'covoiturage'", [(int) ($in['trip'] ?? 0)]);
            if (!$trip) return $fail('Trajet introuvable.');
            $date = $in['date'] ?? date('Y-m-d');
            if (!valid_future_date($date, 60)) return $fail('Date de voyage invalide.');
            if ($date === date('Y-m-d') && $trip['depart_time'] <= date('H:i')) return $fail('Ce départ est déjà passé aujourd\'hui. Choisissez une autre date.');
            $n = max(1, min(4, (int) ($in['passengers'] ?? 1)));
            $sold = (int) val("SELECT COUNT(*) FROM bookings WHERE universe = 'covoiturage' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$trip['id'], $date]);
            if ($sold + $n > (int) $trip['seats_total']) return $fail('Plus assez de places sur ce trajet.');
            $from = $trip['from_detail'] ?: $trip['from_city'];
            $to = $trip['to_detail'] ?: $trip['to_city'];
            $q += ['title' => "Covoiturage {$from} → {$to} · {$trip['depart_time']}", 'amount' => (int) $trip['price'] * $n, 'service_date' => $date];
            $q['provider_id'] = $trip['provider_id'] ? (int) $trip['provider_id'] : null;
            $q['item_type'] = 'trip';
            $q['item_id'] = (int) $trip['id'];
            $q['details'] = ['company' => $trip['company'], 'from' => $from, 'to' => $to, 'time' => $trip['depart_time'], 'station' => $trip['station'], 'duration' => $trip['duration'], 'passengers' => $n, 'passenger_name' => (passenger_names_in($in, $n)[0] ?? '') ?: mb_substr((string) ($in['passenger_name'] ?? ''), 0, 80), 'passenger_names' => passenger_names_in($in, $n)];
            $q['summary'] = [['Chauffeur', $trip['company']], ['Trajet', "{$from} → {$to}"], ['Départ', fmt_date($date) . ' à ' . $trip['depart_time']], ['Passagers', (string) $n . (passenger_names_in($in, $n) ? ' : ' . implode(', ', passenger_names_in($in, $n)) : '')]];
            break;
        }
        case 'immobilier': {
            $p = one('SELECT * FROM properties WHERE id = ? AND active = 1', [(int) ($in['property'] ?? 0)]);
            if (!$p) return $fail('Logement introuvable.');
            $ci = $in['checkin'] ?? '';
            $co = $in['checkout'] ?? '';
            if (!valid_future_date($ci, 365) || !valid_future_date($co, 400) || $co <= $ci) return $fail('Choisissez des dates d\'arrivée et de départ valides.');
            $nights = (int) ((strtotime($co) - strtotime($ci)) / 86400);
            if ($nights > 90) return $fail('Séjour limité à 90 nuits.');
            foreach (all("SELECT details FROM bookings WHERE universe = 'immobilier' AND item_id = ? AND status IN ('BLOQUE','VALIDE','EN_LITIGE') AND service_date < ?", [$p['id'], $co]) as $b) {
                $d = json_decode($b['details'], true);
                if (($d['checkin'] ?? '') < $co && ($d['checkout'] ?? '') > $ci) return $fail('Ces dates ne sont plus disponibles pour ce logement.');
            }
            $guests = max(1, min((int) $p['capacity'], (int) ($in['guests'] ?? 1)));
            $q += ['title' => $p['title'] . " · $nights nuit" . ($nights > 1 ? 's' : ''), 'amount' => immo_price($p, $nights), 'service_date' => $ci];
            $q['item_type'] = 'property';
            $q['item_id'] = (int) $p['id'];
            $q['details'] = ['property' => $p['title'], 'checkin' => $ci, 'checkout' => $co, 'nights' => $nights, 'guests' => $guests, 'commune' => $p['commune']];
            $q['summary'] = [['Logement', $p['title']], ['Arrivée', fmt_date($ci)], ['Départ', fmt_date($co)], ['Durée', "$nights nuit" . ($nights > 1 ? 's' : '') . " · $guests voyageur" . ($guests > 1 ? 's' : '')]];
            break;
        }
        default:
            return $fail('Univers inconnu.');
    }

    $rate = commission_rate($universe);
    $q['commission'] = (int) round($q['amount'] * $rate / 100);
    $q['provider_amount'] = $q['amount'] - $q['commission'];
    $q['total'] = $q['amount'] + $q['service_fee'];
    $q['created'] = time();
    return ['ok' => true, 'quote' => $q];
}
