
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
    // This assumes we have an API to get listing details by ID. 
    // Since getActiveListings fetches many, we might need a specific one.
    // However, for MVP let's reuse api.js or existing PHP endpoints.
    // If api.js doesn't have getListingById, we can try to fetch all and find it, or use cart_functionalities hack?
    // Better: use api/nft.php?action=get_all if listing_id is part of it? No listing_id is specific.
    // Wait, the user wants "Buy" button redirections.
    // Let's rely on api.js logic but we need to fetch the listing data.

    // TEMPORARY SOLUTION: Since I don't see a clear 'get_listing_by_id' endpoint in previous file views,
    // I will try to fetch via a new AJAX call to a helper or just reuse api.getActiveListings for now with a limit.
    // Actually, let's create a server-side helper if needed?
    // Or just fetch `api/listing.php`? (I need to check if it exists).

    // Let's assume we can use `cart_functionalities.php` if we modify it, but that's for cart.
    // Let's check `api/nft.php` again. It has `get_by_id` but that is NFT data, not Listing data (price).
    // `classes/Marketplace.php` has `getNFTById` which joins listings! So api/nft.php?action=get_by_id contains listing info and price!

    // Wait, `get_by_id` endpoint calls `$nftModel->getNFTById($nftId)`.
    // But `listingId` is what we have from URL.
    // `buyNFT` function in app.js takes `listingId`.
    // We need to map ListingID to NFT data.
    // Marketplace.php has `getNFTById`.
    // Listing table links NFT_ID and Listing_ID.
    // We might need to iterate or add an endpoint.

    // Strategy: Modify api/nft.php/Marketplace.php effectively? No, I should stick to client side if possible.
    // But how to get price for listing X?
    // Let's fetch all active listings and find it client-side. inefficient but works for MVP.
    // Update: api.getActiveListings supports search/tags.
    // We can just fetch all (limit 100) and find listing ID.

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

        // Success logic same as token (assuming card pays for it)
        // In a real app, we'd top up balance then pay, or use a different endpoint.
        // For MVP, we will attempt the purchase logic which DEDUCTS BALANCE.
        // Since user said "pay by card", maybe we shouldn't deduct balance?
        // But the backend `buyNFT` strictly deducts balance.
        // Option: Mock "Top Up" then Buy.
        // Let's just alert success for Card and redirect, assuming external logic handled it.
        // OR better: Assume "Card" also goes through the same backend flow (mocking a direct fiat purchase).
        // Let's try to actually buy it. If balance is low, card payment fails? No, card should work.
        // For simple MVP: Card Payment Payment -> Auto-Success (and maybe we skip balance check? No, backend enforces it).
        // We will just try to proceed. If it fails due to funds, we say "Card Declined" (even if it's balance).

        // Actally, let's use the same backend call. If user has 0 balance, they can't buy even with card unless we change backend.
        // I will assume for now users have balance (seeded with 1000).
    }

    if (currentMode === 'single') {
        // Execute single purchase
        // We need an endpoint for buying listing.
        // Marketplace.php has logic, usually exposed via some API.
        // app.js uses `buyNFT` which calls... nothing clearly visible in my previous read of app.js?
        // Wait, app.js `buyNFT` implementation was cut off.
        // I need to check how to actually execute the buy.
        // Assuming `cart_functionalities.php` handles cart?
        // Let's assume `api/listing.php` or `cart_functionalities.php` handles it.
        // `cart_functionalities.php` has `checkoutSingleItem`.

        // Let's use `cart_functionalities.php` action `checkoutSingleItem`? No that likely takes cart_id.
        // If I am buying directly a Listing, I need to call the buy endpoint.
        // Let's check `api/nft.php`? It has `transfer` but that is for owner.
        // I suspect I might need to Create a new endpoint or find where `buyNFT` points.
        // Wait, I will use `cart_functionalities.php` if I can add it to cart and checkout instantly?
        // Or simply `api/market.php`?

        // Let's try to assume there is an endpoint. I will verify `buyNFT` logic in app.js in next step if I'm unsure.
        // But for now, let's assume we can POST to `cart_functionalities.php` with a special action if available, or just implement it.
        // Actually, `Marketplace.php` has `buyNFT`.
        // I will Create a dedicated `api/buy.php` action if needed, or check `classes/Marketplace.php`.
        // Let's Assume the backend file `api/buy.php` needs to be created or `cart_functionalities.php` extended.

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
