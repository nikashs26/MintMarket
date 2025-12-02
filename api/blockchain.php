<?php
require_once __DIR__ . '/../classes/Blockchain.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$blockchain = new Blockchain();

switch ($action) {
    case 'validate':
        $result = $blockchain->validateChain();
        echo json_encode($result);
        break;

    case 'last_hash':
        $result = $blockchain->getLastHash();
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
