<?php
require_once 'connect.php';

try {
    // Check if created_at exists in product_variants
    $check = $conn->query("DESC product_variants");
    $columns = $check->fetchAll();
    $has_created_at = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] == 'created_at') {
            $has_created_at = true;
            break;
        }
    }
    
    if (!$has_created_at) {
        $conn->exec("ALTER TABLE product_variants ADD COLUMN created_at timestamp DEFAULT CURRENT_TIMESTAMP");
        echo "✓ Added created_at to product_variants\n";
    } else {
        echo "✓ product_variants already has created_at\n";
    }
    
    // Check if created_at exists in product_bulk_prices
    $check = $conn->query("DESC product_bulk_prices");
    $columns = $check->fetchAll();
    $has_created_at = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] == 'created_at') {
            $has_created_at = true;
            break;
        }
    }
    
    if (!$has_created_at) {
        $conn->exec("ALTER TABLE product_bulk_prices ADD COLUMN created_at timestamp DEFAULT CURRENT_TIMESTAMP");
        echo "✓ Added created_at to product_bulk_prices\n";
    } else {
        echo "✓ product_bulk_prices already has created_at\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
