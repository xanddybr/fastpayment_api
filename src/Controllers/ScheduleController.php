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
            
            // 1. Validação de Campos Ampliada
            // Verificamos se os novos campos (vacancies e duration_minutes) também estão presentes
           if (
                !isset($data['scheduled_at']) || 
                !isset($data['event_id']) || 
                !isset($data['event_type_id']) ||
                !isset($data['unit_id']) ||
                !isset($data['vacancies']) || $data['vacancies'] === '' ||
                !isset($data['duration_minutes']) || $data['duration_minutes'] === ''
            ) {
                return $this->jsonResponse($response, ["error" => "Dados incompletos no formulário."], 400);
            }

            // 2. Regra de Negócio: 1 hora de antecedência
            $chosenTime = strtotime($data['scheduled_at']);
            if ($chosenTime < (time() + 3600)) {
                return $this->jsonResponse($response, ["error" => "Agendamento requer 1h de antecedência."], 400);
            }

            // 3. Preparação do Payload para o Model
            // Garantimos que os valores numéricos sejam tratados corretamente
            $payload = [
                'scheduled_at'     => $data['scheduled_at'],
                'event_id'         => (int)$data['event_id'],
                'unit_id'          => (int)$data['unit_id'],
                'event_type_id'    => (int)$data['event_type_id'],
                'vacancies'        => (int)$data['vacancies'],
                'duration_minutes' => (int)$data['duration_minutes'],
                'status'           => 'available' // Status padrão para novos horários
            ];

            // 4. Criação no Banco
            $this->scheduleModel->create($payload);
            
            return $this->jsonResponse($response, [
                "success" => true, 
                "message" => "Agendamento de " . $payload['duration_minutes'] . "min salvo com sucesso!"
            ], 201);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function listAdminSchedules(Request $request, Response $response) {

        $this->scheduleModel->syncSchedulesStatus();
        $data = $this->scheduleModel->getAllAdmin();
        return $this->jsonResponse($response, $data);
    }

    public function listAvailableSchedules(Request $request, Response $response) {
        
        $this->scheduleModel->syncSchedulesStatus();
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