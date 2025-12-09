<?php
require_once 'config.php';

// Ensure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0); // Suppress warnings to prevent invalid JSON
header("Content-Type: application/json");

// Define cart arrays if not exist (for guest fallback)
if(!isset($_SESSION['cart'])){ 
    $_SESSION['cart'] = [];
}

$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;

switch($action){
    
    case 'add':
        $listing_id = $_POST['product_ID'];
        // Quantity is always 1 for NFTs
        $quantity = 1; 

        if ($isLoggedIn) {
            try {
                // Check if exists
                $stmt = $db->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND listing_id = ?");
                $stmt->execute([$user_id, $listing_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check self-ownership
                $stmt = $db->prepare("SELECT seller_id FROM listings WHERE listing_id = ?");
                $stmt->execute([$listing_id]);
                $listing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($listing && $listing['seller_id'] == $user_id) {
                    echo json_encode(['status' => 'own_item', 'message' => 'You own this item']);
                    exit;
                }

                if ($existing) {
                    echo json_encode(['status' => 'exists', 'message' => 'Item is already in your cart']);
                    exit;
                } else {
                    $stmt = $db->prepare("INSERT INTO cart_items (user_id, listing_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $listing_id, $quantity]);
                    
                    // Update session sync
                    $_SESSION['cart'][$listing_id] = 1;
                    
                    echo json_encode(['status' => 'success', 'message' => 'Added to cart']);
                    exit;
                }
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Database error']);
                exit; 
            }
        } else {
            // Guest logic
            if (isset($_SESSION['cart'][$listing_id])) {
                echo json_encode(['status' => 'exists', 'message' => 'Item is already in your cart']);
                exit;
            } else {
                $_SESSION['cart'][$listing_id] = 1;
                echo json_encode(['status' => 'success', 'message' => 'Added to cart']);
                exit;
            }
        }
        break;

    case 'update':
        $listing_id = $_POST['cart_ID'];
        $quantity = (int)$_POST['quantity'];
        
        if ($isLoggedIn) {
            if ($quantity > 0) {
                $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND listing_id = ?");
                $stmt->execute([$quantity, $user_id, $listing_id]);
            } else {
                $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND listing_id = ?");
                $stmt->execute([$user_id, $listing_id]);
            }
        }

        if(isset($_SESSION['cart'][$listing_id])){
            if($quantity > 0){
                $_SESSION['cart'][$listing_id] = $quantity;
            } else {
                unset($_SESSION['cart'][$listing_id]);
            }
        }
        break;

    case 'delete':
    case 'delet': // handle typo from js
        $listing_id = $_POST['cart_ID'];
        
        if ($isLoggedIn) {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND listing_id = ?");
            $stmt->execute([$user_id, $listing_id]);
        }

        unset($_SESSION['cart'][$listing_id]);
        break;

    case 'checkoutSingleItem':
        $listing_id = $_POST['cart_ID'];
        if ($isLoggedIn) {
            require_once __DIR__ . '/classes/Marketplace.php';
            $marketplace = new Marketplace();
            
            // Perform actual purchase (transfers funds, updates ownership)
            $result = $marketplace->buyNFT($listing_id, $user_id);
            
            if ($result['success']) {
                $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND listing_id = ?");
                $stmt->execute([$user_id, $listing_id]);
                unset($_SESSION['cart'][$listing_id]);
                echo json_encode(['status' => 'success', 'message' => 'Purchase successful!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $result['message']]);
            }
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Please login to purchase']);
        }
        exit; // Important to exit after JSON
        break;

    case 'checkoutEntireCart':
        if ($isLoggedIn) {
            require_once __DIR__ . '/classes/Marketplace.php';
            $marketplace = new Marketplace();
            
            // Get all items in cart
            $stmt = $db->prepare("SELECT listing_id FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $errors = [];
            $successCount = 0;
            
            foreach ($items as $item) {
                // Attempt to buy each item
                $result = $marketplace->buyNFT($item['listing_id'], $user_id);
                
                if ($result['success']) {
                    // Only remove from cart if purchase succeeded
                    $delStmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND listing_id = ?");
                    $delStmt->execute([$user_id, $item['listing_id']]);
                    unset($_SESSION['cart'][$item['listing_id']]);
                    $successCount++;
                } else {
                    $errors[] = "Item ID " . $item['listing_id'] . ": " . $result['message'];
                }
            }
            
            if (empty($errors)) {
                echo json_encode(['status' => 'success', 'message' => "Successfully purchased $successCount items!"]);
            } else {
                // Partial success or failure
                $msg = ($successCount > 0 ? "Purchased $successCount items, but some failed: " : "Purchase failed: ") . implode(", ", $errors);
                echo json_encode(['status' => 'partial_error', 'message' => $msg]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Please login to check out']);
        }
        exit;
        break;

    case 'load':
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - LOAD called. UserID: " . ($user_id ?? 'guest') . "\n", FILE_APPEND);
        
        if ($isLoggedIn) {
            try {
                $stmt = $db->prepare("SELECT listing_id, quantity FROM cart_items WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $dbItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $_SESSION['cart'] = []; 
                foreach($dbItems as $row) {
                    $_SESSION['cart'][$row['listing_id']] = 1; // Force 1
                }
                file_put_contents('debug_log.txt', " - DB Items Found: " . count($dbItems) . "\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents('debug_log.txt', " - DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents('debug_log.txt', " - Guest Mode. Session Cart: " . print_r($_SESSION['cart'], true) . "\n", FILE_APPEND);
        }

        // Now fetch details for items in local cart array (which is now synced if logged in)
        $items_in_cart = $_SESSION['cart']; // [listing_id => qty]
        
        $itemsHTML = "";
        $subtotal = 0;
        $totalItemCount = 0;

        if (!empty($items_in_cart)) {
            $ids = array_keys($items_in_cart);
            // Validating IDs are integers
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids); // Remove 0s
            
            if (empty($ids)) {
                 $itemsHTML = "<div class='empty-cart-msg'>Your cart is empty.</div>";
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $sql = "
                    SELECT l.listing_id, l.price, n.title as name, n.image_url as image, n.royalty_percentage, c.username as creator_username
                    FROM listings l
                    JOIN nfts n ON l.nft_id = n.nft_id
                    JOIN users c ON n.creator_id = c.user_id
                    WHERE l.listing_id IN ($placeholders)
                ";
                
                try {
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array_values($ids));
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                    // Map for easy access
                    $productMap = [];
                    foreach($products as $p) {
                        $productMap[$p['listing_id']] = $p;
                    }

                    foreach ($items_in_cart as $listing_id => $quantity) {
                        if (isset($productMap[$listing_id])) {
                            $product = $productMap[$listing_id];
                            $quantity = 1; // Strict NFT rule
                            $productTotal = $product['price'] * $quantity;
                            $subtotal += $productTotal;
                            $totalItemCount += $quantity;
                            
                            $imgUrl = htmlspecialchars($product['image']);
                            $name = htmlspecialchars($product['name']);
                            $price = number_format($product['price'], 2);
                            $creator = htmlspecialchars($product['creator_username']);
                            $royalty = number_format($product['royalty_percentage'], 0); // e.g. 10
                            
                            // Legacy Design Match
                            $itemsHTML .= "
                            <div class='cart-item'>
                                <div class='cart-item-image' style=\"background-image: url('$imgUrl')\"></div>
                                <div class='cart-item-details'>
                                    <h3>$name</h3>
                                    <p class='cart-item-creator'>by $creator</p>
                                    <p class='cart-item-royalty'>$royalty% Royalty</p>
                                </div>
                                <div class='cart-item-price'>
                                    <span class='price-label'>Price</span>
                                    <span class='price-value'>$price MTK</span>
                                </div>
                                <button class='cart-item-remove' onclick=\"deletingItemFromCart('$listing_id')\">
                                    ✕
                                </button>
                            </div>";
                        }
                    }
                } catch (Exception $e) {
                     file_put_contents('debug_log.txt', " - QUERY ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }

        if (empty($itemsHTML)) {
            $itemsHTML = "<div class='empty-cart-msg'>Your cart is empty.</div>";
        }

        // Output JSON
        $fee = $subtotal * 0.025; // 2.5% fee
        $total = $subtotal + $fee;

        echo json_encode([
            "itemsInCart" => $itemsHTML, // For checkout.js
            "HTMLitems" => $itemsHTML,   // For shoppingcart.js
            "subtotal" => $subtotal,
            "fee" => $fee,
            "total" => $total,
            "totalItemCount" => $totalItemCount
        ]);
        break;
}
?>