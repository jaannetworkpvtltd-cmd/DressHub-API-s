<?php
header('Content-Type: application/json');
require_once 'connect.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($request_method) {
    case 'POST':
        resetPassword($conn, $input);
        break;
    case 'GET':
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'GET method not allowed. Use POST']);
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}

// Reset password using username
function resetPassword($conn, $input) {
    try {
        // Validate required fields
        $username = isset($input['username']) ? trim($input['username']) : null;
        $new_password = isset($input['new_password']) ? trim($input['new_password']) : null;

        if (!$username || !$new_password) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'username and new_password are required'
            ]);
            return;
        }

        // Validate password strength
        if (strlen($new_password) < 6) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Password must be at least 6 characters long'
            ]);
            return;
        }

        // Check if user exists
        $check_query = "SELECT id FROM users WHERE username = :username";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $check_stmt->execute();
        $user = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Username not found'
            ]);
            return;
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        // Update password in database
        $update_query = "UPDATE users SET password_hash = :password, updated_at = NOW() WHERE username = :username";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $update_stmt->bindParam(':username', $username, PDO::PARAM_STR);

        if ($update_stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Password reset successfully',
                'data' => [
                    'username' => $username
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset password']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Alternative: Send password reset link via email (Optional enhancement)
function sendPasswordResetEmail($conn, $username, $email) {
    try {
        // Generate unique reset token
        $reset_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database
        $query = "UPDATE users SET reset_token = :token, token_expiry = :expiry WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $reset_token, PDO::PARAM_STR);
        $stmt->bindParam(':expiry', $token_expiry, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            // Send email with reset link (you would use mail() or a service like SendGrid)
            $reset_link = "http://localhost/DressHub%20APIs/reset.php?token=" . $reset_token;
            
            // Example email (implement actual email sending)
            // mail($email, "Password Reset Request", "Click here to reset: " . $reset_link);
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}
?>
