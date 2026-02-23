<?php

class Cart {
    private $conn;
    private $table = 'carts';

    public $id;
    public $user_id;
    public $cart_token;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all carts
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching carts: " . $e->getMessage());
        }
    }

    // Get carts by user_id
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching user carts: " . $e->getMessage());
        }
    }

    // Get single cart
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching cart: " . $e->getMessage());
        }
    }

    // Get cart by token
    public function getByToken($token) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE cart_token = :token";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching cart by token: " . $e->getMessage());
        }
    }

    // Create cart
    public function create($data) {
        try {
            // Generate cart token if not provided
            $cart_token = isset($data['cart_token']) ? $data['cart_token'] : bin2hex(random_bytes(60));

            $query = "INSERT INTO " . $this->table . " 
                      (user_id, cart_token)
                      VALUES 
                      (:user_id, :cart_token)";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind values
            $user_id = isset($data['user_id']) ? $data['user_id'] : null;
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':cart_token', $cart_token);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Error creating cart: " . $e->getMessage());
        }
    }

    // Update cart
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (isset($data['user_id'])) {
                $query .= "user_id = :user_id, ";
                $params['user_id'] = $data['user_id'];
            }
            if (isset($data['cart_token'])) {
                $query .= "cart_token = :cart_token, ";
                $params['cart_token'] = $data['cart_token'];
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
            throw new Exception("Error updating cart: " . $e->getMessage());
        }
    }

    // Delete cart
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting cart: " . $e->getMessage());
        }
    }
}
?>
