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
require_once __DIR__ . '/controllers/OrderController.php';

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
$orderController = new OrderController($conn);
$orderItemController = new OrderItemController($conn);

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $request_uri);
$resource_id = isset($segments[count($segments) - 1]) && is_numeric($segments[count($segments) - 1]) ? $segments[count($segments) - 1] : null;

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Get query parameters
$resource_type = isset($_GET['resource']) ? $_GET['resource'] : 'order'; // 'order' or 'items'
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$id = isset($_GET['id']) ? $_GET['id'] : ($resource_id ?? null);
$action = isset($_GET['action']) ? $_GET['action'] : null;

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

// Check if user is admin
function isAdmin($decoded) {
    $role = isset($decoded['role']) ? strtolower($decoded['role']) : '';
    return $role === 'admin';
}

// Get user ID from decoded token
function getUserId($decoded) {
    return isset($decoded['user_id']) ? $decoded['user_id'] : null;
}

// Route handling
switch ($request_method) {
    case 'GET':
        $decoded = verifyToken();
        $current_user_id = getUserId($decoded);
        $is_admin = isAdmin($decoded);
        
        if ($resource_type === 'items') {
            // ORDER ITEMS - GET
            $params = [];
            if ($id) {
                $params['id'] = $id;
            } else if ($order_id) {
                $params['order_id'] = $order_id;
            }
            $result = $orderItemController->getOrderItems($params);
        } else {
            // ORDER - GET (with role-based access)
            $params = [];
            
            if ($id) {
                // Get specific order
                $order_info = $orderController->getOrders(['id' => $id]);
                
                // Check authorization - customer can only see their own, admin can see all
                if (!$is_admin && isset($order_info['data'][0]) && $order_info['data'][0]['user_id'] != $current_user_id) {
                    http_response_code(403);
                    echo json_encode(['status' => false, 'message' => 'Not authorized to view this order']);
                    exit();
                }
                $params['id'] = $id;
            } else if ($user_id) {
                // Get orders by specific user
                // Customer can only view their own orders
                if (!$is_admin && $user_id != $current_user_id) {
                    http_response_code(403);
                    echo json_encode(['status' => false, 'message' => 'Not authorized to view other users orders']);
                    exit();
                }
                $params['user_id'] = $user_id;
            } else {
                // Get all orders or customer's own orders
                if (!$is_admin) {
                    // Customer - show only their orders
                    $params['user_id'] = $current_user_id;
                }
                // Admin - shows all orders (no filter)
            }
            
            $result = $orderController->getOrders($params);
        }
        
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        $decoded = verifyToken();
        $current_user_id = getUserId($decoded);
        $is_admin = isAdmin($decoded);
        
        // Check if this is a combined order + items creation (items array present)
        if (isset($input['items']) && is_array($input['items']) && $resource_type === 'items') {
            // CREATE ORDER WITH ITEMS IN ONE REQUEST
            try {
                // Customer can only create for themselves
                if (!$is_admin && isset($input['user_id']) && $input['user_id'] != $current_user_id) {
                    http_response_code(403);
                    echo json_encode(['status' => false, 'message' => 'Customers can only create orders for themselves']);
                    exit();
                }
                
                // Set user_id if not provided
                if (!isset($input['user_id'])) {
                    $input['user_id'] = $current_user_id;
                }
                
                // Start transaction
                $conn->beginTransaction();
                
                // Create the order
                $order_data = [
                    'user_id' => $input['user_id'],
                    'total_amount' => $input['total_amount'] ?? 0,
                    'note' => $input['note'] ?? null
                ];
                
                $order_result = $orderController->createOrder($order_data);
                
                if (!$order_result['status']) {
                    $conn->rollBack();
                    http_response_code($order_result['code']);
                    echo json_encode($order_result);
                    exit();
                }
                
                $order_id = $order_result['data']['id'];
                $items_created = [];
                $total_price = 0;
                
                // Add all items
                foreach ($input['items'] as $item) {
                    // Prepare item data with all required fields
                    $item_data = [
                        'order_id' => $order_id,
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'unit_price' => $item['unit_price'] ?? null
                    ];
                    
                    $item_result = $orderItemController->addOrderItem($item_data);
                    
                    if (!$item_result['status']) {
                        $conn->rollBack();
                        http_response_code($item_result['code']);
                        echo json_encode($item_result);
                        exit();
                    }
                    
                    $items_created[] = $item_result['data'];
                    $total_price += (float)$item_result['data']['total_price'];
                }
                
                // Update order total amount
                $update_result = $orderController->updateOrder($order_id, ['total_amount' => $total_price]);
                
                if (!$update_result['status']) {
                    $conn->rollBack();
                    http_response_code($update_result['code']);
                    echo json_encode($update_result);
                    exit();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Return combined response
                http_response_code(201);
                echo json_encode([
                    'status' => true,
                    'code' => 201,
                    'data' => [
                        'order' => $update_result['data'],
                        'items' => $items_created,
                        'total_items' => count($items_created),
                        'total_amount' => (float)$total_price
                    ],
                    'message' => 'Order with items created successfully'
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
            // ORDER ITEMS - POST (add single item to existing order)
            $result = $orderItemController->addOrderItem($input);
            http_response_code($result['code']);
            echo json_encode($result);
        } else {
            // ORDER - POST (create order only)
            // Customer can only create for themselves
            if (!$is_admin && isset($input['user_id']) && $input['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Customers can only create orders for themselves']);
                exit();
            }
            
            // Set user_id if not provided
            if (!isset($input['user_id'])) {
                $input['user_id'] = $current_user_id;
            }
            
            $result = $orderController->createOrder($input);
            http_response_code($result['code']);
            echo json_encode($result);
        }
        break;

    case 'PUT':
        $decoded = verifyToken();
        $current_user_id = getUserId($decoded);
        $is_admin = isAdmin($decoded);
        
        if ($resource_type === 'items') {
            // ORDER ITEMS - PUT
            if ($id) {
                $result = $orderItemController->updateOrderItem($id, $input);
                http_response_code($result['code']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Item ID is required for update']);
            }
        } else {
            // ORDER - PUT (only admin can update orders)
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Only admins can update orders']);
                exit();
            }
            
            if ($id) {
                $result = $orderController->updateOrder($id, $input);
                http_response_code($result['code']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Order ID is required for update']);
            }
        }
        break;

    case 'DELETE':
        $decoded = verifyToken();
        $current_user_id = getUserId($decoded);
        $is_admin = isAdmin($decoded);
        
        if ($resource_type === 'items') {
            // ORDER ITEMS - DELETE
            if ($id) {
                $result = $orderItemController->removeOrderItem($id);
                http_response_code($result['code']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Item ID is required for delete']);
            }
        } else {
            // ORDER - DELETE (customer can delete own, admin can delete any)
            if ($id) {
                // Get order details to check ownership
                $order_check = $orderController->getOrders(['id' => $id]);
                
                if (!$order_check['status'] || empty($order_check['data'])) {
                    http_response_code(404);
                    echo json_encode(['status' => false, 'message' => 'Order not found']);
                    exit();
                }
                
                $order_owner_id = $order_check['data'][0]['user_id'];
                
                // Check authorization - customer can only delete own orders, admin can delete any
                if (!$is_admin && $order_owner_id != $current_user_id) {
                    http_response_code(403);
                    echo json_encode(['status' => false, 'message' => 'Not authorized to delete this order']);
                    exit();
                }
                
                $result = $orderController->deleteOrder($id);
                http_response_code($result['code']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Order ID is required for delete']);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>
