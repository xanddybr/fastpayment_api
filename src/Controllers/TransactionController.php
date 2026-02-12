<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Transaction as TransactionModel;
use Exception;

class TransactionController {
    
    private $transactionModel;

    public function __construct() {
        $this->transactionModel = new TransactionModel();
    }

    public function createPayment(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;

        if (!$scheduleId || !$email) {
            return $this->jsonResponse($response, ["error" => "Dados insuficientes."], 400);
        }

        // Token do .env (Sempre use trim para evitar espaços em branco fatais)
        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

        try {
            // 1. Busca dados via Model
            $event = $this->transactionModel->getEventDetailsBySchedule($scheduleId);

            if (!$event) {
                return $this->jsonResponse($response, ["error" => "Agendamento não encontrado."], 404);
            }

            // 2. PAYLOAD PARA MERCADO PAGO
            $preferenceData = [
                "items" => [[
                    "title"       => (string)$event['name'],
                    "quantity"    => 1,
                    "unit_price"  => (float)$event['price'],
                    "currency_id" => "BRL"
                ]],
                "payer" => ["email" => (string)$email],
                "external_reference" => "FP-" . time() . "-" . $scheduleId,
                // Opcional: Redirecionamentos após pagar
                "back_urls" => [
                    "success" => "http://localhost:5173/success",
                    "failure" => "http://localhost:5173/failure"
                ],
                "auto_return" => "approved"
            ];

            // 3. CHAMADA À API (Centralizada)
            $mp = $this->callMercadoPagoAPI($accessToken, $preferenceData);

            if (isset($mp['init_point'])) {
                return $this->jsonResponse($response, ["init_point" => $mp['init_point']]);
            } 
            
            return $this->jsonResponse($response, ["error" => "MP: " . ($mp['message'] ?? 'Erro API')], 400);

        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Encapsula o cURL para o código ficar limpo
     */
    private function callMercadoPagoAPI($token, $payload) {
        $ch = curl_init("https://api.mercadopago.com/checkout/preferences");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /**
     * Recebe as notificações automáticas do Mercado Pago
     */
    public function webhook(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $accessToken = trim($_ENV['MP_ACCESS_TOKEN'] ?? '');

        // O MP envia o ID da operação via Query Param ou Body
        $paymentId = $params['data_id'] ?? $params['id'] ?? null;
        $type = $params['type'] ?? null;

        // Só nos interessa notificações de "payment"
        if ($paymentId && ($type === 'payment' || isset($params['data_id']))) {
            
            // 1. CONSULTAR O STATUS REAL NO MERCADO PAGO (Segurança)
            $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $accessToken
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $paymentData = json_decode($result, true);
            curl_close($ch);

            if (isset($paymentData['status'])) {
                $status = $paymentData['status']; // 'approved', 'pending', etc.
                $externalReference = $paymentData['external_reference'];

                // 2. ATUALIZAR SEU BANCO DE DADOS VIA MODEL
                $this->transactionModel->updatePaymentStatus($externalReference, $status, $paymentId);
            }
        }

        // O Mercado Pago exige um retorno 200 ou 201 para parar de enviar a mesma notificação
        return $response->withStatus(200);
    }
}