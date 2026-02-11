<?php
// Simple test to debug image upload

header('Content-Type: application/json');

echo json_encode([
    'FILES' => $_FILES,
    'POST' => $_POST,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD']
]);
?>
