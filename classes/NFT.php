<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Blockchain.php';

class NFT {
    private $db;
    private $blockchain;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->blockchain = new Blockchain();
    }

    /**
     * Available NFT tags by category
     */
    public static $availableTags = [
        'Art Style' => ['Digital Art', '3D', 'Pixel Art', 'Abstract', 'Photography', 'Generative', 'AI Art'],
        'Theme' => ['Nature', 'Portrait', 'Fantasy', 'Sci-Fi', 'Anime', 'Gaming', 'Music', 'Sports'],
        'Rarity' => ['1/1', 'Limited Edition', 'Common', 'Rare', 'Legendary'],
        'Media' => ['Static', 'Animated', 'Interactive', 'Audio']
    ];

    /**
     * Get all available tags
     */
    public static function getAvailableTags() {
        return self::$availableTags;
    }

    /**
     * Validate tags against available options
     */
    private function validateTags($tags) {
        $allTags = [];
        foreach (self::$availableTags as $category => $categoryTags) {
            $allTags = array_merge($allTags, $categoryTags);
        }
        
        $validTags = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (in_array($tag, $allTags)) {
                $validTags[] = $tag;
            }
        }
        return $validTags;
    }

    /**
     * Mint a new NFT
     * 
     * @param int $creatorId
     * @param string $title
     * @param string $description
     * @param string $imageUrl
     * @param float $royaltyPercentage
     * @param array $tags
     * @return array
     */
    public function mint($creatorId, $title, $description, $imageUrl, $royaltyPercentage, $tags = []) {
        // Validation
        if (empty($title)) {
            return ['success' => false, 'message' => 'Title is required'];
        }
        if ($royaltyPercentage < 0 || $royaltyPercentage > 50) {
            return ['success' => false, 'message' => 'Royalty must be between 0% and 50%'];
        }

        // Validate and sanitize tags
        $validTags = $this->validateTags($tags);
        $tagsString = implode(',', $validTags);

        // Generate Token ID
        $tokenId = '0x' . bin2hex(random_bytes(32));

        try {
            $this->db->beginTransaction();

            // Insert NFT with tags
            $sql = "INSERT INTO nfts (token_id, title, description, image_url, tags, creator_id, current_owner_id, royalty_percentage) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tokenId, $title, $description, $imageUrl, $tagsString, $creatorId, $creatorId, $royaltyPercentage]);
            $nftId = $this->db->lastInsertId();

            // Add to Blockchain (Mint transaction)
            $this->blockchain->addTransaction($nftId, null, $creatorId, 'mint', 0, 0);

            $this->db->commit();

            return [
                'success' => true,
                'nft_id' => $nftId,
                'token_id' => $tokenId,
                'tags' => $validTags,
                'message' => 'NFT minted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Minting failed: ' . $e->getMessage()];
        }
    }

    /**
     * Transfer NFT (Sale or Gift)
     * 
     * @param int $nftId
     * @param int $fromUserId
     * @param int $toUserId
     * @param float $salePrice
     * @return array
     */
    public function transfer($nftId, $fromUserId, $toUserId, $salePrice = 0.0) {
        if ($fromUserId == $toUserId) {
            return ['success' => false, 'message' => 'Cannot transfer to self'];
        }

        try {
            $this->db->beginTransaction();

            // Verify ownership and get NFT details
            $stmt = $this->db->prepare("SELECT * FROM nfts WHERE nft_id = ?");
            $stmt->execute([$nftId]);
            $nft = $stmt->fetch();

            if (!$nft) {
                throw new Exception("NFT not found");
            }
            if ($nft['current_owner_id'] != $fromUserId) {
                throw new Exception("You do not own this NFT");
            }

            // Financials
            $royaltyPaid = 0;
            $sellerReceived = $salePrice;

            if ($salePrice > 0) {
                // Check buyer balance
                $stmt = $this->db->prepare("SELECT balance FROM users WHERE user_id = ?");
                $stmt->execute([$toUserId]);
                $buyerBalance = $stmt->fetchColumn();

                if ($buyerBalance < $salePrice) {
                    throw new Exception("Insufficient funds");
                }

                // Calculate Royalty
                // Royalty applies if seller is NOT the creator
                if ($nft['creator_id'] != $fromUserId) {
                    $royaltyPaid = ($salePrice * $nft['royalty_percentage']) / 100;
                    $sellerReceived = $salePrice - $royaltyPaid;
                }

                // Update Balances
                // 1. Deduct from Buyer
                $stmt = $this->db->prepare("UPDATE users SET balance = balance - ? WHERE user_id = ?");
                $stmt->execute([$salePrice, $toUserId]);

                // 2. Pay Seller
                $stmt = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
                $stmt->execute([$sellerReceived, $fromUserId]);

                // 3. Pay Royalty (if applicable)
                if ($royaltyPaid > 0) {
                    $stmt = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
                    $stmt->execute([$royaltyPaid, $nft['creator_id']]);
                }
            }

            // Update NFT Ownership
            $stmt = $this->db->prepare("UPDATE nfts SET current_owner_id = ?, last_transfer_at = CURRENT_TIMESTAMP WHERE nft_id = ?");
            $stmt->execute([$toUserId, $nftId]);

            // Cancel any active listings for this NFT
            $stmt = $this->db->prepare("UPDATE listings SET status = 'cancelled' WHERE nft_id = ? AND status = 'active'");
            $stmt->execute([$nftId]);

            // Add to Blockchain
            $type = ($salePrice > 0) ? 'sale' : 'transfer';
            $txHash = $this->blockchain->addTransaction($nftId, $fromUserId, $toUserId, $type, $salePrice, $royaltyPaid);

            $this->db->commit();

            return [
                'success' => true,
                'transaction_hash' => $txHash,
                'royalty_paid' => number_format($royaltyPaid, 8),
                'seller_received' => number_format($sellerReceived, 8)
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Transfer failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get NFT by ID
     * 
     * @param int $nftId
     * @return array
     */
    public function getNFTById($nftId) {
        $sql = "SELECT n.*, 
                c.username as creator_username, 
                o.username as owner_username,
                l.price as listing_price,
                (l.listing_id IS NOT NULL) as is_listed
                FROM nfts n
                JOIN users c ON n.creator_id = c.user_id
                JOIN users o ON n.current_owner_id = o.user_id
                LEFT JOIN listings l ON n.nft_id = l.nft_id AND l.status = 'active'
                WHERE n.nft_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$nftId]);
        $nft = $stmt->fetch();

        if ($nft) {
            $nft['is_listed'] = (bool)$nft['is_listed'];
            return ['success' => true, 'nft' => $nft];
        }
        return ['success' => false, 'message' => 'NFT not found'];
    }

    /**
     * Get all NFTs (Paginated)
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllNFTs($limit = 50, $offset = 0) {
        // Enforce max limit
        $limit = min($limit, 100);

        $sql = "SELECT n.*, c.username as creator_username, o.username as owner_username 
                FROM nfts n
                JOIN users c ON n.creator_id = c.user_id
                JOIN users o ON n.current_owner_id = o.user_id
                ORDER BY n.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $nfts = $stmt->fetchAll();

        // Get total count
        $countStmt = $this->db->query("SELECT COUNT(*) FROM nfts");
        $total = $countStmt->fetchColumn();

        return [
            'success' => true,
            'nfts' => $nfts,
            'total' => $total,
            'page' => floor($offset / $limit) + 1,
            'per_page' => $limit
        ];
    }
}
?>
