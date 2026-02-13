<?php

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

    $sql = "CREATE TABLE IF NOT EXISTS `user_roles` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) NOT NULL,
        `role` enum('admin','customer','staff') NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id_role` (`user_id`, `role`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $conn->exec($sql);

    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'status' => true,
        'message' => 'User roles table created or already exists'
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
