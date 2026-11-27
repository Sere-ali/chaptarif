<?php
/**
 * Envoi d'e-mails depuis l'adresse de contact (SMTP, sans dépendance).
 * Variables d'environnement : SMTP_HOST, SMTP_PORT (465 = SSL, 587 = STARTTLS), SMTP_USER, SMTP_PASS,
 * MAIL_FROM (défaut : SMTP_USER), MAIL_FROM_NAME (défaut : ChapTarif).
 */
function mail_configured(): bool
{
    return trim((string) env('SMTP_HOST', '')) !== '' && trim((string) env('SMTP_USER', '')) !== '' && (string) env('SMTP_PASS', '') !== '';
}

function mail_from(): string
{
    return trim((string) env('MAIL_FROM', '')) ?: trim((string) env('SMTP_USER', ''));
}

/** @return array{0:bool,1:string} [succès, message d'erreur] */
function send_mail(string $to, string $subject, string $body): array
{
    if (!mail_configured()) return [false, 'Adresse e-mail non configurée.'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Adresse e-mail du destinataire invalide.'];
    $host = trim((string) env('SMTP_HOST')); $port = (int) env('SMTP_PORT', 465);
    $user = trim((string) env('SMTP_USER')); $pass = (string) env('SMTP_PASS');
    $from = mail_from(); $fromName = (string) env('MAIL_FROM_NAME', 'ChapTarif');
    $clean = static fn (string $s): string => trim(preg_replace('/[\r\n]+/', ' ', $s));
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, 'Connexion au serveur e-mail impossible.'];
    stream_set_timeout($fp, 15);
    $last = '';
    $read = static function () use ($fp, &$last): array {
        $code = 0; $txt = '';
        while (($l = fgets($fp, 1024)) !== false) { $txt .= $l; $code = (int) substr($l, 0, 3); if (strlen($l) < 4 || $l[3] !== '-') break; }
        $last = trim($txt);
        return [$code, $txt];
    };
    $cmd = static function (string $c, array $ok) use ($fp, $read): bool { fwrite($fp, $c . "\r\n"); [$code] = $read(); return in_array($code, $ok, true); };
    try {
        [$c] = $read(); if ($c !== 220) throw new RuntimeException('Serveur e-mail indisponible.');
        $ehlo = 'chaptarif.com';
        if (!$cmd("EHLO $ehlo", [250])) throw new RuntimeException('EHLO refusé.');
        if ($port !== 465 && $port !== 25) {
            if (!$cmd('STARTTLS', [220])) throw new RuntimeException('STARTTLS refusé.');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Connexion sécurisée impossible.');
            if (!$cmd("EHLO $ehlo", [250])) throw new RuntimeException('EHLO refusé.');
        }
        if (!$cmd('AUTH LOGIN', [334]) || !$cmd(base64_encode($user), [334]) || !$cmd(base64_encode($pass), [235])) throw new RuntimeException('Identifiants e-mail refusés.');
        if (!$cmd("MAIL FROM:<$from>", [250])) throw new RuntimeException('Expéditeur refusé.');
        if (!$cmd("RCPT TO:<$to>", [250, 251])) throw new RuntimeException('Destinataire refusé.');
        if (!$cmd('DATA', [354])) throw new RuntimeException('Envoi refusé.');
        $h = 'Date: ' . date('r') . "\r\n"
            . 'From: =?UTF-8?B?' . base64_encode($clean($fromName)) . "?= <$from>\r\n"
            . "To: <$to>\r\nReply-To: <$from>\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . "?=\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@chaptarif.com>' . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n");
        fwrite($fp, $h . "\r\n.\r\n");
        [$code] = $read(); if ($code !== 250) throw new RuntimeException('Le serveur a refusé le message.');
        $cmd('QUIT', [221]);
        fclose($fp);
        return [true, ''];
    } catch (Throwable $e) {
        @fclose($fp);
        $detail = $last !== '' ? ' — réponse du serveur : ' . mb_substr($last, 0, 160) : '';
        error_log('send_mail: ' . $e->getMessage() . $detail);
        return [false, $e->getMessage() . $detail];
    }
}
