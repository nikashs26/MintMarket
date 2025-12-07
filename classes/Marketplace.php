<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/NFT.php';

class Marketplace {
    private $db;
    private $nftModel;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->nftModel = new NFT();
    }

    /**
     * Create a new listing
     * 
     * @param int $nftId
     * @param int $sellerId
     * @param float $price
     * @return array
     */
    public function createListing($nftId, $sellerId, $price) {
        // Validation
        if ($price < 0.001) {
            return ['success' => false, 'message' => 'Price must be at least 0.001 MTK'];
        }

        // Verify ownership
        $nftResult = $this->nftModel->getNFTById($nftId);
        if (!$nftResult['success']) {
            return ['success' => false, 'message' => 'NFT not found'];
        }
        $nft = $nftResult['nft'];

        if ($nft['current_owner_id'] != $sellerId) {
            return ['success' => false, 'message' => 'You do not own this NFT'];
        }
        if ($nft['is_listed']) {
            return ['success' => false, 'message' => 'NFT is already listed'];
        }

        try {
            $sql = "INSERT INTO listings (nft_id, seller_id, price, status) VALUES (?, ?, ?, 'active')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nftId, $sellerId, $price]);

            return [
                'success' => true,
                'listing_id' => $this->db->lastInsertId(),
                'message' => 'NFT listed successfully'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Listing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Buy an NFT
     * 
     * @param int $listingId
     * @param int $buyerId
     * @return array
     */
    public function buyNFT($listingId, $buyerId) {
        try {
            $this->db->beginTransaction();

            // Lock listing row for update (MySQL only)
            $sql = "SELECT * FROM listings WHERE listing_id = ? AND status = 'active'";
            if (defined('DB_CONNECTION') && DB_CONNECTION === 'mysql') {
                $sql .= " FOR UPDATE";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch();

            if (!$listing) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Listing no longer available'];
            }

            if ($listing['seller_id'] == $buyerId) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Cannot buy your own listing'];
            }

            // Execute Transfer (Handles financials and ownership)
            // Note: NFT::transfer handles the transaction logic for money and ownership
            // But we are already in a transaction here. Nested transactions in PDO/MySQL behave as savepoints or are ignored depending on driver.
            // Ideally, NFT::transfer should support being called within an existing transaction.
            // The current NFT::transfer starts its own transaction. We should refactor NFT::transfer or just call the logic.
            // Given the constraints, let's assume NFT::transfer's transaction handling is robust enough or we modify it.
            // Actually, calling beginTransaction inside another throws an error in PDO usually.
            // FIX: We should commit the lock here, OR refactor NFT::transfer to accept an optional existing transaction context.
            // For this MVP, let's just rely on the lock we have. We can release the lock by committing/rolling back.
            // But we need the transfer to happen atomically with the listing update.
            
            // Let's do the transfer logic manually here reusing NFT logic components, OR modify NFT::transfer.
            // Modifying NFT::transfer to be transaction-aware is best practice.
            // However, since I cannot easily modify NFT.php right now without another tool call, I will implement the transfer logic here
            // by calling NFT::transfer but I need to be careful about the nested transaction.
            // Actually, I can just commit the lock now? No, that releases the lock before transfer.
            
            // STRATEGY: We will use the NFT::transfer method, but we need to handle the transaction issue.
            // Since I am writing Marketplace.php now, I can't change NFT.php in this same step easily.
            // I will assume for now that I can call it. If it fails, I'll fix it.
            // Wait, standard PDO throws "There is already an active transaction".
            // I will implement the transfer logic directly here to be safe and correct.
            
            // 1. Check Buyer Balance
            $stmt = $this->db->prepare("SELECT balance FROM users WHERE user_id = ?");
            $stmt->execute([$buyerId]);
            $buyerBalance = $stmt->fetchColumn();

            if ($buyerBalance < $listing['price']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Insufficient funds'];
            }

            // 2. Get NFT Details for Royalty
            $stmt = $this->db->prepare("SELECT creator_id, royalty_percentage FROM nfts WHERE nft_id = ?");
            $stmt->execute([$listing['nft_id']]);
            $nft = $stmt->fetch();

            // 3. Calculate Splits
            $price = $listing['price'];
            $royaltyPaid = 0;
            $sellerReceived = $price;

            if ($nft['creator_id'] != $listing['seller_id']) {
                $royaltyPaid = ($price * $nft['royalty_percentage']) / 100;
                $sellerReceived = $price - $royaltyPaid;
            }

            // 4. Update Balances
            // Deduct Buyer
            $stmt = $this->db->prepare("UPDATE users SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$price, $buyerId]);

            // Pay Seller
            $stmt = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$sellerReceived, $listing['seller_id']]);

            // Pay Royalty
            if ($royaltyPaid > 0) {
                $stmt = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
                $stmt->execute([$royaltyPaid, $nft['creator_id']]);
            }

            // 5. Update NFT Ownership
            $stmt = $this->db->prepare("UPDATE nfts SET current_owner_id = ?, last_transfer_at = CURRENT_TIMESTAMP WHERE nft_id = ?");
            $stmt->execute([$buyerId, $listing['nft_id']]);

            // 6. Update Listing Status
            $stmt = $this->db->prepare("UPDATE listings SET status = 'sold' WHERE listing_id = ?");
            $stmt->execute([$listingId]);

            // 7. Add Blockchain Transaction
            // We need to instantiate Blockchain here since we are bypassing NFT::transfer
            $blockchain = new Blockchain();
            $txHash = $blockchain->addTransaction($listing['nft_id'], $listing['seller_id'], $buyerId, 'sale', $price, $royaltyPaid);

            $this->db->commit();

            return [
                'success' => true,
                'transaction_hash' => $txHash,
                'nft_id' => $listing['nft_id'],
                'amount_paid' => number_format($price, 8),
                'royalty_paid' => number_format($royaltyPaid, 8),
                'new_balance' => number_format($buyerBalance - $price, 8)
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'Purchase failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get active listings with optional tag and search filtering
     * 
     * @param int $limit
     * @param int $offset
     * @param string $tags Comma-separated tags to filter by
     * @param string $search Search term for title
     * @return array
     */
    public function getActiveListings($limit = 50, $offset = 0, $tags = '', $search = '') {
        $limit = min($limit, 100);

        $sql = "SELECT l.*, n.title, n.image_url, n.royalty_percentage, n.tags,
                u.username as seller_username, c.username as creator_username
                FROM listings l
                JOIN nfts n ON l.nft_id = n.nft_id
                JOIN users u ON l.seller_id = u.user_id
                JOIN users c ON n.creator_id = c.user_id
                WHERE l.status = 'active'";
        
        $params = [];
        
        // Add tag filtering
        if (!empty($tags)) {
            $tagArray = explode(',', $tags);
            $tagConditions = [];
            foreach ($tagArray as $index => $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $paramName = ":tag{$index}";
                    $tagConditions[] = "n.tags LIKE {$paramName}";
                    $params[$paramName] = '%' . $tag . '%';
                }
            }
            if (!empty($tagConditions)) {
                $sql .= " AND (" . implode(' OR ', $tagConditions) . ")";
            }
        }
        
        // Add search filtering
        if (!empty($search)) {
            $sql .= " AND n.title LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $listings = $stmt->fetchAll();
        
        // Parse tags for each listing
        foreach ($listings as &$listing) {
            $listing['tags_array'] = !empty($listing['tags']) ? explode(',', $listing['tags']) : [];
        }

        // Count query with same filters
        $countSql = "SELECT COUNT(*) FROM listings l
                     JOIN nfts n ON l.nft_id = n.nft_id
                     WHERE l.status = 'active'";
        
        if (!empty($tags)) {
            $tagArray = explode(',', $tags);
            $tagConditions = [];
            foreach ($tagArray as $index => $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tagConditions[] = "n.tags LIKE :ctag{$index}";
                }
            }
            if (!empty($tagConditions)) {
                $countSql .= " AND (" . implode(' OR ', $tagConditions) . ")";
            }
        }
        
        if (!empty($search)) {
            $countSql .= " AND n.title LIKE :csearch";
        }
        
        $countStmt = $this->db->prepare($countSql);
        
        if (!empty($tags)) {
            $tagArray = explode(',', $tags);
            foreach ($tagArray as $index => $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $countStmt->bindValue(":ctag{$index}", '%' . $tag . '%', PDO::PARAM_STR);
                }
            }
        }
        
        if (!empty($search)) {
            $countStmt->bindValue(':csearch', '%' . $search . '%', PDO::PARAM_STR);
        }
        
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        return [
            'success' => true,
            'listings' => $listings,
            'total' => $total,
            'page' => floor($offset / $limit) + 1,
            'filters' => [
                'tags' => $tags,
                'search' => $search
            ]
        ];
    }

    /**
     * Cancel a listing
     * 
     * @param int $listingId
     * @param int $userId
     * @return array
     */
    public function cancelListing($listingId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM listings WHERE listing_id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();

        if (!$listing) {
            return ['success' => false, 'message' => 'Listing not found'];
        }
        if ($listing['seller_id'] != $userId) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        if ($listing['status'] != 'active') {
            return ['success' => false, 'message' => 'Listing is not active'];
        }

        $stmt = $this->db->prepare("UPDATE listings SET status = 'cancelled' WHERE listing_id = ?");
        $stmt->execute([$listingId]);

        return ['success' => true, 'message' => 'Listing cancelled successfully'];
    }
}
?>
