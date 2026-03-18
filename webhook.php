<?php
require_once __DIR__ . '/vendor/autoload.php';

// Load Environment Variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Controllers\TransactionController;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// Handle the request manually since it's a standalone file
$request = ServerRequestFactory::createFromGlobals();
$response = (new ResponseFactory())->createResponse();

$controller = new TransactionController();
$controller->webhook($request, $response);

http_response_code(200);
echo "Notification received.";