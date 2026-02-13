<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. ENV
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

// 3. MIDDLEWARES GLOBAIS
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$adminMiddleware = new \App\Middlewares\SessionMiddleware();

// 4. CONFIGURAÇÃO DE ERROS
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// -----------------------------------------------------------------------------
// 🟢 ROTAS PÚBLICAS (Abertas para o Front-end do Cliente e Webhooks)
// -----------------------------------------------------------------------------

// Auth
$app->post('/login', \App\Controllers\AuthController::class . ':login');
$app->post('/logout', \App\Controllers\AuthController::class . ':logout');

// Fluxo de Inscrição (Cliente)
$app->get('/api/schedules', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');
$app->post('/api/auth/generate-code', \App\Controllers\AuthController::class . ':generateValidationCode');
$app->post('/api/auth/validate-code', \App\Controllers\AuthController::class . ':validateCode');
$app->post('/api/checkout/pay', \App\Controllers\TransactionController::class . ':createPayment');
$app->get('/api/checkout/check-status', \App\Controllers\TransactionController::class . ':checkStatus');

// Webhooks e Cron (Sem proteção de sessão)
$app->post('/webhook/mercadopago', \App\Controllers\PaymentController::class . ':webhook');
$app->get('/api/cron/schedules-cleanup', \App\Controllers\ScheduleController::class . ':closeExpiredSchedules');


// -----------------------------------------------------------------------------
// 🔴 ROTAS ADMINISTRATIVAS (Protegidas pelo AdminMiddleware)
// -----------------------------------------------------------------------------

$app->group('/api/admin', function ($group) {
    
    // Dashboard & Financeiro
    $group->get('/dashboard/summary', \App\Controllers\RegistrationController::class . ':getDashboardSummary');
    $group->get('/financial/history', \App\Controllers\RegistrationController::class . ':paymentHistory');
    $group->get('/financial/transactions', \App\Controllers\RegistrationController::class . ':listTransactions');

    // Gestão de Inscrições
    $group->get('/subscribers', \App\Controllers\RegistrationController::class . ':listAllSubscribers');
    $group->post('/subscriptions/confirm', \App\Controllers\RegistrationController::class . ':completeSubscription');

    // Gestão de Pessoas (Coringa para o seu Card 8.1)
    $group->get('/persons', \App\Controllers\PersonController::class . ':index');
    $group->get('/persons/{id}', \App\Controllers\PersonController::class . ':show');
    $group->post('/persons', \App\Controllers\PersonController::class . ':store');
    $group->post('/persons/{id}/update', \App\Controllers\PersonController::class . ':update');
    $group->delete('/persons/{id}', \App\Controllers\PersonController::class . ':remove');

    // Tabelas Auxiliares (CRUDs)
    $group->group('/units', function ($g) {
        $g->get('', \App\Controllers\UnitController::class . ':list');
        $g->post('', \App\Controllers\UnitController::class . ':store');
        $g->delete('/{id}', \App\Controllers\UnitController::class . ':delete');
    });

    $group->group('/events', function ($g) {
        $g->get('', \App\Controllers\EventController::class . ':list');
        $g->post('', \App\Controllers\EventController::class . ':store');
        $g->delete('/{id}', \App\Controllers\EventController::class . ':delete');
    });

    $group->group('/event-types', function ($g) {
        $g->get('', \App\Controllers\EventTypeController::class . ':list');
        $g->post('', \App\Controllers\EventTypeController::class . ':store');
        $g->delete('/{id}', \App\Controllers\EventTypeController::class . ':delete');
    });
    
    $group->group('/schedules', function ($g) {
        $g->get('', \App\Controllers\ScheduleController::class . ':listAdminSchedules');
        $g->post('', \App\Controllers\ScheduleController::class . ':store');
        $g->delete('/{id}', \App\Controllers\ScheduleController::class . ':delete');
    });

})->add($adminMiddleware);

$app->run();