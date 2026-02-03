<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config\Database;
use PDO;

class TransactionController {
    
    public function createPayment(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        if (!$scheduleId || !$email) {
            return $this->jsonResponse($response, ["error" => "Dados insuficientes."], 400);
        }

        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');
        $db = Database::getConnection();

        try {
            // QUERY CORRIGIDA: event_id em schedules liga com id em events
            $stmt = $db->prepare("
                SELECT e.name, e.price 
                FROM schedules s 
                JOIN events e ON s.event_id = e.id 
                WHERE s.id = :sid
            ");
            $stmt->execute(['sid' => $scheduleId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                return $this->jsonResponse($response, ["error" => "Agendamento não encontrado."], 404);
            }

            // PAYLOAD PARA MERCADO PAGO
            $preferenceData = [
                "items" => [[
                    "title"       => (string)$event['name'],
                    "quantity"    => 1,
                    "unit_price"  => (float)$event['price'],
                    "currency_id" => "BRL"
                ]],
                "payer" => ["email" => (string)$email],
                "external_reference" => "FP-" . time() . "-" . $scheduleId
            ];

            // CHAMADA À API
            $ch = curl_init("https://api.mercadopago.com/checkout/preferences");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $accessToken
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

            $raw = curl_exec($ch);
            $mp = json_decode($raw, true);
            curl_close($ch);

            if (isset($mp['init_point'])) {
                return $this->jsonResponse($response, ["init_point" => $mp['init_point']]);
            } else {
                return $this->jsonResponse($response, ["error" => "MP: " . ($mp['message'] ?? 'Erro API')], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    private function jsonResponse($response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}