<?php
/**
 * Envoi de SMS (OTP, confirmations).
 * Configurez SMS_API_URL + SMS_API_TOKEN pour brancher votre passerelle
 * (Orange SMS API, Twilio, Infobip, etc. via un petit proxy HTTP JSON).
 * Sans passerelle et avec OTP_DEMO=1, le code est affiché à l'écran (tests uniquement).
 */

function sms_demo_mode(): bool
{
    return env('OTP_DEMO', '1') === '1';
}

function sms_send(string $to, string $message): bool
{
    $url = env('SMS_API_URL');
    if (!$url) {
        error_log("[SMS non envoyé — aucune passerelle] $to : $message");
        return false;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . env('SMS_API_TOKEN', '')],
        CURLOPT_POSTFIELDS => json_encode(['to' => $to, 'from' => env('SMS_SENDER', 'ChapTarif'), 'message' => $message]),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
