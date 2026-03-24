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

    // 3. MIDDLEWARES GLOBAIS
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    
   // Verifica se é localhost OU se a URL contém 'ngrok-free.app'
    $isLocal = ($_SERVER['HTTP_HOST'] === 'localhost:8080');
    $isNgrok = (strpos($_SERVER['HTTP_HOST'], 'ngrok-free.app') !== false);

        if ($isLocal || $isNgrok) {
            $app->setBasePath(''); 
        } else {
            // Para o servidor de produção real
            $app->setBasePath('/agenda/api/public');
        }

    // --- ALTERAÇÃO CIRÚRGICA: MIDDLEWARE DE CORS ---
    $app->add(function (Request $request, $handler) {
        $response = $handler->handle($request);
        
        // Lista de origens permitidas
        $allowedOrigins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'https://misturadeluz.com',
            'http://localhost:8080'
        ];

        // Pega a origem da requisição atual
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Se a origem estiver na nossa lista, usamos ela, senão usamos a padrão de produção
        $resultOrigin = in_array($origin, $allowedOrigins) ? $origin : 'https://misturadeluz.com';
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', $resultOrigin)
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
    $app->get('/api/cron/schedules-cleanup', \App\Controllers\ScheduleController::class . ':closeExpiredSchedules');
    $app->post('/api/public/register', \App\Controllers\RegistrationController::class . ':create');

    $app->post('/api/check-payment', \App\Controllers\TransactionController::class . ':checkPayment');
    $app->post('/api/webhook/mercadopago', \App\Controllers\PaymentController::class . ':webhook');


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

    })->add($adminMiddleware);

    // IMPORTANTE: Adicionado tratamento para o método OPTIONS dentro do Slim
    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });

    $app->run();