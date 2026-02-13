<?php

class Address {
    private $conn;
    private $table = 'addresses';

    public $id;
    public $user_id;
    public $address_line1;
    public $address_line2;
    public $city;
    public $postal_code;
    public $country;
    public $is_default;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all addresses
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY is_default DESC, id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching addresses: " . $e->getMessage());
        }
    }

    // Get addresses by user_id
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY is_default DESC, id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching user addresses: " . $e->getMessage());
        }
    }

    // Get single address
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching address: " . $e->getMessage());
        }
    }

    // Create address
    public function create($data) {
        try {
            // If is_default is true, unset other addresses as default for this user
            if (isset($data['is_default']) && $data['is_default'] == 1) {
                $query = "UPDATE " . $this->table . " SET is_default = 0 WHERE user_id = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $data['user_id']);
                $stmt->execute();
            }

            $query = "INSERT INTO " . $this->table . " 
                      (user_id, address_line1, address_line2, city, postal_code, country, is_default)
                      VALUES 
                      (:user_id, :address_line1, :address_line2, :city, :postal_code, :country, :is_default)";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind values
            $address_line2 = isset($data['address_line2']) ? $data['address_line2'] : null;
            $city = isset($data['city']) ? $data['city'] : null;
            $postal_code = isset($data['postal_code']) ? $data['postal_code'] : null;
            $country = isset($data['country']) ? $data['country'] : null;
            $is_default = isset($data['is_default']) ? (int)$data['is_default'] : 0;
            
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':address_line1', $data['address_line1']);
            $stmt->bindParam(':address_line2', $address_line2);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':postal_code', $postal_code);
            $stmt->bindParam(':country', $country);
            $stmt->bindParam(':is_default', $is_default);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Error creating address: " . $e->getMessage());
        }
    }

    // Update address
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (isset($data['address_line1'])) {
                $query .= "address_line1 = :address_line1, ";
                $params['address_line1'] = $data['address_line1'];
            }
            if (isset($data['address_line2'])) {
                $query .= "address_line2 = :address_line2, ";
                $params['address_line2'] = $data['address_line2'];
            }
            if (isset($data['city'])) {
                $query .= "city = :city, ";
                $params['city'] = $data['city'];
            }
            if (isset($data['postal_code'])) {
                $query .= "postal_code = :postal_code, ";
                $params['postal_code'] = $data['postal_code'];
            }
            if (isset($data['country'])) {
                $query .= "country = :country, ";
                $params['country'] = $data['country'];
            }
            if (isset($data['is_default'])) {
                // If setting this as default, unset others for this user
                if ($data['is_default'] == 1) {
                    $address = $this->getById($id);
                    if ($address) {
                        $reset_query = "UPDATE " . $this->table . " SET is_default = 0 WHERE user_id = :user_id AND id != :id";
                        $reset_stmt = $this->conn->prepare($reset_query);
                        $reset_stmt->bindParam(':user_id', $address['user_id']);
                        $reset_stmt->bindParam(':id', $id);
                        $reset_stmt->execute();
                    }
                }
                $query .= "is_default = :is_default, ";
                $params['is_default'] = (int)$data['is_default'];
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
            throw new Exception("Error updating address: " . $e->getMessage());
        }
    }

    // Delete address
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting address: " . $e->getMessage());
        }
    }
}
?>
