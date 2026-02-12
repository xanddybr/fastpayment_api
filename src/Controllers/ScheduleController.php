<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Schedule;
use Exception;

class ScheduleController {
    private $scheduleModel;

    public function __construct() {
        $this->scheduleModel = new Schedule();
    }

    public function store(Request $request, Response $response) {
        date_default_timezone_set('America/Sao_Paulo');
        try {
            $data = $request->getParsedBody();
            
            // Validação de Campos
            if (empty($data['scheduled_at']) || empty($data['event_id']) || empty($data['unit_id'])) {
                return $this->jsonResponse($response, ["error" => "Dados incompletos"], 400);
            }

            // Regra de Negócio: 1 hora de antecedência
            $chosenTime = strtotime($data['scheduled_at']);
            if ($chosenTime < (time() + 3600)) {
                return $this->jsonResponse($response, ["error" => "Agendamento requer 1h de antecedência."], 400);
            }

            $this->scheduleModel->create($data);
            return $this->jsonResponse($response, ["success" => true, "message" => "Agendamento salvo!"], 201);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function listAdminSchedules(Request $request, Response $response) {
        $this->scheduleModel->autoCloseExpired();
        $data = $this->scheduleModel->getAllAdmin();
        return $this->jsonResponse($response, $data);
    }

    public function listAvailableSchedules(Request $request, Response $response) {
        $this->scheduleModel->autoCloseExpired();
        $params = $request->getQueryParams();
        
        $data = $this->scheduleModel->getAvailable(
            $params['slug'] ?? null, 
            $params['type'] ?? null
        );
        
        return $this->jsonResponse($response, $data);
    }

    public function delete(Request $request, Response $response, array $args) {
        $success = $this->scheduleModel->delete($args['id']);
        return $this->jsonResponse($response, ["status" => $success ? "sucesso" : "erro"]);
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}