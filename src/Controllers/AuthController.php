<?php
namespace App\Controllers;

use App\Models\Person;
use App\Services\EmailService;
use App\Utils\Validator; // Opcional: mover validação de e-mail/tel para cá

class AuthController {
    private $personModel;

    public function __construct() {
        $this->personModel = new Person();
    }

    public function login($request, $response) {
        $data = $request->getParsedBody();
        $user = $this->personModel->authenticate($data['email'], $data['password']);

        if ($user) {
            $this->createSession($user);
            return $this->jsonResponse($response, ["status" => "sucesso", "user" => $user]);
        }
        return $this->jsonResponse($response, ["status" => "erro", "mensagem" => "Falha no login"], 401);
    }

    public function generateValidationCode($request, $response) {
        $data = $request->getParsedBody();
        
        // O Model gera o código e cuida do banco
        $code = $this->personModel->createValidationCode($data['email'], $data['telefone']);
        
        // O Service envia o e-mail
        EmailService::sendOTP($data['email'], $data['nome'], $code);

        return $this->jsonResponse($response, ["status" => "sucesso"]);
    }
}