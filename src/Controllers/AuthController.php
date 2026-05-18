<?php
namespace App\Controllers;

use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\EmailServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(
        private AuthServiceInterface $authService,
        private EmailServiceInterface $emailService
    ) {}

    public function login(Request $request, Response $response): Response
    {
        $data     = $request->getParsedBody();
        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        $user = $this->authService->login($email, $password);

        if ($user) {
            $this->startSession($user);
            return $this->json($response, [
                'status'             => 'sucesso',
                'message'            => 'Bem-vindo, ' . $user['full_name'],
                'session_expires_in' => 20,
                'user'               => $user,
            ]);
        }

        return $this->json($response, [
            'status'   => 'erro',
            'mensagem' => 'E-mail ou senha inválidos ou conta inativa.',
        ], 401);
    }

    public function generateValidationCode(Request $request, Response $response): Response
    {
        $data  = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $nome  = $data['nome']  ?? 'Cliente';

        if (!$email) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => 'E-mail é obrigatório'], 400);
        }

        $code = $this->authService->generateValidationCode($email, $data['telefone'] ?? '');
        $this->emailService->sendOTP($email, $nome, $code);
        return $this->json($response, ['status' => 'sucesso', 'mensagem' => 'Código de verificação enviado!']);
    }

    public function validateCode(Request $request, Response $response): Response
    {
        $data  = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $code  = $data['code']  ?? null;
        $nome  = $data['nome']  ?? null;
        $phone = $data['phone'] ?? $data['telefone'] ?? null;

        if (!$email || !$code) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => 'E-mail e código são obrigatórios'], 400);
        }

        $isValid = $this->authService->validateCode($email, $code, $nome, $phone);

        if ($isValid) {
            return $this->json($response, ['status' => 'sucesso', 'mensagem' => 'Código validado com sucesso!']);
        }

        return $this->json($response, ['status' => 'erro', 'mensagem' => 'Código inválido ou expirado.'], 400);
    }

    public function createTempPerson(Request $request, Response $response): Response
    {
        $data  = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $nome  = $data['nome']  ?? null;

        if (!$email || !$nome) {
            return $this->json($response, ['status' => 'erro'], 400);
        }

        $id = $this->authService->createTempPerson($email, $nome);
        return $this->json($response, ['status' => 'sucesso', 'id' => $id]);
    }

    public function cleanupCodes(Request $request, Response $response): Response
    {
        try {
            $deleted = $this->authService->cleanupCodes();
            return $this->json($response, [
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} código(s) validado(s)/expirado(s) removido(s).",
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function startSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['full_name'];
        $_SESSION['is_admin']      = true;
        $_SESSION['last_activity'] = time();
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
