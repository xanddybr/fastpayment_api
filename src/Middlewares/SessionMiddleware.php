<?php
namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

class SessionMiddleware {
    public function __invoke(Request $request, Handler $handler) {
        $currentTime = time();
        $timeout = 1200; // aqui é informado o tempo de expiração da aplicação

        // 1. Verifica se existe usuário logado na sessão
        if (!isset($_SESSION['user_id'])) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                "status" => "erro",
                "mensagem" => "Acesso negado. Faca o login."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // 2. Verifica expiração por inatividade
        if (isset($_SESSION['last_activity']) && ($currentTime - $_SESSION['last_activity'] > $timeout)) {
            session_unset();
            session_destroy();
            
            $response = new Response();
            $response->getBody()->write(json_encode([
                "status" => "sessao_expirada",
                "mensagem" => "Sua sessao expirou por inatividade. O administrador precisa logar novamente."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // 3. Atualiza o timestamp da última atividade
        $_SESSION['last_activity'] = $currentTime;

        return $handler->handle($request);
    }
}