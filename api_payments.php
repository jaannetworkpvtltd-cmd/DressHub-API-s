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
require_once __DIR__ . '/controllers/PaymentController.php';

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
$paymentController = new PaymentController($conn);

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Get query parameters
$id = isset($_GET['id']) ? $_GET['id'] : null;
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

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

// Route handling
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        verifyToken();
        
        $params = [];
        if ($id) {
            $params['id'] = $id;
        } else if ($order_id) {
            $params['order_id'] = $order_id;
        } else if ($status) {
            $params['status'] = $status;
        }
        
        $result = $paymentController->getPayments($params);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        verifyToken();
        
        $result = $paymentController->createPayment($input);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'PUT':
        verifyToken();
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Payment ID is required for update']);
            exit();
        }
        
        $result = $paymentController->updatePayment($id, $input);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'DELETE':
        verifyToken();
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Payment ID is required for delete']);
            exit();
        }
        
        $result = $paymentController->deletePayment($id);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>
