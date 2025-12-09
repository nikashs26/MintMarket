<?php
require_once 'config.php';
$db = Database::getInstance()->getConnection();

echo "Cleaning up duplicates and resetting quantities...<br>";

// 1. Reset all quantities to 1
$db->query("UPDATE cart_items SET quantity = 1");
echo "Quantities reset to 1.<br>";

// 2. Remove duplicates (keep lowest ID)
// SQLite/MySQL compatible-ish way: Select all, find duplicates, delete.
$stmt = $db->query("SELECT id, user_id, listing_id FROM cart_items ORDER BY id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$seen = [];
$deleted = 0;

foreach ($rows as $row) {
    $key = $row['user_id'] . '-' . $row['listing_id'];
    if (isset($seen[$key])) {
        // Delete this duplicate
        $del = $db->prepare("DELETE FROM cart_items WHERE id = ?");
        $del->execute([$row['id']]);
        $deleted++;
    } else {
        $seen[$key] = true;
    }
}

echo "Deleted $deleted duplicate items.<br>";
echo "Cart database is now clean.";
