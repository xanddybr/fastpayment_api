<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config\Database;
use Exception;
use PDO; 

class ScheduleController {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // Método auxiliar para padronizar respostas JSON
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
    
        public function store(Request $request, Response $response) {
        // 1. Configura o fuso horário para garantir que time() bata com o Brasil
        date_default_timezone_set('America/Sao_Paulo');

        try {
            $data = $request->getParsedBody();
            
            // --- ADICIONE ESTAS LINHAS (O QUE FALTAVA) ---
            $scheduledAt = $data['scheduled_at'] ?? null;
            $eventId     = $data['event_id'] ?? null;
            $unitId      = $data['unit_id'] ?? null;
            $eventTypeId = $data['event_type_id'] ?? null;
            $vacancies   = $data['vacancies'] ?? 0;
            // ---------------------------------------------

            if (!$scheduledAt || !$eventId || !$unitId || !$eventTypeId) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Dados incompletos"], 400);
            }

            $chosenTime = strtotime($scheduledAt);
            $minTimeAllowed = time() + 3600;

            if ($chosenTime < $minTimeAllowed) {
                return $this->jsonResponse($response, [
                    "status" => "erro", 
                    "mensagem" => "O agendamento exige antecedência mínima de 1 hora. Verifique o relógio do servidor."
                ], 400);
            }

            $sql = "INSERT INTO schedules (
                        event_id, 
                        unit_id, 
                        event_type_id, 
                        scheduled_at, 
                        vacancies, 
                        status
                    ) VALUES (:event_id, :unit_id, :event_type_id, :scheduled_at, :vacancies, 'available')";
            
            $stmt = $this->db->prepare($sql);
            // Agora as variáveis abaixo existem!
            $stmt->execute([
                ':event_id'      => $eventId,
                ':unit_id'       => $unitId,
                ':event_type_id' => $eventTypeId,
                ':scheduled_at'  => $scheduledAt,
                ':vacancies'     => $vacancies
            ]);

            return $this->jsonResponse($response, [
                "status" => "sucesso", 
                "mensagem" => "Agendamento salvo!",
                "id" => $this->db->lastInsertId()
            ], 201);

        } catch (Exception $e) {
            // Isso evita o "erro de conexão" e mostra o erro real do PHP
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }
    
    public function listAvailableSchedules(Request $request, Response $response) {
        try {
            $this->closeExpiredSchedulesInternal();
            
            $queryParams = $request->getQueryParams();
            $eventSlug = $queryParams['slug'] ?? null;
            $typeSlug  = $queryParams['type'] ?? null;

            $sql = "SELECT 
                        s.id as schedule_id, 
                        e.name as event_name, 
                        e.price as event_price, -- ADICIONE ESTA LINHA AQUI
                        et.name as type_name, 
                        u.name as unit_name, 
                        s.scheduled_at, 
                        s.vacancies,
                        e.slug
                    FROM schedules s
                    JOIN events e ON s.event_id = e.id
                    JOIN units u ON s.unit_id = u.id
                    JOIN event_types et ON s.event_type_id = et.id
                    WHERE s.status = 'available'";

            if ($eventSlug) {
                $sql .= " AND e.slug = :eventSlug";
            }
            if ($typeSlug) {
                $sql .= " AND et.slug = :typeSlug";
            }

            $stmt = $this->db->prepare($sql);
            if ($eventSlug) $stmt->bindValue(':eventSlug', $eventSlug);
            if ($typeSlug)  $stmt->bindValue(':typeSlug', $typeSlug);

            $stmt->execute();
            return $this->jsonResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function closeExpiredSchedulesInternal() {
        try {
            $stmt = $this->db->prepare("
                UPDATE schedules 
                SET status = 'unavailable' 
                WHERE scheduled_at < NOW() 
                AND status = 'available'
            ");
            $stmt->execute();
        } catch (Exception $e) { }
    }

    public function closeExpiredSchedules(Request $request, Response $response) {   
        $this->closeExpiredSchedulesInternal();
        return $this->jsonResponse($response, ["status" => "limpeza concluída"]);
    }
    
    public function delete(Request $request, Response $response, array $args) {
        try {
            // Captura o ID que vem na URL (ex: /schedules/15)
            $id = $args['id'] ?? null;

            if (!$id) {
                return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "ID inválido"], 400);
            }

            $stmt = $this->db->prepare("DELETE FROM schedules WHERE id = :id");
            $stmt->execute([':id' => $id]);

            return $this->jsonResponse($response, [
                "status" => "sucesso", 
                "mensagem" => "Agendamento excluído com sucesso!"
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, [
                "status" => "erro", 
                "mensagem" => "Erro ao excluir: " . $e->getMessage()
            ], 500);
        }
    }
}