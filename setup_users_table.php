<?php
require_once 'connect.php';

try {
    // Check users table structure
    $check = $conn->query("DESC users");
    $columns = $check->fetchAll();
    
    $has_updated_at = false;
    $has_reset_token = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] == 'updated_at') $has_updated_at = true;
        if ($col['Field'] == 'reset_token') $has_reset_token = true;
    }
    
    // Add columns if missing
    if (!$has_updated_at) {
        $conn->exec("ALTER TABLE users ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✓ Added updated_at to users table\n";
    } else {
        echo "✓ updated_at already exists\n";
    }
    
    if (!$has_reset_token) {
        $conn->exec("ALTER TABLE users ADD COLUMN reset_token varchar(100) DEFAULT NULL");
        echo "✓ Added reset_token to users table\n";
        $conn->exec("ALTER TABLE users ADD COLUMN token_expiry datetime DEFAULT NULL");
        echo "✓ Added token_expiry to users table\n";
    } else {
        echo "✓ reset_token already exists\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
