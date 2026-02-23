<?php

require_once __DIR__ . '/../models/Card.php';

class CardController {
    private $card;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->card = new Card($db);
    }

    // GET: Get all cards or cards by user_id
    public function getCards($params = []) {
        try {
            if (isset($params['user_id'])) {
                $cards = $this->card->getByUserId($params['user_id']);
            } else if (isset($params['id'])) {
                $card = $this->card->getById($params['id']);
                if (!$card) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Card not found'
                    ];
                }
                $cards = [$card];
            } else {
                $cards = $this->card->getAll();
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

    // PUT: Update a card
    public function updateCard($id, $input) {
        try {
            // Check if card exists
            $card = $this->card->getById($id);
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

    // DELETE: Delete a card
    public function deleteCard($id) {
        try {
            // Check if card exists
            $card = $this->card->getById($id);
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
