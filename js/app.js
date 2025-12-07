/**
 * MintMarket Application Logic
 */

const api = (() => {
    try {
        if (typeof MintMarketAPI !== 'undefined') {
            console.log('MintMarket App: API Initialized');
            return new MintMarketAPI();
        } else {
            console.error('MintMarket App: MintMarketAPI class not found');
            return {
                getProfile: async () => ({ success: false }),
                // Minimum mock to prevent immediate crash on load
            };
        }
    } catch (e) {
        console.error('MintMarket App: Failed to initialize API', e);
        return { getProfile: async () => ({ success: false }) };
    }
})();

// Simple router based on current page
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuthStatus();
    updateCartBadge(); // Update cart badge on page load

    const path = window.location.pathname;

    if (path.includes('marketplace.html')) {
        loadMarketplace();
        initTagFilters();
    } else if (path.includes('profile.html')) {
        loadProfile();
    } else if (path.includes('mint.html')) {
        // Check auth before allowing mint
        const authCheck = await api.getProfile();
        if (!authCheck.success) {
            alert('Please log in to mint NFTs.');
            window.location.href = 'index.html#login';
        } else {
            setupMintForm();
        }
    } else if (path.includes('cart.html')) {
        loadCart();
    } else {
        // Home page logic if any
    }

    // Global Event Listeners
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
});

async function checkAuthStatus() {
    const result = await api.getProfile();
    updateNav(result.success, result.profile);
    updateCartBadge();
}

function updateNav(isLoggedIn, user = null) {
    const authLinks = document.getElementById('authLinks');
    const userLinks = document.getElementById('userLinks');

    if (isLoggedIn) {
        if (authLinks) authLinks.style.display = 'none';
        if (userLinks) {
            userLinks.style.display = 'flex';
            const navUsername = document.getElementById('navUsername');
            if (navUsername && user) {
                navUsername.textContent = user.username.toUpperCase();
            }
        }
    } else {
        if (authLinks) authLinks.style.display = 'flex';
        if (userLinks) userLinks.style.display = 'none';
    }
}

async function handleLogout(e) {
    e.preventDefault();
    const result = await api.logout();
    if (result.success) {
        // Clear any cached data
        window.location.href = 'index.html';
    } else {
        alert('Logout failed. Please try again.');
    }
}

// --- Marketplace Page ---

// --- Marketplace Filtering ---
let activeTagFilters = [];
let currentSearchTerm = '';

async function loadMarketplace(tags = '', search = '') {
    const container = document.getElementById('listingsContainer');
    if (!container) return;

    container.innerHTML = '<div class="loading">Loading listings...</div>';

    const result = await api.getActiveListings(50, 0, tags, search);

    if (result.success) {
        if (result.listings.length === 0) {
            container.innerHTML = '<div class="no-data">No NFTs found matching your filters.</div>';
            return;
        }

        container.innerHTML = result.listings.map(listing => {
            // Generate tag badges from actual tags
            const tagsArray = listing.tags_array || [];
            const tagBadges = tagsArray.slice(0, 3).map(tag =>
                `<span class="nft-tag">${tag}</span>`
            ).join('');

            return `
            <div class="nft-card">
                <div class="nft-image" style="background-image: url('${listing.image_url}')">
                    <div class="nft-tags">
                        ${tagBadges || '<span class="nft-tag">NFT</span>'}
                    </div>
                </div>
                <div class="nft-info">
                    <h3>${listing.title}</h3>
                    <p class="creator">${listing.creator_username}</p>
                    <div class="price-box">
                        <span class="price-label">Current Price</span>
                        <span class="price"><span class="currency">MTK</span> ${parseFloat(listing.price).toFixed(2)}</span>
                    </div>
                    <div class="nft-actions">
                        <button onclick="buyNFT(${listing.listing_id})" class="btn-buy">BUY NOW</button>
                        <button onclick="addToCart(${listing.listing_id})" class="btn-secondary">ADD TO CART</button>
                    </div>
                </div>
            </div>
        `}).join('');
    } else {
        container.innerHTML = '<div class="error">Failed to load listings.</div>';
    }
}

function toggleFilters() {
    const panel = document.getElementById('tagFiltersPanel');
    const btn = document.getElementById('filterToggle');
    if (panel) {
        if (panel.style.display === 'none') {
            panel.style.display = 'block';
            btn.textContent = 'FILTER ▲';
        } else {
            panel.style.display = 'none';
            btn.textContent = 'FILTER ▼';
        }
    }
}

function initTagFilters() {
    const filterTags = document.querySelectorAll('.filter-tag');
    filterTags.forEach(tag => {
        tag.addEventListener('click', () => {
            const tagValue = tag.dataset.tag;
            if (tag.classList.contains('active')) {
                tag.classList.remove('active');
                activeTagFilters = activeTagFilters.filter(t => t !== tagValue);
            } else {
                tag.classList.add('active');
                activeTagFilters.push(tagValue);
            }
            updateActiveFiltersDisplay();
            applyFilters();
        });
    });
}

function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFilters');
    const tagsContainer = document.getElementById('activeFilterTags');

    if (activeTagFilters.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'flex';
    tagsContainer.innerHTML = activeTagFilters.map(tag => `
        <span class="active-filter-tag">
            ${tag}
            <button class="remove-tag" onclick="removeTagFilter('${tag}')">×</button>
        </span>
    `).join('');
}

function removeTagFilter(tag) {
    activeTagFilters = activeTagFilters.filter(t => t !== tag);

    // Update button state
    const filterTags = document.querySelectorAll('.filter-tag');
    filterTags.forEach(btn => {
        if (btn.dataset.tag === tag) {
            btn.classList.remove('active');
        }
    });

    updateActiveFiltersDisplay();
    applyFilters();
}

function clearFilters() {
    activeTagFilters = [];
    currentSearchTerm = '';

    // Clear button states
    const filterTags = document.querySelectorAll('.filter-tag');
    filterTags.forEach(btn => btn.classList.remove('active'));

    // Clear search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';

    updateActiveFiltersDisplay();
    applyFilters();
}

function handleSearch(event) {
    if (event.key === 'Enter') {
        currentSearchTerm = event.target.value.trim();
        applyFilters();
    }
}

function applyFilters() {
    const tags = activeTagFilters.join(',');
    loadMarketplace(tags, currentSearchTerm);
}

// Make filter functions global
window.toggleFilters = toggleFilters;
window.clearFilters = clearFilters;
window.handleSearch = handleSearch;
window.removeTagFilter = removeTagFilter;

async function buyNFT(listingId) {
    // Check if user is logged in
    const authCheck = await api.getProfile();
    if (!authCheck.success) {
        const shouldLogin = confirm('You need to log in to purchase NFTs. Would you like to log in now?');
        if (shouldLogin) {
            window.location.href = 'index.html#login';
            return;
        }
        return;
    }

    if (!confirm('Are you sure you want to purchase this NFT?')) return;

    const result = await api.buyNFT(listingId);
    if (result.success) {
        alert(`Purchase successful! Transaction Hash: ${result.transaction_hash.substring(0, 10)}...`);
        loadMarketplace(); // Refresh
        checkAuthStatus(); // Update balance
    } else {
        alert('Purchase failed: ' + result.message);
    }
}

// --- Profile Page ---

async function loadProfile() {
    const profileInfo = document.getElementById('profileInfo');
    const userNFTs = document.getElementById('userNFTs');

    if (!profileInfo) return;

    const result = await api.getProfile();

    if (result.success) {
        // User is logged in, show profile
        const p = result.profile;
        const currentYear = new Date().getFullYear();
        const createdYear = p.created_at ? new Date(p.created_at).getFullYear() : currentYear;
        const initial = p.username.charAt(0).toUpperCase();
        profileInfo.innerHTML = `
            <div class="profile-picture">${initial}</div>
            <div class="profile-name">${p.username.toUpperCase()}</div>
            <div class="profile-meta">MEMBER SINCE ${createdYear}</div>
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-value">${p.balance ? parseFloat(p.balance).toFixed(0) : '0'}</div>
                    <div class="profile-stat-label">MTK Balance</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-value" id="nftCount">0</div>
                    <div class="profile-stat-label">NFTs Owned</div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="mint.html" class="profile-action-btn primary">MINT NEW NFT</a>
                <a href="marketplace.html" class="profile-action-btn">BROWSE MARKET</a>
                <button class="profile-action-btn danger" id="logoutBtn">LOGOUT</button>
            </div>
        `;

        // Add logout handler
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }

        // Load User's NFTs
        loadUserNFTs();
    } else {
        // Not logged in - redirect to homepage with login prompt
        alert('Please log in to view your profile.');
        window.location.href = 'index.html#login';
    }
}

async function loadUserNFTs() {
    const response = await fetch('api/nft.php?action=my_nfts');
    const result = await response.json();

    const container = document.getElementById('userNFTs');
    if (result.success) {
        if (result.nfts.length === 0) {
            container.innerHTML = '<div class="no-data">You don\'t own any NFTs yet.</div>';
            return;
        }

        // Update NFT count in profile
        const nftCountEl = document.getElementById('nftCount');
        if (nftCountEl) nftCountEl.textContent = result.nfts.length;

        container.innerHTML = result.nfts.map(nft => `
            <div class="nft-card">
                <div class="nft-image" style="background-image: url('${nft.image_url}')">
                    <div class="nft-tags">
                        <span class="nft-tag">${nft.is_listed ? 'LISTED' : 'OWNED'}</span>
                        <span class="nft-tag">${nft.royalty_percent || 10}% ROYALTY</span>
                    </div>
                </div>
                <div class="nft-info">
                    <h3>${nft.title}</h3>
                    <p class="creator">Created by you</p>
                    <div class="nft-actions">
                        ${nft.is_listed
                ? '<button class="btn-secondary" disabled style="opacity: 0.7;">ON SALE</button>'
                : `<button onclick="listNFT(${nft.nft_id})" class="btn-buy">LIST FOR SALE</button>`
            }
                    </div>
                </div>
            </div>
        `).join('');
    }
}

async function listNFT(nftId) {
    // Check if user is logged in
    const authCheck = await api.getProfile();
    if (!authCheck.success) {
        const shouldLogin = confirm('You need to log in to list NFTs for sale. Would you like to log in now?');
        if (shouldLogin) {
            window.location.href = 'index.html#login';
            return;
        }
        return;
    }

    const price = prompt("Enter sale price in MTK:");
    if (price === null) return;

    if (isNaN(price) || price <= 0) {
        alert("Invalid price");
        return;
    }

    const result = await api.createListing(nftId, price);
    if (result.success) {
        alert("NFT Listed Successfully!");
        loadUserNFTs();
    } else {
        alert("Failed to list: " + result.message);
    }
}

// --- Mint Page ---

function setupMintForm() {
    const form = document.getElementById('mintForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Check if user is logged in
        const authCheck = await api.getProfile();
        if (!authCheck.success) {
            const shouldLogin = confirm('You need to log in to mint NFTs. Would you like to log in now?');
            if (shouldLogin) {
                window.location.href = 'index.html#login';
                return;
            }
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Minting... (Mining Block)';

        const title = document.getElementById('title').value;
        const description = document.getElementById('description').value;
        const royalty = document.getElementById('royalty').value;
        const price = document.getElementById('price').value;
        const imageFile = document.getElementById('image').files[0];

        // Collect selected tags
        const tagCheckboxes = document.querySelectorAll('input[name="tags"]:checked');
        const selectedTags = Array.from(tagCheckboxes).map(cb => cb.value);

        // Validate tags (max 5)
        if (selectedTags.length > 5) {
            alert('Please select a maximum of 5 tags');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Mint & List NFT';
            return;
        }

        // Validate price
        if (!price || parseFloat(price) <= 0) {
            alert('Please enter a valid price');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Mint & List NFT';
            return;
        }

        const result = await api.mintNFT(title, description, imageFile, royalty, selectedTags);

        if (result.success) {
            // Now list the NFT for sale
            submitBtn.textContent = 'Listing on Marketplace...';
            const listingResult = await api.createListing(result.nft_id, parseFloat(price));

            if (listingResult.success) {
                alert('NFT Minted and Listed Successfully!');
                window.location.href = 'marketplace.html';
            } else {
                alert('NFT Minted but listing failed: ' + listingResult.message + '\nYou can list it from your profile.');
                window.location.href = 'profile.html';
            }
        } else {
            alert('Minting failed: ' + result.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Mint NFT';
        }
    });
}

// --- Cart Functionality ---

function getCart() {
    const cart = localStorage.getItem('mintmarket_cart');
    return cart ? JSON.parse(cart) : [];
}

function saveCart(cart) {
    localStorage.setItem('mintmarket_cart', JSON.stringify(cart));
    updateCartBadge();
}

function updateCartBadge() {
    const cart = getCart();
    const badges = document.querySelectorAll('.cart-badge, #cartBadge');
    badges.forEach(badge => {
        if (cart.length > 0) {
            badge.textContent = cart.length;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    });
}

async function addToCart(listingId) {
    // Check if user is logged in
    const authCheck = await api.getProfile();
    if (!authCheck.success) {
        const shouldLogin = confirm('You need to log in to add items to cart. Would you like to log in now?');
        if (shouldLogin) {
            window.location.href = 'index.html#login';
            return;
        }
        return;
    }

    // Get listing details
    const result = await api.getActiveListings();
    if (!result.success) {
        alert('Failed to get listing details');
        return;
    }

    const listing = result.listings.find(l => l.listing_id == listingId);
    if (!listing) {
        alert('Listing not found');
        return;
    }

    // Add to cart
    const cart = getCart();

    // Check if already in cart
    if (cart.find(item => item.listing_id == listingId)) {
        alert('This item is already in your cart!');
        return;
    }

    cart.push({
        listing_id: listing.listing_id,
        nft_id: listing.nft_id,
        title: listing.title,
        image_url: listing.image_url,
        price: parseFloat(listing.price),
        creator_username: listing.creator_username,
        royalty_percent: listing.royalty_percent || 10
    });

    saveCart(cart);

    // Show confirmation
    const goToCart = confirm('Added to cart! Would you like to view your cart?');
    if (goToCart) {
        window.location.href = 'cart.html';
    }
}

function removeFromCart(listingId) {
    let cart = getCart();
    cart = cart.filter(item => item.listing_id != listingId);
    saveCart(cart);
    loadCart(); // Refresh cart display
}

async function loadCart() {
    const container = document.getElementById('cartItems');
    if (!container) return;

    const cart = getCart();

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="cart-empty">
                <div class="cart-empty-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Discover amazing NFTs in our marketplace</p>
                <a href="marketplace.html" class="btn-primary">Browse Marketplace</a>
            </div>
        `;
        updateSummary(0);
        return;
    }

    container.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-image" style="background-image: url('${item.image_url}')"></div>
            <div class="cart-item-details">
                <h3>${item.title}</h3>
                <p class="cart-item-creator">by ${item.creator_username}</p>
                <p class="cart-item-royalty">${item.royalty_percent}% Royalty</p>
            </div>
            <div class="cart-item-price">
                <span class="price-label">Price</span>
                <span class="price-value">${item.price.toFixed(2)} MTK</span>
            </div>
            <button class="cart-item-remove" onclick="removeFromCart(${item.listing_id})">
                ✕
            </button>
        </div>
    `).join('');

    // Calculate totals
    const subtotal = cart.reduce((sum, item) => sum + item.price, 0);
    updateSummary(subtotal);
}

function updateSummary(subtotal) {
    const platformFee = subtotal * 0.025;
    const total = subtotal + platformFee;

    const subtotalEl = document.getElementById('subtotal');
    const feeEl = document.getElementById('platformFee');
    const totalEl = document.getElementById('totalPrice');
    const checkoutBtn = document.getElementById('checkoutBtn');

    if (subtotalEl) subtotalEl.textContent = `${subtotal.toFixed(2)} MTK`;
    if (feeEl) feeEl.textContent = `${platformFee.toFixed(2)} MTK`;
    if (totalEl) totalEl.textContent = `${total.toFixed(2)} MTK`;
    if (checkoutBtn) {
        checkoutBtn.disabled = subtotal === 0;
        if (subtotal === 0) {
            checkoutBtn.style.opacity = '0.5';
            checkoutBtn.style.cursor = 'not-allowed';
        } else {
            checkoutBtn.style.opacity = '1';
            checkoutBtn.style.cursor = 'pointer';
        }
    }
}

async function checkout() {
    const cart = getCart();
    if (cart.length === 0) {
        alert('Your cart is empty!');
        return;
    }

    // Check if user is logged in
    const authCheck = await api.getProfile();
    if (!authCheck.success) {
        const shouldLogin = confirm('You need to log in to complete your purchase. Would you like to log in now?');
        if (shouldLogin) {
            window.location.href = 'index.html#login';
        }
        return;
    }

    const checkoutBtn = document.getElementById('checkoutBtn');
    checkoutBtn.disabled = true;
    checkoutBtn.textContent = 'Processing...';

    let successCount = 0;
    let failedItems = [];

    // Process each item in cart
    for (const item of cart) {
        const result = await api.buyNFT(item.listing_id);
        if (result.success) {
            successCount++;
        } else {
            failedItems.push({ title: item.title, error: result.message });
        }
    }

    // Clear successful items from cart
    if (successCount > 0) {
        // Clear cart
        saveCart([]);
    }

    checkoutBtn.disabled = false;
    checkoutBtn.textContent = 'COMPLETE PURCHASE';

    if (failedItems.length === 0) {
        alert(`Successfully purchased ${successCount} NFT(s)! Check your profile to see your collection.`);
        window.location.href = 'profile.html';
    } else if (successCount > 0) {
        alert(`Purchased ${successCount} NFT(s). ${failedItems.length} item(s) failed: ${failedItems.map(f => f.title).join(', ')}`);
        loadCart();
    } else {
        alert(`Purchase failed: ${failedItems[0].error}`);
    }
}

// Make functions global for onclick handlers
window.buyNFT = buyNFT;
window.listNFT = listNFT;
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.loadCart = loadCart;
window.checkout = checkout;
