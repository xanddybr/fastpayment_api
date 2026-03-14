<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    
    /**
     * Envia o código de validação (OTP)
     */
    public static function sendOTP($toEmail, $toName, $code) {
        $mail = new PHPMailer(true);
        try {
            // Configurações do Servidor (Centralizadas aqui!)
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@misturadeluz.com';
            $mail->Password   = 'Mistura#1';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Destinatários
            $mail->setFrom('contato@misturadeluz.com', 'Mistura de Luz');
            $mail->addAddress($toEmail, $toName);

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = "Seu codigo de acesso: $code";
            $mail->Body    = "Olá $toName, seu código de validação é: <b>$code</b>. <br>Válido por 5 minutos.";
            
            return $mail->send();
        } catch (Exception $e) {
            // Aqui você poderia logar o erro: error_log($e->getMessage());
            return false;
        }
    }

    public static function sendPaymentConfirmation($payerEmail, $eventData) {
        $mail = new PHPMailer(true);
        try {
            // Configurações do Servidor
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@misturadeluz.com';
            $mail->Password   = 'Mistura#1';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->setFrom('contato@misturadeluz.com', 'Mistura de Luz');
            $mail->isHTML(true);

            // --- 1. E-MAIL PARA O CLIENTE ---
            $mail->addAddress($payerEmail);
            $mail->Subject = "Pagamento Confirmado: " . $eventData['name'];
            $mail->Body    = "
                <h2>Sua vaga está garantida!</h2>
                <p>Olá, identificamos seu pagamento para o evento: <b>{$eventData['name']}</b>.</p>
                <p><b>Data:</b> " . date('d/m/Y H:i', strtotime($eventData['scheduled_at'])) . "h</p>
                <p><b>Local:</b> {$eventData['unit_name']}</p>
                <hr>
                <p><b>IMPORTANTE:</b> Para concluir sua participação, você deve preencher sua ficha de inscrição clicando no link abaixo:</p>
                <a href='https://misturadeluz.com/agenda' style='background:#7c3aed; color:white; padding:10px 20px; text-decoration:none; border-radius:10px;'>Concluir Inscrição Agora</a>
            ";
            $mail->send();

            // --- 2. E-MAIL PARA O VENDEDOR (Admin) ---
            $mail->clearAddresses(); // Limpa o destinatário anterior
            $mail->addAddress('contato@misturadeluz.com', 'Admin Mistura');
            $mail->Subject = "NOVA VENDA: " . $eventData['name'];
            $mail->Body    = "
                <h3>Nova vaga reservada!</h3>
                <p><b>Evento:</b> {$eventData['name']}</p>
                <p><b>Cliente:</b> {$payerEmail}</p>
                <p><b>Valor:</b> R$ {$eventData['price']}</p>
                <p>A vaga já foi automaticamente subtraída do sistema.</p>
            ";
            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de pagamento: " . $e->getMessage());
            return false;
        }
    }
}