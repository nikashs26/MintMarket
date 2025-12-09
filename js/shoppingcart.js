//use ajax php to update cart without refreshing whole page, 
//just update the div and add anotehr item to cart 
//add function from this website: https://www.w3schools.com/PHP/php_ajax_database.asp 

//functions:
// load to cart
// order summary updates
//update delete item 
//update quantity of item 
//update add item

function loadingItemsToCart() {
    const xhttp = new XMLHttpRequest(); //making ajax request to smoothly update div in cart w/out updating whole page
    xhttp.onload = function () {
        try {
            const response = JSON.parse(this.responseText); //json response parsed

            // Attempt to update all possible cart containers found on the page
            const containerIDs = ["itemsInCart", "cartItems", "orderItems"];

            containerIDs.forEach(id => {
                const container = document.getElementById(id);
                if (container) {
                    // Check if response.itemsInCart exists (from PHP change) or use HTMLitems
                    const htmlContent = response.HTMLitems || response.itemsInCart;
                    if (htmlContent) {
                        container.innerHTML = htmlContent;
                    }
                }
            });

            // Only update summary if elements exist
            if (document.getElementById("subtotal")) {
                updatedOrdSum(response.subtotal, response.fee, response.total); //order summary is updated
            }

            const cartIcon = document.getElementById("cartNavIcon"); //updates bag icon in nav bar
            if (cartIcon && response.totalItemCount !== undefined) {
                if (response.totalItemCount > 0) {
                    cartIcon.style.display = "inline";
                    cartIcon.innerText = response.totalItemCount;
                } else {
                    cartIcon.style.display = "none";
                }
            }
        } catch (e) {
            console.error("Error parsing cart JSON", e);
        }
    };

    xhttp.open("GET", "cart_functionalities.php?action=load");
    xhttp.send();
}

function updatedOrdSum(subtotal, fee, total) {
    const subEl = document.getElementById("subtotal");
    const feeEl = document.getElementById("platformFee");
    const totalEl = document.getElementById("totalPrice"); // Fixed ID

    if (subEl) subEl.innerText = subtotal.toFixed(2) + " MTK";
    if (feeEl) feeEl.innerText = fee.toFixed(2) + " MTK";
    if (totalEl) totalEl.innerText = total.toFixed(2) + " MTK";
}

function addingToCart(product_ID) {
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        try {
            const response = JSON.parse(this.responseText);
            if (response.status === 'exists') {
                showCartSuccessModal(response.message);
            } else if (response.status === 'own_item') {
                showCartSuccessModal(response.message);
            } else if (response.status === 'success') {
                loadingItemsToCart();
                showCartSuccessModal("Added to Cart!");
            } else {
                loadingItemsToCart();
            }
        } catch (e) {
            console.warn("Legacy response or error", e);
            loadingItemsToCart();
        }
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=add&product_ID=" + product_ID + "&quantity=1");
}

function updatingItemQuantity(cart_ID, quantity) {
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        loadingItemsToCart();
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=update&cart_ID=" + cart_ID + "&quantity=" + quantity);
}

// Automatically load cart items when the page finishes loading
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('itemsInCart')) {
        loadingItemsToCart();
    }
});

function deletingItemFromCart(cart_ID) {
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        loadingItemsToCart();
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=delet&cart_ID=" + cart_ID); //deletes the item in cart

}

function checkoutSingleItem(cart_ID) {
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        try {
            const response = JSON.parse(this.responseText);
            loadingItemsToCart();
            if (response.status === 'success') {
                alert("Order Completed! " + response.message);
            } else {
                alert("Order failed: " + response.message);
            }
        } catch (e) {
            console.error("JSON parse error", e);
            alert("Order failed: Unknown error");
        }
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=checkoutSingleItem&cart_ID=" + cart_ID); // completing purchase for one item

}



// New Success Modal Logic
function showCartSuccessModal(msg = 'Items added to cart!') {
    const modal = document.getElementById('cartSuccessModal');
    if (modal) {
        // Update message text if possible (assuming simple structure, checking for p tag or inserting text)
        // If modal structure is strict, we might need to find the specific text element.
        // Based on previous edits, let's try to set the text of the message container if it exists, or just use alert fallback if complex.
        const msgContainer = modal.querySelector('.modal-message') || modal.querySelector('p') || modal;
        if (msgContainer) msgContainer.textContent = msg;

        modal.classList.add('active');
        setTimeout(() => {
            modal.classList.remove('active');
        }, 3000); // Hide after 3 seconds
    } else {
        alert(msg);
    }
}

function checkoutEntireCart(cart_ID) {
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        try {
            const response = JSON.parse(this.responseText);
            loadingItemsToCart();
            if (response.status === 'success') {
                alert("Order Completed! " + response.message);
            } else {
                alert("Order failed: " + response.message);
            }
        } catch (e) {
            console.error("JSON parse error", e);
            alert("Order failed: Unknown error");
        }
    };
    xhttp.open("POST", "cart_functionalities.php");
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=checkoutEntireCart"); // completing purchase for all items

}

document.addEventListener('DOMContentLoaded', function () {
    // Small delay to ensure session/DB is ready (addresses user's "loads too fast" concern)
    setTimeout(loadingItemsToCart, 500);
});

//payment popup
const checkoutBtn = document.getElementById('checkoutBtn');
const paymentPopUp = document.getElementById('paymentPopUp');
const payNowBtn = document.getElementById('payNowBtn');

let checkoutMode = 'cart';
let checkoutCart_ID = null;

//when checkout button is clicked, popup is revealed
function showPaymentPopup(mode, cart_ID = null) {
    window.location.href = 'checkout.html';
}

//hide popup
function hidePayPopup() {
    if (!paymentPopUp) { return; }
    paymentPopUp.classList.remove('active');
}

//processing payment

function paymentProcessing() {
    const cardNum = document.querySelector('#paymentPopup input[placeholder="1234 5678 9012 3456"]').value;
    const expiryDate = document.querySelector('#paymentPopup input[placeholder="MM/YY"]').value;
    const cvv = document.querySelector('#paymentPopup input[placeholder="123"]').value;

    if (!cardNum || !expiryDate || !cvv) {
        alert("Enter all card info details!");
        return;
    }
    if (checkoutMode == 'cart') {
        checkoutEntireCart();
    } else {
        checkoutSingleItem(checkoutCart_ID);
    }
    hidePayPopup();
    // alert('Payment Completed!'); // Removed premature alert. Reliance on AJAX callback.
}

//closes popup when clicked elsewhere on page
//closes popup when clicked elsewhere on page
if (paymentPopUp) {
    paymentPopUp.addEventListener('click', function (e) {
        if (e.target === this) {
            hidePayPopup();
        }
    });
}


// Make global
window.loadingItemsToCart = loadingItemsToCart;
window.addingToCart = addingToCart;
window.updatingItemQuantity = updatingItemQuantity;
window.deletingItemFromCart = deletingItemFromCart;
window.checkoutSingleItem = checkoutSingleItem;
window.checkoutEntireCart = checkoutEntireCart;
window.showPaymentPopup = showPaymentPopup;
window.hidePayPopup = hidePayPopup;
window.paymentProcessing = paymentProcessing;
