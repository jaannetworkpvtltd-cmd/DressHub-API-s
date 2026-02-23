<?php

class Payment {
    private $table = 'payments';
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

    public function getByOrderId($order_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE order_id = :order_id ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByStatus($status) {
        $query = "SELECT * FROM " . $this->table . " WHERE payment_status = :status ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (order_id, payment_method, payment_status, amount, paid_at) 
                  VALUES 
                  (:order_id, :payment_method, :payment_status, :amount, :paid_at)";

        $stmt = $this->db->prepare($query);

        $order_id = isset($data['order_id']) ? $data['order_id'] : null;
        $payment_method = isset($data['payment_method']) ? $data['payment_method'] : null;
        $payment_status = isset($data['payment_status']) ? $data['payment_status'] : 'pending';
        $amount = isset($data['amount']) ? $data['amount'] : null;
        $paid_at = isset($data['paid_at']) ? $data['paid_at'] : null;

        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':payment_status', $payment_status);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':paid_at', $paid_at);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " SET ";
        $fields = [];

        if (isset($data['payment_method'])) {
            $fields[] = "payment_method = :payment_method";
        }
        if (isset($data['payment_status'])) {
            $fields[] = "payment_status = :payment_status";
        }
        if (isset($data['amount'])) {
            $fields[] = "amount = :amount";
        }
        if (isset($data['paid_at'])) {
            $fields[] = "paid_at = :paid_at";
        }

        if (empty($fields)) {
            return false;
        }

        $query .= implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);

        if (isset($data['payment_method'])) {
            $stmt->bindParam(':payment_method', $data['payment_method']);
        }
        if (isset($data['payment_status'])) {
            $stmt->bindParam(':payment_status', $data['payment_status']);
        }
        if (isset($data['amount'])) {
            $stmt->bindParam(':amount', $data['amount']);
        }
        if (isset($data['paid_at'])) {
            $stmt->bindParam(':paid_at', $data['paid_at']);
        }

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
