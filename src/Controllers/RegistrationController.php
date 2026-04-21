<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Registration;
use App\Models\Person;
use App\Models\Transaction as TransactionModel;
use Exception;

class RegistrationController {

    private $registrationModel;

    public function __construct() {
        $this->registrationModel = new Registration();
    }

    /**
     * POST /api/register/subscribers
     *
     * Finaliza a inscrição após pagamento aprovado pelo webhook.
     *
     * O que faz:
     *  1. Cria ou recupera pessoa em persons + person_details
     *  2. Atualiza person_id em transactions e events_subscribed
     *  3. Cria anamnese
     *  4. Atualiza events_subscribed.status → 'confirmed'
     */
    public function create(Request $request, Response $response) {
        $data = $request->getParsedBody() ?? [];

        if (empty($data['schedule_id']) || empty($data['payment_id'])) {
            return $this->jsonResponse($response, [
                'status'   => 'erro',
                'mensagem' => 'schedule_id e payment_id são obrigatórios.',
            ], 400);
        }

        try {
            $personModel      = new Person();
            $transactionModel = new TransactionModel();
            $db               = $this->registrationModel->getConnection();

            $db->beginTransaction();

            // 1. Cria ou recupera a pessoa (upsert por e-mail)
            $personId = $personModel->saveCompleteRegistration($data);

            // 2. Liga person_id ao pagamento já aprovado
            $transactionModel->linkPersonToPayment($data['payment_id'], $personId);

            // 3. Busca o events_subscribed criado pelo webhook
            $stmt = $db->prepare("
                SELECT id FROM events_subscribed
                WHERE payment_id = :payid
                LIMIT 1
            ");
            $stmt->execute([':payid' => $data['payment_id']]);
            $subscription = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$subscription) {
                throw new Exception('Inscrição não encontrada. O pagamento pode ainda estar sendo processado.');
            }

            // 4. Cria a ficha de anamnese
            $personModel->createAnamnesis($subscription['id'], $data);

            // 5. Atualiza events_subscribed para 'confirmed'
            $transactionModel->confirmSubscription($data['payment_id']);

            $db->commit();

            return $this->jsonResponse($response, [
                'status'        => 'sucesso',
                'subscribed_id' => $subscription['id'],
                'mensagem'      => 'Inscrição realizada com sucesso!',
            ], 201);

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            return $this->jsonResponse($response, [
                'status'   => 'erro',
                'mensagem' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /subscribers — Lista todos os inscritos (admin)
     */
    public function listAllSubscribers(Request $request, Response $response) {
        try {
            $personModel = new Person();
            $data        = $personModel->getAllSubscribers();
            return $this->jsonResponse($response, $data);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /dashboard/summary — Receita total (admin)
     */
    public function getDashboardSummary(Request $request, Response $response) {
        try {
            $revenue = $this->registrationModel->getTotalRevenue();
            return $this->jsonResponse($response, [
                'status' => 'sucesso',
                'data'   => ['total_revenue' => (float) $revenue, 'currency' => 'BRL'],
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /financial/history — Histórico de pagamentos (admin)
     */
    public function paymentHistory(Request $request, Response $response) {
        try {
            $history = $this->registrationModel->getPaymentHistory();
            return $this->jsonResponse($response, [
                'status' => 'sucesso',
                'total'  => count($history),
                'data'   => $history,
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function jsonResponse(Response $response, $data, $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}