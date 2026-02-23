<?php
// phpMyAdmin SQL Dump - Orders Table
require_once 'connect.php';

try {
    // Create orders table
    $sql = "CREATE TABLE IF NOT EXISTS `orders` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) DEFAULT NULL,
        `status` enum('pending','paid','shipped','completed','cancelled') DEFAULT 'pending',
        `total_amount` decimal(12,2) NOT NULL,
        `note` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'orders table created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>
