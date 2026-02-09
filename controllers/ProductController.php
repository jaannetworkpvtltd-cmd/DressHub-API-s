<?php

require_once '../models/Product.php';

class ProductController {
    private $product;

    public function __construct() {
        $this->product = new Product();
    }

    public function getAllProducts() {
        try {
            $products = $this->product->getAll();
            return [
                'status' => 'success',
                'data' => $products
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function getProductById($id) {
        try {
            $product = $this->product->getById($id);
            if ($product) {
                return [
                    'status' => 'success',
                    'data' => $product
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Product not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
