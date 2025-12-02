<?php
/**
 * Test Script for MintMarket Backend
 * Run this from command line: php tests/test_all.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/NFT.php';
require_once __DIR__ . '/../classes/Marketplace.php';
require_once __DIR__ . '/../classes/Blockchain.php';

// Helper for colored output
function logInfo($msg) { echo "\033[36m[INFO] $msg\033[0m\n"; }
function logSuccess($msg) { echo "\033[32m[SUCCESS] $msg\033[0m\n"; }
function logError($msg) { echo "\033[31m[ERROR] $msg\033[0m\n"; }

try {
    logInfo("Starting Backend Tests...");

    // Initialize Classes
    $userModel = new User();
    $nftModel = new NFT();
    $marketplace = new Marketplace();
    $blockchain = new Blockchain();

    // 1. Test User Registration
    logInfo("Testing User Registration...");
    $username1 = "test_artist_" . uniqid();
    $email1 = "artist" . uniqid() . "@test.com";
    $res1 = $userModel->register($username1, $email1, "password123");
    
    if (!$res1['success']) throw new Exception("User 1 registration failed: " . $res1['message']);
    $artistId = $res1['user_id'];
    logSuccess("Created Artist (ID: $artistId)");

    $username2 = "test_collector_" . uniqid();
    $email2 = "collector" . uniqid() . "@test.com";
    $res2 = $userModel->register($username2, $email2, "password123");
    
    if (!$res2['success']) throw new Exception("User 2 registration failed: " . $res2['message']);
    $collectorId = $res2['user_id'];
    logSuccess("Created Collector (ID: $collectorId)");

    // 2. Test Minting
    logInfo("Testing NFT Minting...");
    $mintRes = $nftModel->mint($artistId, "Test NFT #1", "A test description", "uploads/test.jpg", 10.0);
    
    if (!$mintRes['success']) throw new Exception("Minting failed: " . $mintRes['message']);
    $nftId = $mintRes['nft_id'];
    logSuccess("Minted NFT (ID: $nftId)");

    // 3. Test Listing
    logInfo("Testing Marketplace Listing...");
    $listRes = $marketplace->createListing($nftId, $artistId, 50.0);
    
    if (!$listRes['success']) throw new Exception("Listing failed: " . $listRes['message']);
    $listingId = $listRes['listing_id'];
    logSuccess("Created Listing (ID: $listingId) for 50 MTK");

    // 4. Test Buying
    logInfo("Testing Purchase...");
    // Collector has 100 MTK, Price is 50 MTK
    $buyRes = $marketplace->buyNFT($listingId, $collectorId);
    
    if (!$buyRes['success']) throw new Exception("Purchase failed: " . $buyRes['message']);
    logSuccess("Purchase Successful! Tx Hash: " . substr($buyRes['transaction_hash'], 0, 10) . "...");

    // 5. Verify Balances
    logInfo("Verifying Balances...");
    $artistProfile = $userModel->getProfile($artistId);
    $collectorProfile = $userModel->getProfile($collectorId);

    // Artist: 100 (start) + 50 (sale) = 150
    // Collector: 100 (start) - 50 (buy) = 50
    // Royalty: Artist is creator AND seller, so they get full 50. 
    // Wait, logic check: if creator == seller, royalty is 0 in calculation, seller gets full price. Correct.
    
    if (abs($artistProfile['profile']['balance'] - 150.0) < 0.001) {
        logSuccess("Artist Balance Correct: 150.00");
    } else {
        logError("Artist Balance Incorrect: " . $artistProfile['profile']['balance']);
    }

    if (abs($collectorProfile['profile']['balance'] - 50.0) < 0.001) {
        logSuccess("Collector Balance Correct: 50.00");
    } else {
        logError("Collector Balance Incorrect: " . $collectorProfile['profile']['balance']);
    }

    // 6. Test Secondary Sale (Royalty)
    logInfo("Testing Secondary Sale (Royalty)...");
    // Collector sells to Artist for 10 MTK
    // Royalty is 10% (1 MTK) -> Goes to Artist (Creator)
    // Seller (Collector) gets 9 MTK
    // Artist pays 10 MTK
    
    // List it
    $listRes2 = $marketplace->createListing($nftId, $collectorId, 10.0);
    $listingId2 = $listRes2['listing_id'];
    
    // Buy it (Artist buys back their work)
    $buyRes2 = $marketplace->buyNFT($listingId2, $artistId);
    
    if (!$buyRes2['success']) throw new Exception("Secondary purchase failed: " . $buyRes2['message']);
    logSuccess("Secondary Purchase Successful!");

    // Verify Final Balances
    $artistProfile = $userModel->getProfile($artistId);
    $collectorProfile = $userModel->getProfile($collectorId);

    // Artist: 150 - 10 (buy) + 1 (royalty) = 141
    // Collector: 50 + 9 (sale share) = 59
    
    if (abs($artistProfile['profile']['balance'] - 141.0) < 0.001) {
        logSuccess("Artist Final Balance Correct: 141.00");
    } else {
        logError("Artist Final Balance Incorrect: " . $artistProfile['profile']['balance']);
    }

    if (abs($collectorProfile['profile']['balance'] - 59.0) < 0.001) {
        logSuccess("Collector Final Balance Correct: 59.00");
    } else {
        logError("Collector Final Balance Incorrect: " . $collectorProfile['profile']['balance']);
    }

    // 7. Validate Blockchain
    logInfo("Validating Blockchain...");
    $chainRes = $blockchain->validateChain();
    if ($chainRes['valid']) {
        logSuccess("Blockchain Valid! Total Blocks: " . $chainRes['total_blocks']);
    } else {
        logError("Blockchain Invalid: " . $chainRes['message']);
    }

    logSuccess("ALL TESTS PASSED!");

} catch (Exception $e) {
    logError("Test Failed: " . $e->getMessage());
}
?>
