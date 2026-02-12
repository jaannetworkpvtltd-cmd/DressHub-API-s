<?php

require_once __DIR__ . '/../models/Payment.php';

class PaymentController {
    private $payment;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->payment = new Payment($db);
    }

    public function getPayments($params = []) {
        try {
            if (isset($params['id'])) {
                $payment = $this->payment->getById($params['id']);
                if (!$payment) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Payment not found'
                    ];
                }
                $payments = [$payment];
            } else if (isset($params['order_id'])) {
                $payments = $this->payment->getByOrderId($params['order_id']);
            } else if (isset($params['status'])) {
                $payments = $this->payment->getByStatus($params['status']);
            } else {
                $payments = $this->payment->getAll();
            }

            return [
                'status' => true,
                'code' => 200,
                'data' => $payments,
                'message' => 'Payments retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    public function createPayment($input) {
        try {
            // Validate required fields
            if (!isset($input['order_id'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'order_id is required'
                ];
            }

            if (!isset($input['amount']) || !is_numeric($input['amount']) || $input['amount'] <= 0) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'amount is required and must be a positive number'
                ];
            }

            // Validate payment status if provided
            if (isset($input['payment_status'])) {
                $valid_statuses = ['pending', 'paid', 'failed'];
                if (!in_array($input['payment_status'], $valid_statuses)) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid payment_status. Must be: ' . implode(', ', $valid_statuses)
                    ];
                }
            }

            // If payment_status is 'paid', set paid_at to current time
            if (isset($input['payment_status']) && $input['payment_status'] === 'paid') {
                $input['paid_at'] = date('Y-m-d H:i:s');
            }

            $payment_id = $this->payment->create($input);

            if ($payment_id) {
                $new_payment = $this->payment->getById($payment_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_payment,
                    'message' => 'Payment created successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to create payment'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    public function updatePayment($id, $input) {
        try {
            $payment = $this->payment->getById($id);
            if (!$payment) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Payment not found'
                ];
            }

            // Validate payment status if provided
            if (isset($input['payment_status'])) {
                $valid_statuses = ['pending', 'paid', 'failed'];
                if (!in_array($input['payment_status'], $valid_statuses)) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid payment_status. Must be: ' . implode(', ', $valid_statuses)
                    ];
                }

                // If payment_status is 'paid', set paid_at to current time if not already set
                if ($input['payment_status'] === 'paid' && !isset($input['paid_at'])) {
                    $input['paid_at'] = date('Y-m-d H:i:s');
                }
            }

            // Validate amount if provided
            if (isset($input['amount'])) {
                if (!is_numeric($input['amount']) || $input['amount'] <= 0) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'amount must be a positive number'
                    ];
                }
            }

            if ($this->payment->update($id, $input)) {
                $updated_payment = $this->payment->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_payment,
                    'message' => 'Payment updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update payment'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    public function deletePayment($id) {
        try {
            $payment = $this->payment->getById($id);
            if (!$payment) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Payment not found'
                ];
            }

            if ($this->payment->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => ['id' => $id],
                    'message' => 'Payment deleted successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to delete payment'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>
