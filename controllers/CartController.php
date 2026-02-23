<?php

require_once __DIR__ . '/../models/Cart.php';

class CartController {
    private $cart;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->cart = new Cart($db);
    }

    // GET: Get all carts or carts by user_id or specific cart
    // $jwt_user_id enforces that users only access their own carts
    public function getCarts($params = [], $jwt_user_id = null) {
        try {
            // If JWT user id provided, restrict results to that user
            if ($jwt_user_id !== null) {
                if (isset($params['id'])) {
                    $cart = $this->cart->getByIdAndUserId($params['id'], $jwt_user_id);
                    if (!$cart) {
                        return [
                            'status' => false,
                            'code' => 404,
                            'message' => 'Cart not found'
                        ];
                    }
                    $carts = [$cart];
                } else if (isset($params['token'])) {
                    $cart = $this->cart->getByTokenAndUserId($params['token'], $jwt_user_id);
                    if (!$cart) {
                        return [
                            'status' => false,
                            'code' => 404,
                            'message' => 'Cart not found'
                        ];
                    }
                    $carts = [$cart];
                } else {
                    $carts = $this->cart->getByUserId($jwt_user_id);
                }
            } else {
                // No JWT user provided — fall back to previous behavior
                if (isset($params['user_id'])) {
                    $carts = $this->cart->getByUserId($params['user_id']);
                } else if (isset($params['id'])) {
                    $cart = $this->cart->getById($params['id']);
                    if (!$cart) {
                        return [
                            'status' => false,
                            'code' => 404,
                            'message' => 'Cart not found'
                        ];
                    }
                    $carts = [$cart];
                } else if (isset($params['token'])) {
                    $cart = $this->cart->getByToken($params['token']);
                    if (!$cart) {
                        return [
                            'status' => false,
                            'code' => 404,
                            'message' => 'Cart not found'
                        ];
                    }
                    $carts = [$cart];
                } else {
                    $carts = $this->cart->getAll();
                }
            }

            return [
                'status' => true,
                'code' => 200,
                'data' => $carts,
                'message' => 'Carts retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    // POST: Create a new cart
    // $jwt_user_id will be forced as owner of the cart
    public function createCart($input, $jwt_user_id = null) {
        try {
            // Force user_id to JWT user when available
            if ($jwt_user_id !== null) {
                $input['user_id'] = $jwt_user_id;
            } else if (isset($input['user_id']) && !is_numeric($input['user_id'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'user_id must be numeric'
                ];
            }

            // Check if cart_token already exists
            if (isset($input['cart_token'])) {
                $existing = $this->cart->getByToken($input['cart_token']);
                if ($existing) {
                    return [
                        'status' => false,
                        'code' => 409,
                        'message' => 'Cart token already exists'
                    ];
                }
            }

            $cart_id = $this->cart->create($input);

            if ($cart_id) {
                $new_cart = $this->cart->getById($cart_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_cart,
                    'message' => 'Cart created successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to create cart'
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

    // PUT: Update a cart
    public function updateCart($id, $input, $jwt_user_id = null) {
        try {
            // Check if cart exists and belongs to JWT user when provided
            if ($jwt_user_id !== null) {
                $cart = $this->cart->getByIdAndUserId($id, $jwt_user_id);
            } else {
                $cart = $this->cart->getById($id);
            }
            if (!$cart) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Cart not found'
                ];
            }

            // Validate user_id if provided
            if (isset($input['user_id']) && !is_numeric($input['user_id'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'user_id must be numeric'
                ];
            }

            // Check if new cart_token already exists
            if (isset($input['cart_token']) && $input['cart_token'] !== $cart['cart_token']) {
                $existing = $this->cart->getByToken($input['cart_token']);
                if ($existing) {
                    return [
                        'status' => false,
                        'code' => 409,
                        'message' => 'Cart token already exists'
                    ];
                }
            }

            // Prevent changing ownership via update
            if (isset($input['user_id'])) {
                unset($input['user_id']);
            }

            if ($this->cart->update($id, $input)) {
                $updated_cart = $this->cart->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_cart,
                    'message' => 'Cart updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update cart'
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

    // DELETE: Delete a cart
    public function deleteCart($id, $jwt_user_id = null) {
        try {
            // Check if cart exists and belongs to JWT user when provided
            if ($jwt_user_id !== null) {
                $cart = $this->cart->getByIdAndUserId($id, $jwt_user_id);
            } else {
                $cart = $this->cart->getById($id);
            }
            if (!$cart) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Cart not found'
                ];
            }

            if ($this->cart->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Cart deleted successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to delete cart'
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
