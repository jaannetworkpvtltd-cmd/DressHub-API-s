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

// Initialize JWT and Controllers
$jwt = new JWT();
$cartController = new CartController($conn);
$cartItemController = new CartItemController($conn);

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Verify JWT Token
function verifyToken() {
    global $jwt;
    
    $token = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
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

// Get query parameters
$resource_type = isset($_GET['resource']) ? $_GET['resource'] : 'cart'; // 'cart' or 'items'
$id = isset($_GET['id']) ? $_GET['id'] : null;
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$cart_id = isset($_GET['cart_id']) ? $_GET['cart_id'] : null;

// Route handling
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        verifyToken();
        
        if ($resource_type === 'items') {
            // CART ITEMS - GET
            $params = [];
            if ($id) {
                $params['id'] = $id;
            } else if ($cart_id) {
                $params['cart_id'] = $cart_id;
            }
            $result = $cartItemController->getCartItems($params);
        } else {
            // CART - GET
            $params = [];
            if ($id) {
                $params['id'] = $id;
            } else if ($user_id) {
                $params['user_id'] = $user_id;
            }
            $result = $cartController->getCarts($params);
        }
        
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        verifyToken();
        
        // Check if this is a combined cart + items creation
        if (isset($input['items']) && is_array($input['items'])) {
            // CREATE CART WITH ITEMS IN ONE REQUEST
            try {
                // Start transaction
                $conn->beginTransaction();
                
                // Create the cart
                $cart_data = [
                    'user_id' => $input['user_id'] ?? null,
                    'cart_token' => $input['cart_token'] ?? null
                ];
                
                $cart_result = $cartController->createCart($cart_data);
                
                if (!$cart_result['status']) {
                    $conn->rollBack();
                    http_response_code($cart_result['code']);
                    echo json_encode($cart_result);
                    exit();
                }
                
                $cart_id = $cart_result['data']['id'];
                $items_created = [];
                $total_price = 0;
                
                // Add all items
                foreach ($input['items'] as $item) {
                    // Prepare item data with all required fields
                    $item_data = [
                        'cart_id' => $cart_id,
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'applied_price' => $item['applied_price'] ?? null
                    ];
                    
                    $item_result = $cartItemController->addCartItem($item_data);
                    
                    if (!$item_result['status']) {
                        $conn->rollBack();
                        http_response_code($item_result['code']);
                        echo json_encode($item_result);
                        exit();
                    }
                    
                    $items_created[] = $item_result['data'];
                    $total_price += (float)$item_result['data']['quantity'] * (float)$item_result['data']['applied_price'];
                }
                
                // Commit transaction
                $conn->commit();
                
                // Return combined response
                http_response_code(201);
                echo json_encode([
                    'status' => true,
                    'code' => 201,
                    'data' => [
                        'cart' => $cart_result['data'],
                        'items' => $items_created,
                        'total_items' => count($items_created),
                        'total_price' => number_format($total_price, 2, '.', '')
                    ],
                    'message' => 'Cart with items created successfully'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                http_response_code(500);
                echo json_encode([
                    'status' => false,
                    'code' => 500,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
        } else if ($resource_type === 'items') {
            // CART ITEMS - POST (add single item)
            $result = $cartItemController->addCartItem($input);
            http_response_code($result['code']);
            echo json_encode($result);
        } else {
            // CART - POST (create cart only)
            $result = $cartController->createCart($input);
            http_response_code($result['code']);
            echo json_encode($result);
        }
        break;

    case 'PUT':
        verifyToken();
        
        if ($resource_type === 'items') {
            // CART ITEMS - PUT
            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Item ID is required for update']);
                exit();
            }
            $result = $cartItemController->updateCartItem($id, $input);
        } else {
            // CART - PUT
            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Cart ID is required for update']);
                exit();
            }
            $result = $cartController->updateCart($id, $input);
        }
        
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'DELETE':
        verifyToken();
        
        if ($resource_type === 'items') {
            // CART ITEMS - DELETE
            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Item ID is required for delete']);
                exit();
            }
            $result = $cartItemController->removeCartItem($id);
        } else {
            // CART - DELETE
            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Cart ID is required for delete']);
                exit();
            }
            $result = $cartController->deleteCart($id);
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
