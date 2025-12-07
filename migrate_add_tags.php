<?php
/**
 * Migration script to add tags column to existing nfts table
 */
require_once __DIR__ . '/config.php';

echo "Adding tags column to nfts table...\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if tags column exists
    $result = $db->query("PRAGMA table_info(nfts)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $hasTagsColumn = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'tags') {
            $hasTagsColumn = true;
            break;
        }
    }
    
    if (!$hasTagsColumn) {
        $db->exec("ALTER TABLE nfts ADD COLUMN tags TEXT DEFAULT ''");
        echo "Added 'tags' column to nfts table.\n";
        
        // Create index for tag searching
        $db->exec("CREATE INDEX IF NOT EXISTS idx_nft_tags ON nfts(tags)");
        echo "Created tags index.\n";
    } else {
        echo "Tags column already exists.\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>

