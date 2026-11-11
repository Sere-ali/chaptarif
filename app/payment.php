<?php
/**
 * Passerelle de paiement Mobile Money.
 *
 * PAYMENT_MODE=manuel     → le client envoie lui-même l'argent sur votre numéro Mobile Money
 *   (Wave, Orange Money, MTN MoMo, Moov), puis un administrateur valide la réception
 *   dans le back-office (bouton « Marquer payé »). Aucune API externe requise.
 * PAYMENT_MODE=simulation → simulateur intégré (tests, démonstrations, aucun argent réel)
 * PAYMENT_MODE=paydunya   → PayDunya (Wave, Orange Money, MTN MoMo, Moov, cartes)
 *   Variables : PAYDUNYA_MASTER_KEY, PAYDUNYA_PRIVATE_KEY, PAYDUNYA_TOKEN, APP_URL
 *   (clés « test_ » → environnement de test PayDunya automatiquement)
 * PAYMENT_MODE=cinetpay   → CinetPay Checkout v2 (Wave, Orange Money, MTN MoMo, Moov)
 *   Variables : CINETPAY_APIKEY, CINETPAY_SITE_ID, APP_URL
 */

function payment_mode(): string
{
    $m = (string) env('PAYMENT_MODE', 'simulation');
    // Exigence absolue : en production, jamais de paiement « simulé » validé par le navigateur.
    if ($m === 'simulation' && env('APP_ENV') === 'production') return 'manuel';
    return $m;
}

function payment_methods(): array
{
    $m = [
        'wave'   => ['Wave', '#1DC4FF', 'Paiement instantané via l\'app Wave'],
        'orange' => ['Orange Money', '#FF7900', 'Validation par code secret #144#'],
        'mtn'    => ['MTN MoMo', '#FFCB05', 'Validation sur votre téléphone MTN'],
        'moov'   => ['Moov Money', '#0066B3', 'Validation sur votre téléphone Moov'],
        'djamo'  => ['Djamo', '#1C1C5E', 'Paiement avec votre carte Djamo'],
        'carte'  => ['Carte bancaire', '#334155', 'Visa ou Mastercard, paiement sécurisé'],
    ];
    // Carte bancaire et Djamo masqués en attendant le RCCM (compte PayDunya entreprise ChapTarif).
    // Pour les réactiver : réglage « pay_carte_djamo » = 1 (ou retirer ce bloc).
    if (setting('pay_carte_djamo', '0') !== '1') unset($m['carte'], $m['djamo']);
    if (payment_mode() === 'manuel') unset($m['carte']);
    return $m;
}

/** Logo d'un moyen de paiement : fichier public/assets/img/pay/<clé>.(svg|png|webp|jpg) s'il existe, sinon null. */
function pay_logo(string $k): ?string
{
    foreach (['svg', 'png', 'webp', 'jpg'] as $ext) {
        if (is_file(ROOT . '/public/assets/img/pay/' . $k . '.' . $ext)) return '/assets/img/pay/' . $k . '.' . $ext;
    }
    return null;
}

/** Numéros de réception (mode manuel), configurables dans Admin › Paramètres. */
function payment_numbers(): array
{
    return [
        'wave'   => (string) setting('pay_wave_number', '0100354093'),
        'orange' => (string) setting('pay_orange_number', ''),
        'mtn'    => (string) setting('pay_mtn_number', ''),
        'moov'   => (string) setting('pay_moov_number', ''),
        'djamo'  => (string) setting('pay_djamo_number', ''),
    ];
}

function paydunya_base(): string
{
    return str_starts_with((string) env('PAYDUNYA_PRIVATE_KEY'), 'test_')
        ? 'https://app.paydunya.com/sandbox-api/v1/' : 'https://app.paydunya.com/api/v1/';
}

function paydunya_headers(): array
{
    return ['Content-Type: application/json',
        'PAYDUNYA-MASTER-KEY: ' . env('PAYDUNYA_MASTER_KEY'),
        'PAYDUNYA-PRIVATE-KEY: ' . env('PAYDUNYA_PRIVATE_KEY'),
        'PAYDUNYA-TOKEN: ' . env('PAYDUNYA_TOKEN')];
}

function paydunya_call(string $path, ?array $payload = null, int $timeout = 20): array
{
    $ch = curl_init(paydunya_base() . $path);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTPHEADER => paydunya_headers()];
    if ($payload !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($payload); }
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}

/** Démarre un paiement et retourne l'URL vers laquelle rediriger le client. */
function payment_init(array $b, array $user): array
{
    if (payment_mode() === 'cinetpay') {
        $base = APP_URL ?: ((IS_HTTPS ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $amount = (int) (ceil($b['total'] / 5) * 5); // CinetPay : multiple de 5
        $payload = [
            'apikey' => env('CINETPAY_APIKEY'), 'site_id' => env('CINETPAY_SITE_ID'),
            'transaction_id' => $b['ref'], 'amount' => $amount, 'currency' => 'XOF',
            'description' => 'ChapTarif ' . $b['ref'], 'channels' => 'MOBILE_MONEY',
            'notify_url' => $base . '/webhook/cinetpay', 'return_url' => $base . '/paiement/retour?ref=' . urlencode($b['ref']),
            'customer_phone_number' => $user['phone'] ?? '', 'customer_name' => $user['name'] ?: 'Client', 'customer_surname' => 'ChapTarif',
            'lang' => 'fr', 'metadata' => (string) $b['id'],
        ];
        $res = http_json('https://api-checkout.cinetpay.com/v2/payment', $payload);
        if (($res['code'] ?? '') === '201' && !empty($res['data']['payment_url'])) {
            q('UPDATE bookings SET payment_ref = ? WHERE id = ?', [$res['data']['payment_token'] ?? '', $b['id']]);
            return ['ok' => true, 'url' => $res['data']['payment_url']];
        }
        error_log('CinetPay init error: ' . json_encode($res));
        return ['ok' => false, 'error' => 'La passerelle de paiement est indisponible. Réessayez dans un instant.'];
    }
    if (payment_mode() === 'paydunya') {
        $base = APP_URL ?: ((IS_HTTPS ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $ret = $base . '/paiement/retour?ref=' . urlencode($b['ref']);
        $inv = [
            'invoice' => ['total_amount' => (int) $b['total'], 'description' => 'ChapTarif ' . $b['ref']],
            'store' => ['name' => 'ChapTarif', 'website_url' => $base, 'logo_url' => $base . '/assets/img/icon-512.png'],
            'custom_data' => ['ref' => $b['ref']],
            'actions' => ['cancel_url' => $ret, 'return_url' => $ret, 'callback_url' => $base . '/webhook/paydunya'],
        ];
        // Carte / Djamo : la page sécurisée n'affiche que le moyen choisi par le client.
        $ch = ['carte' => 'card', 'djamo' => 'djamo-ci'][$b['payment_method']] ?? null;
        if ($ch) $inv['invoice']['channels'] = [$ch];
        $res = paydunya_call('checkout-invoice/create', $inv);
        if ($ch && ($res['response_code'] ?? '') !== '00') { // nom de canal refusé : page avec tous les moyens
            unset($inv['invoice']['channels']);
            $res = paydunya_call('checkout-invoice/create', $inv);
        }
        if (($res['response_code'] ?? '') === '00' && !empty($res['response_text']) && !empty($res['token'])) {
            q('UPDATE bookings SET payment_ref = ? WHERE id = ?', [$res['token'], $b['id']]);
            // Carte bancaire / Djamo : page de paiement sécurisée PayDunya (saisie de la carte).
            // Carte / Djamo (page sécurisée PayDunya) uniquement si ces moyens sont réactivés ; sinon tout se passe sur ChapTarif.
            if (in_array($b['payment_method'], ['carte', 'djamo'], true) && isset(payment_methods()[$b['payment_method']])) return ['ok' => true, 'url' => (string) $res['response_text']];
            return ['ok' => true, 'url' => '/paiement/mobile?ref=' . urlencode($b['ref'])];
        }
        error_log('PayDunya init error: ' . json_encode($res));
        return ['ok' => false, 'error' => 'La passerelle de paiement est indisponible. Réessayez dans un instant.'];
    }
    if (payment_mode() === 'manuel') {
        return ['ok' => true, 'url' => '/paiement/manuel?ref=' . urlencode($b['ref'])];
    }
    return ['ok' => true, 'url' => '/paiement/simulateur?ref=' . urlencode($b['ref'])];
}

/** Vérifie auprès de la passerelle que la transaction est réellement payée. */
function payment_verify(array $b): bool
{
    if (payment_mode() === 'paydunya') {
        $tok = (string) ($b['payment_ref'] ?? '');
        if ($tok === '' || $tok === 'EN_ATTENTE_VALIDATION') return false;
        $res = paydunya_call('checkout-invoice/confirm/' . rawurlencode($tok));
        $paid = ($res['status'] ?? '') === 'completed';
        // La facture payée doit bien être celle de cette réservation (référence) et en francs CFA.
        $rref = (string) ($res['custom_data']['ref'] ?? $b['ref']);
        if ($paid && $rref !== (string) $b['ref']) {
            security_event('paiement.reference_incoherente', 'critical', $b['ref'] . ' : facture PayDunya liée à ' . $rref);
            return false;
        }
        $cur = strtoupper((string) ($res['invoice']['currency'] ?? 'XOF'));
        if ($paid && !in_array($cur, ['XOF', 'FCFA', 'CFA'], true)) {
            security_event('paiement.devise_incoherente', 'critical', $b['ref'] . ' : devise ' . $cur);
            return false;
        }
        if ($paid && (int) ($res['invoice']['total_amount'] ?? 0) < (int) $b['total']) {
            security_event('paiement.montant_incoherent', 'critical', $b['ref'] . ' : payé ' . (int) ($res['invoice']['total_amount'] ?? 0) . ' F au lieu de ' . (int) $b['total'] . ' F');
            return false;
        }
        return $paid;
    }
    if (payment_mode() !== 'cinetpay') return false;
    $res = http_json('https://api-checkout.cinetpay.com/v2/payment/check', [
        'apikey' => env('CINETPAY_APIKEY'), 'site_id' => env('CINETPAY_SITE_ID'), 'transaction_id' => $b['ref'],
    ]);
    return ($res['data']['status'] ?? '') === 'ACCEPTED' && (int) ($res['data']['amount'] ?? 0) >= (int) $b['total'];
}

/**
 * Reversement au prestataire (Payout).
 * En simulation : marqué EFFECTUÉ. En production : marqué A_VIRER (traité depuis
 * Finances, ou branchez ici l'API Transfert CinetPay / Wave Business).
 */
function payout_send(?array $provider, int $amount, string $ref): array
{
    if (payment_mode() === 'simulation') {
        return ['status' => 'EFFECTUE', 'reference' => 'SIM-PO-' . strtoupper(bin2hex(random_bytes(3)))];
    }
    return ['status' => 'A_VIRER', 'reference' => 'PO-' . $ref];
}

function payment_refund(array $b): array
{
    if (payment_mode() === 'simulation') {
        return ['status' => 'EFFECTUE', 'reference' => 'SIM-RF-' . strtoupper(bin2hex(random_bytes(3)))];
    }
    return ['status' => 'A_REMBOURSER', 'reference' => 'RF-' . $b['ref']];
}

function http_json(string $url, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $r, true) ?: [];
}


/**
 * Rapprochement : compare les réservations, le grand livre et (pour PayDunya) les sommes
 * réellement reçues. Retourne la liste des anomalies détectées.
 */
function reconcile_run(bool $checkGateway = true): array
{
    $an = [];
    $add = function (string $ref, string $msg) use (&$an) { $an[] = ['ref' => $ref, 'msg' => $msg]; };
    $paidSt = "('BLOQUE','VALIDE','EN_LITIGE','REMBOURSE')";
    // 1. Chaque réservation payée a exactement un encaissement du bon montant.
    foreach (all("SELECT b.id, b.ref, b.total, COUNT(t.id) AS n, COALESCE(SUM(t.amount),0) AS s FROM bookings b
                  LEFT JOIN transactions t ON t.booking_id = b.id AND t.type = 'ENCAISSEMENT'
                  WHERE b.status IN $paidSt GROUP BY b.id, b.ref, b.total") as $r) {
        if ((int) $r['n'] === 0) $add($r['ref'], 'Réservation payée sans encaissement au grand livre.');
        elseif ((int) $r['n'] > 1) $add($r['ref'], 'Encaissement enregistré ' . $r['n'] . ' fois (double paiement ?).');
        elseif ((int) $r['s'] !== (int) $r['total']) $add($r['ref'], 'Montant encaissé ' . $r['s'] . ' F ≠ montant de la réservation ' . $r['total'] . ' F.');
    }
    // 2. Aucun encaissement sur une réservation non payée.
    foreach (all("SELECT b.ref, b.status FROM transactions t JOIN bookings b ON b.id = t.booking_id
                  WHERE t.type = 'ENCAISSEMENT' AND b.status NOT IN $paidSt") as $r) {
        $add($r['ref'], 'Encaissement présent alors que la réservation est « ' . $r['status'] . ' ».');
    }
    // 3. Réservations validées : un seul reversement ; remboursées : un seul remboursement.
    foreach (all("SELECT b.ref, b.status, SUM(CASE WHEN t.type = 'REVERSEMENT' THEN 1 ELSE 0 END) AS rv, SUM(CASE WHEN t.type = 'REMBOURSEMENT' THEN 1 ELSE 0 END) AS rb
                  FROM bookings b LEFT JOIN transactions t ON t.booking_id = b.id WHERE b.status IN ('VALIDE','REMBOURSE') GROUP BY b.id, b.ref, b.status") as $r) {
        if ($r['status'] === 'VALIDE' && (int) $r['rv'] !== 1) $add($r['ref'], (int) $r['rv'] . ' reversement(s) au prestataire au lieu de 1.');
        if ($r['status'] === 'REMBOURSE' && (int) $r['rb'] !== 1) $add($r['ref'], (int) $r['rb'] . ' remboursement(s) au client au lieu de 1.');
        if ($r['status'] === 'REMBOURSE' && (int) $r['rv'] > 0) $add($r['ref'], 'Remboursé ET reversé au prestataire.');
    }
    // 4. Sommes réellement reçues : revérification auprès de PayDunya (paiements des 14 derniers jours).
    $checked = 0;
    if ($checkGateway && payment_mode() === 'paydunya') {
        foreach (all("SELECT * FROM bookings WHERE status IN $paidSt AND payment_ref LIKE 'PD-%' AND paid_at > ? ORDER BY id DESC LIMIT 100", [date('Y-m-d H:i:s', time() - 14 * 86400)]) as $b) {
            $tok = substr((string) $b['payment_ref'], 3);
            if ($tok === '' || $tok === $b['ref']) continue; // anciens paiements sans jeton
            $res = paydunya_call('checkout-invoice/confirm/' . rawurlencode($tok));
            $checked++;
            if (($res['status'] ?? '') !== 'completed') $add($b['ref'], 'PayDunya ne confirme pas ce paiement (statut : ' . ($res['status'] ?? 'inconnu') . ').');
            elseif ((int) ($res['invoice']['total_amount'] ?? 0) < (int) $b['total']) $add($b['ref'], 'PayDunya a reçu ' . (int) $res['invoice']['total_amount'] . ' F au lieu de ' . $b['total'] . ' F.');
        }
    }
    $escrow = (int) val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status IN ('BLOQUE','EN_LITIGE')");
    setting_set('reconcile_last', now());
    setting_set('reconcile_result', json_encode(['anomalies' => $an, 'checked' => $checked, 'escrow' => $escrow], JSON_UNESCAPED_UNICODE));
    if ($an) security_event('rapprochement.anomalies', 'critical', count($an) . ' anomalie(s) : ' . $an[0]['ref'] . ' — ' . $an[0]['msg']);
    return ['anomalies' => $an, 'checked' => $checked, 'escrow' => $escrow];
}
