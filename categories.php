<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'connect.php';
require 'jwt.php';

// Helper function to verify JWT token
function verifyToken() {
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth_header)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing']);
        exit;
    }
    
    $token = str_replace('Bearer ', '', $auth_header);
    $jwt = new JWT();
    $decoded = $jwt->decode($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
        exit;
    }
    
    return $decoded;
}

// Helper function to verify admin role
function verifyAdmin($decoded) {
    // Handle both array and object formats
    $role = isset($decoded['role']) ? $decoded['role'] : (isset($decoded->role) ? $decoded->role : '');
    
    if (strtolower($role) !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
        exit;
    }
}

// Helper function to get product count
function getProductCount($conn, $category_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
        $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

// Helper function to get subcategories
function getSubcategories($conn, $parent_id) {
    try {
        $stmt = $conn->prepare("SELECT id, name, is_active FROM categories WHERE parent_id = :parent_id ORDER BY id");
        $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($request_uri, $params);
$id = isset($params['id']) ? $params['id'] : null;

try {
    
    // GET - Fetch all or single category (No JWT required)
    if ($method === 'GET') {
        if ($id) {
            // Get single category
            $stmt = $conn->prepare("SELECT * FROM categories WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Category not found']);
                exit;
            }
            
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Add product count and subcategories
            $category['product_count'] = getProductCount($conn, $id);
            $category['subcategories'] = getSubcategories($conn, $id);
            
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Category retrieved successfully', 'data' => $category]);
        } else {
            // Get all categories
            $stmt = $conn->query("SELECT * FROM categories ORDER BY parent_id ASC, id ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add product count for each category
            foreach ($categories as &$cat) {
                $cat['product_count'] = getProductCount($conn, $cat['id']);
            }
            
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Categories retrieved successfully', 'data' => $categories, 'count' => count($categories)]);
        }
    }
    
    // POST - Create new category (Admin only)
    else if ($method === 'POST') {
        // Verify JWT token and admin role
        $decoded = verifyToken();
        verifyAdmin($decoded);
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['name']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name is required']);
            exit;
        }
        
        $name = $data['name'];
        $parent_id = isset($data['parent_id']) ? $data['parent_id'] : null;
        $is_active = isset($data['is_active']) ? $data['is_active'] : 1;
        
        // Verify parent category exists if provided
        if ($parent_id) {
            $check = $conn->prepare("SELECT id FROM categories WHERE id = :parent_id");
            $check->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
            $check->execute();
            if ($check->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Parent category not found']);
                exit;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO categories (name, parent_id, is_active) VALUES (:name, :parent_id, :is_active)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':parent_id', $parent_id);
        $stmt->bindParam(':is_active', $is_active);
        $stmt->execute();
        
        $new_id = $conn->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => [
                'id' => (int)$new_id,
                'name' => $name,
                'parent_id' => $parent_id,
                'is_active' => (int)$is_active
            ]
        ]);
    }
    
    // PUT - Update category (Admin only)
    else if ($method === 'PUT') {
        // Verify JWT token and admin role
        $decoded = verifyToken();
        verifyAdmin($decoded);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Category ID is required']);
            exit;
        }
        
        // Check if category exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Category not found']);
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
            // Verify parent category exists if provided
            if ($data['parent_id']) {
                $parent_check = $conn->prepare("SELECT id FROM categories WHERE id = :parent_id");
                $parent_check->bindParam(':parent_id', $data['parent_id'], PDO::PARAM_INT);
                $parent_check->execute();
                if ($parent_check->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Parent category not found']);
                    exit;
                }
            }
            $updates[] = "parent_id = :parent_id";
            $bindings['parent_id'] = $data['parent_id'] ?: null;
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = :is_active";
            $bindings['is_active'] = $data['is_active'];
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
            exit;
        }
        
        $query = "UPDATE categories SET " . implode(", ", $updates) . " WHERE id = :id";
        $stmt = $conn->prepare($query);
        
        foreach ($bindings as $key => $value) {
            $stmt->bindParam(':' . $key, $bindings[$key]);
        }
        
        $stmt->execute();
        
        // Fetch updated category
        $fetch = $conn->prepare("SELECT id, name, parent_id, is_active FROM categories WHERE id = :id");
        $fetch->bindParam(':id', $id, PDO::PARAM_INT);
        $fetch->execute();
        $updated = $fetch->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => [
                'id' => (int)$updated['id'],
                'name' => $updated['name'],
                'parent_id' => $updated['parent_id'],
                'is_active' => (int)$updated['is_active']
            ]
        ]);
    }
    
    // DELETE - Delete category (Admin only)
    else if ($method === 'DELETE') {
        // Verify JWT token and admin role
        $decoded = verifyToken();
        verifyAdmin($decoded);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Category ID is required']);
            exit;
        }
        
        // Check if category exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Category not found']);
            exit;
        }
        
        // Check for products in this category
        $product_check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = :id");
        $product_check->bindParam(':id', $id, PDO::PARAM_INT);
        $product_check->execute();
        $product_result = $product_check->fetch(PDO::FETCH_ASSOC);
        
        if ($product_result['count'] > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete category with products. Remove or reassign products first.']);
            exit;
        }
        
        // Check for subcategories
        $subcategory_check = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = :id");
        $subcategory_check->bindParam(':id', $id, PDO::PARAM_INT);
        $subcategory_check->execute();
        $subcategory_result = $subcategory_check->fetch(PDO::FETCH_ASSOC);
        
        if ($subcategory_result['count'] > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete category with subcategories. Delete or reassign subcategories first.']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Category deleted successfully']);
    }
    
    else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
