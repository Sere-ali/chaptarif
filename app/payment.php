<?php
/**
 * Passerelle de paiement Mobile Money.
 *
 * PAYMENT_MODE=manuel     → le client envoie lui-même l'argent sur votre numéro Mobile Money
 *   (Wave, Orange Money, MTN MoMo, Moov), puis un administrateur valide la réception
 *   dans le back-office (bouton « Marquer payé »). Aucune API externe requise.
 * PAYMENT_MODE=simulation → simulateur intégré (tests, démonstrations, aucun argent réel)
 * PAYMENT_MODE=cinetpay   → CinetPay Checkout v2 (Wave, Orange Money, MTN MoMo, Moov)
 *   Variables : CINETPAY_APIKEY, CINETPAY_SITE_ID, APP_URL
 */

function payment_mode(): string
{
    return env('PAYMENT_MODE', 'simulation');
}

function payment_methods(): array
{
    return [
        'wave'   => ['Wave', '#1DC4FF', 'Paiement instantané via l\'app Wave'],
        'orange' => ['Orange Money', '#FF7900', 'Validation par code secret #144#'],
        'mtn'    => ['MTN MoMo', '#FFCB05', 'Validation sur votre téléphone MTN'],
        'moov'   => ['Moov Money', '#0066B3', 'Validation sur votre téléphone Moov'],
    ];
}

/** Numéros de réception (mode manuel), configurables dans Admin › Paramètres. */
function payment_numbers(): array
{
    return [
        'wave'   => (string) setting('pay_wave_number', '0100354093'),
        'orange' => (string) setting('pay_orange_number', ''),
        'mtn'    => (string) setting('pay_mtn_number', ''),
        'moov'   => (string) setting('pay_moov_number', ''),
    ];
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
    if (payment_mode() === 'manuel') {
        return ['ok' => true, 'url' => '/paiement/manuel?ref=' . urlencode($b['ref'])];
    }
    return ['ok' => true, 'url' => '/paiement/simulateur?ref=' . urlencode($b['ref'])];
}

/** Vérifie auprès de la passerelle que la transaction est réellement payée. */
function payment_verify(array $b): bool
{
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
