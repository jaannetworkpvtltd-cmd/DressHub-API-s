<?php
// phpMyAdmin SQL Dump
// version 5.2.1
// https://www.phpmyadmin.net/
//
// Host: localhost
// Generation Time: Feb 11, 2026 at 11:50 AM
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

    // Create addresses table
    $sql = "CREATE TABLE IF NOT EXISTS `addresses` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) NOT NULL,
        `address_line1` text NOT NULL,
        `address_line2` text DEFAULT NULL,
        `city` varchar(100) DEFAULT NULL,
        `postal_code` varchar(20) DEFAULT NULL,
        `country` varchar(100) DEFAULT NULL,
        `is_default` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'addresses table created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>
