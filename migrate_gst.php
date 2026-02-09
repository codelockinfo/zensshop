<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

function columnExists($db, $table, $column) {
    try {
        $result = $db->fetchAll("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return count($result) > 0;
    } catch (Exception $e) {
        return false;
    }
}

function addColumn($db, $table, $column, $definition) {
    if (!columnExists($db, $table, $column)) {
        echo "Adding column $column to $table...\n";
        $db->execute("ALTER TABLE `$table` ADD COLUMN $column $definition");
    } else {
        echo "Column $column already exists in $table.\n";
    }
}

echo "Starting migration for GST implementation...\n";

try {
    // 1. Products Table
    addColumn($db, 'products', 'is_taxable', "TINYINT(1) DEFAULT 0 AFTER price");
    addColumn($db, 'products', 'hsn_code', "VARCHAR(20) AFTER is_taxable");
    addColumn($db, 'products', 'gst_percent', "DECIMAL(5,2) DEFAULT 0.00 AFTER hsn_code");

    // 2. Orders Table
    addColumn($db, 'orders', 'tax_amount', "DECIMAL(10,2) DEFAULT 0.00 AFTER discount_amount");
    addColumn($db, 'orders', 'cgst_total', "DECIMAL(10,2) DEFAULT 0.00 AFTER tax_amount");
    addColumn($db, 'orders', 'sgst_total', "DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_total");
    addColumn($db, 'orders', 'igst_total', "DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_total");
    addColumn($db, 'orders', 'grand_total', "DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
    addColumn($db, 'orders', 'customer_state', "VARCHAR(50) AFTER billing_address");

    // 3. Order Items Table
    addColumn($db, 'order_items', 'hsn_code', "VARCHAR(20) AFTER variant_attributes");
    addColumn($db, 'order_items', 'gst_percent', "DECIMAL(5,2) DEFAULT 0.00 AFTER hsn_code");
    addColumn($db, 'order_items', 'cgst_amount', "DECIMAL(10,2) DEFAULT 0.00 AFTER gst_percent");
    addColumn($db, 'order_items', 'sgst_amount', "DECIMAL(10,2) DEFAULT 0.00 AFTER cgst_amount");
    addColumn($db, 'order_items', 'igst_amount', "DECIMAL(10,2) DEFAULT 0.00 AFTER sgst_amount");
    addColumn($db, 'order_items', 'line_total', "DECIMAL(10,2) DEFAULT 0.00 AFTER igst_amount");

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
