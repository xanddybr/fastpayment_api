<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\EventType;
use App\Models\Event;
use App\Config\Database;
use Exception;
use PDO;

class EventController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    // --- TIPOS DE EVENTO (Workshop, Palestra, etc) ---
    public function listType(Request $request, Response $response) {
        try {
            $model = new EventType($this->db);
            $types = $model->getAll();
            $response->getBody()->write(json_encode($types ?: []));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Erro ao listar tipos de evento."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function storeType(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            if (empty($data['name'])) {
                $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Nome do tipo é obrigatório."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $model = new EventType($this->db);
            $model->create($data['name']);
            $response->getBody()->write(json_encode(["status" => "sucesso", "mensagem" => "Tipo de evento criado."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Erro ao salvar tipo de evento."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // --- EVENTOS (O Serviço e Preço) ---
    public function listEvent(Request $request, Response $response) {
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

    public function storeEvent(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            if (empty($data['name']) || empty($data['price'])) {
                $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Nome e preço são obrigatórios."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $model = new Event($this->db);
            $model->create($data['name'], $data['price']);
            $response->getBody()->write(json_encode(["status" => "sucesso", "mensagem" => "Evento criado com sucesso."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Erro ao salvar evento."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Deletar Tipo de Evento
public function deleteType(Request $request, Response $response, array $args) {
    try {
        $id = $args['id'];
        $stmt = $this->db->prepare("DELETE FROM event_types WHERE id = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Tipo de evento nao encontrado."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(["status" => "sucesso", "mensagem" => "Tipo de evento removido."]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Erro ao remover tipo de evento."]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
}

// Deletar Evento (Serviço)
public function deleteEvent(Request $request, Response $response, array $args) {
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

}