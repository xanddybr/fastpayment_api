<?php
namespace App\Controllers;

use App\Models\Person;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class PersonController {
    private $personModel;

    public function __construct() { 
        $this->personModel = new Person();
    }

    public function listAll(Request $request, Response $response) {
        try {
            $users = $this->personModel->findAll();
            return $this->jsonResponse($response, $users);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["status" => "erro", "mensagem" => $e->getMessage()], 500);
        }
    }

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

    public function store(Request $request, Response $response) {
    // 1. Pega a conexão PDO diretamente
    $db = $this->personModel->getConnection();

    try {
        $data = $request->getParsedBody();
        
        if (empty($data['email']) || empty($data['full_name'])) {
            return $this->jsonResponse($response, ["error" => "Nome e E-mail são obrigatórios"], 400);
        }

        // 2. Antes de qualquer coisa, limpa transações que ficaram "penduradas" de erros anteriores
        // (Isso é um truque para destravar o ambiente de teste)
        while ($db->inTransaction()) {
            $db->rollBack();
        }

        // 3. Verifica e-mail
        $stmt = $db->prepare("SELECT id FROM persons WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        if ($stmt->fetch()) {
            return $this->jsonResponse($response, ["error" => "Este e-mail já está cadastrado"], 400);
        }

        // 4. Inicia a transação de forma segura
        $db->beginTransaction();

        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // 5. Salva (Certifique-se que o saveUnified NÃO abra transação lá dentro)
        $personId = $this->personModel->saveUnified($data);

        $db->commit();

        return $this->jsonResponse($response, [
            "status" => "sucesso",
            "id" => $personId
        ], 201);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return $this->jsonResponse($response, ["error" => "Falha no banco: " . $e->getMessage()], 500);
    }
}
    
}