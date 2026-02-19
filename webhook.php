<?php
// webhook.php na raiz da pasta api

require_once __DIR__ . '/vendor/autoload.php';

// --- ADICIONE ESTAS LINHAS PARA CARREGAR O .ENV ---
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
// -------------------------------------------------

require_once __DIR__ . '/src/Config/Database.php';
require_once __DIR__ . '/src/Models/Registration.php';

use App\Models\Registration;
use App\Config\Database;

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($data) {
    try {
        // Agora o Database::getConnection() vai encontrar as chaves no $_ENV
        $db = Database::getConnection(); 
        $model = new Registration($db);

        $personId = $data['person_id'] ?? null;
        $scheduleId = $data['schedule_id'] ?? null;
        $paymentId = $data['payment_id'] ?? ($data['data']['id'] ?? 'SIMULACAO-REMOTO');

        if (!$personId || !$scheduleId) {
            throw new Exception("Dados insuficientes.");
        }

        $model->completeSubscription($personId, $scheduleId, $paymentId);
        
        header('Content-Type: application/json');
        echo json_encode(["status" => "sucesso", "ambiente" => "webhook_avulso_carregado"]);
        
    } catch (Exception $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(["error" => $e->getMessage()]);
    }
}