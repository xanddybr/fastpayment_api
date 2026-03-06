<?php
// 1. Removidos headers manuais do topo para evitar erro 500 (Headers already sent)

require __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$app = AppFactory::create();

// 2. Ajuste de BasePath Inteligente
$isLocal = (php_sapi_name() === 'cli-server');
if ($isLocal) {
    $app->setBasePath(''); 
} else {
    $app->setBasePath('/agenda/api/public');
}

// 3. MIDDLEWARES GLOBAIS
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// --- ALTERAÇÃO CIRÚRGICA: MIDDLEWARE DE CORS ---
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    
    // Detecta a origem para permitir tanto local quanto remoto
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://misturadeluz.com';
    
    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS, PUT')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$adminMiddleware = new \App\Middlewares\SessionMiddleware(); 

// 4. CONFIGURAÇÃO DE ERROS
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// -----------------------------------------------------------------------------
// 🟢 ROTAS PÚBLICAS
// -----------------------------------------------------------------------------

// Auth
$app->post('/login', \App\Controllers\AuthController::class . ':login');

$app->post('/logout', function ($request, $response) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    $response->getBody()->write(json_encode(["status" => "tchau"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Fluxo de Inscrição
$app->get('/api/schedules', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');

$app->post('/api/auth/generate-code', \App\Controllers\AuthController::class . ':generateValidationCode');
$app->post('/api/auth/validate-code', \App\Controllers\AuthController::class . ':validateCode');
$app->post('/api/checkout/pay', \App\Controllers\TransactionController::class . ':createPayment');
$app->get('/api/checkout/check-status', \App\Controllers\TransactionController::class . ':checkStatus');

$app->post('/api/public/register', \App\Controllers\RegistrationController::class . ':create');
$app->post('/webhook/mercadopago', \App\Controllers\PaymentController::class . ':webhook');
$app->get('/api/cron/schedules-cleanup', \App\Controllers\ScheduleController::class . ':closeExpiredSchedules');

// -----------------------------------------------------------------------------
// 🔴 ROTAS ADMINISTRATIVAS (Protegidas)
// -----------------------------------------------------------------------------

$app->group('', function ($group) {

    $group->get('/auth/check', function (Request $request, Response $response) {
        if (isset($_SESSION['user_id'])) {
            return $response->withStatus(200);
        }
    });
    
    $group->group('/schedules', function ($g) {
        $g->get('', \App\Controllers\ScheduleController::class . ':listAdminSchedules');
        $g->post('', \App\Controllers\ScheduleController::class . ':store');
        $g->delete('/{id}', \App\Controllers\ScheduleController::class . ':delete');
    });

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

    $group->get('/dashboard/summary', \App\Controllers\RegistrationController::class . ':getDashboardSummary');
    $group->get('/financial/history', \App\Controllers\RegistrationController::class . ':paymentHistory');
    $group->get('/subscribers', \App\Controllers\RegistrationController::class . ':listAllSubscribers');

    $group->get('/persons', \App\Controllers\PersonController::class . ':listAll');
    $group->post('/persons', \App\Controllers\PersonController::class . ':store');
    $group->delete('/persons/{id}', \App\Controllers\PersonController::class . ':remove');
    $group->patch('/persons/password-reset', \App\Controllers\PersonController::class . ':updatePassword');

})->add($adminMiddleware);

// IMPORTANTE: Adicionado tratamento para o método OPTIONS dentro do Slim
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->run();