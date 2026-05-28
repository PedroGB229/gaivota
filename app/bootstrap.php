<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Carrega as variáveis do .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// ── Sessão via Redis ────────────────────────────────────────────────────────
// DEVE vir ANTES de qualquer output e ANTES do Slim inicializar,
// caso contrário o PHP lança "headers already sent".
$redisHost    = $_ENV['REDIS_HOST']         ?? '127.0.0.1';
$redisPort    = $_ENV['REDIS_PORT']         ?? 6379;
$redisPass    = $_ENV['REDIS_PASSWORD']     ?? '';
$redisDb      = $_ENV['REDIS_DATABASE']     ?? 1;
$redisPrefix  = $_ENV['REDIS_PREFIX']       ?? 'SESS:';
$redisTimeout = $_ENV['REDIS_TIMEOUT']      ?? 2.5;

ini_set('session.save_handler', 'redis');
ini_set(
    'session.save_path',
    "tcp://{$redisHost}:{$redisPort}?auth={$redisPass}&database={$redisDb}&prefix={$redisPrefix}&timeout={$redisTimeout}"
);

session_start();
// ───────────────────────────────────────────────────────────────────────────

$app = AppFactory::create();

$app->addRoutingMiddleware();

$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

$app->addErrorMiddleware($debug, $debug, $debug);

require __DIR__ . '/helpers/settings.php';
require __DIR__ . '/routes/routes.php';

return $app;