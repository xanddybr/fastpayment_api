<?php
namespace App\Controllers;

use App\Models\Person;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PersonController {
    private $personModel;

    public function __construct() { 
        $this->personModel = new Person();
    }

    // --- FALTAVA ESTE MÉTODO ---
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
    }

    public function listAll(Request $request, Response $response) {
        try {
            $users = $this->personModel->findAll();
            return $this->jsonResponse($response, $users);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    /**
     * Cria um novo usuário ou Admin (POST /api/admin/person/create)
     */
    public function createAdmin(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            
            // Usamos o seu método unificado que cuida de persons + person_details
            $personId = $this->personModel->saveUnified($data);
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "mensagem" => "Usuário criado/atualizado com sucesso",
                "id" => $personId
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 400);
        }
    }

    /**
     * Atualiza um usuário existente (POST /api/admin/person/update/{id})
     */
    public function update(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            
            $this->personModel->updateUnified($id, $data);
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "mensagem" => "Dados atualizados com sucesso"
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 400);
        }
    }

    /**
     * Remove um usuário (DELETE /api/admin/person/{id})
     */
    public function remove(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $this->personModel->delete($id);
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "mensagem" => "Usuário removido com sucesso"
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    /**
     * Auxiliar para respostas JSON padronizadas
     */
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    
}