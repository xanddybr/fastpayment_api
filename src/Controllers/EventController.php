<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\EventType;
use App\Models\Event;
use App\Config\Database;
use App\Utils\Slugger;
use Exception;
use PDO;

class EventController {

    use Slugger;
    
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }
    
    // --- EVENTOS (O Serviço e Preço) ---
    public function list(Request $request, Response $response) {
        try {
            $model = new Event($this->db);
            $events = $model->getAll();
            $response->getBody()->write(json_encode($events ?: []));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Erro ao listar eventos."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            
            if (empty($data['name']) || empty($data['price'])) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Nome e preço são obrigatórios."], 400);
            }

            // 1. GERAÇÃO DO SLUG ÚNICO (Inteligência do Controller)
            // Passamos o nome do evento, o nome da tabela e a conexão PDO
            $slug = $this->generateUniqueSlug($data['name'], 'events', $this->db);

            // 2. CHAMADA DO MODEL (Persistência)
            $model = new Event($this->db);
            $success = $model->create($data['name'], $data['price'], $slug);

            if ($success) {
                return $this->jsonResponse($response, [
                    "status" => "sucesso", 
                    "mensagem" => "Evento criado com sucesso.",
                    "slug" => $slug // Retornamos o slug gerado para conferência
                ], 201);
            }

            throw new Exception("Falha ao executar inserção no banco.");

        } catch (Exception $e) {
            return $this->jsonResponse($response, [
                "status" => "erro", 
                "mensagem" => "Erro ao salvar evento: " . $e->getMessage()
            ], 500);
        }
    }
   
// Deletar Evento (Serviço)
    public function delete(Request $request, Response $response, array $args) {
            try {
                $id = $args['id'];
                $stmt = $this->db->prepare("DELETE FROM events WHERE id = :id");
                $stmt->bindParam(":id", $id);
                $stmt->execute();

                if ($stmt->rowCount() === 0) {
                    $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Evento nao encontrado."]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }

                $response->getBody()->write(json_encode(["status" => "sucesso", "mensagem" => "Evento removido com sucesso."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } catch (Exception $e) {
                $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Erro ao remover evento."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

    // Método auxiliar para não repetir código de resposta
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

}