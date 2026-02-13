<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Registration;
use App\Models\Person;
use Exception;

class RegistrationController {

    private $registrationModel;

    public function __construct() {
        $this->registrationModel = new Registration();
    }

    /**
     * Card 11.1: Lista todos os inscritos com detalhes completos (Menu Inscritos)
     */
    public function listAllSubscribers(Request $request, Response $response) {
        try {
            $list = $this->registrationModel->getFullSubscribersList();
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "total" => count($list),
                "data" => $list
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Card 7.4: Verifica se o cliente já pagou mas não finalizou a inscrição
     * Utilizado no botão de recuperação da agenda pública
     */
    public function verifyPendingRegistration(Request $request, Response $response) {
        try {
            $params = $request->getQueryParams();
            $email = $params['email'] ?? null;
            $scheduleId = $params['schedule_id'] ?? null;

            if (!$email || !$scheduleId) {
                return $this->jsonResponse($response, ["error" => "E-mail e Agenda são obrigatórios"], 400);
            }

            // Busca se existe pagamento aprovado sem inscrição vinculada
            $sql = "SELECT id FROM payments 
                    WHERE payer_email = :email 
                    AND status = 'approved' 
                    AND id NOT IN (SELECT payment_id FROM events_subscribed)
                    LIMIT 1";
            
            $stmt = $this->registrationModel->getConnection()->prepare($sql);
            $stmt->execute([':email' => $email]);
            $pendingPayment = $stmt->fetch();

            return $this->jsonResponse($response, [
                "pending" => (bool)$pendingPayment,
                "payment_id" => $pendingPayment ? $pendingPayment['id'] : null,
                "message" => $pendingPayment ? "Pagamento localizado! Prossiga para finalizar." : "Nenhuma pendência encontrada."
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function finalizeRegistration(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;

        try {
            $db = $this->registrationModel->getConnection();
            $db->beginTransaction();

            // 1. Atualiza/Salva dados da pessoa e detalhes profissionais
            $personModel = new Person();
            $data['type_person_id'] = 2; // Cliente
            $personId = $personModel->saveUnified($data);

            // 2. Se houver um payment_id (vindo da recuperação ou webhook), vincula a inscrição
            // Esta parte será integrada com o Model Subscription no próximo passo
            
            $db->commit();
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "message" => "Dados profissionais salvos com sucesso!"
            ]);

        } catch (Exception $e) {
            if ($this->registrationModel->getConnection()->inTransaction()) {
                $this->registrationModel->getConnection()->rollBack();
            }
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

    /**
     * Método auxiliar para respostas JSON
     */
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function paymentHistory(Request $request, Response $response) {
        try {
            $history = $this->registrationModel->getPaymentHistory();
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "total" => count($history),
                "data" => $history
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Card 11.3: Finaliza o processo de inscrição (Pode ser chamado manualmente ou via Webhook)
     */
    public function confirmSubscription(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $personId   = $data['person_id'] ?? null;
            $scheduleId = $data['schedule_id'] ?? null;
            $paymentId  = $data['payment_id'] ?? null;

            if (!$personId || !$scheduleId || !$paymentId) {
                return $this->jsonResponse($response, ["error" => "Dados insuficientes para confirmar inscrição"], 400);
            }

            $this->registrationModel->completeSubscription($personId, $scheduleId, $paymentId);

            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "mensagem" => "Inscrição confirmada e vaga garantida!"
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }
    /**
     * Card 10.0: Lista o extrato real da tabela transactions
     */
    public function listTransactions(Request $request, Response $response) {
        try {
            $data = $this->registrationModel->getTransactionsReport();
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "total" => count($data),
                "data" => $data
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function getDashboardSummary(Request $request, Response $response) {
        try {
            $revenue = $this->registrationModel->getTotalRevenue();
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "data" => [
                    "total_revenue" => (float)$revenue,
                    "currency" => "BRL"
                ]
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    // Dentro de src/Controllers/RegistrationController.php

    public function completeSubscription(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();

            // Pega os IDs enviados pelo Postman
            $personId   = $data['person_id'] ?? null;
            $scheduleId = $data['schedule_id'] ?? null;
            $paymentId  = $data['payment_id'] ?? null;

            if (!$personId || !$scheduleId || !$paymentId) {
                $response->getBody()->write(json_encode(["error" => "Dados incompletos"]));
                return $response->withStatus(400);
            }

            // CHAMA O MODEL (A função que corrigimos antes com o schema.sql)
            $success = $this->registrationModel->completeSubscription($personId, $scheduleId, $paymentId);

            if ($success) {
                $response->getBody()->write(json_encode(["status" => "sucesso", "message" => "Inscrição confirmada!"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}