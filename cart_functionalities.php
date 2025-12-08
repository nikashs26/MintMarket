<?php
session_start();
header("Content-Type: application/json");

if(!isset($_SESSION['cart'])){ //when cart in session
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? ''; //ajax

switch($action){ //switch case for cart actions like add, delete, update, checkout single, checkout all
    
    case 'add':
        $product_ID = $_POST['product_ID']; //product id is updated to cart
        $quantity = (int)$_POST['quantity']; //quantity is updated t cart
        if(isset($products[$product_ID])){
            $_SESSION['cart']['product_ID'] = ($_SESSION['cart'][$product_ID] ?? 0) + $quantity; //stores info in cart session
        }
        break;

    case 'update':
        $cart_ID = $_POST['cart_ID']; //cart is updated
        $quantity = (int)$_POST['quantity']; //quantity is updated
        if(isset( $_SESSION['cart'][$cart_ID])){
            if( $quantity > 0){
                $_SESSION['cart'][$cart_ID] = $quantity;
            }else{
                unset($_SESSION['cart'][$cart_ID]);
            }
        }
        break;

    case 'delete':
        $cart_ID = $_POST['cart_ID'];
        unset($_SESSION['cart'][$cart_ID]); //free memory once deleted
        break;

    case 'checkoutSingleItem':
        $cart_ID = $_POST['cart_ID'];
        $quantity = $_SESSION['cart'][$cart_ID] ?? 0; //quantity is updated
        if(isset($_SESSION['cart'][$cart_ID])){
            //completes payment & frees memory
            unset($_SESSION['cart'][$cart_ID]);
        }
        break;

    case 'checkoutEntireCart':
        //complete payment
        $_SESSION['cart'] = [];
        break;

    case 'loadingCart': //loads cart for display
        default:
            break;

}

//making cart
$itemsInCart = '';
$subtotal = 0;
$totalItemCount = 0;

foreach($_SESSION['cart'] as $product_ID => $quantity){
    if(!isset($products[$product_ID])) continue;
    
    $product = $products[$product_ID];
    $productTotal = $product['price'] * $quantity;
    $subtotal += $productTotal;
    $totalItemCount += $quantity;

    $itemsInCart .= "
    <div class='product-in-cart'>
        <div class='product-in-cart-image'>
            <img src='{$product['image']}' alt='{product['name']}'>
        </div>
        <div class='product-in-cart-info'>
            <h3 class='item-name'>{$product['name']}</h3>
            <p class='item-price'>" . number_format($product['price'], 2) . " MTK</p>
            <input type='number' class='prod-quantity' value='$quantity' min='1' onchange=\"updatingItemQuantity('product_ID', this.value)\">
            <p class='item-product-total'>Total: " . number_format($productTotal, 2) . " MTK</p>
            <div class='cart-product-actions'>
                <button class='remove-button' onclick=\"deletingItemFromCart('$product_ID')\">Remove</button>
                <button class='buy-now-button' onclick=\"checkoutSingleItem('$product_ID')\">Buy Now</button>
            </div>
        </div>
    </div>";
}

$fee = $subtotal * 0.025;
$total = $subtotal + $fee;

echo json_encode([
    'itemsInCart' => $itemsInCart,
    'subtotal' => $subtotal,
    'fee' => $fee,
    'total' => $total,
    'totalItemCount' => totalItemCount
]);

exit;

?>