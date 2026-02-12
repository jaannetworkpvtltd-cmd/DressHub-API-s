<?php

class Card {
    private $conn;
    private $table = 'card_details';

    public $id;
    public $user_id;
    public $card_holder_name;
    public $last4_digits;
    public $brand;
    public $expiry_month;
    public $expiry_year;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all cards
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching cards: " . $e->getMessage());
        }
    }

    // Get cards by user_id
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching user cards: " . $e->getMessage());
        }
    }

    // Get single card
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching card: " . $e->getMessage());
        }
    }

    // Create card
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (user_id, card_holder_name, last4_digits, brand, expiry_month, expiry_year)
                      VALUES 
                      (:user_id, :card_holder_name, :last4_digits, :brand, :expiry_month, :expiry_year)";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind values
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':card_holder_name', $data['card_holder_name']);
            $stmt->bindParam(':last4_digits', $data['last4_digits']);
            $stmt->bindParam(':brand', $data['brand']);
            $stmt->bindParam(':expiry_month', $data['expiry_month']);
            $stmt->bindParam(':expiry_year', $data['expiry_year']);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Error creating card: " . $e->getMessage());
        }
    }

    // Update card
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (isset($data['card_holder_name'])) {
                $query .= "card_holder_name = :card_holder_name, ";
                $params['card_holder_name'] = $data['card_holder_name'];
            }
            if (isset($data['last4_digits'])) {
                $query .= "last4_digits = :last4_digits, ";
                $params['last4_digits'] = $data['last4_digits'];
            }
            if (isset($data['brand'])) {
                $query .= "brand = :brand, ";
                $params['brand'] = $data['brand'];
            }
            if (isset($data['expiry_month'])) {
                $query .= "expiry_month = :expiry_month, ";
                $params['expiry_month'] = $data['expiry_month'];
            }
            if (isset($data['expiry_year'])) {
                $query .= "expiry_year = :expiry_year, ";
                $params['expiry_year'] = $data['expiry_year'];
            }
            
            // Remove trailing comma
            $query = rtrim($query, ", ");
            $query .= " WHERE id = :id";
            $params['id'] = $id;
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindParam(':' . $key, $params[$key]);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating card: " . $e->getMessage());
        }
    }

    // Delete card
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting card: " . $e->getMessage());
        }
    }
}
?>
