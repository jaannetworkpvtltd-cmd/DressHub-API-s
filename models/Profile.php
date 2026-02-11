<?php

require_once __DIR__ . '/../config/Database.php';

class Profile {
    private $conn;
    private $table = 'profiles';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . "
                  (user_id, full_name, phone, avatar_url, is_active)
                  VALUES (:user_id, :full_name, :phone, :avatar_url, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        
        $user_id = $data['user_id'];
        $full_name = $data['full_name'] ?? null;
        $phone = $data['phone'] ?? null;
        $avatar_url = $data['avatar_url'] ?? null;
        $is_active = $data['is_active'] ?? 1;
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':avatar_url', $avatar_url);
        $stmt->bindParam(':is_active', $is_active);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($user_id, $data) {
        $query = "UPDATE " . $this->table . " SET 
                  full_name = :full_name,
                  phone = :phone,
                  avatar_url = :avatar_url,
                  is_active = :is_active
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        
        $full_name = $data['full_name'] ?? null;
        $phone = $data['phone'] ?? null;
        $avatar_url = $data['avatar_url'] ?? null;
        $is_active = $data['is_active'] ?? 1;
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':avatar_url', $avatar_url);
        $stmt->bindParam(':is_active', $is_active);
        
        return $stmt->execute();
    }

    public function delete($user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }
}
