<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require 'connect.php';
require 'jwt.php';

// Get Authorization header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($auth_header)) {
    http_response_code(401);
    echo json_encode(['message' => 'Authorization header missing']);
    exit;
}

// Extract token from "Bearer <token>"
$token = str_replace('Bearer ', '', $auth_header);

// Verify token
$jwt = new JWT();
$decoded = $jwt->decode($token);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(['message' => 'Invalid or expired token']);
    exit;
}

// Access granted - this is a protected endpoint
http_response_code(200);
echo json_encode([
    'message' => 'Access granted',
    'user' => $decoded
]);
