<?php
require_once __DIR__ . '/config.php';

echo "Initializing SQLite Database...\n";

if (file_exists(DB_FILE)) {
    unlink(DB_FILE);
    echo "Removed existing database file.\n";
}

try {
    $db = Database::getInstance()->getConnection();

    // Users Table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        wallet_address TEXT UNIQUE NOT NULL,
        balance REAL DEFAULT 100.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created 'users' table.\n";

    // NFTs Table
    $db->exec("CREATE TABLE IF NOT EXISTS nfts (
        nft_id INTEGER PRIMARY KEY AUTOINCREMENT,
        token_id TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        image_url TEXT NOT NULL,
        creator_id INTEGER NOT NULL,
        current_owner_id INTEGER NOT NULL,
        royalty_percentage REAL DEFAULT 10.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_transfer_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (creator_id) REFERENCES users(user_id),
        FOREIGN KEY (current_owner_id) REFERENCES users(user_id)
    )");
    echo "Created 'nfts' table.\n";

    // Transactions Table
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        transaction_id INTEGER PRIMARY KEY AUTOINCREMENT,
        block_hash TEXT NOT NULL,
        previous_hash TEXT,
        nft_id INTEGER,
        from_user_id INTEGER,
        to_user_id INTEGER,
        transaction_type TEXT NOT NULL CHECK(transaction_type IN ('mint', 'transfer', 'sale')),
        amount REAL,
        royalty_amount REAL DEFAULT 0,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        nonce INTEGER NOT NULL,
        FOREIGN KEY (nft_id) REFERENCES nfts(nft_id),
        FOREIGN KEY (from_user_id) REFERENCES users(user_id),
        FOREIGN KEY (to_user_id) REFERENCES users(user_id)
    )");
    echo "Created 'transactions' table.\n";

    // Listings Table
    $db->exec("CREATE TABLE IF NOT EXISTS listings (
        listing_id INTEGER PRIMARY KEY AUTOINCREMENT,
        nft_id INTEGER NOT NULL,
        seller_id INTEGER NOT NULL,
        price REAL NOT NULL,
        status TEXT DEFAULT 'active' CHECK(status IN ('active', 'sold', 'cancelled')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (nft_id) REFERENCES nfts(nft_id),
        FOREIGN KEY (seller_id) REFERENCES users(user_id)
    )");
    
    // Create partial unique index for active listings only
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_active_listings ON listings(nft_id) WHERE status = 'active'");
    
    echo "Created 'listings' table.\n";

    echo "Database initialized successfully at " . DB_FILE . "\n";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage() . "\n");
}
?>
