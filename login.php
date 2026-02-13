<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require 'connect.php';
require 'jwt.php';
require 'models/UserRole.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Username and password required']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

try {
    $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid username or password']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid username or password']);
        exit;
    }
    
    // Get user role
    $userRole = new UserRole($conn);
    $role = $userRole->getFirstRoleByUserId($user['id']);
    
    // Generate JWT token with role
    $jwt = new JWT();
    $token_data = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'role' => $role,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // 24 hours expiration
    ];
    
    $token = $jwt->encode($token_data);
    
    http_response_code(200);
    echo json_encode([
        'status' => true,
        'code' => 200,
        'message' => 'Login successful',
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
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
