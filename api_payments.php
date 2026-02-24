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
require_once __DIR__ . '/connect.php';

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
        $decoded = verifyToken();

        $params = [];
        if ($id) {
            $params['id'] = $id;
        }
        if ($order_id) {
            $params['order_id'] = $order_id;
        }
        if ($status) {
            $params['status'] = $status;
        }

        // If user is not admin, restrict to their own payments
        $isAdmin = (isset($decoded['role']) && strtolower($decoded['role']) === 'admin');
        if (!$isAdmin) {
            // If requesting an id, ensure ownership after fetch; for lists, add user_id filter
            if (!isset($params['id'])) {
                $params['user_id'] = $decoded['user_id'];
            }
        }

        $result = $paymentController->getPayments($params);

        // If non-admin requested by id, verify ownership
        if (isset($params['id']) && !$isAdmin) {
            if ($result['status'] && isset($result['data'][0])) {
                $payment = $result['data'][0];
                if (isset($payment['user_id']) && $payment['user_id'] != $decoded['user_id']) {
                    http_response_code(403);
                    echo json_encode(['status' => false, 'message' => 'Forbidden']);
                    break;
                }
            }
        }

        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'POST':
        $decoded = verifyToken();

        if (is_array($decoded) && isset($decoded['user_id'])) {
            $input['user_id'] = $decoded['user_id'];
        }

        $result = $paymentController->createPayment($input);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'PUT':
        $decoded = verifyToken();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Payment ID is required for update']);
            exit();
        }

        // Only admin or owner can update
        $existing = $paymentController->getPayments(['id' => $id]);
        if (!($existing['status'] && isset($existing['data'][0]))) {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Payment not found']);
            break;
        }
        $payment = $existing['data'][0];
        $isAdmin = (isset($decoded['role']) && strtolower($decoded['role']) === 'admin');
        if (!$isAdmin && (!isset($payment['user_id']) || $payment['user_id'] != $decoded['user_id'])) {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Forbidden']);
            break;
        }

        // Prevent non-admins from changing user_id
        if (!$isAdmin && isset($input['user_id'])) {
            unset($input['user_id']);
        }

        $result = $paymentController->updatePayment($id, $input);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'DELETE':
        $decoded = verifyToken();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Payment ID is required for delete']);
            exit();
        }

        // Only admin or owner can delete
        $existing = $paymentController->getPayments(['id' => $id]);
        if (!($existing['status'] && isset($existing['data'][0]))) {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Payment not found']);
            break;
        }
        $payment = $existing['data'][0];
        $isAdmin = (isset($decoded['role']) && strtolower($decoded['role']) === 'admin');
        if (!$isAdmin && (!isset($payment['user_id']) || $payment['user_id'] != $decoded['user_id'])) {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Forbidden']);
            break;
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
