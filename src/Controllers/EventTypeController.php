<?php
namespace App\Controllers;

use App\Contracts\Repositories\EventTypeRepositoryInterface;
use App\Utils\Slugger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EventTypeController
{
    use Slugger;

    public function __construct(private EventTypeRepositoryInterface $eventTypeRepo) {}

    public function list(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, $this->eventTypeRepo->getAll() ?: []);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? null;

            if (empty($name)) {
                return $this->json($response, ['status' => 'erro', 'mensagem' => 'Nome é obrigatório.'], 400);
            }

            $slug = $this->generateUniqueSlug($name, 'event_types', $this->eventTypeRepo->getConnection());
            $this->eventTypeRepo->create($name, $slug);
            return $this->json($response, ['status' => 'sucesso', 'mensagem' => 'Tipo de evento criado.'], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->eventTypeRepo->delete((int) $args['id']);
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
