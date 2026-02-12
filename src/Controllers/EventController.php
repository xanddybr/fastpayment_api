<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Event;
use App\Utils\Slugger;
use Exception;

class EventController {

    use Slugger;
    
    private $eventModel;

    public function __construct() {
        // Instancia o Model que já se conecta sozinho via Singleton
        $this->eventModel = new Event();
    }
    
    public function list(Request $request, Response $response) {
        try {
            $events = $this->eventModel->getAll();
            return $this->jsonResponse($response, $events ?: []);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Erro ao listar eventos."], 500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            
            if (empty($data['name']) || empty($data['price'])) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Nome e preço são obrigatórios."], 400);
            }

            // 1. GERAÇÃO DO SLUG (Regra de negócio/utilitário)
            // Note: O Slugger agora usa a conexão do Singleton internamente
            $slug = $this->generateUniqueSlug($data['name'], 'events', $this->eventModel->getConnection());

            // 2. CHAMADA DO MODEL
            $success = $this->eventModel->create($data['name'], $data['price'], $slug);

            if ($success) {
                return $this->jsonResponse($response, [
                    "status" => "sucesso", 
                    "mensagem" => "Evento criado com sucesso.",
                    "slug" => $slug
                ], 201);
            }

            throw new Exception("Falha ao salvar no banco.");

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }
   
    public function delete(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            
            // Verifica se existe antes de deletar (Boa prática)
            $event = $this->eventModel->findById($id);
            if (!$event) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Evento não encontrado."], 404);
            }

            $this->eventModel->delete($id);
            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Evento removido com sucesso."]);
            
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Erro ao remover evento."], 400);
        }
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}