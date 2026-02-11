<?php
header('Content-Type: application/json');
require_once 'connect.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_uri, '/'));
$variant_id = isset($path_parts[3]) ? (int)$path_parts[3] : null;

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($request_method) {
    case 'GET':
        if ($variant_id) {
            getVariantById($conn, $variant_id);
        } else {
            getAllVariants($conn);
        }
        break;
    case 'POST':
        createVariant($conn, $input);
        break;
    case 'PUT':
        updateVariant($conn, $variant_id, $input);
        break;
    case 'DELETE':
        deleteVariant($conn, $variant_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}

// GET all variants
function getAllVariants($conn) {
    try {
        $query = "SELECT pv.*, p.name as product_name FROM product_variants pv 
                  LEFT JOIN products p ON pv.product_id = p.id 
                  ORDER BY pv.product_id, pv.id";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Variants retrieved successfully',
            'data' => $variants,
            'count' => count($variants)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// GET single variant by ID
function getVariantById($conn, $id) {
    try {
        $query = "SELECT pv.*, p.name as product_name FROM product_variants pv 
                  LEFT JOIN products p ON pv.product_id = p.id 
                  WHERE pv.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($variant) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Variant retrieved successfully',
                'data' => $variant
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Variant not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// POST create variant
function createVariant($conn, $input) {
    try {
        $product_id = $input['product_id'] ?? null;
        $size = $input['size'] ?? null;
        $color = $input['color'] ?? null;
        $stock = (int)($input['stock'] ?? 0);

        if (!$product_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'product_id is required']);
            return;
        }

        // Check if product exists
        $check_query = "SELECT id FROM products WHERE id = :product_id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            return;
        }

        $query = "INSERT INTO product_variants (product_id, size, color, stock) 
                  VALUES (:product_id, :size, :color, :stock)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':size', $size, PDO::PARAM_STR);
        $stmt->bindParam(':color', $color, PDO::PARAM_STR);
        $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $variant_id = $conn->lastInsertId();
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Variant created successfully',
                'data' => ['id' => $variant_id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create variant']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// PUT update variant
function updateVariant($conn, $id, $input) {
    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Variant ID is required']);
            return;
        }

        // Check if variant exists
        $check_query = "SELECT id FROM product_variants WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Variant not found']);
            return;
        }

        $updates = [];
        $params = [':id' => $id];

        if (isset($input['size'])) {
            $updates[] = "size = :size";
            $params[':size'] = $input['size'];
        }
        if (isset($input['color'])) {
            $updates[] = "color = :color";
            $params[':color'] = $input['color'];
        }
        if (isset($input['stock'])) {
            $updates[] = "stock = :stock";
            $params[':stock'] = (int)$input['stock'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
            return;
        }

        $query = "UPDATE product_variants SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Variant updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update variant']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// DELETE variant
function deleteVariant($conn, $id) {
    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Variant ID is required']);
            return;
        }

        $query = "DELETE FROM product_variants WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Variant deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete variant']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
