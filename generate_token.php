<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'jwt.php';

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $user_id = $input['user_id'] ?? 1;
        $username = $input['username'] ?? 'testuser';
        $exp_hours = $input['exp_hours'] ?? 24;
        
    } else {
        $user_id = $_GET['user_id'] ?? 1;
        $username = $_GET['username'] ?? 'testuser';
        $exp_hours = $_GET['exp_hours'] ?? 24;
    }
    
    $jwt = new JWT();
    
    $payload = [
        'user_id' => (int)$user_id,
        'username' => $username,
        'iat' => time(),
        'exp' => time() + ($exp_hours * 3600)
    ];
    
    $token = $jwt->encode($payload);
    
    http_response_code(200);
    echo json_encode([
        'status' => true,
        'code' => 200,
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => ($exp_hours * 3600),
        'user_id' => (int)$user_id,
        'username' => $username,
        'message' => 'Token generated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'code' => 500,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
