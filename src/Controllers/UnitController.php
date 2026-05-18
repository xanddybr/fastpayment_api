<?php
namespace App\Controllers;

use App\Contracts\Repositories\UnitRepositoryInterface;
use App\Utils\Slugger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UnitController
{
    use Slugger;

    public function __construct(private UnitRepositoryInterface $unitRepo) {}

    public function list(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, $this->unitRepo->getAll() ?: []);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => 'Falha ao listar unidades.'], 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';

            if (empty($name)) {
                return $this->json($response, ['status' => 'erro', 'mensagem' => 'O nome é obrigatório.'], 400);
            }

            $slug = $this->generateUniqueSlug($name, 'units', $this->unitRepo->getConnection());

            if ($this->unitRepo->create($name, $slug)) {
                return $this->json($response, ['status' => 'sucesso', 'mensagem' => 'Unidade criada!', 'slug' => $slug], 201);
            }
            throw new \Exception('Erro ao inserir no banco.');
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->unitRepo->delete((int) $args['id']);
            return $this->json($response, ['message' => 'Excluído com sucesso']);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
