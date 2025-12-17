<?php
require_once 'db_config.php';

function getDbConnectionFor($dbName) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName);
    if ($conn->connect_error) {
        error_log('Database connection error (' . $dbName . '): ' . $conn->connect_error);
        throw new Exception('Database connection failed');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
