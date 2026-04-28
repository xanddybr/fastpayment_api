<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Services\Templates\OtpEmailTemplate;

class EmailService {

    /**
     * Sends the OTP verification email using the Mistura de Luz template.
     */
    public static function sendOTP($toEmail, $toName, $code) {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet    = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@misturadeluz.com';
            $mail->Password   = 'Mistura#1';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('contato@misturadeluz.com', 'Mistura de Luz');
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = "🔐 Seu código de verificação — Mistura de Luz";

            // ✅ REQ-009: Beautiful HTML template
            $mail->Body    = OtpEmailTemplate::render($toName, $code);

            // Plain text fallback for email clients that don't support HTML
            $mail->AltBody = "Olá {$toName}, seu código de verificação é: {$code}. Válido por 5 minutos. Se não solicitou este código, ignore este e-mail.";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar OTP para {$toEmail}: " . $e->getMessage());
            return false;
        }
    }

    public static function sendPaymentConfirmation($payerEmail, $eventData) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@misturadeluz.com';
            $mail->Password   = 'Mistura#1';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->setFrom('contato@misturadeluz.com', 'Mistura de Luz');
            $mail->isHTML(true);

            // Email to client
            $mail->addAddress($payerEmail);
            $mail->Subject = "Pagamento Confirmado: " . $eventData['name'];
            $mail->Body    = "
                <h2>Sua vaga está garantida!</h2>
                <p>Olá, identificamos seu pagamento para o evento: <b>{$eventData['name']}</b>.</p>
                <p><b>Data:</b> " . date('d/m/Y H:i', strtotime($eventData['scheduled_at'])) . "h</p>
                <p><b>Local:</b> {$eventData['unit_name']}</p>
                <hr>
                <p><b>IMPORTANTE:</b> Para concluir sua participação, preencha sua ficha de inscrição:</p>
                <a href='https://misturadeluz.com/beta' style='background:#7c3aed;color:white;padding:10px 20px;text-decoration:none;border-radius:10px;'>Concluir Inscrição Agora</a>
            ";
            $mail->send();

            // Email to admin
            $mail->clearAddresses();
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