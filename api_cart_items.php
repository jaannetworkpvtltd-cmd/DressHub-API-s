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
require_once __DIR__ . '/controllers/CartItemController.php';

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
$controller = new CartItemController($conn);

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $request_uri);
$item_id = isset($segments[count($segments) - 1]) && is_numeric($segments[count($segments) - 1]) ? $segments[count($segments) - 1] : null;

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Get query parameters
$cart_id = isset($_GET['cart_id']) ? $_GET['cart_id'] : null;
$item_id_query = isset($_GET['id']) ? $_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

// If item_id not found in path, check query parameter
if (!$item_id && $item_id_query) {
    $item_id = $item_id_query;
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
        if ($item_id) {
            $params['id'] = $item_id;
        } else if ($cart_id) {
            $params['cart_id'] = $cart_id;
            if ($action === 'total') {
                $result = $controller->getCartTotal($cart_id);
            } else {
                $result = $controller->getCartItems($params);
            }
        } else {
            $result = $controller->getCartItems($params);
        }
        if (!isset($result)) {
            $result = $controller->getCartItems($params);
        }
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        verifyToken();
        $result = $controller->addCartItem($input);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'PUT':
        verifyToken();
        if ($item_id) {
            $result = $controller->updateCartItem($item_id, $input);
            http_response_code($result['code']);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Item ID is required for update']);
        }
        break;

    case 'DELETE':
        verifyToken();
        if ($action === 'clear' && $cart_id) {
            // Clear entire cart
            $result = $controller->clearCart($cart_id);
            http_response_code($result['code']);
            echo json_encode($result);
        } else if ($item_id) {
            // Remove single item
            $result = $controller->removeCartItem($item_id);
            http_response_code($result['code']);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Item ID is required for delete']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>
