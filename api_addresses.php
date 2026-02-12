<?php

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/controllers/AddressController.php';

// Database connection
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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database Connection Error: ' . $e->getMessage()]);
    exit();
}

// Initialize JWT and Controller
$jwt = new JWT();
$controller = new AddressController($conn);

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $request_uri);
$address_id = isset($segments[count($segments) - 1]) && is_numeric($segments[count($segments) - 1]) ? $segments[count($segments) - 1] : null;

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Get query parameters
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$address_id_query = isset($_GET['id']) ? $_GET['id'] : null;

// If address_id not found in path, check query parameter
if (!$address_id && $address_id_query) {
    $address_id = $address_id_query;
}

// Verify JWT Token (except for OPTIONS)
function verifyToken() {
    global $jwt;
    
    $token = null;

    // Get Authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    // Fallback for servers without HTTP_AUTHORIZATION
    if (!$token && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token is required']);
        exit();
    }

    $decoded = $jwt->decode($token);
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Invalid or expired token']);
        exit();
    }

    return $decoded;
}

// Route handling
switch ($request_method) {
    case 'GET':
        verifyToken();
        $params = [];
        if ($address_id) {
            $params['id'] = $address_id;
        } else if ($user_id) {
            $params['user_id'] = $user_id;
        }
        $result = $controller->getAddresses($params);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        verifyToken();
        $result = $controller->createAddress($input);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'PUT':
        verifyToken();
        if ($address_id) {
            $result = $controller->updateAddress($address_id, $input);
            http_response_code($result['code']);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Address ID is required for update']);
        }
        break;

    case 'DELETE':
        verifyToken();
        if ($address_id) {
            $result = $controller->deleteAddress($address_id);
            http_response_code($result['code']);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Address ID is required for delete']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>
