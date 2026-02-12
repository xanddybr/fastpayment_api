<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\EventType;
use App\Utils\Slugger;
use Exception;

class EventTypeController {

    use Slugger;

    private $eventTypeModel;

    public function __construct() {
        // Inicializa o model que já resolve a conexão sozinho
        $this->eventTypeModel = new EventType();
    }

    public function list(Request $request, Response $response) {
        try {
            $types = $this->eventTypeModel->getAll();
            return $this->jsonResponse($response, $types ?: []);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? null;

            if (empty($name)) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Nome é obrigatório."], 400);
            }

            // Usamos a conexão do Singleton para o Slugger
            $slug = $this->generateUniqueSlug($name, 'event_types', $this->eventTypeModel->getConnection());

            $this->eventTypeModel->create($name, $slug);

            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Tipo de evento criado."], 201);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            
            // Verifica se existe antes de tentar deletar
            $type = $this->eventTypeModel->findById($id);
            if (!$type) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Tipo não encontrado."], 404);
            }

            $this->eventTypeModel->delete($id);
            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Tipo removido."]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 400);
        }
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}