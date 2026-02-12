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

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyToken();
    
    // Check if this is a combined order + items creation
    if (isset($input['items']) && is_array($input['items'])) {
        // CREATE ORDER WITH ITEMS IN ONE REQUEST
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Create the order
            $order_data = [
                'user_id' => $input['user_id'] ?? null,
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
    } else {
        // CREATE ORDER ONLY
        verifyToken();
        $result = $orderController->createOrder($input);
        http_response_code($result['code']);
        echo json_encode($result);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Only POST method is allowed']);
}
?>
