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

$app = AppFactory::create();

$app->setBasePath(''); 

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// --- DEFINIÇÃO DO MIDDLEWARE DE SESSÃO (Adicione isso aqui) ---
$adminMiddleware = function ($request, $handler) {
    // Inicia a sessão se ainda não estiver aberta
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica se o analista está logado
    if (!isset($_SESSION['user_id'])) {
        $response = new \Slim\Psr7\Response();
        $payload = json_encode(['error' => 'Sessão expirada. Faça login novamente.']);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }

    // Se estiver logado, segue para o Controller
    return $handler->handle($request);
};

// No index.php (API)
$app->get('/agenda', function ($request, $response) {
    // Pega todos os parâmetros que o MP mandou (?status=approved...)
    $params = $request->getQueryParams();
    $queryString = http_build_query($params);
    
    // Manda de volta para o seu Vite local
    return $response
        ->withHeader('Location', 'http://localhost:5173/agenda/?' . $queryString)
        ->withStatus(302);
});

// A rota precisa ser IGUAL ao que você configurou no Mercado Pago
$app->map(['POST', 'OPTIONS'], '/api/webhook/mercadopago', function ($request, $response) {
    if ($request->getMethod() === 'OPTIONS') {
        return $response->withStatus(200);
    }
    $controller = new \App\Controllers\TransactionController();
    return $controller->webhook($request, $response);
});
// --- 4. ROTAS PÚBLICAS ---

$app->post('/login', \App\Controllers\AuthController::class . ':login');

$app->post('/logout', function ($request, $response) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    $response->getBody()->write(json_encode(["status" => "tchau"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Fluxo de Inscrição e Checkout
$app->get('/api/schedules', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');
$app->post('/api/auth/generate-code', \App\Controllers\AuthController::class . ':generateValidationCode');
$app->post('/api/auth/validate-code', \App\Controllers\AuthController::class . ':validateCode');
$app->post('/api/checkout/pay', \App\Controllers\TransactionController::class . ':createPayment');
$app->get('/api/checkout/check-status', \App\Controllers\TransactionController::class . ':checkStatus');
$app->get('/api/cron/schedules-cleanup', \App\Controllers\ScheduleController::class . ':closeExpiredSchedules');
$app->post('/api/check-payment', \App\Controllers\TransactionController::class . ':checkPayment');
$app->post('/api/register/subscribers', \App\Controllers\RegistrationController::class . ':create');

// --- 5. ROTAS ADMINISTRATIVAS (PROTEGIDAS) ---

// Defina seu $adminMiddleware aqui ou certifique-se que ele está acessível
$app->group('', function ($group) {

    $group->get('/auth/check', function (Request $request, Response $response) {
        if (isset($_SESSION['user_id'])) {
            return $response->withStatus(200);
        }
        return $response->withStatus(401);
    });
    
    $group->group('/schedules', function ($g) {
        $g->get('', \App\Controllers\ScheduleController::class . ':listAdminSchedules');
        $g->post('', \App\Controllers\ScheduleController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\ScheduleController::class . ':delete');
    });

    $group->group('/units', function ($g) {
        $g->get('', \App\Controllers\UnitController::class . ':list');
        $g->post('', \App\Controllers\UnitController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\UnitController::class . ':delete');
    });

    $group->group('/events', function ($g) {
        $g->get('', \App\Controllers\EventController::class . ':list');
        $g->post('', \App\Controllers\EventController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\EventController::class . ':delete');
    });

    $group->group('/event-types', function ($g) {
        $g->get('', \App\Controllers\EventTypeController::class . ':list');
        $g->post('', \App\Controllers\EventTypeController::class . ':store');
        $g->delete('/{id:[0-9]+}', \App\Controllers\EventTypeController::class . ':delete');
    });

    $group->get('/dashboard/summary', \App\Controllers\RegistrationController::class . ':getDashboardSummary');
    $group->get('/financial/history', \App\Controllers\RegistrationController::class . ':paymentHistory');
    $group->get('/subscribers', \App\Controllers\RegistrationController::class . ':listAllSubscribers');

    $group->get('/persons', \App\Controllers\PersonController::class . ':listAll');
    $group->post('/person', \App\Controllers\PersonController::class . ':store');
    $group->delete('/persons/{id:[0-9]+}', \App\Controllers\PersonController::class . ':remove');
    $group->patch('/persons/password-reset', \App\Controllers\PersonController::class . ':updatePassword');

})->add($adminMiddleware); // Certifique-se que $adminMiddleware foi definido antes

// --- 6. MIDDLEWARE DE CORS E TRATAMENTO DE ERROS (FINAL DO ARQUIVO) ---

$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->addErrorMiddleware(true, true, true);

$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    
    // Identifica de onde vem a requisição
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin) // Permite dinamicamente
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS, PUT')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Responde imediatamente aos pre-flights do navegador

// --- 7. RUN ---
$app->run();