<?php

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    echo json_encode(['status' => 'error', 'message' => 'Database Connection Error: ' . $e->getMessage()]);
    exit();
}

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $request_uri);
$product_id = isset($segments[count($segments) - 1]) && is_numeric($segments[count($segments) - 1]) ? $segments[count($segments) - 1] : null;

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Route handling
switch ($request_method) {
    case 'GET':
        if ($product_id) {
            getProductById($conn, $product_id);
        } else {
            getAllProducts($conn);
        }
        break;

    case 'POST':
        createProduct($conn, $input);
        break;

    case 'PUT':
        if ($product_id) {
            updateProduct($conn, $product_id, $input);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Product ID is required for update']);
        }
        break;

    case 'DELETE':
        if ($product_id) {
            deleteProduct($conn, $product_id);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Product ID is required for delete']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        break;
}

function formatProductWithCategory($product, $conn = null) {
    $formatted = [
        'id' => (int)$product['id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => $product['price'],
        'is_active' => (int)$product['is_active'],
        'created_at' => $product['created_at'],
        'category' => $product['category_id'] ? [
            'id' => (int)$product['category_id'],
            'name' => $product['category_name'],
            'parent_id' => $product['parent_id'] ? (int)$product['parent_id'] : null,
            'is_active' => (int)$product['category_is_active']
        ] : null
    ];

    // Get images for this product if connection is available
    if ($conn !== null) {
        try {
            $img_query = "SELECT id, product_id, image_url, is_primary FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC";
            $img_stmt = $conn->prepare($img_query);
            $img_stmt->bindParam(':product_id', $product['id'], PDO::PARAM_INT);
            $img_stmt->execute();
            $images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted['images'] = [];
            foreach ($images as $image) {
                $formatted['images'][] = [
                    'id' => (int)$image['id'],
                    'image_url' => $image['image_url'],
                    'is_primary' => (int)$image['is_primary']
                ];
            }
        } catch (Exception $e) {
            $formatted['images'] = [];
        }
    }

    return $formatted;
}

// GET all products
function getAllProducts($conn) {
    try {
        $query = "SELECT p.id, p.name, p.description, p.price, p.is_active, p.created_at,
                  c.id as category_id, c.name as category_name, c.parent_id, c.is_active as category_is_active
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.id
                  ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedProducts = [];
        foreach ($products as $product) {
            $formattedProducts[] = formatProductWithCategory($product, $conn);
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Products retrieved successfully',
            'data' => $formattedProducts,
            'count' => count($formattedProducts)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving products: ' . $e->getMessage()]);
    }
}

// GET product by ID
function getProductById($conn, $id) {
    try {
        $query = "SELECT p.id, p.name, p.description, p.price, p.is_active, p.created_at,
                  c.id as category_id, c.name as category_name, c.parent_id, c.is_active as category_is_active
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => formatProductWithCategory($product, $conn)
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving product: ' . $e->getMessage()]);
    }
}

// CREATE new product
function createProduct($conn, $input) {
    try {
        // Handle both JSON input and form-data
        if ($input === null) {
            $input = $_POST;
        }

        // Validate required fields
        $required_fields = ['name', 'price', 'category_id'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => "Field '$field' is required"]);
                return;
            }
        }

        $name = trim($input['name']);
        $description = isset($input['description']) ? trim($input['description']) : '';
        $price = trim($input['price']);
        $category_id = trim($input['category_id']);
        $is_active = isset($input['is_active']) ? (int)trim($input['is_active']) : 1;

        $query = "INSERT INTO products (category_id, name, description, price, is_active, created_at)
                  VALUES (:category_id, :name, :description, :price, :is_active, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $product_id = $conn->lastInsertId();

            // Handle image upload if provided
            $image_url = null;
            
            if (isset($_FILES['image'])) {
                if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image_url = uploadProductImageFile($product_id, $_FILES['image'], true, $conn);
                }
            }

            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => [
                    'id' => $product_id,
                    'image_url' => $image_url
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create product']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error creating product: ' . $e->getMessage()]);
    }
}

// Helper function to upload product image
function uploadProductImageFile($product_id, $file, $is_primary = false, $conn = null) {
    $images_folder = __DIR__ . '/images/products/';
    
    try {
        // Ensure folder exists and is writable
        if (!is_dir($images_folder)) {
            mkdir($images_folder, 0777, true);
        }
        
        if (!is_writable($images_folder)) {
            chmod($images_folder, 0777);
        }

        $file_name = $file['name'] ?? '';
        $file_tmp = $file['tmp_name'] ?? '';
        $file_error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $file_size = $file['size'] ?? 0;

        // Validate file upload
        if ($file_error !== UPLOAD_ERR_OK) {
            error_log("Upload error {$file_error}: {$file_name}");
            return null;
        }

        if (!file_exists($file_tmp) || !is_uploaded_file($file_tmp)) {
            error_log("Invalid temp file: {$file_tmp}");
            return null;
        }

        // Check file size (max 5MB)
        if ($file_size > 5242880) {
            error_log("File too large: {$file_size}");
            return null;
        }

        // Validate file type
        $file_info = getimagesize($file_tmp);
        if (!$file_info) {
            error_log("Not a valid image file: {$file_name}");
            return null;
        }

        // Generate unique filename
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            error_log("Invalid file extension: {$file_ext}");
            return null;
        }

        $new_file_name = 'product_' . $product_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $images_folder . $new_file_name;

        // Move uploaded file
        if (!move_uploaded_file($file_tmp, $file_path)) {
            error_log("Failed to move file from {$file_tmp} to {$file_path}");
            return null;
        }

        // Set permissions
        chmod($file_path, 0644);
        error_log("Image file saved: {$file_path}");

        // Generate URL
        $image_url = 'http://' . $_SERVER['HTTP_HOST'] . '/DressHub%20APIs/images/products/' . $new_file_name;

        // Save to product_images table if connection available
        if ($conn !== null) {
            try {
                // If primary, set others to non-primary
                if ($is_primary) {
                    $update_query = "UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                }

                // Insert image record (without created_at if it doesn't exist)
                $insert_query = "INSERT INTO product_images (product_id, image_url, is_primary)
                                VALUES (:product_id, :image_url, :is_primary)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':image_url', $image_url, PDO::PARAM_STR);
                $is_primary_int = $is_primary ? 1 : 0;
                $insert_stmt->bindParam(':is_primary', $is_primary_int, PDO::PARAM_INT);
                
                if ($insert_stmt->execute()) {
                    error_log("Image DB record saved: {$image_url}");
                    return $image_url;
                } else {
                    error_log("Failed to insert image record into DB: " . json_encode($insert_stmt->errorInfo()));
                    return $image_url; // Return URL even if DB save fails
                }
            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                return $image_url; // Return URL even if DB save fails
            }
        }

        return $image_url;
    } catch (Exception $e) {
        error_log("Upload exception: " . $e->getMessage());
        return null;
    }
}

// UPDATE product
function updateProduct($conn, $id, $input) {
    try {
        // Check if product exists
        $check_query = "SELECT id FROM products WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();

        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            return;
        }

        // Build update query dynamically
        $allowed_fields = ['name', 'description', 'price', 'category_id', 'is_active'];
        $update_fields = [];
        $params = [':id' => $id];

        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_fields[] = "$field = :$field";
                $params[":$field"] = $input[$field];
            }
        }

        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
            return;
        }

        $query = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = :id";
        $stmt = $conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => ['id' => $id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update product']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error updating product: ' . $e->getMessage()]);
    }
}

// DELETE product
function deleteProduct($conn, $id) {
    try {
        // Check if product exists
        $check_query = "SELECT id FROM products WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();

        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            return;
        }

        $query = "DELETE FROM products WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product deleted successfully',
                'data' => ['id' => $id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete product']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error deleting product: ' . $e->getMessage()]);
    }
}

?>
