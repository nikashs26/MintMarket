<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // SQLite compatible syntax
    $sql = "CREATE TABLE IF NOT EXISTS cart_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        listing_id INTEGER NOT NULL,
        quantity INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    
    // Create unique index separately for better compatibility
    $sqlIndex = "CREATE UNIQUE INDEX IF NOT EXISTS unique_cart_item ON cart_items (user_id, listing_id)";
    $db->exec($sqlIndex);
    
    echo "Table 'cart_items' created successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
