/**
 * MintMarket Application Logic
 */

const api = new MintMarketAPI();

// Simple router based on current page
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuthStatus();

    const path = window.location.pathname;

    if (path.includes('marketplace.html')) {
        loadMarketplace();
    } else if (path.includes('profile.html')) {
        loadProfile();
    } else if (path.includes('mint.html')) {
        setupMintForm();
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
}

function updateNav(isLoggedIn, user = null) {
    const authLinks = document.getElementById('authLinks');
    const userLinks = document.getElementById('userLinks');

    if (isLoggedIn) {
        if (authLinks) authLinks.style.display = 'none';
        if (userLinks) {
            userLinks.style.display = 'flex';
            document.getElementById('navUsername').textContent = user.username;
            document.getElementById('navBalance').textContent = parseFloat(user.balance).toFixed(2) + ' MTK';
        }
    } else {
        if (authLinks) authLinks.style.display = 'flex';
        if (userLinks) userLinks.style.display = 'none';
    }
}

async function handleLogout(e) {
    e.preventDefault();
    await api.logout();
    window.location.href = 'index.html';
}

// --- Marketplace Page ---

async function loadMarketplace() {
    const container = document.getElementById('listingsContainer');
    if (!container) return;

    container.innerHTML = '<div class="loading">Loading listings...</div>';

    const result = await api.getActiveListings();

    if (result.success) {
        if (result.listings.length === 0) {
            container.innerHTML = '<div class="no-data">No active listings found.</div>';
            return;
        }

        container.innerHTML = result.listings.map(listing => `
            <div class="nft-card">
                <div class="nft-image" style="background-image: url('${listing.image_url}')"></div>
                <div class="nft-info">
                    <h3>${listing.title}</h3>
                    <p class="creator">by ${listing.creator_username}</p>
                    <div class="price-box">
                        <span class="price">${parseFloat(listing.price).toFixed(2)} MTK</span>
                        <button onclick="buyNFT(${listing.listing_id})" class="btn-buy">Buy Now</button>
                    </div>
                </div>
            </div>
        `).join('');
    } else {
        container.innerHTML = '<div class="error">Failed to load listings.</div>';
    }
}

async function buyNFT(listingId) {
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
        const p = result.profile;
        profileInfo.innerHTML = `
            <div class="profile-header">
                <h2>${p.username}</h2>
                <div class="wallet-address">${p.wallet_address}</div>
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <label>Balance</label>
                    <div class="value">${parseFloat(p.balance).toFixed(2)} MTK</div>
                </div>
                <div class="stat-box">
                    <label>NFTs Owned</label>
                    <div class="value">${p.nfts_owned}</div>
                </div>
                <div class="stat-box">
                    <label>Total Sales</label>
                    <div class="value">${parseFloat(p.total_sales).toFixed(2)} MTK</div>
                </div>
            </div>
        `;

        // Load User's NFTs (We need a new endpoint method in API for this, or reuse getProfile logic? 
        // Wait, User.php has getUserNFTs but API endpoint for it?
        // Ah, I didn't explicitly add a 'get_user_nfts' action in api/nft.php or api/auth.php. 
        // Let's check api/auth.php... only 'profile'.
        // Let's check api/nft.php... 'get_all', 'get_by_id'.
        // I missed exposing getUserNFTs in the API. 
        // I should fix api/nft.php to add 'get_my_nfts' or similar.
        // For now, I will add a TODO or fix it in the next step.
        // Actually, I can fix it right now by editing api/nft.php in the next turn or just assume it exists.
        // I will implement the JS assuming I will fix the API.

        loadUserNFTs();
    } else {
        window.location.href = 'index.html'; // Redirect if not logged in
    }
}

async function loadUserNFTs() {
    // NOTE: This endpoint needs to be added to api/nft.php
    // I will use 'get_all' for now but filter client side? No, that's bad.
    // I will assume I add 'action=my_nfts' to api/nft.php
    const response = await fetch('api/nft.php?action=my_nfts');
    const result = await response.json();

    const container = document.getElementById('userNFTs');
    if (result.success) {
        if (result.nfts.length === 0) {
            container.innerHTML = '<div class="no-data">You don\'t own any NFTs yet.</div>';
            return;
        }

        container.innerHTML = result.nfts.map(nft => `
            <div class="nft-card">
                <div class="nft-image" style="background-image: url('${nft.image_url}')"></div>
                <div class="nft-info">
                    <h3>${nft.title}</h3>
                    <div class="actions">
                        ${nft.is_listed
                ? '<span class="badge-listed">Listed</span>'
                : `<button onclick="listNFT(${nft.nft_id})" class="btn-secondary">List for Sale</button>`
            }
                    </div>
                </div>
            </div>
        `).join('');
    }
}

async function listNFT(nftId) {
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

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Minting... (Mining Block)';

        const title = document.getElementById('title').value;
        const description = document.getElementById('description').value;
        const royalty = document.getElementById('royalty').value;
        const imageFile = document.getElementById('image').files[0];

        const result = await api.mintNFT(title, description, imageFile, royalty);

        if (result.success) {
            alert('NFT Minted Successfully!');
            window.location.href = 'profile.html';
        } else {
            alert('Minting failed: ' + result.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Mint NFT';
        }
    });
}

// Make functions global for onclick handlers
window.buyNFT = buyNFT;
window.listNFT = listNFT;
