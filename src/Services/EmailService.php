<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Contracts\Services\EmailServiceInterface;
use App\Services\Templates\OtpEmailTemplate;

class EmailService implements EmailServiceInterface
{
    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private int $port,
        private string $fromName
    ) {}

    public function sendOTP(string $toEmail, string $toName, string $code): bool
    {
        $mail = $this->buildMailer();
        try {
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = "🔐 Seu código de verificação — {$this->fromName}";
            $mail->Body    = OtpEmailTemplate::render($toName, $code);
            $mail->AltBody = "Olá {$toName}, seu código é: {$code}. Se não solicitou, ignore este e-mail.";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Erro ao enviar OTP para {$toEmail}: " . $e->getMessage());
            return false;
        }
    }

    public function sendPaymentConfirmation(string $payerEmail, array $eventData): bool
    {
        $mail = $this->buildMailer();
        try {
            $mail->addAddress($payerEmail);
            $mail->isHTML(true);
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

            $mail->clearAddresses();
            $mail->addAddress($this->username, 'Admin');
            $mail->Subject = "NOVA VENDA: " . $eventData['name'];
            $mail->Body    = "
                <h3>Nova vaga reservada!</h3>
                <p><b>Evento:</b> {$eventData['name']}</p>
                <p><b>Cliente:</b> {$payerEmail}</p>
                <p><b>Valor:</b> R$ {$eventData['price']}</p>
            ";
            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de pagamento: " . $e->getMessage());
            return false;
        }
    }

    private function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet    = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $this->port;
        $mail->setFrom($this->username, $this->fromName);
        return $mail;
    }
}
