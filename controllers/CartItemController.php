<?php

require_once __DIR__ . '/../models/CartItem.php';

class CartItemController {
    private $cartItem;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->cartItem = new CartItem($db);
    }

    // GET: Get all cart items or items by cart_id
    public function getCartItems($params = []) {
        try {
            if (isset($params['cart_id'])) {
                $items = $this->cartItem->getByCartId($params['cart_id']);
                $total = $this->cartItem->getCartTotal($params['cart_id']);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $items,
                    'total' => $total,
                    'message' => 'Cart items retrieved successfully'
                ];
            } else if (isset($params['id'])) {
                $item = $this->cartItem->getById($params['id']);
                if (!$item) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Cart item not found'
                    ];
                }
                $items = [$item];
            } else {
                $items = $this->cartItem->getAll();
            }

            return [
                'status' => true,
                'code' => 200,
                'data' => $items,
                'message' => 'Cart items retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    // POST: Create a new cart item
    public function addCartItem($input) {
        try {
            // Validate required fields
            if (!isset($input['cart_id']) || !isset($input['product_variant_id']) || 
                !isset($input['quantity']) || !isset($input['applied_price'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Missing required fields: cart_id, product_variant_id, quantity, applied_price'
                ];
            }

            // Validate numeric fields
            if (!is_numeric($input['cart_id']) || !is_numeric($input['product_variant_id'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'cart_id and product_variant_id must be numeric'
                ];
            }

            if (!is_numeric($input['quantity']) || $input['quantity'] <= 0) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'quantity must be a positive number'
                ];
            }

            if (!is_numeric($input['applied_price']) || $input['applied_price'] < 0) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'applied_price must be a non-negative number'
                ];
            }

            // Check if item already exists in cart
            $existing = $this->cartItem->checkExists($input['cart_id'], $input['product_variant_id']);
            if ($existing) {
                // Update quantity instead
                $new_quantity = $existing['quantity'] + $input['quantity'];
                $this->cartItem->update($existing['id'], ['quantity' => $new_quantity]);
                $updated_item = $this->cartItem->getById($existing['id']);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_item,
                    'message' => 'Cart item quantity updated'
                ];
            }

            $item_id = $this->cartItem->create($input);

            if ($item_id) {
                $new_item = $this->cartItem->getById($item_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_item,
                    'message' => 'Cart item added successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to add cart item'
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

    // PUT: Update a cart item
    public function updateCartItem($id, $input) {
        try {
            // Check if item exists
            $item = $this->cartItem->getById($id);
            if (!$item) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Cart item not found'
                ];
            }

            // Validate quantity if provided
            if (isset($input['quantity'])) {
                if (!is_numeric($input['quantity']) || $input['quantity'] <= 0) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'quantity must be a positive number'
                    ];
                }
            }

            // Validate price if provided
            if (isset($input['applied_price'])) {
                if (!is_numeric($input['applied_price']) || $input['applied_price'] < 0) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'applied_price must be a non-negative number'
                    ];
                }
            }

            if ($this->cartItem->update($id, $input)) {
                $updated_item = $this->cartItem->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_item,
                    'message' => 'Cart item updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update cart item'
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

    // DELETE: Delete a cart item
    public function removeCartItem($id) {
        try {
            // Check if item exists
            $item = $this->cartItem->getById($id);
            if (!$item) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Cart item not found'
                ];
            }

            if ($this->cartItem->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Cart item removed successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to remove cart item'
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

    // DELETE: Clear entire cart
    public function clearCart($cart_id) {
        try {
            if ($this->cartItem->deleteByCartId($cart_id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Cart cleared successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to clear cart'
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

    // GET: Get cart total
    public function getCartTotal($cart_id) {
        try {
            $total = $this->cartItem->getCartTotal($cart_id);
            return [
                'status' => true,
                'code' => 200,
                'data' => ['total' => $total],
                'message' => 'Cart total calculated'
            ];
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
