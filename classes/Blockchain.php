<?php
require_once __DIR__ . '/../config.php';

class Blockchain {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Calculate SHA-256 hash for a block
     * 
     * @param array $data Transaction data
     * @param int $nonce Proof-of-work nonce
     * @param string $previousHash Hash of the previous block
     * @return string Calculated hash
     */
    public function calculateHash($data, $nonce, $previousHash) {
        // Ensure consistent JSON encoding
        $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $input = $jsonData . $nonce . $previousHash;
        return hash('sha256', $input);
    }

    /**
     * Mine a new block (Proof of Work)
     * 
     * @param array $data Transaction details
     * @param string $previousHash Last block hash
     * @return array ['hash' => string, 'nonce' => int]
     */
    public function mineBlock($data, $previousHash) {
        $nonce = 0;
        $target = str_repeat("0", MINING_DIFFICULTY);
        
        // Safety break to prevent infinite loops in dev
        $maxIterations = 10000000; 

        while ($nonce < $maxIterations) {
            $hash = $this->calculateHash($data, $nonce, $previousHash);
            
            if (substr($hash, 0, MINING_DIFFICULTY) === $target) {
                return [
                    'hash' => $hash,
                    'nonce' => $nonce
                ];
            }
            
            $nonce++;
        }

        throw new Exception("Mining failed: Could not find hash within iteration limit.");
    }

    /**
     * Add a transaction to the blockchain
     * 
     * @param int $nftId
     * @param int|null $fromUserId
     * @param int $toUserId
     * @param string $type 'mint', 'transfer', 'sale'
     * @param float $amount
     * @param float $royaltyAmount
     * @return string Transaction hash
     */
    public function addTransaction($nftId, $fromUserId, $toUserId, $type, $amount, $royaltyAmount) {
        // Get last block hash
        $stmt = $this->db->query("SELECT block_hash FROM transactions ORDER BY transaction_id DESC LIMIT 1");
        $lastBlock = $stmt->fetch();
        $previousHash = $lastBlock ? $lastBlock['block_hash'] : '0'; // Genesis block prev hash is '0'

        $timestamp = time();
        $dbTimestamp = date('Y-m-d H:i:s', $timestamp);

        $data = [
            'nft_id' => (int)$nftId,
            'from' => $fromUserId === null ? null : (int)$fromUserId,
            'to' => (int)$toUserId,
            'type' => $type,
            'amount' => (float)$amount,
            'royalty' => (float)$royaltyAmount,
            'timestamp' => $timestamp
        ];

        // Mine the block
        $mined = $this->mineBlock($data, $previousHash);

        // Insert into database
        $sql = "INSERT INTO transactions (block_hash, previous_hash, nft_id, from_user_id, to_user_id, transaction_type, amount, royalty_amount, nonce, timestamp) 
                VALUES (:hash, :prev, :nft, :from, :to, :type, :amt, :royalty, :nonce, :ts)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':hash' => $mined['hash'],
            ':prev' => $previousHash,
            ':nft' => $nftId,
            ':from' => $fromUserId,
            ':to' => $toUserId,
            ':type' => $type,
            ':amt' => $amount,
            ':royalty' => $royaltyAmount,
            ':nonce' => $mined['nonce'],
            ':ts' => $dbTimestamp
        ]);

        return $mined['hash'];
    }

    /**
     * Validate the entire blockchain integrity
     * 
     * @return array ['valid' => bool, 'total_blocks' => int, 'message' => string]
     */
    public function validateChain() {
        $stmt = $this->db->query("SELECT * FROM transactions ORDER BY transaction_id ASC");
        $chain = $stmt->fetchAll();
        
        $count = count($chain);
        if ($count === 0) {
            return ['valid' => true, 'total_blocks' => 0, 'message' => 'Chain is empty'];
        }

        // Verify Genesis Block (optional specific check, but loop covers logic usually)
        // We start loop from index 0, but for index 0 previous hash should be '0'
        
        for ($i = 0; $i < $count; $i++) {
            $currentBlock = $chain[$i];
            
            // Reconstruct data array to match mining input
            $data = [
                'nft_id' => (int)$currentBlock['nft_id'],
                'from' => $currentBlock['from_user_id'] === null ? null : (int)$currentBlock['from_user_id'],
                'to' => (int)$currentBlock['to_user_id'],
                'type' => $currentBlock['transaction_type'],
                'amount' => (float)$currentBlock['amount'],
                'royalty' => (float)$currentBlock['royalty_amount'],
                'timestamp' => strtotime($currentBlock['timestamp']) // MySQL timestamp to unix
            ];

            // 1. Verify Hash Calculation
            $recalculatedHash = $this->calculateHash($data, $currentBlock['nonce'], $currentBlock['previous_hash']);
            
            if ($recalculatedHash !== $currentBlock['block_hash']) {
                return [
                    'valid' => false,
                    'total_blocks' => $count,
                    'message' => "Invalid hash at block ID: " . $currentBlock['transaction_id']
                ];
            }

            // 2. Verify Difficulty
            $target = str_repeat("0", MINING_DIFFICULTY);
            if (substr($currentBlock['block_hash'], 0, MINING_DIFFICULTY) !== $target) {
                return [
                    'valid' => false,
                    'total_blocks' => $count,
                    'message' => "Insufficient difficulty at block ID: " . $currentBlock['transaction_id']
                ];
            }

            // 3. Verify Chain Link (Previous Hash)
            if ($i > 0) {
                $previousBlock = $chain[$i - 1];
                if ($currentBlock['previous_hash'] !== $previousBlock['block_hash']) {
                    return [
                        'valid' => false,
                        'total_blocks' => $count,
                        'message' => "Broken chain link at block ID: " . $currentBlock['transaction_id']
                    ];
                }
            } else {
                // Genesis block check
                if ($currentBlock['previous_hash'] !== '0') {
                    return [
                        'valid' => false,
                        'total_blocks' => $count,
                        'message' => "Invalid genesis block previous hash"
                    ];
                }
            }
        }

        return [
            'valid' => true,
            'total_blocks' => $count,
            'message' => 'Blockchain integrity verified'
        ];
    }
    
    /**
     * Get the last block hash
     */
    public function getLastHash() {
        $stmt = $this->db->query("SELECT block_hash, transaction_id FROM transactions ORDER BY transaction_id DESC LIMIT 1");
        $result = $stmt->fetch();
        return $result ? ['hash' => $result['block_hash'], 'block_number' => $result['transaction_id']] : null;
    }
}
?>
