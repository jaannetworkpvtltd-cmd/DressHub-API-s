<?php

require_once __DIR__ . '/../models/Card.php';

class CardController {
    private $card;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->card = new Card($db);
    }

    // GET: Get cards for the authenticated user only
    public function getCards($params = []) {
        try {
            $user_id = $params['user_id'];

            if (isset($params['id'])) {
                // Get specific card but only if it belongs to this user
                $card = $this->card->getByIdAndUserId($params['id'], $user_id);
                if (!$card) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Card not found'
                    ];
                }
                $cards = [$card];
            } else {
                // Get all cards for this user
                $cards = $this->card->getByUserId($user_id);
            }

            return [
                'status' => true,
                'code' => 200,
                'data' => $cards,
                'message' => 'Cards retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    // POST: Create a new card
    public function createCard($input) {
        try {
            // Validate required fields
            if (!isset($input['user_id']) || !isset($input['card_holder_name']) || 
                !isset($input['last4_digits']) || !isset($input['brand']) ||
                !isset($input['expiry_month']) || !isset($input['expiry_year'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Missing required fields: user_id, card_holder_name, last4_digits, brand, expiry_month, expiry_year'
                ];
            }

            // Validate card data
            if (strlen($input['last4_digits']) !== 4 || !is_numeric($input['last4_digits'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'last4_digits must be exactly 4 numeric characters'
                ];
            }

            if ($input['expiry_month'] < 1 || $input['expiry_month'] > 12) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'expiry_month must be between 1 and 12'
                ];
            }

            if ($input['expiry_year'] < date('Y')) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'expiry_year must be valid and not in the past'
                ];
            }

            $card_id = $this->card->create($input);

            if ($card_id) {
                $new_card = $this->card->getById($card_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_card,
                    'message' => 'Card created successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to create card'
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

    // PUT: Update a card (only if owned by user)
    public function updateCard($id, $input, $user_id = null) {
        try {
            // Check if card exists and belongs to the user
            $card = $user_id ? $this->card->getByIdAndUserId($id, $user_id) : $this->card->getById($id);
            if (!$card) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Card not found'
                ];
            }

            // Validate card data if provided
            if (isset($input['last4_digits'])) {
                if (strlen($input['last4_digits']) !== 4 || !is_numeric($input['last4_digits'])) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'last4_digits must be exactly 4 numeric characters'
                    ];
                }
            }

            if (isset($input['expiry_month'])) {
                if ($input['expiry_month'] < 1 || $input['expiry_month'] > 12) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'expiry_month must be between 1 and 12'
                    ];
                }
            }

            if (isset($input['expiry_year'])) {
                if ($input['expiry_year'] < date('Y')) {
                    return [
                        'status' => false,
                        'code' => 400,
                        'message' => 'expiry_year must be valid and not in the past'
                    ];
                }
            }

            if ($this->card->update($id, $input)) {
                $updated_card = $this->card->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_card,
                    'message' => 'Card updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update card'
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

    // DELETE: Delete a card (only if owned by user)
    public function deleteCard($id, $user_id = null) {
        try {
            // Check if card exists and belongs to the user
            $card = $user_id ? $this->card->getByIdAndUserId($id, $user_id) : $this->card->getById($id);
            if (!$card) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Card not found'
                ];
            }

            if ($this->card->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Card deleted successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to delete card'
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
