<?php
// misturadeluz.com/fastpayment/api/webhook.php
require 'vendor/autoload.php'; // Se usar Slim/PHPMailer

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// O Mercado Pago envia o ID do pagamento
$resourceId = $data['data']['id'] ?? ($data['id'] ?? null);

if ($resourceId) {
    // 1. Consultar o Mercado Pago via SDK ou CURL para confirmar o status 'approved'
    // 2. No banco de dados, marcar a transaction como 'approved'
    // 3. Importante: Atualizar as vagas (Schedules) aqui!
    // 4. Disparar o EmailService::sendPaymentConfirmation para Cliente e Admins (Type 1)
}

http_response_code(200); // Responde 200 para o MP parar de enviar