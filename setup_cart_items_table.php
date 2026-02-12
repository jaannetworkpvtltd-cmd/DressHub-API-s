<?php
// phpMyAdmin SQL Dump - Cart Items Table
// version 5.2.1
// https://www.phpmyadmin.net/
//
// Host: localhost
// Generation Time: Feb 12, 2026 at 06:22 AM
// Server version: 10.4.28-MariaDB
// PHP Version: 8.2.4

$host = 'localhost';
$db_name = 'ecommerce_app';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        'mysql:host=' . $host . ';dbname=' . $db_name,
        $username,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create cart_items table
    $sql = "CREATE TABLE IF NOT EXISTS `cart_items` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `cart_id` bigint(20) NOT NULL,
        `product_variant_id` bigint(20) NOT NULL,
        `quantity` int(11) NOT NULL,
        `applied_price` decimal(10,2) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `cart_id` (`cart_id`),
        KEY `product_variant_id` (`product_variant_id`),
        CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
        CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'cart_items table created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>
