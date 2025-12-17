<?php
require_once 'db_config.php';
require_once 'db_functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['highest_customer_number']) && isset($data['customer_type'])) {
    saveSearchHistory($data['highest_customer_number'], $data['customer_type']);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data received']);
}
