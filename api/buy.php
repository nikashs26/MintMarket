<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Marketplace.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$listingId = $input['listing_id'] ?? 0;

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Listing ID required']);
    exit;
}

$marketplace = new Marketplace();
$result = $marketplace->buyNFT($listingId, $_SESSION['user_id']);

echo json_encode($result);
?>
