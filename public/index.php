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
    '/vtc'                  => 'pages/vtc.php',
    '/menage'               => 'pages/services.php',
    '/pressing'             => 'pages/services.php',
    '/location-car'         => 'pages/services.php',
    '/location-camion'      => 'pages/services.php',
    '/chauffeurs'           => 'pages/services.php',
    '/covoiturage'          => 'pages/covoiturage.php',
    '/livreur'              => 'pages/livreur.php',
    '/cars'                 => 'pages/cars.php',
    '/immobilier'           => 'pages/immobilier.php',
    '/reserver'             => 'pages/checkout.php',
    '/paiement'             => 'pages/pay.php',
    '/paiement/simulateur'  => 'pages/pay_simulator.php',
    '/paiement/manuel'      => 'pages/pay_manual.php',
    '/paiement/retour'      => 'pages/pay_return.php',
    '/webhook/cinetpay'     => 'pages/webhook_cinetpay.php',
    '/connexion'            => 'pages/login.php',
    '/deconnexion'          => 'pages/logout.php',
    '/compte'               => 'pages/account.php',
    '/billet'               => 'pages/ticket.php',
    '/devenir-prestataire'  => 'pages/become_provider.php',
    '/prestataire/valider'  => 'pages/provider_validate.php',
    '/comment-ca-marche'    => 'pages/how.php',
    '/contact'              => 'pages/contact.php',
    '/mentions-legales'     => 'pages/legal.php',
    '/api/estimate'         => 'pages/api_estimate.php',
    '/sante'                => 'pages/health.php',

    // Back-office
    '/admin'                => 'admin/dashboard.php',
    '/admin/login'          => 'admin/login.php',
    '/admin/logout'         => 'admin/logout.php',
    '/admin/mot-de-passe'   => 'admin/password.php',
    '/admin/reservations'   => 'admin/bookings.php',
    '/admin/reservation'    => 'admin/booking.php',
    '/admin/litiges'        => 'admin/bookings.php',
    '/admin/prestataires'   => 'admin/providers.php',
    '/admin/prestataire'    => 'admin/provider_edit.php',
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

if ($path !== '/webhook/cinetpay') csrf_check();

require ROOT . '/' . $routes[$path];
