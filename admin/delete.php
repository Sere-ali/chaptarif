<?php
// Suppression définitive depuis les listes de l'admin — réservée au Super Administrateur.
$me = require_super();
if (!is_post()) redirect('/admin');
$type = (string) ($_POST['type'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$back = (string) ($_POST['back'] ?? '/admin');
if (!str_starts_with($back, '/admin') || str_starts_with($back, '//')) $back = '/admin';
$live = "status IN ('EN_ATTENTE_PAIEMENT','BLOQUE','VALIDE','EN_LITIGE')";
$used = fn (string $sql, array $p) => (int) val($sql, $p) > 0;
$stop = function (string $msg) use ($back) { flash('error', $msg); redirect($back); };
$label = '';

switch ($type) {
    case 'provider':
        $p = one('SELECT * FROM providers WHERE id = ?', [$id]) ?: $stop('Prestataire introuvable.');
        if ($used("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND $live", [$id])) $stop('Ce prestataire a des réservations en cours : suspendez-le plutôt que de le supprimer.');
        q('DELETE FROM visit_requests WHERE provider_id = ?', [$id]);
        q('DELETE FROM provider_services WHERE provider_id = ?', [$id]);
        q("UPDATE trips SET active = 0, provider_id = NULL WHERE provider_id = ?", [$id]);
        q('DELETE FROM providers WHERE id = ?', [$id]);
        $label = 'Prestataire « ' . $p['name'] . ' »';
        break;
    case 'listing':
        $a = one('SELECT * FROM provider_services WHERE id = ?', [$id]) ?: $stop('Annonce introuvable.');
        if ($used("SELECT COUNT(*) FROM bookings WHERE item_type = 'listing' AND item_id = ? AND $live", [$id])) $stop('Cette annonce a des réservations en cours : désactivez-la plutôt.');
        q('DELETE FROM visit_requests WHERE service_id = ?', [$id]);
        q('DELETE FROM provider_services WHERE id = ?', [$id]);
        $label = 'Annonce « ' . $a['title'] . ' »';
        break;
    case 'client':
        $u = one("SELECT * FROM users WHERE id = ? AND role = 'client'", [$id]) ?: $stop('Client introuvable.');
        if ($used('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$id])) $stop('Ce client a un historique de réservations (obligation comptable) : suspendez-le plutôt.');
        q('DELETE FROM chat_messages WHERE user_id = ?', [$id]);
        q('DELETE FROM visit_requests WHERE user_id = ?', [$id]);
        q('DELETE FROM users WHERE id = ?', [$id]);
        $label = 'Client « ' . ($u['name'] ?: $u['phone']) . ' »';
        break;
    case 'message':
        q('DELETE FROM contact_messages WHERE id = ?', [$id]);
        $label = 'Message #' . $id;
        break;
    case 'chat':
        q('DELETE FROM chat_messages WHERE user_id = ?', [$id]);
        $label = 'Conversation #' . $id;
        break;
    case 'trip':
        $t = one('SELECT * FROM trips WHERE id = ?', [$id]) ?: $stop('Trajet introuvable.');
        if ($used("SELECT COUNT(*) FROM bookings WHERE item_type = 'trip' AND item_id = ? AND $live", [$id])) $stop('Ce trajet a des billets vendus : désactivez-le plutôt.');
        q('DELETE FROM trips WHERE id = ?', [$id]);
        $label = 'Trajet ' . $t['from_city'] . ' → ' . $t['to_city'];
        break;
    case 'offer':
        $o = one('SELECT * FROM offers WHERE id = ?', [$id]) ?: $stop('Formule introuvable.');
        if ($used("SELECT COUNT(*) FROM bookings WHERE item_type = 'offer' AND item_id = ?", [$id])) $stop('Cette formule apparaît dans des réservations : désactivez-la plutôt.');
        q('DELETE FROM offers WHERE id = ?', [$id]);
        $label = 'Formule « ' . $o['title'] . ' »';
        break;
    case 'zone':
        q('DELETE FROM zones WHERE id = ?', [$id]);
        $label = 'Quartier #' . $id;
        break;
    case 'property':
        $p = one('SELECT * FROM properties WHERE id = ?', [$id]) ?: $stop('Logement introuvable.');
        if ($used("SELECT COUNT(*) FROM bookings WHERE item_type = 'property' AND item_id = ? AND $live", [$id])) $stop('Ce logement a des réservations en cours : masquez-le plutôt.');
        q('DELETE FROM properties WHERE id = ?', [$id]);
        $label = 'Logement « ' . $p['title'] . ' »';
        break;
    default:
        $stop('Suppression non autorisée.');
}
audit('suppression.' . $type, $label . ' #' . $id);
flash('success', $label . ' supprimé(e).');
redirect($back);
