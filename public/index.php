<?php
        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");

        // Muito importante: responder 200 OK para o navegador nas requisições OPTIONS
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        use Psr\Http\Message\ResponseInterface as Response;
        use Psr\Http\Message\ServerRequestInterface as Request;
        use Slim\Factory\AppFactory;
        use App\Controllers\ScheduleController;
        use Slim\Exception\HttpNotFoundException;
        use Dotenv\Dotenv;

        // 1. CARREGAR AUTOLOAD (OBRIGATÓRIO SER O PRIMEIRO)
        require __DIR__ . '/../vendor/autoload.php';

        // 2. CONFIGURAÇÕES DE AMBIENTE E ERROS
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // Carrega o .env da raiz da pasta /api
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }

        // 3. SESSÃO E COOKIES
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 600);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 4. INSTANCIAR O APP
        $app = AppFactory::create();

        // 5. MIDDLEWARES GLOBAIS
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();


        // Resposta para o pre-flight do navegador (OPTIONS)
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
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

        // -----------------------------------------------------------------------------
        // 7. ROTAS PÚBLICAS (FLUXO DO ALUNO - FASTPAYMENT)
        // -----------------------------------------------------------------------------

        $app->get('/api/auth/check', function ($request, $response) {
            if (isset($_SESSION['user_id'])) {
                $response->getBody()->write(json_encode(["status" => "autenticado"]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            $response->getBody()->write(json_encode(["status" => "nao_autenticado"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        });

        //Rotas para dashboard
        $app->get('/api/admin/dashboard', \App\Controllers\RegistrationController::class . ':dashboardStats');
        
        // Rota que o Cron Job vai "bater"
        $app->get('/api/schedules/cleanup', [ScheduleController::class, 'closeExpiredSchedules']);

        // 5.1 & 5.2 - Listagem de Cards na Agenda
        $app->get('/api/schedules', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');

        // 5.3 & 5.4 - Identificação e Geração de OTP
        $app->post('/generate-email-code', \App\Controllers\AuthController::class . ':generateValidationCode');

        // 5.5 - Validação do Código
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

        // -----------------------------------------------------------------------------
        // 9. GRUPOS ADMINISTRATIVOS (PROTEGIDOS POR SESSION)
        // -----------------------------------------------------------------------------
        $adminMiddleware = new \App\Middlewares\SessionMiddleware();

        // Usuários
        $app->group('/users', function ($group) {
            $group->get('/list', \App\Controllers\AuthController::class . ':listUsers');
            $group->post('/register', \App\Controllers\AuthController::class . ':register');
            $group->delete('/{id}', \App\Controllers\AuthController::class . ':deleteUser');
            $group->post('/logout', \App\Controllers\AuthController::class . ':logout');
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
            $group->get('', \App\Controllers\ScheduleController::class . ':listAvailableSchedules');
            $group->post('', \App\Controllers\ScheduleController::class . ':store');
            $group->delete('/{id}', \App\Controllers\ScheduleController::class . ':delete');
        })->add($adminMiddleware);

        // 10. RODAR APLICAÇÃO
$app->run();