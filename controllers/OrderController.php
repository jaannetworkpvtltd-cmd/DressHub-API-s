<?php

require_once __DIR__ . '/../models/Order.php';

class OrderController {
    private $order;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->order = new Order($db);
    }

    public function getOrders($params = []) {
        try {
            if (isset($params['user_id'])) {
                $orders = $this->order->getByUserId($params['user_id']);
            } else if (isset($params['id'])) {
                $order = $this->order->getById($params['id']);
                if (!$order) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Order not found'
                    ];
                }
                $orders = [$order];
            } else {
                $orders = $this->order->getAll();
            }

            return [
                'status' => true,
                'code' => 200,
                'data' => $orders,
                'message' => 'Orders retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    public function createOrder($input) {
        try {
            if (!isset($input['total_amount']) || !is_numeric($input['total_amount'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'total_amount is required and must be numeric'
                ];
            }

            $order_id = $this->order->create($input);

            if ($order_id) {
                $new_order = $this->order->getById($order_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_order,
                    'message' => 'Order created successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to create order'
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

    public function updateOrder($id, $input) {
        try {
            $order = $this->order->getById($id);
            if (!$order) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Order not found'
                ];
            }

            if (isset($input['status'])) {
                $valid_statuses = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];
                if (!in_array($input['status'], $valid_statuses)) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid status. Must be: ' . implode(', ', $valid_statuses)
                    ];
                }
            }

            if ($this->order->update($id, $input)) {
                $updated_order = $this->order->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_order,
                    'message' => 'Order updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update order'
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

    public function deleteOrder($id) {
        try {
            $order = $this->order->getById($id);
            if (!$order) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Order not found'
                ];
            }

            if ($this->order->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Order deleted successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to delete order'
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

class OrderItemController {
    private $orderItem;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->orderItem = new OrderItem($db);
    }

    public function getOrderItems($params = []) {
        try {
            if (isset($params['order_id'])) {
                $items = $this->orderItem->getByOrderId($params['order_id']);
                $total = $this->orderItem->getOrderTotal($params['order_id']);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $items,
                    'total' => $total,
                    'message' => 'Order items retrieved successfully'
                ];
            } else if (isset($params['id'])) {
                $item = $this->orderItem->getById($params['id']);
                if (!$item) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Order item not found'
                    ];
                }
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => [$item],
                    'message' => 'Order item retrieved successfully'
                ];
            }

            return [
                'status' => false,
                'code' => 400,
                'message' => 'order_id or id parameter required'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    public function addOrderItem($input) {
        try {
            if (!isset($input['order_id']) || !isset($input['product_variant_id']) || 
                !isset($input['quantity']) || !isset($input['unit_price'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Missing required fields: order_id, product_variant_id, quantity, unit_price'
                ];
            }

            if (!is_numeric($input['order_id']) || !is_numeric($input['product_variant_id'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'order_id and product_variant_id must be numeric'
                ];
            }

            if (!is_numeric($input['quantity']) || $input['quantity'] <= 0) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'quantity must be a positive number'
                ];
            }

            if (!is_numeric($input['unit_price']) || $input['unit_price'] < 0) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'unit_price must be a non-negative number'
                ];
            }

            $item_id = $this->orderItem->create($input);

            if ($item_id) {
                $new_item = $this->orderItem->getById($item_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_item,
                    'message' => 'Order item added successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to add order item'
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

    public function updateOrderItem($id, $input) {
        try {
            $item = $this->orderItem->getById($id);
            if (!$item) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Order item not found'
                ];
            }

            if (isset($input['quantity']) && (!is_numeric($input['quantity']) || $input['quantity'] <= 0)) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'quantity must be a positive number'
                ];
            }

            if (isset($input['unit_price']) && (!is_numeric($input['unit_price']) || $input['unit_price'] < 0)) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'unit_price must be a non-negative number'
                ];
            }

            if ($this->orderItem->update($id, $input)) {
                $updated_item = $this->orderItem->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_item,
                    'message' => 'Order item updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update order item'
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

    public function removeOrderItem($id) {
        try {
            $item = $this->orderItem->getById($id);
            if (!$item) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Order item not found'
                ];
            }

            if ($this->orderItem->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Order item removed successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to remove order item'
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
