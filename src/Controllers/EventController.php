<?php
namespace App\Controllers;

use App\Contracts\Repositories\EventRepositoryInterface;
use App\Utils\Slugger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EventController
{
    use Slugger;

    public function __construct(private EventRepositoryInterface $eventRepo) {}

    public function list(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, $this->eventRepo->getAll() ?: []);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => 'Erro ao listar eventos.'], 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();

            if (empty($data['name']) || empty($data['price'])) {
                return $this->json($response, ['status' => 'erro', 'mensagem' => 'Nome e preço são obrigatórios.'], 400);
            }

            $slug    = $this->generateUniqueSlug($data['name'], 'events', $this->eventRepo->getConnection());
            $success = $this->eventRepo->create($data['name'], (float) $data['price'], $slug);

            if ($success) {
                return $this->json($response, ['status' => 'sucesso', 'mensagem' => 'Evento criado com sucesso.', 'slug' => $slug], 201);
            }
            throw new \Exception('Falha ao salvar no banco.');
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->eventRepo->delete((int) $args['id']);
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
