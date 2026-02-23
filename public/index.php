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


use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$app = AppFactory::create();

$app = AppFactory::create();

// Detecta se é o servidor embutido do PHP (local) ou Apache (remoto)
$isLocal = (php_sapi_name() === 'cli-server');

if ($isLocal) {
    // No local (php -S), não existe o prefixo da pasta na URL
    $app->setBasePath(''); 
} else {
    // No servidor real, precisamos do caminho completo até a pasta public
    // MAS SEM o index.php, já que vamos usar o .htaccess
    $app->setBasePath('/agenda/api/public');
}

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
$app->post('/logout', function ($request, $response) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    
    $response->getBody()->write(json_encode(["status" => "tchau"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// No seu index.php, adicione esta linha junto com as outras rotas de Auth
$app->get('/api/auth/check', function (Request $request, Response $response) {
    if (isset($_SESSION['user_id'])) {
        return $response->withStatus(200);
    }
    return $response->withStatus(401);
});

// Fluxo de Inscrição (Vitrine do Cliente)
// Chamada no front: http://localhost:8080/api/schedules
$app->get('/api/schedules', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');

$app->post('/api/auth/generate-code', \App\Controllers\AuthController::class . ':generateValidationCode');
$app->post('/api/auth/validate-code', \App\Controllers\AuthController::class . ':validateCode');
$app->post('/api/checkout/pay', \App\Controllers\TransactionController::class . ':createPayment');
$app->get('/api/checkout/check-status', \App\Controllers\TransactionController::class . ':checkStatus');

// Webhooks e Cron
$app->post('/webhook/mercadopago', \App\Controllers\PaymentController::class . ':webhook');
$app->get('/api/cron/schedules-cleanup', \App\Controllers\ScheduleController::class . ':closeExpiredSchedules');

// -----------------------------------------------------------------------------
// 🔴 ROTAS ADMINISTRATIVAS (Protegidas)
// Removido o prefixo "/api/admin" para atender à sua estrutura de pastas
// -----------------------------------------------------------------------------

$app->group('', function ($group) {
    
    // Agora acessível em: http://localhost:8080/schedules
    $group->group('/schedules', function ($g) {
        $g->get('', \App\Controllers\ScheduleController::class . ':listAdminSchedules');
        $g->post('', \App\Controllers\ScheduleController::class . ':store');
        $g->delete('/{id}', \App\Controllers\ScheduleController::class . ':delete');
    });

    // Tabelas Auxiliares (Acessíveis diretamente: /events, /units, etc)
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

    // Dashboard & Gestão
    $group->get('/dashboard/summary', \App\Controllers\RegistrationController::class . ':getDashboardSummary');
    $group->get('/financial/history', \App\Controllers\RegistrationController::class . ':paymentHistory');
    $group->get('/subscribers', \App\Controllers\RegistrationController::class . ':listAllSubscribers');

    // Gestão de Pessoas
    $group->get('/persons', \App\Controllers\PersonController::class . ':index');
    $group->get('/persons/{id}', \App\Controllers\PersonController::class . ':show');
    $group->post('/persons', \App\Controllers\PersonController::class . ':store');
    $group->delete('/persons/{id}', \App\Controllers\PersonController::class . ':remove');
    $group->patch('/persons/password-reset', \App\Controllers\PersonController::class . ':updatePassword');

})->add($adminMiddleware);

$app->run();