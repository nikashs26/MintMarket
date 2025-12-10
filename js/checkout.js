
document.addEventListener('DOMContentLoaded', function () {
    loadCheckoutItems();
});

let currentListingId = null;
let currentMode = 'cart'; // 'cart' or 'single'
let currentTotal = 0; // Store total for USD conversion logic

function loadCheckoutItems() {
    const urlParams = new URLSearchParams(window.location.search);
    const listingId = urlParams.get('listing_id');

    if (listingId) {
        currentMode = 'single';
        currentListingId = listingId;
        loadSingleItem(listingId);
    } else {
        currentMode = 'cart';
        loadCartItems();
    }
}

async function loadSingleItem(listingId) {
    // Fetch active listings to find the specific item details (including price)
    // using the existing marketplace API.

    const container = document.getElementById('orderItems');
    container.innerHTML = '<div class="loading">Fetching item details...</div>';

    try {
        // Corrected: Fetch active listings from marketplace API
        const response = await fetch('api/marketplace.php?action=get_listings&limit=100');
        const data = await response.json();

        if (data.success) {
            // Find the specific listing
            const listing = data.listings.find(l => l.listing_id == listingId);

            if (listing) {
                renderSingleItem(listing);
            } else {
                container.innerHTML = '<div class="error">Listing not found or expired.</div>';
            }
        } else {
            container.innerHTML = '<div class="error">Failed to load item.</div>';
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = '<div class="error">Error loading item.</div>';
    }
}

function renderSingleItem(listing) {
    const listHtml = `
        <div class="item-row">
            <div>
                <strong>${listing.title}</strong><br>
                <small>by ${listing.creator_username}</small>
            </div>
            <span>${parseFloat(listing.price).toFixed(2)} MTK</span>
        </div>
    `;

    const container = document.getElementById('orderItems');
    if (container) container.innerHTML = listHtml;

    const price = parseFloat(listing.price);
    const fee = price * 0.025;
    const total = price + fee;

    updateSummary(price, fee, total);
}

function loadCartItems() {
    // Reuse cart_functionalities.php logic via AJAX
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function () {
        try {
            console.log('Raw Cart Response:', this.responseText); // Debug

            let response;
            try {
                response = JSON.parse(this.responseText);
            } catch (jsonErr) {
                console.error('JSON Parse Error:', jsonErr);
                document.getElementById("orderItems").innerHTML = 'Error loading cart. Please try again.';
                return;
            }

            // Update HTML list
            const container = document.getElementById("orderItems");
            if (response.HTMLitems || response.itemsInCart) {
                container.innerHTML = response.HTMLitems || response.itemsInCart;
            } else {
                container.innerHTML = 'Your cart is empty.';
            }

            // Parse numbers safely
            const subtotal = parseFloat(response.subtotal) || 0;
            const fee = parseFloat(response.fee) || 0;
            const total = parseFloat(response.total) || 0;

            console.log('Parsed Totals:', { subtotal, fee, total });
            updateSummary(subtotal, fee, total);

        } catch (e) {
            console.error('Error in loadCartItems:', e);
            document.getElementById("orderItems").innerHTML = 'Error loading cart.';
        }
    };
    xhttp.onerror = function () {
        console.error('Request failed');
        document.getElementById("orderItems").innerHTML = 'Network error. Please try again.';
    };
    xhttp.open("GET", "cart_functionalities.php?action=load");
    xhttp.send();
}

function updateSummary(subtotal, fee, total) {
    currentTotal = total;
    const subEl = document.getElementById('checkoutSubtotal');
    const feeEl = document.getElementById('checkoutFee');
    const totalEl = document.getElementById('checkoutTotal');

    if (subEl) subEl.innerText = subtotal.toFixed(2) + ' MTK';
    if (feeEl) feeEl.innerText = fee.toFixed(2) + ' MTK';

    // Always update total text initially
    if (totalEl) totalEl.innerText = total.toFixed(2) + ' MTK';

    // Then run USD conversion logic
    updateUSDConversion(total);
}

function updateUSDConversion(totalMTK) {
    const isCard = document.querySelector('input[name="paymentMethod"]:checked')?.value === 'card';
    const totalEl = document.getElementById('checkoutTotal');

    if (isCard) {
        const usd = totalMTK * 2;
        totalEl.innerHTML = `${totalMTK.toFixed(2)} MTK <span style="font-size: 0.8em; color: #059669;">($${usd.toFixed(2)} USD)</span>`;
    } else {
        totalEl.innerText = totalMTK.toFixed(2) + ' MTK';
    }
}

function togglePaymentForm() {
    const method = document.querySelector('input[name="paymentMethod"]:checked').value;
    document.getElementById('cardFields').style.display = (method === 'card') ? 'block' : 'none';
    updateUSDConversion(currentTotal);
}

async function confirmPayment() {
    const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
    const btn = document.querySelector('#paymentForm button[type="submit"]');
    const originalText = btn.innerText;

    btn.innerText = 'Processing...';
    btn.disabled = true;

    // Simulate Card Payment
    if (paymentMethod === 'card') {
        const cardNum = document.querySelector('input[placeholder="1234 5678 9012 3456"]').value;
        if (!cardNum && paymentMethod === 'card') {
            alert('Please enter a valid card number.');
            btn.innerText = originalText;
            btn.disabled = false;
            return;
        }

        // Mock delay for card processing
        await new Promise(r => setTimeout(r, 1500));

        // Simulate processing delay and proceed to success logic (backend balance check will still occur)
    }

    if (currentMode === 'single') {
        // Execute single purchase
        // Create a temporary endpoint api/buy.php (or dedicated action) if needed, 
        // but for now relying on creating api/buy.php as discussed in plan.

        // Implementing logic to call backend:
        try {
            // We'll create `api/buy.php` for this purpose to be clean.
            const response = await fetch('api/buy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ listing_id: currentListingId })
            });
            const result = await response.json();

            if (result.success) {
                alert('Purchase Successful!');
                window.location.href = 'profile.html';
            } else {
                alert('Purchase Failed: ' + result.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            alert('Network error.');
            btn.innerText = originalText;
            btn.disabled = false;
        }

    } else {
        // Execute Cart checkout
        const xhttp = new XMLHttpRequest();
        xhttp.onload = function () {
            try {
                const response = JSON.parse(this.responseText);
                if (response.status === 'success') {
                    alert("Order Completed! " + response.message);
                    window.location.href = 'profile.html';
                } else {
                    alert("Order failed: " + response.message);
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            } catch (e) {
                console.error("JSON parse error", e);
                alert("Order failed: " + this.responseText || "Unknown error");
                btn.innerText = originalText;
                btn.disabled = false;
            }
        };
        xhttp.open("POST", "cart_functionalities.php");
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("action=checkoutEntireCart");
    }
}
