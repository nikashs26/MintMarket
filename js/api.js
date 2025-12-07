/**
 * MintMarket API Client
 * Handles all communication with the backend API
 */
class MintMarketAPI {
    constructor(baseUrl = 'api') {
        this.baseUrl = baseUrl;
    }

    /**
     * Helper method for fetch requests
     */
    async request(endpoint, method = 'GET', data = null, isFileUpload = false) {
        const url = `${this.baseUrl}/${endpoint}`;
        const options = {
            method: method,
            headers: {}
        };

        if (data) {
            if (isFileUpload) {
                // For file uploads, data should be FormData object
                // Content-Type header is automatically set by browser
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('API Request Failed:', error);
            return { success: false, message: 'Network or server error' };
        }
    }

    // --- Authentication ---

    async register(username, email, password) {
        return this.request('auth.php?action=register', 'POST', { username, email, password });
    }

    async login(username, password) {
        return this.request('auth.php?action=login', 'POST', { username, password });
    }

    async logout() {
        return this.request('auth.php?action=logout');
    }

    async getProfile() {
        return this.request('auth.php?action=profile');
    }

    // --- NFT Operations ---

    async mintNFT(title, description, imageFile, royaltyPercentage, tags = []) {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', description);
        formData.append('image', imageFile);
        formData.append('royalty_percentage', royaltyPercentage);
        formData.append('tags', tags.join(','));

        return this.request('nft.php?action=mint', 'POST', formData, true);
    }

    async getAllNFTs(limit = 50, offset = 0) {
        return this.request(`nft.php?action=get_all&limit=${limit}&offset=${offset}`);
    }

    async getNFTById(nftId) {
        return this.request(`nft.php?action=get_by_id&nft_id=${nftId}`);
    }

    // --- Marketplace Operations ---

    async createListing(nftId, price) {
        return this.request('marketplace.php?action=create_listing', 'POST', { nft_id: nftId, price: price });
    }

    async buyNFT(listingId) {
        return this.request('marketplace.php?action=buy', 'POST', { listing_id: listingId });
    }

    async getActiveListings(limit = 50, offset = 0, tags = '', search = '') {
        let url = `marketplace.php?action=get_listings&limit=${limit}&offset=${offset}`;
        if (tags) url += `&tags=${encodeURIComponent(tags)}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        return this.request(url);
    }

    async cancelListing(listingId) {
        return this.request('marketplace.php?action=cancel_listing', 'POST', { listing_id: listingId });
    }

    // --- Blockchain Operations ---

    async validateChain() {
        return this.request('blockchain.php?action=validate');
    }

    async getLastHash() {
        return this.request('blockchain.php?action=last_hash');
    }
}
