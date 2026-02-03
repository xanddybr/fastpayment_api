<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config\Database;
use App\Models\EventType;

class EventTypeController {

    private function jsonResponse($response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    // Listar todos os tipos
    public function list(Request $request, Response $response) {
        try {
            $db = (new Database())->getConnection();
            $model = new EventType($db);
            $types = $model->getAll();
            
            return $this->jsonResponse($response, $types);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    // Criar um novo tipo
    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? null;

            if (!$name) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Nome é obrigatório"], 400);
            }

            $db = (new Database())->getConnection();
            $model = new EventType($db);
            
            if ($model->create($name)) {
                return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Tipo de evento criado"], 201);
            }

            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Erro ao criar tipo"], 500);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }
}