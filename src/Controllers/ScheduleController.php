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

    /**
     * POST /schedules (admin)
     * Cria um novo agendamento.
     */
    public function store(Request $request, Response $response) {
        date_default_timezone_set('America/Sao_Paulo');

        $data = $request->getParsedBody() ?? [];

        $required = ['scheduled_at', 'event_id', 'event_type_id', 'unit_id', 'vacancies', 'duration_minutes'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->jsonResponse($response, ['error' => "Campo obrigatório ausente: {$field}"], 400);
            }
        }

        if (strtotime($data['scheduled_at']) < (time() + 3600)) {
            return $this->jsonResponse($response, ['error' => 'Agendamento requer 1h de antecedência.'], 400);
        }

        try {
            $this->scheduleModel->create([
                'scheduled_at'     => $data['scheduled_at'],
                'event_id'         => (int) $data['event_id'],
                'unit_id'          => (int) $data['unit_id'],
                'event_type_id'    => (int) $data['event_type_id'],
                'vacancies'        => (int) $data['vacancies'],
                'duration_minutes' => (int) $data['duration_minutes'],
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => "Agendamento de {$data['duration_minutes']}min salvo com sucesso!",
            ], 201);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /schedules (admin)
     * Lista todos os agendamentos sem filtro de status/vagas.
     */
    public function listAdminSchedules(Request $request, Response $response) {
        try {
            $this->scheduleModel->closeExpiredSchedules();
            $data = $this->scheduleModel->getAllAdmin();
            return $this->jsonResponse($response, $data);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/schedules (público)
     * Lista apenas agendamentos disponíveis, com vagas e no futuro.
     */
    public function listAvailableSchedules(Request $request, Response $response) {
        try {
            $this->scheduleModel->closeExpiredSchedules();
            $params = $request->getQueryParams();
            $data   = $this->scheduleModel->getAvailable(
                $params['slug'] ?? null,
                $params['type'] ?? null
            );
            return $this->jsonResponse($response, $data);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/cron/schedules-cleanup
     * Endpoint chamado por cron externo para fechar agendamentos expirados.
     */
    public function closeExpiredSchedules(Request $request, Response $response) {
        try {
            $this->scheduleModel->closeExpiredSchedules();
            return $this->jsonResponse($response, ['success' => true, 'message' => 'Agendamentos expirados fechados.']);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /schedules/{id} (admin)
     */
    public function delete(Request $request, Response $response, array $args) {
        try {
            $this->scheduleModel->delete($args['id']);
            return $this->jsonResponse($response, ['status' => 'sucesso']);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function jsonResponse(Response $response, $data, $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}