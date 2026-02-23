<?php
// phpMyAdmin SQL Dump - Order Items Table
require_once 'connect.php';

try {
    // Create order_items table
    $sql = "CREATE TABLE IF NOT EXISTS `order_items` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `order_id` bigint(20) NOT NULL,
        `product_variant_id` bigint(20) NOT NULL,
        `quantity` int(11) NOT NULL,
        `unit_price` decimal(10,2) NOT NULL,
        `total_price` decimal(12,2) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `order_id` (`order_id`),
        KEY `product_variant_id` (`product_variant_id`),
        CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
        CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'order_items table created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>
