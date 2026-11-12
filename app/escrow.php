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
            $ref = ref_gen(in_array($quote['universe'], ['cars', 'covoiturage'], true) ? 'CT-BUS' : 'CT');
        } while (val('SELECT 1 FROM bookings WHERE ref = ?', [$ref]));
        $id = insert('bookings', [
            'ref' => $ref, 'user_id' => $user['id'], 'universe' => $quote['universe'],
            'item_type' => $quote['item_type'], 'item_id' => $quote['item_id'], 'provider_id' => $quote['provider_id'],
            'title' => $quote['title'], 'details' => json_encode($quote['details'], JSON_UNESCAPED_UNICODE),
            'service_date' => $quote['service_date'], 'amount' => $quote['amount'], 'service_fee' => $quote['service_fee'],
            'total' => $quote['total'], 'discount' => (int) ($quote['discount'] ?? 0), 'discount_code' => $quote['discount_code'] ?? null, 'commission' => $quote['commission'], 'provider_amount' => $quote['provider_amount'],
            'payment_method' => $method, 'status' => 'EN_ATTENTE_PAIEMENT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return one('SELECT * FROM bookings WHERE id = ?', [$id]);
    });
}

/** Paiement confirmé par la passerelle → fonds bloqués. Idempotent. */
function booking_mark_paid(array $b, string $paymentRef): array
{
    if ($b['status'] !== 'EN_ATTENTE_PAIEMENT') return $b;
    $done = false;
    tx(function () use ($b, $paymentRef, &$done) {
        // Anti double paiement : on « réserve » d'abord la transition de statut ; si une autre requête
        // (webhook + retour navigateur simultanés) l'a déjà faite, on s'arrête sans rien encaisser deux fois.
        $st = q("UPDATE bookings SET status = 'BLOQUE', updated_at = ? WHERE id = ? AND status = 'EN_ATTENTE_PAIEMENT'", [now(), $b['id']]);
        if ($st->rowCount() !== 1) return;
        $done = true;
        $seat = null;
        if (in_array($b['universe'], ['cars', 'covoiturage'], true)) {
            $taken = (int) val("SELECT COALESCE(MAX(seat_no), 0) FROM bookings WHERE universe = ? AND item_id = ? AND service_date = ? AND status IN ('BLOQUE','VALIDE')", [$b['universe'], $b['item_id'], $b['service_date']]);
            $n = (int) (json_decode((string) $b['details'], true)['passengers'] ?? 1);
            $seat = $taken + 1;
            if ($n > 1) {
                $d = json_decode((string) $b['details'], true);
                $d['seats'] = range($seat, $seat + $n - 1);
                q('UPDATE bookings SET details = ? WHERE id = ?', [json_encode($d, JSON_UNESCAPED_UNICODE), $b['id']]);
            }
        }
        q("UPDATE bookings SET paid_at = ?, payment_ref = ?, release_code = ?, seat_no = ?, updated_at = ? WHERE id = ?",
            [now(), $paymentRef, (string) random_int(1000, 9999), $seat, now(), $b['id']]);
        if (!empty($b['discount_code'])) q('UPDATE discount_codes SET uses = uses + 1 WHERE code = ?', [$b['discount_code']]);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'ENCAISSEMENT', 'amount' => $b['total'],
            'method' => $b['payment_method'], 'status' => 'BLOQUE', 'reference' => $paymentRef,
            'note' => 'Fonds reçus et bloqués sur le compte séquestre', 'created_at' => now(),
        ]);
    });
    $b = one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
    if (!$done) return $b;
    $u = one('SELECT phone FROM users WHERE id = ?', [$b['user_id']]);
    if ($u && $u['phone']) {
        sms_send($u['phone'], "ChapTarif : paiement de {$b['total']} F reçu pour {$b['ref']}. Fonds sécurisés jusqu'à la fin du service. Code de validation à remettre au prestataire : {$b['release_code']}");
    }
    // Le prestataire est prévenu de la nouvelle réservation (sans les coordonnées du client).
    if (!empty($b['provider_id']) && ($pv = one('SELECT phone FROM providers WHERE id = ?', [$b['provider_id']])) && !empty($pv['phone'])) {
        $d = json_decode((string) ($b['details'] ?? ''), true) ?: [];
        $when = $b['service_date'] ? ' le ' . date('d/m', strtotime((string) $b['service_date'])) . (!empty($d['heure']) ? ' à ' . $d['heure'] : '') : '';
        $where = !empty($d['commune']) ? ', ' . $d['commune'] : (!empty($d['adresse']) ? ', ' . mb_substr((string) $d['adresse'], 0, 40) : '');
        sms_send($pv['phone'], 'ChapTarif : nouvelle réservation ' . $b['ref'] . ' — ' . mb_substr((string) $b['title'], 0, 50) . $when . $where . '. Montant pour vous : ' . (int) $b['provider_amount'] . ' F. Détails dans votre espace prestataire.');
    }
    return $b;
}

/** Déblocage : commission conservée, reste reversé au prestataire. */
function booking_release(array $b, string $by = 'client', string $note = ''): bool
{
    if (!in_array($b['status'], ['BLOQUE', 'EN_LITIGE'], true)) return false;
    $done = false;
    tx(function () use ($b, $by, $note, &$done) {
        // Anti double versement : la transition n'a lieu qu'une fois, même en cas de double clic.
        $st = q("UPDATE bookings SET status = 'VALIDE', validated_at = ?, updated_at = ?, admin_note = COALESCE(admin_note, '') || ? WHERE id = ? AND status IN ('BLOQUE','EN_LITIGE')",
            [now(), now(), $note ? "\n[" . now() . "] $note" : '', $b['id']]);
        if ($st->rowCount() !== 1) return;
        $done = true;
        $rev = (int) $b['commission'] + (int) $b['service_fee'] - (int) ($b['discount'] ?? 0);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'COMMISSION', 'amount' => $rev,
            'method' => 'interne', 'status' => 'ACQUIS', 'reference' => $b['ref'],
            'note' => 'Commission ChapTarif' . ((int) $b['service_fee'] ? ' + frais de service' : '') . ((int) ($b['discount'] ?? 0) ? ' − réduction ' . $b['discount_code'] : ''), 'created_at' => now(),
        ]);
        $prov = $b['provider_id'] ? one('SELECT * FROM providers WHERE id = ?', [$b['provider_id']]) : null;
        $payout = payout_send($prov, (int) $b['provider_amount'], $b['ref']);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'REVERSEMENT', 'amount' => (int) $b['provider_amount'],
            'method' => $prov['payout_method'] ?? (in_array($b['universe'], ['cars', 'covoiturage', 'immobilier'], true) ? 'virement partenaire' : '—'),
            'status' => $payout['status'], 'reference' => $payout['reference'],
            'note' => 'Reversement ' . ($prov['name'] ?? 'partenaire') . " (validé par $by)", 'created_at' => now(),
        ]);
        if ($b['provider_id']) q('UPDATE providers SET missions = missions + 1 WHERE id = ?', [$b['provider_id']]);
    });
    return $done;
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
    $done = false;
    tx(function () use ($b, $note, &$done) {
        $st = q("UPDATE bookings SET status = 'REMBOURSE', updated_at = ?, admin_note = COALESCE(admin_note, '') || ? WHERE id = ? AND status IN ('BLOQUE','EN_LITIGE')", [now(), $note ? "\n[" . now() . "] $note" : '', $b['id']]);
        if ($st->rowCount() !== 1) return;
        $done = true;
        $r = payment_refund($b);
        insert('transactions', [
            'booking_id' => $b['id'], 'provider_id' => $b['provider_id'], 'type' => 'REMBOURSEMENT', 'amount' => (int) $b['total'],
            'method' => $b['payment_method'], 'status' => $r['status'], 'reference' => $r['reference'],
            'note' => 'Remboursement client', 'created_at' => now(),
        ]);
    });
    return $done;
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
