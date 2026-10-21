<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

$routes->get('health', 'Api\V1\HealthController::index');

$routes->post('auth/login', 'Api\V1\AuthController::login');

$routes->get(
    'me',
    'Api\V1\MeController::index',
    ['filter' => 'apiAuth']
);

$routes->get(
    'dashboard',
    'Api\V1\DashboardController::index',
    ['filter' => 'apiAuth']
);

$routes->post(
    'auth/logout',
    'Api\V1\AuthController::logout',
    ['filter' => 'apiAuth']
);

$routes->post(
    'auth/mfa/setup/method',
    'Api\V1\MfaController::selectSetupMethod'
);

$routes->post(
    'auth/mfa/otp/send',
    'Api\V1\MfaController::sendOtp'
);

$routes->post(
    'auth/mfa/otp/verify',
    'Api\V1\MfaController::verifyOtp'
);
$routes->get(
    'sites',
    'Api\V1\SiteController::index',
    ['filter' => 'apiAuth']
);

$routes->get(
    'sites/(:num)/locations',
    'Api\V1\LocationController::bySite/$1',
    ['filter' => 'apiAuth']
);
