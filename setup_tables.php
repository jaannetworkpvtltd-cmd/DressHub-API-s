<?php
require_once 'connect.php';

try {
    // Create product_variants table
    $sql1 = "CREATE TABLE IF NOT EXISTS `product_variants` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `product_id` bigint(20) NOT NULL,
      `size` varchar(50) DEFAULT NULL,
      `color` varchar(50) DEFAULT NULL,
      `stock` int(11) DEFAULT 0,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `product_id` (`product_id`),
      CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql1);
    echo "✓ product_variants table created\n";

    // Create product_bulk_prices table
    $sql2 = "CREATE TABLE IF NOT EXISTS `product_bulk_prices` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `product_id` bigint(20) NOT NULL,
      `min_quantity` int(11) NOT NULL,
      `bulk_price` decimal(10,2) NOT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `product_id` (`product_id`,`min_quantity`),
      KEY `product_id_2` (`product_id`),
      CONSTRAINT `product_bulk_prices_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql2);
    echo "✓ product_bulk_prices table created\n";
    
    echo "\n✓ All tables created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
