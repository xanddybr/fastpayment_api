<?php
namespace App\Controllers;

use App\Models\Person;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PersonController {
    private $personModel;

    public function __construct() { 
        $this->personModel = new Person();
    }

    // --- FALTAVA ESTE MÉTODO ---
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
    }

    public function listAll(Request $request, Response $response) {
        try {
            $users = $this->personModel->findAll();
            return $this->jsonResponse($response, $users);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    /**
     * BUSCAR POR ID: Retorna um único usuário com seus detalhes (GET /person/{id})
     */
    /**
     * Card 1.5 / 8.1: Carrega dados para o modal de edição
     */
    public function show(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $data = $this->personModel->getFullProfile($id);

            if (!$data) {
                return $this->jsonResponse($response, ["error" => "Usuário não encontrado"], 404);
            }

            // Organizando o objeto para o Front-end
            $profile = [
                "id" => $data[0]['id'],
                "full_name" => $data[0]['full_name'],
                "email" => $data[0]['email'],
                "phone" => $data[0]['phone'],
                "details" => [
                    "activity_professional" => $data[0]['activity_professional'],
                    "address" => [
                        "street" => $data[0]['street'],
                        "number" => $data[0]['number'],
                        "neighborhood" => $data[0]['neighborhood'],
                        "city" => $data[0]['city']
                    ]
                ],
                "subscriptions" => []
            ];

           foreach ($data as $row) {
                if ($row['subscription_id']) {
                    $profile['subscriptions'][] = [
                        "course" => $row['course_name'],
                        "date" => $row['course_date'],
                        "status" => $row['subscription_status'],
                        "anamnesis" => [
                            "first_time" => (int)$row['first_time'],
                            "who_recomend" => $row['who_recomend'],
                            "is_medium" => (int)$row['is_medium'],
                            "is_tule_member" => (int)$row['is_tule_member'],
                            "religion" => (int)$row['religion'],
                            "religion_mention" => $row['religion_mention'],
                            "course_reason" => $row['course_reason'],
                            "expectations" => $row['expectations'],
                            "obs_motived" => $row['obs_motived']
                        ]
                    ];
                }
            }

            return $this->jsonResponse($response, $profile);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function createAdmin(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            
            // Usamos o seu método unificado que cuida de persons + person_details
            $personId = $this->personModel->saveUnified($data);
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "mensagem" => "Usuário criado/atualizado com sucesso",
                "id" => $personId
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 400);
        }
    }

    public function remove(Request $request, Response $response, array $args) {
        try {
            $id = $args['id'];
            $this->personModel->delete($id);
            
            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "mensagem" => "Usuário removido com sucesso"
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

    /**
     * Card 8.1: Recebe o POST/PUT para atualizar a ficha
     */
    public function update(Request $request, Response $response, array $args) {
        try {
            $data = $request->getParsedBody();
            $data['id'] = $args['id']; // ID da URL

            $this->personModel->updateFullProfile($data);

            return $this->jsonResponse($response, [
                "status" => "sucesso",
                "message" => "Ficha atualizada com sucesso!"
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    /**
     * Auxiliar para respostas JSON padronizadas
     */
    private function jsonResponse(Response $response, $data, $status = 200) {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    
}