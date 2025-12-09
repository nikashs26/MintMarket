<?php
require_once __DIR__ . '/../classes/NFT.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$nftModel = new NFT();
$user = new User();

switch ($action) {
    case 'mint':
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

        // Handle File Upload
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Image upload failed']);
            exit;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save image']);
            exit;
        }

        $imageUrl = 'uploads/' . $filename;
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $royalty = (float)($_POST['royalty_percentage'] ?? 10);
        
        // Parse tags from POST data
        $tagsInput = $_POST['tags'] ?? '';
        $tags = !empty($tagsInput) ? explode(',', $tagsInput) : [];
        $tags = array_map('trim', $tags);

        $result = $nftModel->mint($_SESSION['user_id'], $title, $description, $imageUrl, $royalty, $tags);
        
        // Add image URL to response if successful
        if ($result['success']) {
            $result['image_url'] = $imageUrl;
        }
        
        echo json_encode($result);
        break;

    case 'get_all':
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $result = $nftModel->getAllNFTs($limit, $offset);
        echo json_encode($result);
        break;

    case 'get_by_id':
        $nftId = (int)($_GET['nft_id'] ?? 0);
        $result = $nftModel->getNFTById($nftId);
        echo json_encode($result);
        break;

    case 'my_nfts':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $result = $user->getUserNFTs($_SESSION['user_id']);
        echo json_encode($result);
        break;

    case 'transfer':
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
        $toUserId = $input['to_user_id'] ?? 0;
        
        // Direct transfer (gift) implies price is 0
        $result = $nftModel->transfer($nftId, $_SESSION['user_id'], $toUserId, 0);
        echo json_encode($result);
        break;
    
    case 'get_tags':
        // Return available tags for the mint form
        echo json_encode([
            'success' => true,
            'tags' => NFT::getAvailableTags()
        ]);
        break;

    case 'history':
        $nftId = (int)($_GET['nft_id'] ?? 0);
        $blockchain = new Blockchain();
        $history = $blockchain->getTransactionHistory($nftId);
        echo json_encode(['success' => true, 'history' => $history]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
