<?php
namespace App\Controllers;

use App\Contracts\Services\RegistrationServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RegistrationController
{
    public function __construct(private RegistrationServiceInterface $registrationService) {}

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        if (empty($data['schedule_id']) || empty($data['payment_id'])) {
            return $this->json($response, [
                'status'   => 'erro',
                'mensagem' => 'schedule_id e payment_id são obrigatórios.',
            ], 400);
        }

        try {
            $result = $this->registrationService->register($data);
            return $this->json($response, [
                'status'        => 'sucesso',
                'subscribed_id' => $result['subscribed_id'],
                'mensagem'      => 'Inscrição realizada com sucesso!',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 400);
        }
    }

    public function listAllSubscribers(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, $this->registrationService->getAllSubscribers());
        } catch (\Exception $e) {
            return $this->json($response, ['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function getDashboardSummary(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, [
                'status' => 'sucesso',
                'data'   => [
                    'total_revenue' => $this->registrationService->getTotalRevenue(),
                    'currency'      => 'BRL',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function paymentHistory(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, $this->registrationService->getPaymentHistory());
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
