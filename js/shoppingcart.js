//use ajax php to update cart without refreshing whole page, 
//just update the div and add anotehr item to cart 
//add function from this website: https://www.w3schools.com/PHP/php_ajax_database.asp 

//functions:
// load to cart
// order summary updates
//update delete item 
//update quantity of item 
//update add item

function loadingItemsToCart(){
    const xhttp = new XMLHttpRequest(); //making ajax request to smoothly update div in cart w/out updating whole page
    xhttp.onload = function() {
        const response = JSON.parse(this.responseText); //json response parsed
        
        document.getElementById("itemsInCart").innerHTML = response.HTMLitems; //update cart from php w/ html
        updatedOrdSum(response.subtotal, response.fee, response.total); //order summary is updated

        const cartIcon = document.getElementById("cartNavIcon"); //updates bag icon in nav bar
        if (response.totalItemCount > 0) {
            cartIcon.style.display = "incline";
            cartIcon.innerText = response.totalItems;
        }else{
            cartIcon.style.display = "none";
        }
    
    };
    xhttp.open("GET", "cart_functionalities.php?action=load");
    xhttp.send();
    
}

function updatedOrdSum(subtotal, fee, total){
    document.getElementById("subtotal").innerText = subtotal.toFixed(2) + " MTK";
    document.getElementById("platformFee").innerText = fee.toFixed(2) + " MTK";
    document.getElementById("ordertotal").innerText = total.toFixed(2) + " MTK";

}

function addingToCart(product_ID){
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        loadingItemsToCart();
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=add&product_ID=" + product_ID + "&quantity=1"); //adds the product, product id, and quantity initialized to 1

}

function updatingItemQuantity(cart_ID, quantity){
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        loadingItemsToCart();
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=update&cart_ID=" + cart_ID + "&quantity=" + quantity); //adds the product, cart id (different from original quantity initialization for item), and new quantity

}

function deletingItemFromCart(cart_ID){
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        loadingItemsToCart();
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=delet&cart_ID=" + cart_ID); //deletes the item in cart

}

function checkoutSingleItem(cart_ID){
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        loadingItemsToCart();
        alert("Order Completed!");
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=checkoutSingleItem&cart_ID=" + cart_ID); // completing purchase for one item

}

function checkoutEntireCart(cart_ID){
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        loadingItemsToCart();
        alert("Order Completed!");
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=checkoutEntireCart"); // completing purchase for all items
    
}

document.addEventListener('DOMContentLoaded', function(){
    loadingItemsToCart(); //with page refresh, load cart
});

//payment popup
const checkoutBtn = document.getElementById('checkoutBtn');
const paymentPopUp = document.getElementById('paymentPopUp');
const payNowBtn = document.getElementById('payNowBtn');

let checkoutMode = 'cart';
let checkoutCart_ID = null;

//when checkout button is clicked, popup is revealed
function showPaymentPopup(mode, cart_ID = null){
    checkoutMode = mode;4
    checkoutCart_ID = cart_ID;
    paymentPopUp.classList.add('active');
    
}

//hide popup
function hidePayPopup(){
    if(!paymentPopUp){return;}
    paymentPopUp.classList.remove('active');
}

//processing payment

function paymentProcessing(){
    const cardNum = document.querySelector('#paymentPopup input[placeholder="1234 5678 9012 3456"]').value;
    const expiryDate = document.querySelector('#paymentPopup input[placeholder="MM/YY"]').value;
    const cvv = document.querySelector('#paymentPopup input[placeholder="123"]').value;

    if(!cardNum || !expiryDate || !cvv){
        alert("Enter all card info details!");
        return;
    }
    if(checkoutMode == 'cart'){
        checkoutEntireCart();
    }else{
        checkoutSingleItem(checkoutCart_ID);
    }
    hidePayPopup();
    alert('Payment Completed!');
}

//closes popup when clicked elsewhere on page
paymentPopUp.addEventListener('click', function(e) {
    if(e.target === this) {
        hidePayPopup();
    }
});


