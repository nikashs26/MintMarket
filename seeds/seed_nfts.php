<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/NFT.php';
require_once __DIR__ . '/../classes/Marketplace.php';

// Ensure a user exists to be the creator
$db = Database::getInstance()->getConnection();
$username = 'SyntheticArtist';
$email = 'artist@mintmarket.test';
$password = 'password123';

$stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->execute([$username]);
$creatorId = $stmt->fetchColumn();

if (!$creatorId) {
    if (class_exists('User')) {
        $userModel = new User();
        // Register returns array with success/user_id or similar? 
        // Let's assume standard insert if User class registration is complex or session based
        // Checking User.php content is safer but let's try direct insert for seed speed
        $hashedPasword = password_hash($password, PASSWORD_DEFAULT);
        $walletAddress = '0x' . bin2hex(random_bytes(20));
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, balance, wallet_address) VALUES (?, ?, ?, 1000.00, ?)");
        $stmt->execute([$username, $email, $hashedPasword, $walletAddress]);
        $creatorId = $db->lastInsertId();
        echo "Created seed user: $username (ID: $creatorId)\n";
    }
} else {
    echo "Using existing seed user: $username (ID: $creatorId)\n";
}

$nftModel = new NFT();
$marketplace = new Marketplace();

$nfts = [
    [
        'title' => 'Cosmic Journey #1',
        'description' => 'A journey through the stars.',
        'image_url' => 'images/mint1.jpeg',
        'tags' => ['Digital Art', 'Sci-Fi', 'Space', 'Abstract'],
        'price' => 12.50
    ],
    [
        'title' => 'Cyber Punk Vista',
        'description' => 'Neon lights and rain.',
        'image_url' => 'images/mint2.jpeg',
        'tags' => ['Digital Art', 'Sci-Fi', 'City', 'Future', '3D'],
        'price' => 25.00
    ],
    [
        'title' => 'Ethereal Forest',
        'description' => 'Where magic grows.',
        'image_url' => 'images/mint3.jpeg',
        'tags' => ['Fantasy', 'Nature', 'Digital Art', 'Common'],
        'price' => 5.00
    ],
    [
        'title' => 'Abstract Mind',
        'description' => 'Thoughts in color.',
        'image_url' => 'images/mint4.jpeg',
        'tags' => ['Abstract', 'Digital Art', 'Rare'],
        'price' => 45.00
    ],
    [
        'title' => 'Pixel Hero',
        'description' => '8-bit nostalgia.',
        'image_url' => 'images/mint5.jpeg',
        'tags' => ['Pixel Art', 'Gaming', 'Legendary', '1/1'],
        'price' => 100.00
    ],
    [
        'title' => 'Generative Waves',
        'description' => 'Flowing code art.',
        'image_url' => 'images/mint6.jpeg',
        'tags' => ['Generative', 'Code', 'Abstract', 'Interactive'],
        'price' => 18.50
    ],
    [
        'title' => 'Urban Glitch',
        'description' => 'The city breaks.',
        'image_url' => 'images/mint7.jpeg',
        'tags' => ['Photography', 'Glitch', 'Urban', 'Limited Edition'],
        'price' => 30.00
    ]
];

foreach ($nfts as $data) {
    echo "Minting: " . $data['title'] . "... ";
    
    // Check if duplicate exists (simple check by title)
    $stmt = $db->prepare("SELECT nft_id FROM nfts WHERE title = ?");
    $stmt->execute([$data['title']]);
    $existingId = $stmt->fetchColumn();

    $nftId = $existingId;

    if (!$existingId) {
        $result = $nftModel->mint(
            $creatorId,
            $data['title'],
            $data['description'],
            $data['image_url'],
            10, // 10% royalty
            $data['tags']
        );

        if ($result['success']) {
            $nftId = $result['nft_id'];
            echo "Success (ID: $nftId). ";
        } else {
            echo "Failed: " . $result['message'] . "\n";
            continue;
        }
    } else {
        echo "Already exists (ID: $nftId). ";
    }

    // List it if not listed
    $stmt = $db->prepare("SELECT listing_id FROM listings WHERE nft_id = ? AND status = 'active'");
    $stmt->execute([$nftId]);
    $isListed = $stmt->fetchColumn();

    if (!$isListed) {
        $listResult = $marketplace->createListing($nftId, $creatorId, $data['price']);
        if ($listResult['success']) {
            echo "Listed for " . $data['price'] . " MTK.\n";
        } else {
            echo "Listing failed: " . $listResult['message'] . "\n";
        }
    } else {
        echo "Already listed.\n";
    }
}

echo "Seeding complete.\n";
?>
