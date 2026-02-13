<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'connect.php';
require 'jwt.php';
require 'models/UserRole.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'code' => 400,
        'message' => 'Username and password are required'
    ]);
    exit;
}

// Validate password length
if (strlen($data['password']) < 6) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'code' => 400,
        'message' => 'Password must be at least 6 characters'
    ]);
    exit;
}

$username = $data['username'];
$password = $data['password'];
$role = isset($data['role']) ? $data['role'] : 'customer'; // Default role is customer

// Validate role
$valid_roles = ['admin', 'customer', 'staff'];
if (!in_array($role, $valid_roles)) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'code' => 400,
        'message' => 'Invalid role. Must be: ' . implode(', ', $valid_roles)
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode([
            'status' => false,
            'code' => 409,
            'message' => 'Username already exists'
        ]);
        exit;
    }
    
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (:username, :password_hash, NOW())");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password_hash', $password_hash);
    $stmt->execute();
    
    $user_id = $conn->lastInsertId();
    
    // Assign role to new user
    $userRole = new UserRole($conn);
    $userRole->assignRole($user_id, $role);
    
    // Generate JWT token
    $jwt = new JWT();
    $token_data = [
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // 24 hours expiration
    ];
    
    $token = $jwt->encode($token_data);
    
    http_response_code(201);
    echo json_encode([
        'status' => true,
        'code' => 201,
        'message' => 'User registered successfully',
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => [
            'id' => (int)$user_id,
            'username' => $username,
            'role' => $role
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'code' => 500,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
