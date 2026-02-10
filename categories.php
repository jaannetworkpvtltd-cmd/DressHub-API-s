<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'connect.php';
require 'jwt.php';

// Helper function to verify JWT token
function verifyToken() {
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth_header)) {
        http_response_code(401);
        echo json_encode(['message' => 'Authorization header missing']);
        exit;
    }
    
    $token = str_replace('Bearer ', '', $auth_header);
    $jwt = new JWT();
    $decoded = $jwt->decode($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid or expired token']);
        exit;
    }
    
    return $decoded;
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($request_uri, $params);
$id = isset($params['id']) ? $params['id'] : null;

try {
    
    // GET - Fetch all or single category
    if ($method === 'GET') {
        if ($id) {
            // Get single category
            $stmt = $conn->prepare("SELECT * FROM categories WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['message' => 'Category not found']);
                exit;
            }
            
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode(['message' => 'Success', 'data' => $category]);
        } else {
            // Get all categories
            $stmt = $conn->query("SELECT * FROM categories");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode(['message' => 'Success', 'data' => $categories]);
        }
    }
    
    // POST - Create new category
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['name'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Name is required']);
            exit;
        }
        
        $name = $data['name'];
        $parent_id = isset($data['parent_id']) ? $data['parent_id'] : null;
        $is_active = isset($data['is_active']) ? $data['is_active'] : 1;
        
        $stmt = $conn->prepare("INSERT INTO categories (name, parent_id, is_active) VALUES (:name, :parent_id, :is_active)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':parent_id', $parent_id);
        $stmt->bindParam(':is_active', $is_active);
        $stmt->execute();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Category created successfully',
            'id' => $conn->lastInsertId()
        ]);
    }
    
    // PUT - Update category
    else if ($method === 'PUT') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['message' => 'Category ID is required']);
            exit;
        }
        
        // Check if category exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
            exit;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        $updates = [];
        $bindings = ['id' => $id];
        
        if (isset($data['name'])) {
            $updates[] = "name = :name";
            $bindings['name'] = $data['name'];
        }
        
        if (isset($data['parent_id'])) {
            $updates[] = "parent_id = :parent_id";
            $bindings['parent_id'] = $data['parent_id'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = :is_active";
            $bindings['is_active'] = $data['is_active'];
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['message' => 'No fields to update']);
            exit;
        }
        
        $query = "UPDATE categories SET " . implode(", ", $updates) . " WHERE id = :id";
        $stmt = $conn->prepare($query);
        
        foreach ($bindings as $key => $value) {
            $stmt->bindParam(':' . $key, $bindings[$key]);
        }
        
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(['message' => 'Category updated successfully']);
    }
    
    // DELETE - Delete category
    else if ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['message' => 'Category ID is required']);
            exit;
        }
        
        // Check if category exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['message' => 'Category not found']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(['message' => 'Category deleted successfully']);
    }
    
    else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
}
