<?php
require __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$container = \App\Config\Container::build();
AppFactory::setContainer($container);

$app = AppFactory::create();

$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$basePath    = rtrim(str_replace('/public', '', $scriptName), '/');
$app->setBasePath($basePath);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// --- SESSION MIDDLEWARE ---
$adminMiddleware = function (Request $request, $handler) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Sessão expirada. Faça login novamente.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
    return $handler->handle($request);
};

// --- PUBLIC ROUTES ---

$app->get('/beta', function (Request $request, Response $response) {
    $queryString = http_build_query($request->getQueryParams());
    return $response->withHeader('Location', 'http://localhost:5173/beta/?' . $queryString)->withStatus(302);
});

$app->map(['POST', 'OPTIONS'], '/api/payment/webhook', \App\Controllers\TransactionController::class . ':webhook');

$app->post('/login', \App\Controllers\AuthController::class . ':login');

$app->post('/logout', function (Request $request, Response $response) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_destroy();
    $response->getBody()->write(json_encode(['status' => 'tchau']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/api/schedules',             \App\Controllers\ScheduleController::class    . ':listAvailableSchedules');
$app->post('/api/auth/generate-code',   \App\Controllers\AuthController::class        . ':generateValidationCode');
$app->post('/api/auth/validate-code',   \App\Controllers\AuthController::class        . ':validateCode');
$app->post('/api/checkout/pay',         \App\Controllers\TransactionController::class . ':createPayment');
$app->get('/api/cron/schedules-cleanup',   \App\Controllers\ScheduleController::class    . ':closeExpiredSchedules');
$app->get('/api/cron/transactions-cleanup', \App\Controllers\TransactionController::class . ':cleanupPendingTransactions');
$app->post('/api/check-payment',        \App\Controllers\TransactionController::class . ':checkPayment');
$app->get('/api/cron/codes-cleanup',    \App\Controllers\AuthController::class        . ':cleanupCodes');
$app->post('/api/auth/create-temp-person', \App\Controllers\AuthController::class     . ':createTempPerson');
$app->post('/api/payment/validate',     \App\Controllers\TransactionController::class . ':validatePayment');
$app->post('/api/register/subscribers', \App\Controllers\RegistrationController::class . ':create');

// --- ADMIN ROUTES (PROTECTED) ---

$app->group('', function ($group) {

    $group->get('/auth/check', function (Request $request, Response $response) {
        return $response->withStatus(isset($_SESSION['user_id']) ? 200 : 401);
    });

    $group->group('/schedules', function ($g) {
        $g->get('',                  \App\Controllers\ScheduleController::class . ':listAdminSchedules');
        $g->post('',                 \App\Controllers\ScheduleController::class . ':store');
        $g->delete('/{id:[0-9]+}',   \App\Controllers\ScheduleController::class . ':delete');
    });

    $group->group('/units', function ($g) {
        $g->get('',                \App\Controllers\UnitController::class . ':list');
        $g->post('',               \App\Controllers\UnitController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\UnitController::class . ':delete');
    });

    $group->group('/events', function ($g) {
        $g->get('',                \App\Controllers\EventController::class . ':list');
        $g->post('',               \App\Controllers\EventController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\EventController::class . ':delete');
    });

    $group->group('/event-types', function ($g) {
        $g->get('',                \App\Controllers\EventTypeController::class . ':list');
        $g->post('',               \App\Controllers\EventTypeController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\EventTypeController::class . ':delete');
    });

    $group->get('/dashboard/summary',  \App\Controllers\RegistrationController::class . ':getDashboardSummary');
    $group->get('/financial/history',  \App\Controllers\RegistrationController::class . ':paymentHistory');
    $group->get('/subscribers',        \App\Controllers\RegistrationController::class . ':listAllSubscribers');

    $group->get('/persons',                      \App\Controllers\PersonController::class . ':listAll');
    $group->post('/person',                      \App\Controllers\PersonController::class . ':store');
    $group->delete('/persons/{id:[0-9]+}',       \App\Controllers\PersonController::class . ':remove');
    $group->patch('/persons/password-reset',     \App\Controllers\PersonController::class . ':updatePassword');

})->add($adminMiddleware);

// --- CORS & ERROR HANDLING ---

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->addErrorMiddleware(true, true, true);

$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    $origin   = $_SERVER['HTTP_ORIGIN'] ?? '*';
    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS, PUT, PATCH')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->run();
