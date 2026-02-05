<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config\Database;
use App\Models\EventType;
use App\Utils\Slugger;
use Exception; // Importação necessária para o catch funcionar

class EventTypeController {

    use Slugger;

    // 1. ADICIONE A PROPRIEDADE
    private $db;

    // 2. ADICIONE O CONSTRUTOR PARA INICIALIZAR O DB
    public function __construct() {
        $this->db = Database::getConnection();
    }

    // --- TIPOS DE EVENTO (Workshop, Palestra, etc) ---
    public function list(Request $request, Response $response) {
        try {
            // Agora $this->db existe e funciona!
            $model = new EventType($this->db);
            $types = $model->getAll();
            
            return $this->jsonResponse($response, $types ?: []);
        } catch (Exception $e) {
            return $this->jsonResponse($response, [
                "status" => "erro", 
                "mensagem" => "Erro ao listar tipos: " . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? null;

            if (empty($name)) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Nome do tipo é obrigatório."], 400);
            }

            // 3. GERAÇÃO DO SLUG (Usando o Trait que você adicionou)
            $slug = $this->generateUniqueSlug($name, 'event_types', $this->db);

            $model = new EventType($this->db);
            // Certifique-se que seu Model EventType aceita o slug no método create
            $model->create($name, $slug);

            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Tipo de evento criado."], 201);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $stmt = $this->db->prepare("DELETE FROM event_types WHERE id = :id");
            $stmt->bindParam(":id", $id);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Tipo não encontrado."], 404);
            }

            return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Tipo removido."]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 400);
        }
    }

    // Método auxiliar centralizado
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}