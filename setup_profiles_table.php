<?php

require_once 'config/Database.php';

try {
    $database = new Database();
    $conn = $database->connect();

    // Create profiles table
    $sql = "CREATE TABLE IF NOT EXISTS `profiles` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `user_id` bigint(20) DEFAULT NULL,
      `full_name` varchar(150) DEFAULT NULL,
      `phone` varchar(20) DEFAULT NULL,
      `avatar_url` text DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_id` (`user_id`),
      KEY `user_id_2` (`user_id`),
      CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);
    echo json_encode([
        'status' => 'success',
        'message' => 'Profiles table created successfully'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
