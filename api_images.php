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

// Image folder path
$images_folder = __DIR__ . '/images/products/';

// Get the request method
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', $request_uri);
$image_id = isset($segments[count($segments) - 1]) && is_numeric($segments[count($segments) - 1]) ? $segments[count($segments) - 1] : null;

// Get input data
$input = json_decode(file_get_contents("php://input"), true);

// Route handling
switch ($request_method) {
    case 'GET':
        if ($image_id) {
            getImageById($conn, $image_id);
        } else {
            getAllImages($conn);
        }
        break;

    case 'POST':
        uploadProductImage($conn, $images_folder);
        break;

    case 'PUT':
        if ($image_id) {
            updateImage($conn, $image_id, $input);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Image ID is required for update']);
        }
        break;

    case 'DELETE':
        if ($image_id) {
            deleteImage($conn, $image_id, $images_folder);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Image ID is required for delete']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        break;
}

function formatImage($image) {
    return [
        'id' => (int)$image['id'],
        'product_id' => (int)$image['product_id'],
        'image_url' => $image['image_url'],
        'is_primary' => (int)$image['is_primary']
    ];
}

// GET all images
function getAllImages($conn) {
    try {
        $query = "SELECT id, product_id, image_url, is_primary
                  FROM product_images
                  ORDER BY product_id ASC, is_primary DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedImages = [];
        foreach ($images as $image) {
            $formattedImages[] = formatImage($image);
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'All images retrieved successfully',
            'data' => $formattedImages,
            'count' => count($formattedImages)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving images: ' . $e->getMessage()]);
    }
}

// GET image by ID
function getImageById($conn, $id) {
    try {
        $query = "SELECT id, product_id, image_url, is_primary
                  FROM product_images
                  WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($image) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Image retrieved successfully',
                'data' => formatImage($image)
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Image not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error retrieving image: ' . $e->getMessage()]);
    }
}

// UPLOAD product image
function uploadProductImage($conn, $images_folder) {
    try {
        // Check if files are uploaded
        if (!isset($_FILES['image'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Image file is required']);
            return;
        }

        // Check product_id
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Product ID is required']);
            return;
        }

        $product_id = $_POST['product_id'];
        $is_primary = isset($_POST['is_primary']) ? (int)$_POST['is_primary'] : 0;

        // Verify product exists
        $check_query = "SELECT id FROM products WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $check_stmt->execute();

        if (!$check_stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            return;
        }

        $file = $_FILES['image'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_error = $file['error'];
        $file_size = $file['size'];

        // Validate file
        if ($file_error !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'File upload error']);
            return;
        }

        // Check file size (max 5MB)
        if ($file_size > 5242880) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB limit']);
            return;
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = getimagesize($file_tmp);

        if (!$file_info) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid image file']);
            return;
        }

        // Generate unique filename
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = 'product_' . $product_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $images_folder . $new_file_name;

        // Move uploaded file
        if (!move_uploaded_file($file_tmp, $file_path)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save image file']);
            return;
        }

        // Generate URL
        $image_url = 'http://' . $_SERVER['HTTP_HOST'] . '/DressHub%20APIs/images/products/' . $new_file_name;

        // If this is primary, set others to non-primary
        if ($is_primary) {
            $update_query = "UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $update_stmt->execute();
        }

        // Insert into database
        $query = "INSERT INTO product_images (product_id, image_url, is_primary, created_at)
                  VALUES (:product_id, :image_url, :is_primary, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $image_url, PDO::PARAM_STR);
        $stmt->bindParam(':is_primary', $is_primary, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $image_id = $conn->lastInsertId();
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Image uploaded successfully',
                'data' => [
                    'id' => $image_id,
                    'product_id' => (int)$product_id,
                    'image_url' => $image_url,
                    'is_primary' => $is_primary
                ]
            ]);
        } else {
            http_response_code(500);
            unlink($file_path);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save image to database']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error uploading image: ' . $e->getMessage()]);
    }
}

// UPDATE image
function updateImage($conn, $id, $input) {
    try {
        // Check if image exists
        $check_query = "SELECT id, product_id FROM product_images WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();
        $image = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Image not found']);
            return;
        }

        $product_id = $image['product_id'];

        // Build update query dynamically
        $allowed_fields = ['is_primary'];
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

        // If setting as primary, unset others
        if (isset($input['is_primary']) && $input['is_primary']) {
            $reset_query = "UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id AND id != :id";
            $reset_stmt = $conn->prepare($reset_query);
            $reset_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $reset_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $reset_stmt->execute();
        }

        $query = "UPDATE product_images SET " . implode(', ', $update_fields) . " WHERE id = :id";
        $stmt = $conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Image updated successfully',
                'data' => ['id' => $id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update image']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error updating image: ' . $e->getMessage()]);
    }
}

// DELETE image
function deleteImage($conn, $id, $images_folder) {
    try {
        // Get image details
        $check_query = "SELECT id, image_url FROM product_images WHERE id = :id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();
        $image = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Image not found']);
            return;
        }

        // Extract filename from URL
        $url_parts = parse_url($image['image_url']);
        $path_parts = explode('/', $url_parts['path']);
        $file_name = end($path_parts);
        $file_path = $images_folder . $file_name;

        // Delete from database
        $query = "DELETE FROM product_images WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Delete file from folder
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Image deleted successfully',
                'data' => ['id' => $id]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete image']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error deleting image: ' . $e->getMessage()]);
    }
}

?>
