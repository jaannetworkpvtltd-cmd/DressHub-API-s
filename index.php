<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Get the request path
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = str_replace('/DressHub APIs', '', $request);
$request = ltrim($request, '/');

// Simple routing
if ($request == '') {
    echo json_encode(['message' => 'DressHub API - Welcome']);
} else {
    http_response_code(404);
    echo json_encode(['message' => 'Endpoint not found']);
}
