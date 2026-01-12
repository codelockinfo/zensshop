<?php
require_once __DIR__ . '/classes/Database.php';
$db = Database::getInstance();

try {
    // Check if cart table exists
    $tables = $db->fetchAll("SHOW TABLES LIKE 'cart'");
    if (empty($tables)) {
        echo "Cart table does not exist.\n";
        exit;
    }

    // Get table create statement to see existing constraints
    $res = $db->fetchOne("SHOW CREATE TABLE cart");
    $createStatement = $res['Create Table'];
    echo "Current Cart Table Schema:\n" . $createStatement . "\n\n";

    // Extract constraint name if it exists pointing to users
    if (preg_match('/CONSTRAINT `([^`]+)` FOREIGN KEY \(`user_id`\) REFERENCES `users`/', $createStatement, $matches)) {
        $constraintName = $matches[1];
        echo "Found constraint referring to users: $constraintName\n";
        
        // Drop the old constraint
        echo "Dropping constraint $constraintName...\n";
        $db->execute("ALTER TABLE cart DROP FOREIGN KEY $constraintName");
        echo "Constraint dropped.\n";
    }

    // Check if customers table exists
    $tables = $db->fetchAll("SHOW TABLES LIKE 'customers'");
    if (!empty($tables)) {
        // Add new constraint pointing to customers
        echo "Adding new constraint referring to customers...\n";
        // We use user_id in the table, let's keep it but point to customers(id)
        $db->execute("ALTER TABLE cart ADD CONSTRAINT fk_cart_customer FOREIGN KEY (user_id) REFERENCES customers(id) ON DELETE CASCADE");
        echo "New constraint added successfully.\n";
    } else {
        echo "Customers table not found. Skipping FK addition.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
