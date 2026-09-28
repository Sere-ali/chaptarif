<?php
/**
 * Séquestre (escrow) : cycle de vie financier d'une réservation.
 *
 *  EN_ATTENTE_PAIEMENT → (paiement confirmé) → BLOQUE
 *  BLOQUE → (client confirme / code OTP prestataire / admin) → VALIDE  [commission + reversement]
 *  BLOQUE → (réclamation client) → EN_LITIGE → VALIDE ou REMBOURSE (arbitrage admin)
 *  BLOQUE → (admin) → REMBOURSE
 *  EN_ATTENTE_PAIEMENT → ANNULE
 */

function booking_create(array $quote, array $user, string $method): array
{
    return tx(function () use ($quote, $user, $method) {
        do {
            $ref = ref_gen($quote['universe'] === 'cars' ? 'CT-BUS' : 'CT');
        } while (val('SELECT 1 FROM bookings WHERE ref = ?', [$ref]));
        $id = insert('bookings', [
            'ref' => $ref, 'user_id' => $user['id'], 'universe' => $quote['universe'],
            'item_type' => $quote['item_type'], 'item_id' => $quote['item_id'], 'provider_id' => $quote['provider_id'],
            'title' => $quote['title'], 'details' => json_encode($quote['details'], JSON_UNESCAPED_UNICODE),
            'service_date' => $quote['service_date'], 'amount' => $quote['amount'], 'service_fee' => $quote['service_fee'],
            'total' => $quote['total'], 'commission' => $quote['commission'], 'provider_amount' => $quote['provider_amount'],
            'payment_method' => $method, 'status' => 'EN_ATTENTE_PAIEMENT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return one('SELECT * FROM bookings WHERE id = ?', [$id]);
    });
}

/** Paiement confirmé par la passerelle → fonds bloqués. Idempotent. */
function booking_mark_paid(array $b, string $paymentRef): array
{
    if ($b['status'] !== 'EN_ATTENTE_PAIEMENT') return $b;
    tx(function () use ($b, $paymentRef) {
        $seat = null;
        if ($b['universe'] === 'cars') {
            $taken = (int) val("SELECT COALESCE(MAX(seat_no), 0) FROM bookings WHERE universe = 'cars' AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$b['item_id'], $b['service_date']]);
            $n = (int) (json_decode((string) $b['details'], true)['passengers'] ?? 1);
            $seat = $taken + 1;
            if ($n > 1) {
                $d = json_decode((string) $b['details'], true);
                $d['seats'] = range($seat, $seat + $n - 1);
                q('UPDATE bookings SET details = ? WHERE id = ?', [json_encode($d, JSON_UNESCAPED_UNICODE), $b['id']]);
            }
        }
        q("UPDATE bookings SET status = 'BLOQUE', paid_at = ?, payment_ref = ?, release_code = ?, seat_no = ?, updated_at = ? WHERE id = ? AND status = 'EN_ATTENTE_PAIEMENT'",
            [now(), $paymentRef, (string) random_int(1000, 9999), $seat, now(), $b['id']]);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'ENCAISSEMENT', 'amount' => $b['total'],
            'method' => $b['payment_method'], 'status' => 'BLOQUE', 'reference' => $paymentRef,
            'note' => 'Fonds reçus et bloqués sur le compte séquestre', 'created_at' => now(),
        ]);
    });
    $b = one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
    $u = one('SELECT phone FROM users WHERE id = ?', [$b['user_id']]);
    if ($u && $u['phone']) {
        sms_send($u['phone'], "ChapTarif : paiement de {$b['total']} F reçu pour {$b['ref']}. Fonds sécurisés jusqu'à la fin du service. Code de validation à remettre au prestataire : {$b['release_code']}");
    }
    return $b;
}

/** Déblocage : commission conservée, reste reversé au prestataire. */
function booking_release(array $b, string $by = 'client', string $note = ''): bool
{
    if (!in_array($b['status'], ['BLOQUE', 'EN_LITIGE'], true)) return false;
    tx(function () use ($b, $by, $note) {
        q("UPDATE bookings SET status = 'VALIDE', validated_at = ?, updated_at = ?, admin_note = COALESCE(admin_note, '') || ? WHERE id = ?",
            [now(), now(), $note ? "\n[" . now() . "] $note" : '', $b['id']]);
        $rev = (int) $b['commission'] + (int) $b['service_fee'];
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'COMMISSION', 'amount' => $rev,
            'method' => 'interne', 'status' => 'ACQUIS', 'reference' => $b['ref'],
            'note' => 'Commission ChapTarif' . ((int) $b['service_fee'] ? ' + frais de service' : ''), 'created_at' => now(),
        ]);
        $prov = $b['provider_id'] ? one('SELECT * FROM providers WHERE id = ?', [$b['provider_id']]) : null;
        $payout = payout_send($prov, (int) $b['provider_amount'], $b['ref']);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'REVERSEMENT', 'amount' => (int) $b['provider_amount'],
            'method' => $prov['payout_method'] ?? ($b['universe'] === 'cars' || $b['universe'] === 'immobilier' ? 'virement partenaire' : '—'),
            'status' => $payout['status'], 'reference' => $payout['reference'],
            'note' => 'Reversement ' . ($prov['name'] ?? 'partenaire') . " (validé par $by)", 'created_at' => now(),
        ]);
        if ($b['provider_id']) q('UPDATE providers SET missions = missions + 1 WHERE id = ?', [$b['provider_id']]);
    });
    return true;
}

function booking_dispute(array $b, string $reason): bool
{
    if ($b['status'] !== 'BLOQUE') return false;
    q("UPDATE bookings SET status = 'EN_LITIGE', dispute_reason = ?, updated_at = ? WHERE id = ?", [mb_substr($reason, 0, 1000), now(), $b['id']]);
    return true;
}

function booking_refund(array $b, string $note = ''): bool
{
    if (!in_array($b['status'], ['BLOQUE', 'EN_LITIGE'], true)) return false;
    tx(function () use ($b, $note) {
        q("UPDATE bookings SET status = 'REMBOURSE', updated_at = ?, admin_note = COALESCE(admin_note, '') || ? WHERE id = ?", [now(), $note ? "\n[" . now() . "] $note" : '', $b['id']]);
        $r = payment_refund($b);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'REMBOURSEMENT', 'amount' => (int) $b['total'],
            'method' => $b['payment_method'], 'status' => $r['status'], 'reference' => $r['reference'],
            'note' => 'Remboursement client', 'created_at' => now(),
        ]);
    });
    return true;
}

function booking_cancel(array $b): bool
{
    if ($b['status'] !== 'EN_ATTENTE_PAIEMENT') return false;
    q("UPDATE bookings SET status = 'ANNULE', updated_at = ? WHERE id = ?", [now(), $b['id']]);
    return true;
}

// ---- E-billet QR --------------------------------------------------------------
function ticket_sig(string $ref): string
{
    return strtoupper(substr(hash_hmac('sha256', $ref, APP_KEY), 0, 10));
}

function ticket_payload(array $b): string
{
    return 'CHAPTARIF|' . $b['ref'] . '|' . ticket_sig($b['ref']);
}

function ticket_verify(string $payload): ?array
{
    $payload = trim($payload);
    if (preg_match('/^CHAPTARIF\|([A-Z0-9-]+)\|([A-Z0-9]{10})$/', $payload, $m)) {
        if (!hash_equals(ticket_sig($m[1]), $m[2])) return null;
        return one('SELECT * FROM bookings WHERE ref = ?', [$m[1]]);
    }
    // Saisie manuelle de la référence
    if (preg_match('/^CT(-BUS)?-[A-Z0-9]{6}$/', strtoupper($payload))) {
        return one('SELECT * FROM bookings WHERE ref = ?', [strtoupper($payload)]);
    }
    return null;
}
