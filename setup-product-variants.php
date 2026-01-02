<?php
/**
 * Setup script to create product variants tables
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "Creating product variants tables...\n\n";
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/database/add_product_variants_tables.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments
    
    // Split by semicolon and clean up
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            $stmt = trim($stmt);
            return !empty($stmt) && strlen($stmt) > 10; // Ignore very short strings
        }
    );
    
    $created = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $created++;
                echo "✓ Executed SQL statement\n";
            } catch (PDOException $e) {
                // Ignore "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Duplicate table') !== false) {
                    echo "ℹ Table already exists (skipping)\n";
                } else {
                    echo "✗ Error executing statement: " . $e->getMessage() . "\n";
                    echo "  Statement: " . substr($statement, 0, 100) . "...\n";
                    throw $e;
                }
            }
        }
    }
    
    if ($created > 0) {
        echo "\n✓ Product variants tables created successfully!\n";
    } else {
        echo "\nℹ All tables already exist or no statements were executed.\n";
    }
    
    echo "\nTables:\n";
    echo "  - product_variant_options (stores variant option types like Size, Color)\n";
    echo "  - product_variants (stores individual variant combinations)\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

