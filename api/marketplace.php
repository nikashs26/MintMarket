<?php
require_once __DIR__ . '/../classes/Marketplace.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$marketplace = new Marketplace();

switch ($action) {
    case 'create_listing':
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
        $nftId = $input['nft_id'] ?? 0;
        $price = $input['price'] ?? 0;

        $result = $marketplace->createListing($nftId, $_SESSION['user_id'], $price);
        echo json_encode($result);
        break;

    case 'buy':
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

        $result = $marketplace->buyNFT($listingId, $_SESSION['user_id']);
        echo json_encode($result);
        break;

    case 'get_listings':
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $tags = isset($_GET['tags']) ? $_GET['tags'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $result = $marketplace->getActiveListings($limit, $offset, $tags, $search);
        echo json_encode($result);
        break;

    case 'cancel_listing':
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

        $result = $marketplace->cancelListing($listingId, $_SESSION['user_id']);
        echo json_encode($result);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
