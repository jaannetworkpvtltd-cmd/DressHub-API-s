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

// Get the request method and URI
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query_string = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

// Parse URI segments
$segments = explode('/', trim($request_uri, '/'));
$endpoint = end($segments);

// Parse query parameters
parse_str($query_string, $query_params);

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Route handling based on endpoint
if ($request_method === 'GET') {
    // Check if it's a numeric ID
    if (is_numeric($endpoint)) {
        $id = $endpoint;
        // Determine if it's a product or category based on context
        // Try product first
        getProductById($conn, $id);
    } elseif ($endpoint === 'api_advanced.php' || strpos($request_uri, 'api_advanced.php') !== false) {
        // Handle root endpoint - list available endpoints
        if (isset($query_params['type'])) {
            if ($query_params['type'] === 'products') {
                getAllProducts($conn);
            } elseif ($query_params['type'] === 'categories') {
                getAllCategories($conn);
            } elseif ($query_params['type'] === 'products_by_category' && isset($query_params['category_id'])) {
                getProductsByCategory($conn, $query_params['category_id']);
            } elseif ($query_params['type'] === 'product' && isset($query_params['id'])) {
                getProductById($conn, $query_params['id']);
            } elseif ($query_params['type'] === 'category' && isset($query_params['id'])) {
                getCategoryById($conn, $query_params['id']);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid query parameters']);
            }
        } else {
            // Default: show all products
            getAllProducts($conn);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}

function formatProductWithCategory($product) {
    $formatted = [
        'id' => (int)$product['id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => $product['price'],
        'is_active' => (int)$product['is_active'],
        'created_at' => $product['created_at'],
        'url' => 'http://' . $_SERVER['HTTP_HOST'] . '/DressHub%20APIs/api_advanced.php?type=product&id=' . $product['id'],
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

function formatCategory($category) {
    return [
        'id' => (int)$category['id'],
        'name' => $category['name'],
        'parent_id' => $category['parent_id'] ? (int)$category['parent_id'] : null,
        'is_active' => (int)$category['is_active']
    ];
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
            'message' => 'All products retrieved successfully',
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

// GET all categories
function getAllCategories($conn) {
    try {
        $query = "SELECT id, name, parent_id, is_active
                  FROM categories
                  ORDER BY id ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedCategories = [];
        foreach ($categories as $category) {
            $formattedCategories[] = formatCategory($category);
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'All categories retrieved successfully',
            'data' => $formattedCategories,
            'count' => count($formattedCategories)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving categories: ' . $e->getMessage()]);
    }
}

// GET category by ID
function getCategoryById($conn, $id) {
    try {
        $query = "SELECT id, name, parent_id, is_active
                  FROM categories
                  WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($category) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Category retrieved successfully',
                'data' => formatCategory($category)
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Category not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving category: ' . $e->getMessage()]);
    }
}

// GET products by category ID
function getProductsByCategory($conn, $category_id) {
    try {
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

        if (!empty($products)) {
            $formattedProducts = [];
            foreach ($products as $product) {
                $formattedProducts[] = formatProductWithCategory($product, $conn);
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Products retrieved successfully for category',
                'data' => $formattedProducts,
                'count' => count($formattedProducts)
            ]);
        } else {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'No products found for this category',
                'data' => [],
                'count' => 0
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving products: ' . $e->getMessage()]);
    }
}

?>
