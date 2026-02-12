<?php
namespace App\Controllers;

use App\Models\Person;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private $personModel;

    public function __construct() { 
        $this->personModel = new Person();
    }

    public function login(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $user = $this->personModel->authenticate($email, $password);

        if ($user) {
            $this->createSession($user);
            return $this->jsonResponse($response, ["status" => "sucesso", "user" => $user]);
        }
        return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "E-mail ou senha inválidos"], 401);
    }

    // --- FALTAVA ESTE MÉTODO ---
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
    }

    // --- FALTAVA ESTE MÉTODO ---
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function generateValidationCode(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $nome = $data['nome'] ?? 'Cliente';
        $telefone = $data['telefone'] ?? '';

        if (!$email) return $this->jsonResponse($response, ["error" => "Email obrigatório"], 400);

        $code = $this->personModel->createValidationCode($email, $telefone);
        
        if ($code) {
            EmailService::sendOTP($email, $nome, $code);
            return $this->jsonResponse($response, ["status" => "sucesso", "message" => "Código enviado"]);
        }
        return $this->jsonResponse($response, ["error" => "Falha ao gerar código"], 500);
    }
}