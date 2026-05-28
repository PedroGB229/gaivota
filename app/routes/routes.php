<?php

declare(strict_types=1);

use app\middleware\Middleware;

$app->get('/', app\controller\Home::class . ':home')->add(Middleware::web());
$app->get('/home', app\controller\Home::class . ':home')->add(Middleware::web());
$app->get('/login', app\controller\Login::class . ':login')->add(Middleware::web());

// ── Autenticação ─────────────────────────────────────────────
$app->post('/auth/login',              app\controller\Login::class . ':authenticate');
$app->post('/auth/google',             app\controller\Login::class . ':google');
$app->get('/auth/google/callback',     app\controller\Login::class . ':googleCallback');
$app->post('/auth/google/set-password', app\controller\Login::class . ':setGooglePassword'); // << NOVO
$app->post('/auth/preregister',        app\controller\Login::class . ':preRegister');
$app->get('/auth/logout',              app\controller\Login::class . ':logout')->add(Middleware::web());

// ── Usuários ─────────────────────────────────────────────────
$app->group('/usuario', function (Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/insert', app\controller\User::class . ':insert');
});

// ── Clientes ─────────────────────────────────────────────────
$app->group('/cliente', function (Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/lista', app\controller\Customer::class . ':list')->add(Middleware::web());;
    $group->get('/detalhes/{id}', app\controller\Customer::class . ':details')->add(Middleware::web());;
    $group->get('/detalhes', app\controller\Customer::class . ':details')->add(Middleware::web());
    $group->post('/insert', app\controller\Customer::class . ':insert')->add(Middleware::web());;
    $group->post('/update', app\controller\Customer::class . ':update')->add(Middleware::web());
    $group->post('/delete', app\controller\Customer::class . ':delete')->add(Middleware::web());
    $group->post('/listingdata', app\controller\Customer::class . ':listingdata')->add(Middleware::web());
});

$app->group('/users', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\User::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\User::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\User::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\User::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\User::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\User::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\User::class . ':listingdata')->add(Middleware::api());
});

$app->group('/supplier', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Supplier::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Supplier::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Supplier::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Supplier::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Supplier::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Supplier::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Supplier::class . ':listingdata')->add(Middleware::api());
});

$app->group('/company', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Company::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Company::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Company::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Company::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Company::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Company::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Company::class . ':listingdata')->add(Middleware::api());
});

$app->group('/product', function (\Slim\Routing\RouteCollectorProxy $group) {
    # Páginas HTML protegidas
    $group->get('/lista',         app\controller\Product::class . ':list')->add(Middleware::web());
    $group->get('/detalhes/{id}', app\controller\Product::class . ':details')->add(Middleware::web());
    $group->get('/detalhes',      app\controller\Product::class . ':details')->add(Middleware::web());
    # Endpoints JSON protegidos
    $group->post('/insert',      app\controller\Product::class . ':insert')->add(Middleware::api());
    $group->post('/update',      app\controller\Product::class . ':update')->add(Middleware::api());
    $group->post('/delete',      app\controller\Product::class . ':delete')->add(Middleware::api());
    $group->post('/listingdata', app\controller\Product::class . ':listingdata')->add(Middleware::api());
});