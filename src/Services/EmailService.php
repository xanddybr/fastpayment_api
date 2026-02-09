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
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@misturadeluz.com';
            $mail->Password   = 'Mistura#1';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Destinatários
            $mail->setFrom('contato@misturadeluz.com', 'FastPayment');
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
}