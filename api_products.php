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

// Include JWT
require_once __DIR__ . '/jwt.php';

// Initialize JWT globally
$jwt = new JWT();

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

// Parse multipart form-data for non-POST requests (PUT, PATCH, DELETE)
function parseMultipartFormData() {
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($content_type, 'multipart/form-data') === false) {
        return ['fields' => [], 'files' => []];
    }
    
    // Extract boundary from Content-Type
    if (!preg_match('/boundary=([^;]+)/', $content_type, $matches)) {
        error_log("Failed to extract boundary from Content-Type: $content_type");
        return ['fields' => [], 'files' => []];
    }
    
    $boundary = trim($matches[1], '"');
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        error_log("Empty input stream for multipart parsing");
        return ['fields' => [], 'files' => []];
    }
    
    // Split by boundary
    $parts = explode('--' . $boundary, $input);
    
    $fields = [];
    $files = [];
    
    foreach ($parts as $part) {
        if (empty($part) || $part === '--' || trim($part) === '--') {
            continue;
        }
        
        // Split headers from content
        $parts_split = explode("\r\n\r\n", $part, 2);
        if (count($parts_split) !== 2) {
            continue;
        }
        
        $headers = $parts_split[0];
        $content = $parts_split[1];
        
        // Remove trailing \r\n
        $content = preg_replace("/\r\n$/", '', $content);
        
        // Parse Content-Disposition header
        if (!preg_match('/name="([^"]+)"/', $headers, $name_match)) {
            continue;
        }
        
        $name = $name_match[1];
        
        try {
            // Check if it's a file upload
            if (preg_match('/filename="([^"]+)"/', $headers, $filename_match)) {
                $filename = $filename_match[1];
                
                // Extract MIME type
                $mime_type = 'application/octet-stream';
                if (preg_match('/Content-Type: ([^\r\n]+)/', $headers, $mime_match)) {
                    $mime_type = trim($mime_match[1]);
                }
                
                // Create temporary file in a more reliable location
                $images_folder = __DIR__ . '/images/products/';
                if (!is_dir($images_folder)) {
                    @mkdir($images_folder, 0755, true);
                }
                
                // Try to use system temp dir first, then fall back to images folder
                $tmp_name = tempnam(sys_get_temp_dir(), 'php_upload_');
                if ($tmp_name === false) {
                    error_log("System temp failed, trying images folder");
                    $tmp_name = tempnam($images_folder, 'tmp_upload_');
                }
                
                if ($tmp_name === false) {
                    error_log("Failed to create temp file for upload in either location");
                    http_response_code(500);
                    die(json_encode(['status' => 'error', 'message' => 'Failed to create temp file for upload']));
                }
                
                $bytes_written = file_put_contents($tmp_name, $content);
                if ($bytes_written === false) {
                    error_log("Failed to write content to temp file: $tmp_name");
                    @unlink($tmp_name);
                    http_response_code(500);
                    die(json_encode(['status' => 'error', 'message' => 'Failed to write uploaded file']));
                }
                
                error_log("DEBUG: Temp file created for '$name': $tmp_name (size: $bytes_written bytes)");
                
                $files[$name] = [
                    'name' => $filename,
                    'type' => $mime_type,
                    'tmp_name' => $tmp_name,
                    'error' => UPLOAD_ERR_OK,
                    'size' => $bytes_written
                ];
            } else {
                // Regular form field
                $fields[$name] = $content;
            }
        } catch (Exception $e) {
            error_log("Error parsing multipart field '$name': " . $e->getMessage());
            continue;
        }
    }
    
    return ['fields' => $fields, 'files' => $files];
}

// Get input data
// For JSON requests, decode from php://input
// For form-data on POST: $_POST and $_FILES are auto-populated
// For form-data on PUT/PATCH: parse manually
$input = null;
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

error_log("DEBUG: Request method: $request_method, Content-Type: $content_type");

try {
    if (strpos($content_type, 'application/json') !== false) {
        $input = json_decode(file_get_contents("php://input"), true);
        error_log("DEBUG: Parsed JSON input");
    } else if (strpos($content_type, 'multipart/form-data') !== false) {
        if ($request_method === 'POST') {
            // POST: PHP auto-populates $_POST and $_FILES
            $input = $_POST;
            error_log("DEBUG: Using native $_POST for multipart/form-data POST request");
            // $_FILES will be handled separately in createProduct
        } else {
            // PUT/PATCH: Parse manually
            error_log("DEBUG: Parsing multipart/form-data for $request_method request");
            $parsed = parseMultipartFormData();
            $input = $parsed['fields'];
            error_log("DEBUG: Parsed fields: " . json_encode(array_keys($input)));
            error_log("DEBUG: Parsed files: " . json_encode(array_keys($parsed['files'])));
            // Store parsed files in $_FILES for consistency
            foreach ($parsed['files'] as $key => $file) {
                $_FILES[$key] = $file;
                error_log("DEBUG: Populated \$_FILES['$key'] with temp file: {$file['tmp_name']}");
            }
        }
    } else if (empty($content_type)) {
        // No content type specified, assume form-data
        $input = $_POST ?: [];
        error_log("DEBUG: No Content-Type header, using \$_POST");
    } else {
        error_log("WARNING: Unknown Content-Type: $content_type");
        $input = [];
    }
} catch (Exception $e) {
    error_log("ERROR: Failed to parse input: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data: ' . $e->getMessage()]);
    exit();
}

// Get query parameters - check both path and query string for product_id
$category_id = isset($_GET['category_id']) ? $_GET['category_id'] : null;
$product_id_query = isset($_GET['product_id']) ? $_GET['product_id'] : (isset($_GET['id']) ? $_GET['id'] : null);

// If product_id not found in path, check query parameter
if (!$product_id && $product_id_query) {
    $product_id = $product_id_query;
}

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
        echo json_encode(['status' => 'error', 'message' => 'Token is required']);
        exit();
    }

    $decoded = $jwt->decode($token);
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
        exit();
    }

    return $decoded;
}

// Check if user is admin
function verifyAdmin($decoded) {
    if (!isset($decoded['role']) || $decoded['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
        exit();
    }
}

// Route handling
switch ($request_method) {
    case 'GET':
        // GET is public, no authentication required
        if ($product_id) {
            getProductById($conn, $product_id);
        } else if ($category_id) {
            getProductsByCategory($conn, $category_id);
        } else {
            getAllProducts($conn);
        }
        break;

    case 'POST':
        // POST requires admin role
        $decoded = verifyToken();
        verifyAdmin($decoded);
        createProduct($conn, $input);
        break;

    case 'PUT':
        // PUT requires admin role
        $decoded = verifyToken();
        verifyAdmin($decoded);
        if ($product_id) {
            updateProduct($conn, $product_id, $input);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Product ID is required for update']);
        }
        break;

    case 'DELETE':
        // DELETE requires admin role
        $decoded = verifyToken();
        verifyAdmin($decoded);
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

    // Get images, variants, and bulk prices if connection is available
    if ($conn !== null) {
        try {
            // Get images
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

            // Get variants
            $var_query = "SELECT id, product_id, size, color, stock, created_at FROM product_variants WHERE product_id = :product_id ORDER BY id";
            $var_stmt = $conn->prepare($var_query);
            $var_stmt->bindParam(':product_id', $product['id'], PDO::PARAM_INT);
            $var_stmt->execute();
            $variants = $var_stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted['variants'] = [];
            foreach ($variants as $variant) {
                $formatted['variants'][] = [
                    'id' => (int)$variant['id'],
                    'size' => $variant['size'],
                    'color' => $variant['color'],
                    'stock' => (int)$variant['stock'],
                    'created_at' => $variant['created_at']
                ];
            }

            // Get bulk prices
            $bulk_query = "SELECT id, product_id, min_quantity, bulk_price, created_at FROM product_bulk_prices WHERE product_id = :product_id ORDER BY min_quantity";
            $bulk_stmt = $conn->prepare($bulk_query);
            $bulk_stmt->bindParam(':product_id', $product['id'], PDO::PARAM_INT);
            $bulk_stmt->execute();
            $bulk_prices = $bulk_stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted['bulk_prices'] = [];
            foreach ($bulk_prices as $bp) {
                $formatted['bulk_prices'][] = [
                    'id' => (int)$bp['id'],
                    'min_quantity' => (int)$bp['min_quantity'],
                    'bulk_price' => $bp['bulk_price'],
                    'created_at' => $bp['created_at']
                ];
            }

        } catch (Exception $e) {
            error_log("Error loading product details: " . $e->getMessage());
            $formatted['images'] = [];
            $formatted['variants'] = [];
            $formatted['bulk_prices'] = [];
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

// GET products by category
function getProductsByCategory($conn, $category_id) {
    try {
        // Validate category exists
        $cat_check = "SELECT id FROM categories WHERE id = :category_id";
        $cat_stmt = $conn->prepare($cat_check);
        $cat_stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $cat_stmt->execute();

        if ($cat_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Category not found']);
            return;
        }

        $query = "SELECT p.id, p.name, p.description, p.price, p.is_active, p.created_at,
                  c.id as category_id, c.name as category_name, c.parent_id, c.is_active as category_is_active
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.category_id = :category_id
                  ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
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
            'count' => count($formattedProducts),
            'category_id' => (int)$category_id
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

        // Debug: Log the input received
        error_log("DEBUG: Input received: " . json_encode($input));

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

        error_log("DEBUG: Creating product - name: $name, price: $price, category_id: $category_id");

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
            error_log("DEBUG: Product created with ID: $product_id");

            // Handle image upload if provided
            $image_url = null;
            
            // Check for 'image' or 'primary_image' field
            $image_field = null;
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                $image_field = 'primary_image';
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_field = 'image';
            }
            
            if ($image_field) {
                error_log("DEBUG: Uploading image for product $product_id");
                $image_url = uploadProductImageFile($product_id, $_FILES[$image_field], true, $conn);
                error_log("DEBUG: Image upload result: " . ($image_url ? $image_url : 'null'));
            }

            // Handle variants if provided
            $variants_created = 0;
            $variants = isset($input['variants']) ? $input['variants'] : null;
            
            // If variants is a string (from form-data), parse it as JSON
            if (is_string($variants)) {
                error_log("DEBUG: Parsing variants JSON string");
                $variants = json_decode($variants, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("DEBUG: JSON parse error for variants: " . json_last_error_msg());
                }
            }
            
            if (isset($variants) && is_array($variants)) {
                error_log("DEBUG: Processing " . count($variants) . " variants");
                foreach ($variants as $variant) {
                    $v_size = $variant['size'] ?? null;
                    $v_color = $variant['color'] ?? null;
                    $v_stock = (int)($variant['stock'] ?? 0);
                    
                    error_log("DEBUG: Adding variant - size: $v_size, color: $v_color, stock: $v_stock");
                    
                    $var_query = "INSERT INTO product_variants (product_id, size, color, stock) 
                                  VALUES (:product_id, :size, :color, :stock)";
                    $var_stmt = $conn->prepare($var_query);
                    $var_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                    $var_stmt->bindParam(':size', $v_size, PDO::PARAM_STR);
                    $var_stmt->bindParam(':color', $v_color, PDO::PARAM_STR);
                    $var_stmt->bindParam(':stock', $v_stock, PDO::PARAM_INT);
                    
                    if ($var_stmt->execute()) {
                        $variants_created++;
                    } else {
                        error_log("DEBUG: Variant insert failed: " . json_encode($var_stmt->errorInfo()));
                    }
                }
            }

            // Handle bulk prices if provided
            $bulk_prices_created = 0;
            $bulk_prices = isset($input['bulk_prices']) ? $input['bulk_prices'] : null;
            
            // If bulk_prices is a string (from form-data), parse it as JSON
            if (is_string($bulk_prices)) {
                error_log("DEBUG: Parsing bulk_prices JSON string");
                $bulk_prices = json_decode($bulk_prices, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("DEBUG: JSON parse error for bulk_prices: " . json_last_error_msg());
                }
            }
            
            if (isset($bulk_prices) && is_array($bulk_prices)) {
                error_log("DEBUG: Processing " . count($bulk_prices) . " bulk prices");
                foreach ($bulk_prices as $bulk) {
                    $b_min_qty = (int)($bulk['min_quantity'] ?? 0);
                    $b_price = $bulk['bulk_price'] ?? null;
                    
                    if ($b_min_qty && $b_price) {
                        error_log("DEBUG: Adding bulk price - min_qty: $b_min_qty, price: $b_price");
                        
                        $bulk_query = "INSERT INTO product_bulk_prices (product_id, min_quantity, bulk_price) 
                                       VALUES (:product_id, :min_quantity, :bulk_price)";
                        $bulk_stmt = $conn->prepare($bulk_query);
                        $bulk_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                        $bulk_stmt->bindParam(':min_quantity', $b_min_qty, PDO::PARAM_INT);
                        $bulk_stmt->bindParam(':bulk_price', $b_price, PDO::PARAM_STR);
                        
                        if ($bulk_stmt->execute()) {
                            $bulk_prices_created++;
                        } else {
                            error_log("DEBUG: Bulk price insert failed: " . json_encode($bulk_stmt->errorInfo()));
                        }
                    }
                }
            }

            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => [
                    'id' => $product_id,
                    'image_url' => $image_url,
                    'variants_created' => $variants_created,
                    'bulk_prices_created' => $bulk_prices_created
                ]
            ]);
        } else {
            error_log("DEBUG: Product insert failed: " . json_encode($stmt->errorInfo()));
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create product: ' . json_encode($stmt->errorInfo())]);
        }
    } catch (Exception $e) {
        error_log("DEBUG: Exception in createProduct: " . $e->getMessage());
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
            if (!mkdir($images_folder, 0777, true)) {
                throw new Exception("Failed to create images folder");
            }
        }
        
        if (!is_writable($images_folder)) {
            if (!chmod($images_folder, 0777)) {
                throw new Exception("Images folder is not writable");
            }
        }

        $file_name = $file['name'] ?? '';
        $file_tmp = $file['tmp_name'] ?? '';
        $file_error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $file_size = $file['size'] ?? 0;

        // Validate file upload
        if ($file_error !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error {$file_error}: {$file_name}");
        }

        if (!file_exists($file_tmp)) {
            throw new Exception("Invalid temp file: {$file_tmp}");
        }
        
        // Only check is_uploaded_file() for files from native PHP upload
        // For files from multipart parser, just check file exists
        $is_native_upload = is_uploaded_file($file_tmp);
        error_log("DEBUG: File upload check - is_uploaded_file: " . ($is_native_upload ? 'true' : 'false') . ", file_exists: true");

        // Check file size (max 5MB)
        if ($file_size > 5242880) {
            throw new Exception("File too large. Max 5MB allowed");
        }

        // Validate file type
        $file_info = getimagesize($file_tmp);
        if (!$file_info) {
            throw new Exception("Not a valid image file: {$file_name}");
        }

        // Generate unique filename
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new Exception("Invalid file extension. Allowed: jpg, jpeg, png, gif, webp");
        }

        $new_file_name = 'product_' . $product_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $images_folder . $new_file_name;

        // Move or copy uploaded file
        // For native uploads, use move_uploaded_file; for manual temp files, use rename
        if ($is_native_upload) {
            if (!move_uploaded_file($file_tmp, $file_path)) {
                throw new Exception("Failed to move uploaded file");
            }
        } else {
            // For manually created temp files (from multipart parser)
            if (!rename($file_tmp, $file_path)) {
                // If rename fails, try copy
                if (!copy($file_tmp, $file_path)) {
                    throw new Exception("Failed to copy uploaded file");
                }
                @unlink($file_tmp); // Clean up temp file
            }
        }

        // Set permissions
        chmod($file_path, 0644);

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

                // Insert image record
                $insert_query = "INSERT INTO product_images (product_id, image_url, is_primary)
                                VALUES (:product_id, :image_url, :is_primary)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':image_url', $image_url, PDO::PARAM_STR);
                $is_primary_int = $is_primary ? 1 : 0;
                $insert_stmt->bindParam(':is_primary', $is_primary_int, PDO::PARAM_INT);
                
                if ($insert_stmt->execute()) {
                    return $image_url;
                } else {
                    return $image_url; // Return URL even if DB save fails
                }
            } catch (Exception $e) {
                return $image_url; // Return URL even if DB error
            }
        }

        return $image_url;

    } catch (Exception $e) {
        // Log error but don't crash
        error_log("Image upload error: " . $e->getMessage());
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

        // Handle image upload if provided
        $new_image_url = null;
        $image_field = null;
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
            $image_field = 'primary_image';
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_field = 'image';
        }

        if ($image_field) {
            // Delete old image file before uploading new one
            $old_image_query = "SELECT image_url FROM product_images WHERE product_id = :id AND is_primary = 1";
            $old_image_stmt = $conn->prepare($old_image_query);
            $old_image_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $old_image_stmt->execute();
            $old_image = $old_image_stmt->fetch(PDO::FETCH_ASSOC);

            if ($old_image) {
                // Extract filename from URL and delete file
                $old_filename = basename($old_image['image_url']);
                $old_file_path = __DIR__ . '/images/products/' . $old_filename;
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }

            // Delete old image record
            $delete_old_query = "DELETE FROM product_images WHERE product_id = :id";
            $delete_old_stmt = $conn->prepare($delete_old_query);
            $delete_old_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $delete_old_stmt->execute();

            // Upload new image
            $new_image_url = uploadProductImageFile($id, $_FILES[$image_field], true, $conn);
        }

        // Handle variants update if provided
        $variants_updated = 0;
        $variants = isset($input['variants']) ? $input['variants'] : null;
        
        // If variants is a string (from form-data), parse it as JSON
        if (is_string($variants)) {
            $variants = json_decode($variants, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $variants = null;
            }
        }
        
        if (isset($variants) && is_array($variants)) {
            // Delete old variants for this product
            $delete_variants_query = "DELETE FROM product_variants WHERE product_id = :id";
            $delete_variants_stmt = $conn->prepare($delete_variants_query);
            $delete_variants_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $delete_variants_stmt->execute();
            
            // Insert new variants
            foreach ($variants as $variant) {
                $v_size = $variant['size'] ?? null;
                $v_color = $variant['color'] ?? null;
                $v_stock = (int)($variant['stock'] ?? 0);
                
                $var_query = "INSERT INTO product_variants (product_id, size, color, stock) 
                              VALUES (:product_id, :size, :color, :stock)";
                $var_stmt = $conn->prepare($var_query);
                $var_stmt->bindParam(':product_id', $id, PDO::PARAM_INT);
                $var_stmt->bindParam(':size', $v_size, PDO::PARAM_STR);
                $var_stmt->bindParam(':color', $v_color, PDO::PARAM_STR);
                $var_stmt->bindParam(':stock', $v_stock, PDO::PARAM_INT);
                
                if ($var_stmt->execute()) {
                    $variants_updated++;
                }
            }
        }

        // Handle bulk prices update if provided
        $bulk_prices_updated = 0;
        $bulk_prices = isset($input['bulk_prices']) ? $input['bulk_prices'] : null;
        
        // If bulk_prices is a string (from form-data), parse it as JSON
        if (is_string($bulk_prices)) {
            $bulk_prices = json_decode($bulk_prices, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $bulk_prices = null;
            }
        }
        
        if (isset($bulk_prices) && is_array($bulk_prices)) {
            // Delete old bulk prices for this product
            $delete_bulk_query = "DELETE FROM product_bulk_prices WHERE product_id = :id";
            $delete_bulk_stmt = $conn->prepare($delete_bulk_query);
            $delete_bulk_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $delete_bulk_stmt->execute();
            
            // Insert new bulk prices
            foreach ($bulk_prices as $bulk) {
                $b_min_qty = (int)($bulk['min_quantity'] ?? 0);
                $b_price = $bulk['bulk_price'] ?? null;
                
                if ($b_min_qty && $b_price) {
                    $bulk_query = "INSERT INTO product_bulk_prices (product_id, min_quantity, bulk_price) 
                                   VALUES (:product_id, :min_quantity, :bulk_price)";
                    $bulk_stmt = $conn->prepare($bulk_query);
                    $bulk_stmt->bindParam(':product_id', $id, PDO::PARAM_INT);
                    $bulk_stmt->bindParam(':min_quantity', $b_min_qty, PDO::PARAM_INT);
                    $bulk_stmt->bindParam(':bulk_price', $b_price, PDO::PARAM_STR);
                    
                    if ($bulk_stmt->execute()) {
                        $bulk_prices_updated++;
                    }
                }
            }
        }

        if (empty($update_fields) && !$new_image_url && $variants_updated === 0 && $bulk_prices_updated === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
            return;
        }

        if (!empty($update_fields)) {
            $query = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to update product']);
                return;
            }
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => [
                'id' => $id,
                'image_url' => $new_image_url,
                'variants_updated' => $variants_updated,
                'bulk_prices_updated' => $bulk_prices_updated
            ]
        ]);
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

        // Get all image files for this product and delete them
        $images_query = "SELECT image_url FROM product_images WHERE product_id = :id";
        $images_stmt = $conn->prepare($images_query);
        $images_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $images_stmt->execute();
        $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as $image) {
            $filename = basename($image['image_url']);
            $file_path = __DIR__ . '/images/products/' . $filename;
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        // Delete all related records (images, variants, bulk prices)
        $delete_images_query = "DELETE FROM product_images WHERE product_id = :id";
        $delete_images_stmt = $conn->prepare($delete_images_query);
        $delete_images_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $delete_images_stmt->execute();

        $delete_variants_query = "DELETE FROM product_variants WHERE product_id = :id";
        $delete_variants_stmt = $conn->prepare($delete_variants_query);
        $delete_variants_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $delete_variants_stmt->execute();

        $delete_bulk_query = "DELETE FROM product_bulk_prices WHERE product_id = :id";
        $delete_bulk_stmt = $conn->prepare($delete_bulk_query);
        $delete_bulk_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $delete_bulk_stmt->execute();

        // Delete the product
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

// Cleanup temp files created by multipart parser
function cleanupTempFiles() {
    if (!empty($_FILES)) {
        foreach ($_FILES as $field_name => $file_info) {
            if (is_array($file_info) && isset($file_info['tmp_name']) && !empty($file_info['tmp_name'])) {
                // Check if file starts with temp directory (safety check)
                $tmp_dir = sys_get_temp_dir();
                if (strpos($file_info['tmp_name'], $tmp_dir) === 0) {
                    if (file_exists($file_info['tmp_name'])) {
                        @unlink($file_info['tmp_name']);
                        error_log("DEBUG: Cleaned up temp file: {$file_info['tmp_name']}");
                    }
                }
            }
        }
    }
}

// Register cleanup function to run at end of script
register_shutdown_function('cleanupTempFiles');

?>
