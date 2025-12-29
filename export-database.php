<?php
/**
 * Database Export Script
 * Exports the database to SQL file
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get all tables
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $output = "-- Database Export for oecom_db\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = \"+00:00\";\n\n";
    
    foreach ($tables as $table) {
        $output .= "--\n";
        $output .= "-- Table structure for table `$table`\n";
        $output .= "--\n\n";
        
        // Get CREATE TABLE statement
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $createTable['Create Table'] . ";\n\n";
        
        // Get table data
        $output .= "--\n";
        $output .= "-- Dumping data for table `$table`\n";
        $output .= "--\n\n";
        
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote($value);
                    }
                }
                $output .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    $output .= "COMMIT;\n";
    
    // Write to file
    $filename = __DIR__ . '/database/oecom_db.sql';
    file_put_contents($filename, $output);
    
    echo "Database exported successfully to: $filename\n";
    echo "Total tables exported: " . count($tables) . "\n";
    
} catch (Exception $e) {
    echo "Error exporting database: " . $e->getMessage() . "\n";
    exit(1);
}

