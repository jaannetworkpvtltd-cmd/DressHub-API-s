<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'details' => $errstr . ' in ' . $errfile . ':' . $errline
    ]);
    exit;
});

try {
    require_once 'jwt.php';
    require_once 'controllers/ProfileController.php';

    // Get JWT token from headers
    $headers = getallheaders();
    $token = null;

    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }

    // Verify JWT token
    $jwt = new JWT();
    $decoded = $jwt->decode($token);

    if (!$decoded || !isset($decoded['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized - Invalid or missing token'
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error during authentication',
        'details' => $e->getMessage()
    ]);
    exit;
}

$user_id = $decoded['user_id'];
$controller = new ProfileController();
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    // API Endpoints
    if ($method === 'GET' && preg_match('/\/api_profile\.php$/', $request_uri)) {
        // Get profile
        $response = $controller->getProfile($user_id);
        echo json_encode($response);
    }
    elseif ($method === 'POST' && preg_match('/\/api_profile\.php$/', $request_uri)) {
        // Create or Update Profile - JSON only
        $input = json_decode(file_get_contents('php://input'), true);
        
        $data = [
            'user_id' => $user_id,
            'full_name' => isset($input['full_name']) && !empty($input['full_name']) ? $input['full_name'] : null,
            'phone' => isset($input['phone']) && !empty($input['phone']) ? $input['phone'] : null,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
        ];

        // Check if profile exists
        $existing = $controller->getProfile($user_id);
        
        if ($existing['status'] === 'success' && isset($existing['data'])) {
            // Update existing profile
            $response = $controller->updateProfile($user_id, $data);
        } else {
            // Create new profile
            $response = $controller->createProfile($data);
        }
        echo json_encode($response);
    }
    elseif ($method === 'PUT' && preg_match('/\/api_profile\.php$/', $request_uri)) {
        // Update profile (JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        $data = [
            'full_name' => $input['full_name'] ?? null,
            'phone' => $input['phone'] ?? null,
            'is_active' => $input['is_active'] ?? 1
        ];
        $response = $controller->updateProfile($user_id, $data);
        echo json_encode($response);
    }
    else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Endpoint not found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
?>
