<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    // Check if column exists
    $columns = $db->fetchAll("DESCRIBE pages");
    $hasPageId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'page_id') {
            $hasPageId = true;
            break;
        }
    }

    if (!$hasPageId) {
        echo "Adding page_id column to pages table...\n";
        $db->execute("ALTER TABLE pages ADD COLUMN page_id BIGINT(20) NULL AFTER id");
        $db->execute("CREATE INDEX idx_page_id ON pages(page_id)");
        echo "Column added successfully.\n";
        
        // Backfill existing pages
        echo "Backfilling existing pages with random 10-digit IDs...\n";
        $existingPages = $db->fetchAll("SELECT id FROM pages WHERE page_id IS NULL");
        foreach ($existingPages as $page) {
            $randomId = rand(1000000000, 9999999999);
            // Ensure uniqueness (simple check)
            while ($db->fetchOne("SELECT id FROM pages WHERE page_id = ?", [$randomId])) {
                $randomId = rand(1000000000, 9999999999);
            }
            $db->execute("UPDATE pages SET page_id = ? WHERE id = ?", [$randomId, $page['id']]);
        }
        echo "Backfill complete.\n";
    } else {
        echo "page_id column already exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
