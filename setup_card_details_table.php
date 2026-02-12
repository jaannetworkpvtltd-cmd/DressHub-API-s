<?php
// phpMyAdmin SQL Dump
// version 5.2.1
// https://www.phpmyadmin.net/
//
// Host: localhost
// Generation Time: Feb 11, 2026 at 11:31 AM
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

    // Create card_details table
    $sql = "CREATE TABLE IF NOT EXISTS `card_details` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) NOT NULL,
        `card_holder_name` varchar(150) DEFAULT NULL,
        `last4_digits` varchar(4) DEFAULT NULL,
        `brand` varchar(50) DEFAULT NULL,
        `expiry_month` int(11) DEFAULT NULL,
        `expiry_year` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `card_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'card_details table created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>
