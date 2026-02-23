<?php

class Order {
    private $conn;
    private $table = 'orders';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all orders
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching orders: " . $e->getMessage());
        }
    }

    // Get orders by user_id
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching user orders: " . $e->getMessage());
        }
    }

    // Get single order
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching order: " . $e->getMessage());
        }
    }

    // Create order
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (user_id, status, total_amount, note)
                      VALUES 
                      (:user_id, :status, :total_amount, :note)";
            
            $stmt = $this->conn->prepare($query);
            
            $user_id = isset($data['user_id']) ? $data['user_id'] : null;
            $status = isset($data['status']) ? $data['status'] : 'pending';
            $total_amount = isset($data['total_amount']) ? $data['total_amount'] : 0;
            $note = isset($data['note']) ? $data['note'] : null;
            
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':note', $note);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Error creating order: " . $e->getMessage());
        }
    }

    // Update order
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (isset($data['status'])) {
                $query .= "status = :status, ";
                $params['status'] = $data['status'];
            }
            if (isset($data['total_amount'])) {
                $query .= "total_amount = :total_amount, ";
                $params['total_amount'] = $data['total_amount'];
            }
            if (isset($data['note'])) {
                $query .= "note = :note, ";
                $params['note'] = $data['note'];
            }
            
            $query = rtrim($query, ", ");
            $query .= " WHERE id = :id";
            $params['id'] = $id;
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindParam(':' . $key, $params[$key]);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating order: " . $e->getMessage());
        }
    }

    // Delete order
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting order: " . $e->getMessage());
        }
    }
}

class OrderItem {
    private $conn;
    private $table = 'order_items';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get order items by order_id
    public function getByOrderId($order_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE order_id = :order_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching order items: " . $e->getMessage());
        }
    }

    // Get single order item
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching order item: " . $e->getMessage());
        }
    }

    // Create order item
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (order_id, product_variant_id, quantity, unit_price, total_price)
                      VALUES 
                      (:order_id, :product_variant_id, :quantity, :unit_price, :total_price)";
            
            $stmt = $this->conn->prepare($query);
            
            $total_price = $data['quantity'] * $data['unit_price'];
            
            $stmt->bindParam(':order_id', $data['order_id']);
            $stmt->bindParam(':product_variant_id', $data['product_variant_id']);
            $stmt->bindParam(':quantity', $data['quantity']);
            $stmt->bindParam(':unit_price', $data['unit_price']);
            $stmt->bindParam(':total_price', $total_price);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Error creating order item: " . $e->getMessage());
        }
    }

    // Update order item
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (isset($data['quantity'])) {
                $query .= "quantity = :quantity, ";
                $params['quantity'] = $data['quantity'];
            }
            if (isset($data['unit_price'])) {
                $query .= "unit_price = :unit_price, ";
                $params['unit_price'] = $data['unit_price'];
            }
            
            // Recalculate total_price if quantity or price changed
            if (isset($data['quantity']) || isset($data['unit_price'])) {
                $item = $this->getById($id);
                $quantity = isset($data['quantity']) ? $data['quantity'] : $item['quantity'];
                $unit_price = isset($data['unit_price']) ? $data['unit_price'] : $item['unit_price'];
                $total_price = $quantity * $unit_price;
                $query .= "total_price = :total_price, ";
                $params['total_price'] = $total_price;
            }
            
            $query = rtrim($query, ", ");
            $query .= " WHERE id = :id";
            $params['id'] = $id;
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindParam(':' . $key, $params[$key]);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error updating order item: " . $e->getMessage());
        }
    }

    // Delete order item
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting order item: " . $e->getMessage());
        }
    }

    // Get order total
    public function getOrderTotal($order_id) {
        try {
            $query = "SELECT SUM(total_price) as total FROM " . $this->table . " WHERE order_id = :order_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            throw new Exception("Error calculating order total: " . $e->getMessage());
        }
    }
}
?>
