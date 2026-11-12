<?php
/**
 * Contrôleur frontal ChapTarif.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = '/' . trim($path, '/');

$routes = [
    // Site public
    '/'                     => 'pages/home.php',
    '/menage'               => 'pages/services.php',
    '/pressing'             => 'pages/services.php',
    '/location-car'         => 'pages/services.php',
    '/location-camion'      => 'pages/services.php',
    '/location-vehicule'    => 'pages/services.php',
    '/chauffeur'            => 'pages/services.php',
    '/coiffeuse'            => 'pages/services.php',
    '/maquilleuse'          => 'pages/services.php',
    '/onglerie'             => 'pages/services.php',
    '/covoiturage'          => 'pages/covoiturage.php',
    '/cars'                 => 'pages/cars.php',
    '/transport'            => 'pages/domaine.php',
    '/beaute'               => 'pages/domaine.php',
    '/service-a-la-personne' => 'pages/domaine.php',
    '/immobilier'           => 'pages/immobilier.php',
    '/reserver'             => 'pages/checkout.php',
    '/reserver/code'        => 'pages/checkout_code.php',
    '/admin/codes'          => 'admin/codes.php',
    '/admin/supprimer'      => 'admin/delete.php',
    '/paiement'             => 'pages/pay.php',
    '/paiement/simulateur'  => 'pages/pay_simulator.php',
    '/paiement/manuel'      => 'pages/pay_manual.php',
    '/paiement/retour'      => 'pages/pay_return.php',
    '/paiement/mobile'      => 'pages/pay_mobile.php',
    '/webhook/cinetpay'     => 'pages/webhook_cinetpay.php',
    '/webhook/paydunya'     => 'pages/webhook_paydunya.php',
    '/connexion'            => 'pages/login.php',
    '/otp/envoyer'          => 'pages/otp_send.php',
    '/deconnexion'          => 'pages/logout.php',
    '/compte'               => 'pages/account.php',
    '/billet'               => 'pages/ticket.php',
    '/devenir-prestataire'  => 'pages/become_provider.php',
    '/espace-prestataire'   => 'pages/provider_space.php',
    '/chat'                 => 'pages/chat.php',
    '/admin/chat'           => 'admin/chat.php',
    '/profil-prestataire'   => 'pages/provider_public.php',
    '/admin/annonces'       => 'admin/listings.php',
    '/prestataire/valider'  => 'pages/provider_validate.php',
    '/comment-ca-marche'    => 'pages/how.php',
    '/garantie-dommage'     => 'pages/garantie_dommage.php',
    '/contact'              => 'pages/contact.php',
    '/devis-menage'         => 'pages/devis_menage.php',
    '/devis-maquillage'     => 'pages/devis_maquillage.php',
    '/mentions-legales'     => 'pages/legal.php',
    '/sante'                => 'pages/health.php',

    // Back-office
    '/admin'                => 'admin/dashboard.php',
    '/admin/login'          => 'admin/login.php',
    '/admin/logout'         => 'admin/logout.php',
    '/admin/mot-de-passe'   => 'admin/password.php',
    '/admin/2fa'            => 'admin/twofa.php',
    '/admin/2fa/activer'    => 'admin/twofa.php',
    '/admin/securite'       => 'admin/security.php',
    '/admin/taches'         => 'admin/tasks.php',
    '/admin/reservations'   => 'admin/bookings.php',
    '/admin/reservation'    => 'admin/booking.php',
    '/admin/litiges'        => 'admin/bookings.php',
    '/admin/candidatures'   => 'admin/candidatures.php',
    '/admin/prestataires'   => 'admin/providers.php',
    '/admin/prestataire'    => 'admin/provider_edit.php',
    '/admin/prestataire/voir' => 'admin/provider_view.php',
    '/admin/document'       => 'admin/document.php',
    '/admin/offres'         => 'admin/offers.php',
    '/admin/cars'           => 'admin/trips.php',
    '/admin/covoiturage'    => 'admin/covoiturage.php',
    '/admin/immobilier'     => 'admin/properties.php',
    '/admin/bien'           => 'admin/property_edit.php',
    '/admin/zones'          => 'admin/zones.php',
    '/admin/clients'        => 'admin/clients.php',
    '/admin/finances'       => 'admin/finances.php',
    '/admin/controle'       => 'admin/control.php',
    '/admin/messages'       => 'admin/messages.php',
    // Super admin uniquement
    '/admin/administrateurs'=> 'admin/admins.php',
    '/admin/parametres'     => 'admin/settings.php',
    '/admin/journal'        => 'admin/audit.php',
];

if (!isset($routes[$path])) not_found();

// Mode maintenance (le back-office reste accessible)
if (setting('maintenance') === '1' && !str_starts_with($path, '/admin') && !is_admin() && $path !== '/sante') {
    http_response_code(503);
    require ROOT . '/views/maintenance.php';
    exit;
}

if (!in_array($path, ['/webhook/cinetpay', '/webhook/paydunya'], true)) csrf_check();

require ROOT . '/' . $routes[$path];
