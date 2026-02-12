<?php
namespace App\Controllers;

use App\Models\Person;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private $personModel;

    public function __construct() { 
        // O Model já nasce com a conexão Singleton via BaseModel
        $this->personModel = new Person();
    }

    public function login(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // O Model faz o trabalho pesado de buscar e verificar o hash
        $user = $this->personModel->authenticate($email, $password);

        if ($user) {
            $this->createSession($user);
            return $this->jsonResponse($response, [
                "status" => "sucesso", 
                "message" => "Bem-vindo, " . $user['full_name'],
                "user" => $user
            ]);
        }

        return $this->jsonResponse($response, [
            "status" => "erro", 
            "mensagem" => "E-mail ou senha inválidos ou conta inativa."
        ], 401);
    }

    public function logout(Request $request, Response $response) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Sessão encerrada."]);
    }

    public function generateValidationCode(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $nome = $data['nome'] ?? 'Cliente';
        $telefone = $data['telefone'] ?? '';

        if (!$email) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "E-mail é obrigatório"], 400);
        }

        $code = $this->personModel->createValidationCode($email, $telefone);
        
        if ($code) {
            // Service isolado para não sujar o Controller com lógica de e-mail
            EmailService::sendOTP($email, $nome, $code);
            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Código de verificação enviado!"]);
        }

        return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Falha ao gerar código."], 500);
    }

    // Auxiliares para manter o Controller limpo
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['is_admin'] = true; // Útil para o seu SessionMiddleware
        $_SESSION['last_activity'] = time();
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}