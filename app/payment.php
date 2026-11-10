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
    return env('PAYMENT_MODE', 'simulation');
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
    // Paiement manuel : la carte bancaire n'est possible qu'avec une passerelle en ligne.
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
        $res = paydunya_call('checkout-invoice/create', [
            'invoice' => ['total_amount' => (int) $b['total'], 'description' => 'ChapTarif ' . $b['ref']],
            'store' => ['name' => 'ChapTarif', 'website_url' => $base],
            'custom_data' => ['ref' => $b['ref']],
            'actions' => ['cancel_url' => $ret, 'return_url' => $ret, 'callback_url' => $base . '/webhook/paydunya'],
        ]);
        if (($res['response_code'] ?? '') === '00' && !empty($res['response_text']) && !empty($res['token'])) {
            q('UPDATE bookings SET payment_ref = ? WHERE id = ?', [$res['token'], $b['id']]);
            // Carte bancaire / Djamo : page de paiement sécurisée PayDunya (saisie de la carte).
            if (in_array($b['payment_method'], ['carte', 'djamo'], true)) return ['ok' => true, 'url' => (string) $res['response_text']];
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
        return ($res['status'] ?? '') === 'completed' && (int) ($res['invoice']['total_amount'] ?? 0) >= (int) $b['total'];
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
