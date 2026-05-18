<?php
namespace App\Controllers;

use App\Contracts\Repositories\ScheduleRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ScheduleController
{
    public function __construct(private ScheduleRepositoryInterface $scheduleRepo) {}

    public function store(Request $request, Response $response): Response
    {
        date_default_timezone_set('America/Sao_Paulo');
        $data = $request->getParsedBody() ?? [];

        $required = ['scheduled_at', 'event_id', 'event_type_id', 'unit_id', 'vacancies', 'duration_minutes'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->json($response, ['error' => "Campo obrigatório ausente: {$field}"], 400);
            }
        }

        try {
            $this->scheduleRepo->create([
                'scheduled_at'     => $data['scheduled_at'],
                'event_id'         => (int) $data['event_id'],
                'unit_id'          => (int) $data['unit_id'],
                'event_type_id'    => (int) $data['event_type_id'],
                'vacancies'        => (int) $data['vacancies'],
                'duration_minutes' => (int) $data['duration_minutes'],
            ]);
            return $this->json($response, [
                'success' => true,
                'message' => "Agendamento de {$data['duration_minutes']}min salvo com sucesso!",
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function listAdminSchedules(Request $request, Response $response): Response
    {
        try {
            $this->scheduleRepo->closeExpired();
            return $this->json($response, $this->scheduleRepo->getAllAdmin());
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function listAvailableSchedules(Request $request, Response $response): Response
    {
        try {
            $this->scheduleRepo->closeExpired();
            $params = $request->getQueryParams();
            return $this->json($response, $this->scheduleRepo->getAvailable(
                $params['slug'] ?? null,
                $params['type'] ?? null
            ));
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function closeExpiredSchedules(Request $request, Response $response): Response
    {
        try {
            $this->scheduleRepo->closeExpired();
            return $this->json($response, ['success' => true, 'message' => 'Agendamentos expirados fechados.']);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->scheduleRepo->delete((int) $args['id']);
            return $this->json($response, ['status' => 'sucesso']);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
