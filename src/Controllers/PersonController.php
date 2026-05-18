<?php
namespace App\Controllers;

use App\Contracts\Repositories\PersonRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PersonController
{
    public function __construct(private PersonRepositoryInterface $personRepo) {}

    public function listAll(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, $this->personRepo->findAll());
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
            return $this->json($response, ['error' => 'Dados incompletos. Nome, e-mail e senha são obrigatórios.'], 400);
        }

        try {
            if ($this->personRepo->create($data)) {
                return $this->json($response, ['message' => 'Usuário criado com sucesso!'], 201);
            }
            return $this->json($response, ['error' => 'Erro ao salvar. Verifique se o e-mail já está cadastrado.'], 409);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            $this->personRepo->delete((int) $args['id']);
            return $this->json($response, ['status' => 'sucesso', 'mensagem' => 'Usuário removido com sucesso']);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function updatePassword(Request $request, Response $response): Response
    {
        $data     = $request->getParsedBody();
        $email    = $data['email']    ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json($response, ['error' => 'Campos obrigatórios: email e password.'], 400);
        }

        try {
            if ($this->personRepo->updatePasswordByEmail($email, $password)) {
                return $this->json($response, ['message' => "Senha atualizada com sucesso para o e-mail: {$email}"]);
            }
            return $this->json($response, ['error' => 'Não foi possível atualizar. Verifique se o e-mail está correto.'], 404);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
