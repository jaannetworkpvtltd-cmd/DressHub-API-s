<?php

require_once __DIR__ . '/../models/Address.php';

class AddressController {
    private $address;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->address = new Address($db);
    }

    // GET: Get all addresses or addresses by user_id
    public function getAddresses($params = []) {
        try {
            if (isset($params['user_id'])) {
                $addresses = $this->address->getByUserId($params['user_id']);
            } else if (isset($params['id'])) {
                $address = $this->address->getById($params['id']);
                if (!$address) {
                    return [
                        'status' => false,
                        'code' => 404,
                        'message' => 'Address not found'
                    ];
                }
                $addresses = [$address];
            } else {
                $addresses = $this->address->getAll();
            }

            return [
                'status' => true,
                'code' => 200,
                'data' => $addresses,
                'message' => 'Addresses retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'code' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    // POST: Create a new address
    public function createAddress($input) {
        try {
            // Validate required fields
            if (!isset($input['user_id']) || !isset($input['address_line1'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Missing required fields: user_id, address_line1'
                ];
            }

            // Validate user_id is numeric
            if (!is_numeric($input['user_id'])) {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'user_id must be numeric'
                ];
            }

            $address_id = $this->address->create($input);

            if ($address_id) {
                $new_address = $this->address->getById($address_id);
                return [
                    'status' => true,
                    'code' => 201,
                    'data' => $new_address,
                    'message' => 'Address created successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to create address'
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

    // PUT: Update an address
    public function updateAddress($id, $input) {
        try {
            // Check if address exists
            $address = $this->address->getById($id);
            if (!$address) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Address not found'
                ];
            }

            if ($this->address->update($id, $input)) {
                $updated_address = $this->address->getById($id);
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => $updated_address,
                    'message' => 'Address updated successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to update address'
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

    // DELETE: Delete an address
    public function deleteAddress($id) {
        try {
            // Check if address exists
            $address = $this->address->getById($id);
            if (!$address) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Address not found'
                ];
            }

            if ($this->address->delete($id)) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Address deleted successfully'
                ];
            } else {
                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Failed to delete address'
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
