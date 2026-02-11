<?php

require_once __DIR__ . '/../models/Profile.php';

class ProfileController {
    private $profile;

    public function __construct() {
        $this->profile = new Profile();
    }

    public function getProfile($user_id) {
        try {
            $profile = $this->profile->getByUserId($user_id);
            if ($profile) {
                return [
                    'status' => 'success',
                    'data' => $profile
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Profile not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function createProfile($data) {
        try {
            $profile_id = $this->profile->create($data);
            if ($profile_id) {
                return [
                    'status' => 'success',
                    'message' => 'Profile created successfully',
                    'profile_id' => $profile_id
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to create profile'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function updateProfile($user_id, $data) {
        try {
            $result = $this->profile->update($user_id, $data);
            if ($result) {
                return [
                    'status' => 'success',
                    'message' => 'Profile updated successfully'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to update profile'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function uploadAvatar($user_id) {
        try {
            if (!isset($_FILES['avatar'])) {
                return [
                    'status' => 'error',
                    'message' => 'No file uploaded'
                ];
            }

            $file = $_FILES['avatar'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = '';
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_message = 'File exceeds upload_max_filesize';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = 'File exceeds MAX_FILE_SIZE';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = 'File was only partially uploaded';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message = 'No file was uploaded';
                        break;
                    default:
                        $error_message = 'Unknown upload error';
                }
                return [
                    'status' => 'error',
                    'message' => 'Upload error: ' . $error_message
                ];
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed. Got: ' . $file['type']
                ];
            }

            if ($file['size'] > $max_size) {
                return [
                    'status' => 'error',
                    'message' => 'File size exceeds 5MB limit. Size: ' . round($file['size'] / 1024 / 1024, 2) . 'MB'
                ];
            }

            // Create avatars directory if it doesn't exist
            $upload_dir = dirname(__DIR__) . '/images/avatars/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to create upload directory: ' . $upload_dir
                    ];
                }
            }

            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                return [
                    'status' => 'error',
                    'message' => 'Upload directory is not writable: ' . $upload_dir
                ];
            }

            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to move uploaded file to: ' . $filepath
                ];
            }

            // Save URL to database
            $avatar_url = 'images/avatars/' . $filename;
            $data = [
                'user_id' => $user_id,
                'avatar_url' => $avatar_url
            ];
            
            if ($this->profile->update($user_id, $data)) {
                return [
                    'status' => 'success',
                    'message' => 'Avatar uploaded successfully',
                    'avatar_url' => $avatar_url
                ];
            } else {
                // Delete uploaded file if database update fails
                unlink($filepath);
                return [
                    'status' => 'error',
                    'message' => 'Failed to save avatar URL to database'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Upload error: ' . $e->getMessage()
            ];
        }
    }
}
