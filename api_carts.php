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
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/CartItemController.php';

// Database connection
require_once __DIR__ . '/connect.php';

// Initialize JWT and Controllers
$jwt = new JWT();
$cartController = new CartController($conn);
$cartItemController = new CartItemController($conn);

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $request_uri);
$resource_id = isset($segments[count($segments) - 1]) && is_numeric($segments[count($segments) - 1]) ? $segments[count($segments) - 1] : null;

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Get query parameters
$resource_type = isset($_GET['resource']) ? $_GET['resource'] : 'cart'; // 'cart' or 'items'
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$cart_id = isset($_GET['cart_id']) ? $_GET['cart_id'] : null;
$id = isset($_GET['id']) ? $_GET['id'] : ($resource_id ?? null);
$token = isset($_GET['token']) ? $_GET['token'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

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

// Route handling - Determine which resource to handle
switch ($request_method) {
    case 'GET':
        $decoded = verifyToken();
        $jwt_user_id = $decoded['user_id'];
        
        if ($resource_type === 'items') {
            // CART ITEMS - GET
            $params = [];
            if ($id) {
                $params['id'] = $id;
            } else if ($cart_id) {
                $params['cart_id'] = $cart_id;
                if ($action === 'total') {
                    $result = $cartItemController->getCartTotal($cart_id, $jwt_user_id);
                } else {
                    $result = $cartItemController->getCartItems($params, $jwt_user_id);
                }
            } else {
                $result = $cartItemController->getCartItems($params, $jwt_user_id);
            }
            if (!isset($result)) {
                $result = $cartItemController->getCartItems($params, $jwt_user_id);
            }
        } else {
            // CART - GET
            $params = [];
            if ($id) {
                $params['id'] = $id;
            }
            $result = $cartController->getCarts($params, $jwt_user_id);
        }
        
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        $decoded = verifyToken();
        $jwt_user_id = $decoded['user_id'];
        
        if ($resource_type === 'items') {
            // CART ITEMS - POST (Add item to cart)
            $result = $cartItemController->addCartItem($input, $jwt_user_id);
        } else {
            // CART - POST (Create cart)
            $result = $cartController->createCart($input, $jwt_user_id);
        }
        
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'PUT':
        $decoded = verifyToken();
        $jwt_user_id = $decoded['user_id'];
        
        if ($resource_type === 'items') {
            // CART ITEMS - PUT (Update item)
            if ($id) {
                $result = $cartItemController->updateCartItem($id, $input, $jwt_user_id);
                http_response_code($result['code']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Item ID is required for update']);
            }
        } else {
            // CART - PUT (Update cart)
            if ($id) {
                $result = $cartController->updateCart($id, $input, $jwt_user_id);
                http_response_code($result['code']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Cart ID is required for update']);
            }
        }
        break;

    case 'DELETE':
        $decoded = verifyToken();
        $jwt_user_id = $decoded['user_id'];
        
        if ($resource_type === 'items') {
            // CART ITEMS - DELETE
            if ($action === 'clear' && $cart_id) {
                // Clear entire cart
                $result = $cartItemController->clearCart($cart_id, $jwt_user_id);
            } else if ($id) {
                // Remove single item
                $result = $cartItemController->removeCartItem($id, $jwt_user_id);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Item ID is required for delete']);
                break;
            }
        } else {
            // CART - DELETE
            if ($id) {
                $result = $cartController->deleteCart($id, $jwt_user_id);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Cart ID is required for delete']);
                break;
            }
        }
        
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>
