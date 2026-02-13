<?php
        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // 3. SESSÃO E COOKIES
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 600);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require __DIR__ . '/../vendor/autoload.php';

        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }

        use Psr\Http\Message\ResponseInterface as Response;
        use Psr\Http\Message\ServerRequestInterface as Request;
        use Slim\Factory\AppFactory;
        use App\Controllers\ScheduleController;
        use Slim\Exception\HttpNotFoundException;
        use Dotenv\Dotenv;

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // 4. INSTANCIAR O APP
        $app = AppFactory::create();

        // 5. MIDDLEWARES GLOBAIS
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // Trata requisições OPTIONS (Pre-flight)
        $app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        // 6. CONFIGURAÇÃO DE ERROS (JSON)
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (Request $request) use ($app) {
        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Rota nao encontrada."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        });

        $adminMiddleware = new \App\Middlewares\SessionMiddleware();


        $app->group('/api', function ($group) {
            $group->get('/payments/history', \App\Controllers\RegistrationController::class . ':paymentHistory');
            $group->get('/subscribers', \App\Controllers\RegistrationController::class . ':listAllSubscribers');
            $group->post('/subscriptions/confirm', \App\Controllers\RegistrationController::class . ':confirmSubscription');
        })->add($adminMiddleware);
        
        // Rotas para dashboard
        $app->get('/api/admin/dashboard', \App\Controllers\RegistrationController::class . ':dashboardStats');
        
        // Rota que o Cron Job vai "bater"
        $app->get('/api/schedules/cleanup', \App\Controllers\ScheduleController::class . ':closeExpiredSchedules');

        // Listagem de Cards na Agenda
        $app->get('/api/schedules', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');

        // Identificação e Geração de OTP
        $app->post('/generate-email-code', \App\Controllers\AuthController::class . ':generateValidationCode');

        // Validação do Código
        $app->post('/validate-code', \App\Controllers\AuthController::class . ':validateCode');

        // 5.6 & 5.7 - Geração do Checkout Mercado Pago
        $app->post('/checkout/pay', \App\Controllers\TransactionController::class . ':createPayment');

        // 5.8 & 5.9 - Retorno do Pagamento (Webhook)
        $app->post('/webhook/mercadopago', \App\Controllers\TransactionController::class . ':webhook');

        // Finalização da Inscrição (Pós-pagamento)
        $app->post('/finalize-registration', \App\Controllers\RegistrationController::class . ':finalizeRegistration');

        // Verificação de Status do Pagamento
        $app->get('/check-payment', \App\Controllers\TransactionController::class . ':checkStatus');

        // -----------------------------------------------------------------------------
        // 8. AUTENTICAÇÃO E TESTES
        // -----------------------------------------------------------------------------

        $app->post('/login', \App\Controllers\AuthController::class . ':login');
        $app->post('/logout', \App\Controllers\AuthController::class . ':logout');
        // -----------------------------------------------------------------------------
        // 9. GRUPOS ADMINISTRATIVOS (PROTEGIDOS POR SESSION)
        // -----------------------------------------------------------------------------
       

        // Usuários
        $app->group('/person', function ($group) {
            $group->get('/list', \App\Controllers\PersonController::class . ':listAll');
            $group->get('/{id}', \App\Controllers\PersonController::class . ':show');
            $group->post('/create', \App\Controllers\PersonController::class . ':createAdmin');
            $group->put('/update/{id}', \App\Controllers\PersonController::class . ':update');
            $group->delete('/{id}', \App\Controllers\PersonController::class . ':remove');
        })->add($adminMiddleware);

        // Unidades
        $app->group('/units', function ($group) {
            $group->get('', \App\Controllers\UnitController::class . ':list');
            $group->post('', \App\Controllers\UnitController::class . ':store');
            $group->delete('/{id}', \App\Controllers\UnitController::class . ':delete');
        })->add($adminMiddleware);

        // Eventos (Cursos)
        $app->group('/events', function ($group) {
            $group->get('', \App\Controllers\EventController::class . ':list');
            $group->post('', \App\Controllers\EventController::class . ':store');
            $group->delete('/{id}', \App\Controllers\EventController::class . ':delete');
        })->add($adminMiddleware);

        $app->group('/event-types', function ($group) {
            $group->get('', \App\Controllers\EventTypeController::class . ':list');
            $group->post('', \App\Controllers\EventTypeController::class . ':store');
            $group->delete('/{id}', \App\Controllers\EventTypeController::class . ':delete'); // <--- ADICIONE ESTA LINHA
        })->add($adminMiddleware);
        
        // Agendas (Schedules)
        $app->group('/schedules', function ($group) {
            $group->get('', \App\Controllers\ScheduleController::class . ':listAdminSchedules');
            $group->post('', \App\Controllers\ScheduleController::class . ':store');
            $group->delete('/{id}', \App\Controllers\ScheduleController::class . ':delete');
        })->add($adminMiddleware);

        // 10. RODAR APLICAÇÃO
$app->run();