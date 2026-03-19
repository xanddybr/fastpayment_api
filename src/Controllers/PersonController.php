<?php
namespace App\Controllers;

use App\Models\Person;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class PersonController {
    private $personModel;

    public function __construct() { 
        $this->personModel = new Person();
    }


    public function listAll(Request $request, Response $response) {
        try {
            $users = $this->personModel->findAll();
            return $this->jsonResponse($response, $users);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }


    public function updatePassword(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Validação básica
        if (!$email || !$password) {
            $response->getBody()->write(json_encode([
                "error" => "Campos obrigatórios: email e password."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // O Person model herda de BaseModel, capturando a conexão singleton
            $personModel = new Person();
            $success = $personModel->updatePasswordByEmail($email, $password);

            if ($success) {
                $response->getBody()->write(json_encode([
                    "message" => "Senha atualizada com sucesso para o e-mail: $email"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            $response->getBody()->write(json_encode([
                "error" => "Não foi possível atualizar. Verifique se o e-mail está correto."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
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

    public function store(Request $request, Response $response) {
        $data = $request->getParsedBody();

        // Validação básica dos campos obrigatórios
        if (empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
            $response->getBody()->write(json_encode([
                "error" => "Dados incompletos. Nome, e-mail e senha são obrigatórios."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $success = $this->personModel->create($data);

            if ($success) {
                $response->getBody()->write(json_encode(["message" => "Usuário criado com sucesso!"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            } else {
                $response->getBody()->write(json_encode(["error" => "Erro ao salvar. Verifique se o e-mail já está cadastrado."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Card 8.1: Recebe o POST/PUT para atualizar a ficha
     */
    public function update(Request $request, Response $response, array $args) {
        try {
            $data = $request->getParsedBody();
            $data['id'] = $args['id']; // ID da URL

            $this->personModel->updateFullProfile($data);

            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "message" => "Ficha atualizada com sucesso!"
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
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