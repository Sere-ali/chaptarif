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

/**
 * Envoi du code OTP par WhatsApp (WhatsApp Business Cloud API de Meta).
 * Variables d'environnement : WHATSAPP_TOKEN, WHATSAPP_PHONE_ID, WHATSAPP_TEMPLATE (modèle « authentification » approuvé),
 * WHATSAPP_LANG (défaut fr).
 */
function whatsapp_configured(): bool
{
    return env('WHATSAPP_TOKEN') && env('WHATSAPP_PHONE_ID') && env('WHATSAPP_TEMPLATE');
}

function whatsapp_send_code(string $to, string $code): bool
{
    if (!whatsapp_configured()) return false;
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => ltrim($to, '+'),
        'type' => 'template',
        'template' => [
            'name' => env('WHATSAPP_TEMPLATE'),
            'language' => ['code' => env('WHATSAPP_LANG', 'fr')],
            'components' => [
                ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ],
        ],
    ];
    $ch = curl_init('https://graph.facebook.com/v20.0/' . rawurlencode((string) env('WHATSAPP_PHONE_ID')) . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . env('WHATSAPP_TOKEN')],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http < 200 || $http >= 300) error_log('[WhatsApp OTP échec] HTTP ' . $http . ' ' . substr((string) $res, 0, 300));
    return $http >= 200 && $http < 300;
}
