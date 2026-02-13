<?php

class UserRole {
    private $table = 'user_roles';
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFirstRoleByUserId($user_id) {
        $query = "SELECT role FROM " . $this->table . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['role'] : 'customer';
    }

    public function assignRole($user_id, $role) {
        $query = "INSERT IGNORE INTO " . $this->table . " (user_id, role) VALUES (:user_id, :role)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    public function removeRole($user_id, $role) {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id AND role = :role";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    public function updateRole($id, $role) {
        $query = "UPDATE " . $this->table . " SET role = :role WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
