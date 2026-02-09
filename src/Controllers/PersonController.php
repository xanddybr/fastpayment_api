<?php
namespace App\Controllers;

use App\Models\Person;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class PersonController {
    
    private $personModel;

    public function __construct() {
        // O Model agora é autossuficiente e resolve a própria conexão internamente
        $this->personModel = new Person();   
    }

    /**
     * Listar todos (Read)
     */
    public function listAll(Request $request, Response $response) {
        try {
            $data = $this->personModel->findAll();
            return $this->jsonResponse($response, $data);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Criar Admin (Create)
     */
    public function createAdmin(Request $request, Response $response) {
        $data = $request->getParsedBody();

        if (empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
            return $this->jsonResponse($response, ["error" => "Dados obrigatórios faltando."], 400);
        }

        try {
            $id = $this->personModel->saveUnified($data);
            return $this->jsonResponse($response, ["message" => "Salvo com sucesso", "id" => $id], 201);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Atualizar (Update)
     */
    public function update(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            $this->personModel->updateUnified($id, $data);
            return $this->jsonResponse($response, ["message" => "Atualizado com sucesso"]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Deletar (Delete)
     */
    public function remove(Request $request, Response $response, array $args) {
        try {
            $this->personModel->delete($args['id']);
            return $this->jsonResponse($response, ["message" => "Removido com sucesso"]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Método auxiliar para respostas JSON
     */
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}