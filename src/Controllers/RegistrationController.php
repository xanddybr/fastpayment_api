<?php

namespace App\Controllers;

use App\Config\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class RegistrationController {

    // Método auxiliar para respostas JSON (Manter consistência)
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * FINALIZAÇÃO DE CADASTRO (CLIENTE)
     * Card 05: Salva detalhes profissionais e endereço após validação/pagamento
     */
    public function finalizeRegistration(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $fullName = $data['full_name'] ?? null;

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // 1. Busca a pessoa e o nome do último evento aprovado
            $stmt = $db->prepare("
                SELECT 
                    p.id as person_id_found, 
                    e.name as last_event 
                FROM persons p
                LEFT JOIN transactions t ON p.id = t.person_id AND t.payment_status = 'approved'
                LEFT JOIN schedules s ON t.schedule_id = s.id
                LEFT JOIN events e ON s.event_id = e.id
                WHERE p.email = :email
                ORDER BY t.created_at DESC 
                LIMIT 1
            ");
            $stmt->execute(['email' => $email]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRecord || !isset($userRecord['person_id_found'])) {
                throw new \Exception("Usuário não localizado no sistema com o e-mail: " . $email);
            }

            $personId = $userRecord['person_id_found'];
            $isExistingUser = !empty($userRecord['last_event']);
            $firstTime = $isExistingUser ? 0 : 1;

            // 2. Atualiza nome completo
            $stmtUpdate = $db->prepare("UPDATE persons SET full_name = :name WHERE id = :id");
            $stmtUpdate->execute(['name' => $fullName, 'id' => $personId]);

            // 3. Insere ou Atualiza detalhes na person_details
            $sqlDetails = "INSERT INTO person_details 
                (person_id, activity_professional, phone, street, number, neighborhood, city, obs_motived, first_time) 
                VALUES (:pid, :act, :ph, :st, :num, :neigh, :city, :obs, :ft)
                ON DUPLICATE KEY UPDATE 
                activity_professional = VALUES(activity_professional), 
                phone = VALUES(phone), 
                street = VALUES(street), 
                number = VALUES(number),
                neighborhood = VALUES(neighborhood),
                city = VALUES(city), 
                obs_motived = VALUES(obs_motived),
                first_time = 0";
            
            $stmtDet = $db->prepare($sqlDetails);
            $stmtDet->execute([
                'pid'   => $personId,
                'act'   => $data['activity_professional'] ?? null,
                'ph'    => $data['phone'] ?? null,
                'st'    => $data['street'] ?? null,
                'num'   => $data['number'] ?? null,
                'neigh' => $data['neighborhood'] ?? null,
                'city'  => $data['city'] ?? null,
                'obs'   => $data['obs_motived'] ?? null,
                'ft'    => $firstTime
            ]);

            $db->commit();

            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "is_returning_user" => $isExistingUser,
                "message" => "Dados salvos com sucesso!"
            ]);

        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * ESTATÍSTICAS E LISTAGEM DO DASHBOARD (ADMIN)
     * Card 07: Fornece dados consolidados para o painel administrativo
     */
    public function dashboardStats(Request $request, Response $response) {
        try {
            $db = Database::getConnection();

            // Query que une as tabelas para criar o relatório completo
            $sql = "SELECT 
                        r.id as registration_id,
                        p.full_name as client_name,
                        p.email as client_email,
                        e.name as event_name,
                        s.scheduled_at as event_date,
                        pay.status as payment_status,
                        pay.amount as paid_amount,
                        r.created_at as registration_date
                    FROM registrations r
                    JOIN persons p ON r.person_id = p.id
                    JOIN schedules s ON r.schedule_id = s.id
                    JOIN events e ON s.event_id = e.id
                    LEFT JOIN payments pay ON r.payment_id = pay.id
                    ORDER BY r.created_at DESC";

            $stmt = $db->query($sql);
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cálculos rápidos para o Admin
            $totalRevenue = 0;
            $approvedCount = 0;

            foreach ($list as $item) {
                if ($item['payment_status'] === 'approved') {
                    $totalRevenue += (float)$item['paid_amount'];
                    $approvedCount++;
                }
            }

            return $this->jsonResponse($response, [
                "stats" => [
                    "total_entries" => count($list),
                    "approved_payments" => $approvedCount,
                    "total_revenue" => number_format($totalRevenue, 2, '.', '')
                ],
                "registrations" => $list
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => "Erro no Dashboard: " . $e->getMessage()], 500);
        }
    }
}