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
     * Finaliza a inscrição após o pagamento já ter sido aprovado pelo webhook.
     *
     * O frontend envia:
     *   payment_id         — ID do pagamento aprovado pelo MP
     *   schedule_id        — ID da agenda escolhida
     *   student_full_name  — nome completo
     *   student_email      — e-mail (mesmo usado no checkout)
     *   student_phone      — telefone
     *   activity_professional, neighborhood, city — detalhes
     *   course_reason, who_recomended, is_medium,
     *   religion, religion_mention, is_tule_member, first_time — anamnese
     *
     * O que este método faz (e SOMENTE isso):
     *   1. Cria ou recupera a pessoa em `persons` + `person_details`
     *   2. Atualiza person_id em `transactions` e `events_subscribed`
     *   3. Cria a ficha de anamnese em `anamnesis`
     *
     * NÃO mexe em vagas (já decrementado pelo webhook).
     * NÃO insere em events_subscribed (já feito pelo webhook).
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

            // 2. Liga o person_id ao pagamento já aprovado
            $transactionModel->linkPersonToPayment($data['payment_id'], $personId);

            // 3. Busca o events_subscribed criado pelo webhook para criar a anamnese
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