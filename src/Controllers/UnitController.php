<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Unit;
use App\Utils\Slugger;
use Exception;

class UnitController {

    use Slugger;

    private $unitModel;

    public function __construct() {
        // O Model já se resolve sozinho com o Singleton
        $this->unitModel = new Unit();
    }

    public function list(Request $request, Response $response) {
        try {
            $units = $this->unitModel->getAll();
            return $this->jsonResponse($response, $units ?: []);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Falha ao listar unidades."], 500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';

            if (empty($name)) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "O nome é obrigatório."], 400);
            }

            // Usamos a conexão do Singleton para o gerador de Slugs
            $slug = $this->generateUniqueSlug($name, 'units', $this->unitModel->getConnection());
            
            if ($this->unitModel->create($name, $slug)) {
                return $this->jsonResponse($response, [
                    "status" => "sucesso", 
                    "mensagem" => "Unidade criada!",
                    "slug" => $slug
                ], 201);
            }
            
            throw new Exception("Erro ao inserir no banco.");
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            
            // Verifica existência
            if (!$this->unitModel->findById($id)) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Unidade não encontrada."], 404);
            }

            $this->unitModel->delete($id);
            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Removida com sucesso."]);
            
        } catch (Exception $e) {
            // Tratamento específico para restrição de chave estrangeira (se houver eventos na unidade)
            return $this->jsonResponse($response, [
                "status" => "erro", 
                "mensagem" => "Não é possível excluir: existem eventos vinculados a esta unidade."
            ], 400);
        }
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}