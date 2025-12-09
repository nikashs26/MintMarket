<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = Database::getInstance()->getConnection();

echo "<h1>Debug Cart Database</h1>";

// 1. Show Cart Items
echo "<h2>Cart Items Table</h2>";
$stmt = $db->query("SELECT * FROM cart_items");
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($cart_items, true) . "</pre>";

// 2. Show Listings for these items
if (!empty($cart_items)) {
    echo "<h2>Listings Check</h2>";
    foreach($cart_items as $item) {
        $lid = $item['listing_id'];
        $stmt = $db->prepare("SELECT * FROM listings WHERE listing_id = ?");
        $stmt->execute([$lid]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Check Listing ID $lid: ";
        if ($listing) {
            echo "FOUND. Status: " . $listing['status'] . ", NFT_ID: " . $listing['nft_id'] . "<br>";
            
            // Check NFT
            $stmt2 = $db->prepare("SELECT * FROM nfts WHERE nft_id = ?");
            $stmt2->execute([$listing['nft_id']]);
            $nft = $stmt2->fetch(PDO::FETCH_ASSOC);
            echo "--> NFT Found: " . ($nft ? $nft['title'] : "NO") . "<br>";
            
        } else {
            echo "NOT FOUND in listings table.<br>";
        }
    }
} else {
    echo "Cart is empty.<br>";
}
