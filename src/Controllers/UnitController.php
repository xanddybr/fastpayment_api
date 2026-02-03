<?php
namespace App\Controllers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Unit;
use App\Config\Database;
use Exception;

class UnitController {
    public function list(Request $request, Response $response) {
        try {
            $db = (new Database())->getConnection();
            $unitModel = new Unit($db);
            $units = $unitModel->getAll();
            
            $response->getBody()->write(json_encode($units ?: []));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Falha ao listar unidades."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';

            if (empty($name)) {
                $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "O nome da unidade é obrigatório."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $db = (new Database())->getConnection();
            $unitModel = new Unit($db);
            
            if ($unitModel->create($name)) {
                $response->getBody()->write(json_encode(["status" => "sucesso", "mensagem" => "Unidade cadastrada com sucesso."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            }
            
            throw new Exception("Erro ao inserir no banco.");
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Não foi possível salvar a unidade."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function delete(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $db = (new Database())->getConnection();
            
            // Verifica se existe antes de deletar
            $stmt = $db->prepare("DELETE FROM units WHERE id = :id");
            $stmt->bindParam(":id", $id);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Unidade nao encontrada."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode(["status" => "sucesso", "mensagem" => "Unidade removida com sucesso."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(["status" => "erro", "mensagem" => "Nao e possivel deletar: unidade pode estar vinculada a um evento."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}