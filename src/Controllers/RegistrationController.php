<?php

namespace App\Controllers;

use App\Models\Registration;
use App\Models\Person; // Podemos reusar o PersonModel aqui!
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class RegistrationController {

    private $registrationModel;

    public function __construct() {
        $this->registrationModel = new Registration();
    }

    public function finalizeRegistration(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $fullName = $data['full_name'] ?? null;

        try {
            $this->registrationModel->getConnection()->beginTransaction();

            // 1. Usa o Model para buscar o status
            $userRecord = $this->registrationModel->getUserStatusForFinalization($email);

            if (!$userRecord || !isset($userRecord['person_id_found'])) {
                throw new Exception("Usuário não localizado: " . $email);
            }

            $personId = $userRecord['person_id_found'];
            $isExistingUser = !empty($userRecord['last_event']);

            // 2. Reaproveitamos a lógica de salvar unificado que já criamos no PersonModel!
            // Isso evita código duplicado (DRY - Don't Repeat Yourself)
            $personModel = new \App\Models\Person();
            
            // Preparamos o array no formato que o saveUnified espera
            $data['full_name'] = $fullName;
            $data['type_person_id'] = 2; // Garantimos que é cliente
            // O saveUnified já cuida de 'persons' e 'person_details' via ON DUPLICATE KEY
            $personModel->saveUnified($data);

            $this->registrationModel->getConnection()->commit();

            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "is_returning_user" => $isExistingUser,
                "message" => "Inscrição finalizada com sucesso!"
            ]);

        } catch (Exception $e) {
            $this->registrationModel->getConnection()->rollBack();
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function dashboardStats(Request $request, Response $response) {
        try {
            $list = $this->registrationModel->getDashboardReport();

            $totalRevenue = 0;
            $approvedCount = 0;

            foreach ($list as $item) {
                if ($item['payment_status'] === 'approved') {
                    $totalRevenue += (float)$item['paid_amount'];
                    $approvedCount++;
                }
            }

            return $this->jsonResponse($response, [
                "stats" => [
                    "total_entries" => count($list),
                    "approved_payments" => $approvedCount,
                    "total_revenue" => number_format($totalRevenue, 2, '.', '')
                ],
                "registrations" => $list
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}