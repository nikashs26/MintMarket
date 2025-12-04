<?php
require_once __DIR__ . '/../config.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Register a new user
     * 
     * @param string $username
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'user_id' => int, 'wallet_address' => string, 'message' => string]
     */
    public function register($username, $email, $password) {
        // Validation
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Username must be between 3 and 50 characters'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        // Check duplicates
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        // Generate wallet address (0x + 40 hex chars)
        $walletAddress = '0x' . bin2hex(random_bytes(20));
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $sql = "INSERT INTO users (username, email, password_hash, wallet_address, balance) VALUES (?, ?, ?, ?, 100.00)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username, $email, $passwordHash, $walletAddress]);
            
            return [
                'success' => true,
                'user_id' => $this->db->lastInsertId(),
                'wallet_address' => $walletAddress,
                'message' => 'Account created successfully'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     * 
     * @param string $username Username or Email
     * @param string $password
     * @return array ['success' => bool, 'user' => array, 'message' => string]
     */
    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['wallet_address'] = $user['wallet_address'];

            // Return user data (excluding sensitive info)
            unset($user['password_hash']);
            return [
                'success' => true,
                'user' => $user
            ];
        }

        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    /**
     * Get user profile
     * 
     * @param int $userId
     * @return array ['success' => bool, 'profile' => array]
     */
    public function getProfile($userId) {
        $stmt = $this->db->prepare("SELECT user_id, username, email, wallet_address, balance, created_at as member_since FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Get stats
        // NFTs Owned
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM nfts WHERE current_owner_id = ?");
        $stmt->execute([$userId]);
        $profile['nfts_owned'] = $stmt->fetchColumn();

        // NFTs Created
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM nfts WHERE creator_id = ?");
        $stmt->execute([$userId]);
        $profile['nfts_created'] = $stmt->fetchColumn();

        // Total Sales (sum of amounts where user was seller)
        // Note: In transactions table, 'to_user_id' is the seller receiving money in a 'sale'
        // Wait, logic check: In a sale, money goes TO the seller. So to_user_id is seller.
        // Let's verify transaction structure: from_user (buyer) -> to_user (seller).
        // Yes.
        $stmt = $this->db->prepare("SELECT SUM(amount) FROM transactions WHERE to_user_id = ? AND transaction_type = 'sale'");
        $stmt->execute([$userId]);
        $profile['total_sales'] = $stmt->fetchColumn() ?: "0.00000000";

        return ['success' => true, 'profile' => $profile];
    }

    /**
     * Get NFTs owned by user
     * 
     * @param int $userId
     * @return array
     */
    public function getUserNFTs($userId) {
        $sql = "SELECT n.*, u.username as creator_username, 
                (SELECT COUNT(*) FROM listings l WHERE l.nft_id = n.nft_id AND l.status = 'active') as is_listed
                FROM nfts n 
                JOIN users u ON n.creator_id = u.user_id 
                WHERE n.current_owner_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $nfts = $stmt->fetchAll();

        // Convert is_listed to boolean
        foreach ($nfts as &$nft) {
            $nft['is_listed'] = (bool)$nft['is_listed'];
        }

        return ['success' => true, 'nfts' => $nfts];
    }
}
?>
