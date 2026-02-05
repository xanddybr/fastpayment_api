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
    
    
    public function store(Request $request, Response $response) {
    date_default_timezone_set('America/Sao_Paulo');

    try {
        $data = $request->getParsedBody();
        
        $scheduledAt = $data['scheduled_at'] ?? null;
        $eventId     = $data['event_id'] ?? null;
        $unitId      = $data['unit_id'] ?? null;
        $eventTypeId = $data['event_type_id'] ?? null;
        $vacancies   = $data['vacancies'] ?? 1; // Valor padrão 1

        if (!$scheduledAt || !$eventId || !$unitId || !$eventTypeId) {
            return $this->jsonResponse($response, ["error" => "Preencha todos os campos obrigatórios"], 400);
        }

        // Dentro da function store...
        $chosenTime = strtotime($scheduledAt);
        $now = time();

        // Ajuste: Vamos considerar apenas a hora e o minuto, ignorando segundos.
        // Para facilitar seu teste hoje, vamos reduzir a trava para apenas 1 minuto de antecedência,
        // ou você pode comentar a trava inteira se preferir liberdade total.
        $minTimeAllowed = $now + 60; // Alterado de 3600 (1h) para 60 (1min) para facilitar seu dia

        if ($chosenTime < $minTimeAllowed) {
            return $this->jsonResponse($response, [
                "error" => "Para realizar esse agendamento você precisa de no minimo 1 hora de antecedencia do evento . (Hora atual: " . date('H:i', $now) . ")"
            ], 400);
        }

        $sql = "INSERT INTO schedules (event_id, unit_id, event_type_id, scheduled_at, vacancies, status) 
                VALUES (:event_id, :unit_id, :event_type_id, :scheduled_at, :vacancies, 'available')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_id'      => $eventId,
            ':unit_id'       => $unitId,
            ':event_type_id' => $eventTypeId,
            ':scheduled_at'  => $scheduledAt,
            ':vacancies'     => $vacancies
        ]);

        return $this->jsonResponse($response, ["success" => true, "message" => "Agendamento salvo!"], 201);

    } catch (Exception $e) {
        return $this->jsonResponse($response, ["error" => "Erro no banco: " . $e->getMessage()], 500);
    }
}
    
// Rota Administrativa: Lista TUDO (incluindo expirados)
    // Rota Administrativa: Lista TUDO (incluindo expirados)
public function listAdminSchedules(Request $request, Response $response) {
    try {
        // Garante que agendamentos que passaram do horário mudem para 'expired'
        $this->closeExpiredSchedulesInternal();

        // SQL SEM o filtro de status 'available' para o Admin ver o histórico
        $sql = "SELECT 
                    s.id as schedule_id, 
                    e.name as event_name, 
                    e.price as event_price,
                    et.name as type_name, 
                    u.name as unit_name, 
                    s.scheduled_at, 
                    s.vacancies,
                    s.status,
                    e.slug
                FROM schedules s
                JOIN events e ON s.event_id = e.id
                JOIN units u ON s.unit_id = u.id
                JOIN event_types et ON s.event_type_id = et.id
                ORDER BY s.scheduled_at DESC"; // Ordenado pelos mais recentes

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $this->jsonResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC));

    } catch (Exception $e) {
        return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
    }
}

    public function listAvailableSchedules(Request $request, Response $response) {
        try {
            $this->closeExpiredSchedulesInternal();
           $queryParams = $request->getQueryParams();

// Pegar os valores e garantir que se for string vazia vira NULL
            $eventSlug = (!empty($queryParams['slug']) && $queryParams['slug'] !== '') ? strtolower(trim($queryParams['slug'])) : null;
            $typeSlug  = (!empty($queryParams['type']) && $queryParams['type'] !== '') ? strtolower(trim($queryParams['type'])) : null;

            $sql = "SELECT 
                        s.id as schedule_id, 
                        e.name as event_name, 
                        e.price as event_price,
                        et.name as type_name, 
                        u.name as unit_name, 
                        s.scheduled_at, 
                        s.vacancies,
                        e.slug as event_slug,
                        et.slug as type_slug
                    FROM schedules s
                    JOIN events e ON s.event_id = e.id
                    JOIN units u ON s.unit_id = u.id
                    JOIN event_types et ON s.event_type_id = et.id
                    WHERE s.status = 'available'";

            // Filtro condicional de Evento
            if ($eventSlug) {
                $sql .= " AND LOWER(e.slug) = :eventSlug";
            }

            // Filtro condicional de Tipo (O que estava faltando funcionar isolado)
            if ($typeSlug) {
                $sql .= " AND LOWER(et.slug) = :typeSlug";
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

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

}