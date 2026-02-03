<?php
namespace App\Controllers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use libphonenumber\PhoneNumberUtil;

class AuthController {

    // Função auxiliar interna (MANTENHA AQUI NO TOPO DA CLASSE)
    private function jsonResponse($response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    // --- LOGIN ADMINISTRATIVO ---
    public function login(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT * FROM persons WHERE email = :email AND status = 'active'");
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['last_activity'] = time();

                return $this->jsonResponse($response, [
                    "status" => "sucesso",
                    "mensagem" => "Login realizado",
                    "user" => ["id" => $user['id'], "name" => $user['full_name'], "email" => $user['email']]
                ]);
            }

            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "E-mail ou senha invalidos."], 401);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Erro interno: " . $e->getMessage()], 500);
        }
    }

    // --- LOGOUT ---
    public function logout(Request $request, Response $response) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        return $this->jsonResponse($response, ["status" => "sucesso", "mensagem" => "Logout realizado"]);
    }

    // --- LISTAR USUÁRIOS ---
    public function listUsers(Request $request, Response $response) {
        $db = (new Database())->getConnection();
        $stmt = $db->query("SELECT id, full_name, email, status FROM persons");
        return $this->jsonResponse($response, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // --- GERAR CÓDIGO OTP ---
    public function generateValidationCode(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $fullName = $data['nome'] ?? 'Cliente';
        $email = $data['email'] ?? null;
        $phone = $data['telefone'] ?? null; 
        
        $validator = new EmailValidator();
        if (!$email || !$validator->isValid($email, new RFCValidation())) {
            return $this->jsonResponse($response, ["error" => "E-mail invalido"], 400);
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $numberProto = $phoneUtil->parse($phone, "BR");
            $phoneFormatted = $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => "Telefone invalido"], 400);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+5 minutes"));

        $db = (new Database())->getConnection();
        $db->prepare("UPDATE registered_codes SET status = 'substituido' WHERE email = :email AND status = 'pendente'")->execute(['email' => $email]);
        $stmt = $db->prepare("INSERT INTO registered_codes (email, phone, validation_method, code, expires_at, status) VALUES (:email, :phone, 'email', :code, :expiresAt, 'pendente')");
        $stmt->execute(['email' => $email, 'phone' => $phoneFormatted, 'code' => $code, 'expiresAt' => $expiresAt]);

        if ($this->sendEmail($email, $fullName, $code)) {
            return $this->jsonResponse($response, ["status" => "sucesso", "message" => "Codigo enviado!"]);
        }
        return $this->jsonResponse($response, ["error" => "Falha no envio"], 500);
    }

    // --- VALIDAR CÓDIGO ---
    public function validateCode(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT * FROM registered_codes WHERE email = :email AND code = :code AND status = 'pendente' LIMIT 1");
        $stmt->execute(['email' => $data['email'], 'code' => $data['code']]);
        $otp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($otp && strtotime($otp['expires_at']) > time()) {
            $db->prepare("UPDATE registered_codes SET status = 'validated' WHERE id = :id")->execute(['id' => $otp['id']]);
            return $this->jsonResponse($response, ["status" => "sucesso"]);
        }
        return $this->jsonResponse($response, ["status" => "erro", "message" => "Invalido ou expirado"], 401);
    }

    private function sendEmail($toEmail, $toName, $code) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'contato@misturadeluz.com';
            $mail->Password = 'Mistura#1';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->setFrom('contato@misturadeluz.com', 'FastPayment');
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = "Codigo: $code";
            $mail->Body = "Seu codigo e: $code";
            $mail->send();
            return true;
        } catch (\Exception $e) { return false; }
    }
}