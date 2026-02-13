<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Registration;

class PaymentController {

    /**
     * Card 06.1: Webhook para receber notificações do Mercado Pago
     */
    public function webhook(Request $request, Response $response) {
        $params = $request->getQueryParams();
        
        // O MP envia o ID do pagamento via 'data.id' ou 'id'
        $paymentId = $params['data_id'] ?? $params['id'] ?? null;
        $type = $params['type'] ?? $params['topic'] ?? null;

        if ($type === 'payment' && $paymentId) {
            // 1. Aqui você faria a consulta na API do Mercado Pago usando o $paymentId
            // para confirmar se o status é 'approved'
            
            // Simulação de resposta aprovada para o seu teste:
            $isApproved = true; 

            if ($isApproved) {
                // 2. Recuperar os metadados (PersonID e ScheduleID)
                // Geralmente você envia isso no 'external_reference' ao criar o link
                // Ex: "PERSON_7_SCHED_1"
                
                $registrationModel = new Registration();
                // $registrationModel->completeSubscription($personId, $scheduleId, $paymentId);
            }
        }

        // O Mercado Pago exige um 200 ou 201 para parar de enviar notificações
        return $response->withStatus(200);
    }
}