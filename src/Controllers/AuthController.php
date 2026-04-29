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
                "session_expires_in" => 20,
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

    public function validateCode(Request $request, Response $response) {
        $data  = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $code  = $data['code']  ?? null;
        $nome  = $data['nome']  ?? null; // ✅ name from #user-name

        if (!$email || !$code) {
            return $this->jsonResponse($response, [
                "status"   => "erro",
                "mensagem" => "E-mail e código são obrigatórios"
            ], 400);
        }

        $isValid = $this->personModel->validateOTP($email, $code);

        if ($isValid) {
            error_log("=== VALIDATE_CODE DEBUG ===");
            error_log("nome recebido: '" . ($nome ?? 'NULL') . "'");
            error_log("email: '{$email}'");
            
            if ($nome) {
                $newId = $this->personModel->createTemporaryPerson($email, $nome);
                error_log("✅ person criado/atualizado id={$newId}");
            } else {
                error_log("❌ NOME ESTÁ VAZIO — pessoa NÃO criada");
            }
            error_log("=== END DEBUG ===");
            // ... rest
    
            return $this->jsonResponse($response, [
                "status"   => "sucesso",
                "mensagem" => "Código validado com sucesso!"
            ]);
        }

        return $this->jsonResponse($response, [
            "status"   => "erro",
            "mensagem" => "Código inválido ou expirado."
        ], 400);
    }


    public function createTempPerson(Request $request, Response $response) {
        $data  = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $nome  = $data['nome']  ?? null;

        if (!$email || !$nome) {
            return $this->jsonResponse($response, ["status" => "erro"], 400);
        }

        $id = $this->personModel->createTemporaryPerson($email, $nome);
        return $this->jsonResponse($response, ["status" => "sucesso", "id" => $id]);
    }

    public function generateValidationCode(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $nome = $data['nome'] ?? 'Cliente';


        if (!$email) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "E-mail é obrigatório"], 400);
        }

        $code = $this->personModel->createValidationCode($email, $data['telefone'] ?? '');
        
        if ($code) {
            EmailService::sendOTP($email, $nome, $code);
            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Código de verificação enviado!"]);
        }

        return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Falha ao gerar código."], 500);
    }

    public function cleanupCodes(Request $request, Response $response) {
        try {
            $deleted = $this->personModel->deleteValidatedCodes();
            error_log("CLEANUP CODES: {$deleted} código(s) deletado(s)");
            return $this->jsonResponse($response, [
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} código(s) validado(s)/expirado(s) removido(s).",
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
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