<?php

class CartItem {
    private $conn;
    private $table = 'cart_items';

    public $id;
    public $cart_id;
    public $product_variant_id;
    public $quantity;
    public $applied_price;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all cart items
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching cart items: " . $e->getMessage());
        }
    }

    // Get cart items by cart_id
    public function getByCartId($cart_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE cart_id = :cart_id ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':cart_id', $cart_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching cart items: " . $e->getMessage());
        }
    }

    // Get single cart item
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching cart item: " . $e->getMessage());
        }
    }

    // Check if item already in cart
    public function checkExists($cart_id, $product_variant_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE cart_id = :cart_id AND product_variant_id = :product_variant_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':cart_id', $cart_id);
            $stmt->bindParam(':product_variant_id', $product_variant_id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error checking cart item: " . $e->getMessage());
        }
    }

    // Create cart item
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (cart_id, product_variant_id, quantity, applied_price)
                      VALUES 
                      (:cart_id, :product_variant_id, :quantity, :applied_price)";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind values
            $stmt->bindParam(':cart_id', $data['cart_id']);
            $stmt->bindParam(':product_variant_id', $data['product_variant_id']);
            $stmt->bindParam(':quantity', $data['quantity']);
            $stmt->bindParam(':applied_price', $data['applied_price']);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Error creating cart item: " . $e->getMessage());
        }
    }

    // Update cart item
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (isset($data['quantity'])) {
                $query .= "quantity = :quantity, ";
                $params['quantity'] = $data['quantity'];
            }
            if (isset($data['applied_price'])) {
                $query .= "applied_price = :applied_price, ";
                $params['applied_price'] = $data['applied_price'];
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
            throw new Exception("Error updating cart item: " . $e->getMessage());
        }
    }

    // Delete cart item
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting cart item: " . $e->getMessage());
        }
    }

    // Delete all items from cart
    public function deleteByCartId($cart_id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE cart_id = :cart_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':cart_id', $cart_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting cart items: " . $e->getMessage());
        }
    }

    // Get cart total price
    public function getCartTotal($cart_id) {
        try {
            $query = "SELECT SUM(quantity * applied_price) as total FROM " . $this->table . " WHERE cart_id = :cart_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':cart_id', $cart_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            throw new Exception("Error calculating cart total: " . $e->getMessage());
        }
    }
}
?>
