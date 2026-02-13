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
require_once __DIR__ . '/models/UserRole.php';

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

// Initialize JWT and Model
$jwt = new JWT();
$userRole = new UserRole($conn);

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Get query parameters
$id = isset($_GET['id']) ? $_GET['id'] : null;
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

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
function verifyAdmin($decoded) {
    if (!isset($decoded['role']) || $decoded['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Admin access required']);
        exit();
    }
}

// Route handling
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        verifyToken();
        
        if ($id) {
            $role = $userRole->getById($id);
            if (!$role) {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Role not found']);
                exit();
            }
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'code' => 200,
                'data' => $role,
                'message' => 'Role retrieved successfully'
            ]);
        } else if ($user_id) {
            $roles = $userRole->getByUserId($user_id);
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'code' => 200,
                'data' => $roles,
                'message' => 'User roles retrieved successfully'
            ]);
        } else {
            $roles = $userRole->getAll();
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'code' => 200,
                'data' => $roles,
                'message' => 'All roles retrieved successfully'
            ]);
        }
        break;

    case 'POST':
        $decoded = verifyToken();
        verifyAdmin($decoded);
        
        // Validate required fields
        if (!isset($input['user_id']) || !isset($input['role'])) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'user_id and role are required']);
            exit();
        }
        
        $valid_roles = ['admin', 'customer', 'staff'];
        if (!in_array($input['role'], $valid_roles)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid role. Must be: ' . implode(', ', $valid_roles)]);
            exit();
        }
        
        if ($userRole->assignRole($input['user_id'], $input['role'])) {
            http_response_code(201);
            echo json_encode([
                'status' => true,
                'code' => 201,
                'data' => [
                    'user_id' => $input['user_id'],
                    'role' => $input['role']
                ],
                'message' => 'Role assigned successfully'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Failed to assign role']);
        }
        break;

    case 'PUT':
        $decoded = verifyToken();
        verifyAdmin($decoded);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Role ID is required']);
            exit();
        }
        
        if (!isset($input['role'])) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'role is required']);
            exit();
        }
        
        $valid_roles = ['admin', 'customer', 'staff'];
        if (!in_array($input['role'], $valid_roles)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid role. Must be: ' . implode(', ', $valid_roles)]);
            exit();
        }
        
        if ($userRole->updateRole($id, $input['role'])) {
            $updated = $userRole->getById($id);
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'code' => 200,
                'data' => $updated,
                'message' => 'Role updated successfully'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Failed to update role']);
        }
        break;

    case 'DELETE':
        $decoded = verifyToken();
        verifyAdmin($decoded);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Role ID is required']);
            exit();
        }
        
        if ($userRole->delete($id)) {
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'code' => 200,
                'data' => ['id' => $id],
                'message' => 'Role deleted successfully'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Failed to delete role']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>
