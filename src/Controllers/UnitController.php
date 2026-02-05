<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Unit;
use App\Config\Database;
use App\Utils\Slugger;
use Exception;

class UnitController {

    use Slugger;

    // 1. Defina a propriedade privada
    private $db;

    // 2. Inicialize a conexão no construtor (mais limpo e eficiente)
    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function list(Request $request, Response $response) {
        try {
            $unitModel = new Unit($this->db);
            $units = $unitModel->getAll();
            return $this->jsonResponse($response, $units ?: []);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Falha ao listar."], 500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';

            if (empty($name)) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "O nome é obrigatório."], 400);
            }

            // CORREÇÃO AQUI: Agora usamos $this->db que foi carregado no construtor
            $slug = $this->generateUniqueSlug($name, 'units', $this->db);
            
            $unitModel = new Unit($this->db);
            if ($unitModel->create($name, $slug)) {
                return $this->jsonResponse($response, ["status" => "sucesso", "slug" => $slug], 201);
            }
            
            throw new Exception("Erro ao inserir no banco.");
        } catch (Exception $e) {
            // Dica: Adicione $e->getMessage() para debugar o erro real se falhar novamente
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $unitModel = new Unit($this->db);
            
            if ($unitModel->delete($id)) {
                return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Removida com sucesso."]);
            }

            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Não encontrada."], 404);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Erro ao deletar: verifique se há vínculos."], 400);
        }
    }
}