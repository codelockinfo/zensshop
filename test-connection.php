<?php
/**
 * Test Database Connection
 */

require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    echo "✓ Database connection successful!\n\n";
    
    // Test queries
    $users = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "Users in database: " . $users['count'] . "\n";
    
    $categories = $db->fetchOne("SELECT COUNT(*) as count FROM categories");
    echo "Categories in database: " . $categories['count'] . "\n";
    
    $tables = $db->fetchAll("SHOW TABLES");
    echo "\nTables created: " . count($tables) . "\n";
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "  - $tableName\n";
    }
    
    echo "\n✓ All tests passed! Database is ready to use.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}


