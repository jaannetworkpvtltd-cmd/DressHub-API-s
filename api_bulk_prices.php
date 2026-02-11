<?php
header('Content-Type: application/json');
require_once 'connect.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_uri, '/'));
$bulk_price_id = isset($path_parts[3]) ? (int)$path_parts[3] : null;

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($request_method) {
    case 'GET':
        if ($bulk_price_id) {
            getBulkPriceById($conn, $bulk_price_id);
        } else {
            getAllBulkPrices($conn);
        }
        break;
    case 'POST':
        createBulkPrice($conn, $input);
        break;
    case 'PUT':
        updateBulkPrice($conn, $bulk_price_id, $input);
        break;
    case 'DELETE':
        deleteBulkPrice($conn, $bulk_price_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}

// GET all bulk prices
function getAllBulkPrices($conn) {
    try {
        $query = "SELECT pb.*, p.name as product_name, p.price as regular_price FROM product_bulk_prices pb 
                  LEFT JOIN products p ON pb.product_id = p.id 
                  ORDER BY pb.product_id, pb.min_quantity";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $bulk_prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Bulk prices retrieved successfully',
            'data' => $bulk_prices,
            'count' => count($bulk_prices)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// GET single bulk price by ID
function getBulkPriceById($conn, $id) {
    try {
        $query = "SELECT pb.*, p.name as product_name, p.price as regular_price FROM product_bulk_prices pb 
                  LEFT JOIN products p ON pb.product_id = p.id 
                  WHERE pb.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $bulk_price = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bulk_price) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Bulk price retrieved successfully',
                'data' => $bulk_price
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Bulk price not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// POST create bulk price
function createBulkPrice($conn, $input) {
    try {
        $product_id = $input['product_id'] ?? null;
        $min_quantity = (int)($input['min_quantity'] ?? 0);
        $bulk_price = $input['bulk_price'] ?? null;

        if (!$product_id || !$min_quantity || !$bulk_price) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'product_id, min_quantity, and bulk_price are required']);
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

        $query = "INSERT INTO product_bulk_prices (product_id, min_quantity, bulk_price) 
                  VALUES (:product_id, :min_quantity, :bulk_price)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':min_quantity', $min_quantity, PDO::PARAM_INT);
        $stmt->bindParam(':bulk_price', $bulk_price, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $bulk_price_id = $conn->lastInsertId();
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Bulk price created successfully',
                'data' => ['id' => $bulk_price_id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create bulk price']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// PUT update bulk price
function updateBulkPrice($conn, $id, $input) {
    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Bulk price ID is required']);
            return;
        }

        // Check if bulk price exists
        $check_query = "SELECT id FROM product_bulk_prices WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Bulk price not found']);
            return;
        }

        $updates = [];
        $params = [':id' => $id];

        if (isset($input['min_quantity'])) {
            $updates[] = "min_quantity = :min_quantity";
            $params[':min_quantity'] = (int)$input['min_quantity'];
        }
        if (isset($input['bulk_price'])) {
            $updates[] = "bulk_price = :bulk_price";
            $params[':bulk_price'] = $input['bulk_price'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
            return;
        }

        $query = "UPDATE product_bulk_prices SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Bulk price updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update bulk price']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// DELETE bulk price
function deleteBulkPrice($conn, $id) {
    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Bulk price ID is required']);
            return;
        }

        $query = "DELETE FROM product_bulk_prices WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Bulk price deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete bulk price']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
