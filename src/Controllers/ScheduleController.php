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
        try {
            $data = $request->getParsedBody();
            
            $eventId     = $data['event_id'] ?? null;
            $unitId      = $data['unit_id'] ?? null;
            $eventTypeId = $data['event_type_id'] ?? null;
            $scheduledAt = $data['scheduled_at'] ?? null;
            $vacancies   = $data['vacancies'] ?? 0;
            $price       = $data['price'] ?? null; // Adicionado caso venha do form

            // Validação básica
            if (!$eventTypeId || !$eventId || !$unitId || !$scheduledAt) {
                return $this->jsonResponse($response, [
                    "status" => "erro", 
                    "mensagem" => "Dados incompletos",
                    "recebido" => $data
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
            return $this->jsonResponse($response, [
                "status" => "erro", 
                "mensagem" => "Erro ao salvar: " . $e->getMessage()
            ], 500);
        }
    }

    public function listAvailableSchedules(Request $request, Response $response) {
        try {
            $this->closeExpiredSchedulesInternal(); 

            $sql = "SELECT 
                        s.*, 
                        e.name as event_name, 
                        e.price as event_price, 
                        u.name as unit_name,
                        et.name as event_type_name
                    FROM schedules s
                    INNER JOIN events e ON s.event_id = e.id
                    INNER JOIN units u ON s.unit_id = u.id
                    INNER JOIN event_types et ON s.event_type_id = et.id
                    ORDER BY s.scheduled_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, $schedules ?: []);
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

    public function listSchedules(Request $request, Response $response) {
        try {
            $queryParams = $request->getQueryParams();
            $slug = $queryParams['slug'] ?? null;

            $sql = "SELECT 
                        s.id as schedule_id, 
                        e.name as event_name, 
                        e.price as event_price, 
                        u.name as unit_name, 
                        s.scheduled_at, 
                        s.vacancies,
                        e.slug
                    FROM schedules s
                    JOIN events e ON s.event_id = e.id
                    JOIN units u ON s.unit_id = u.id
                    WHERE s.status = 'available'";

            if ($slug) {
                $sql .= " AND e.slug = :slug";
            }

            $sql .= " ORDER BY s.scheduled_at ASC";
            $stmt = $this->db->prepare($sql);

            if ($slug) {
                $stmt->bindParam(':slug', $slug);
            }

            $stmt->execute();
            return $this->jsonResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }
}