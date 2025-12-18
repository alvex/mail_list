<?php
require_once 'db_config.php';

function getDbConnectionFor($dbName) {
    $dbUser = DB_USER;
    $dbPass = DB_PASS;

    if ($dbName === DB_NAME_USERS_MAIL) {
        $dbUser = DB_USER_USERS_MAIL;
        $dbPass = DB_PASS_USERS_MAIL;
    } elseif ($dbName === DB_NAME_FRESH_BOKNING) {
        $dbUser = DB_USER_FRESH_BOKNING;
        $dbPass = DB_PASS_FRESH_BOKNING;
    } elseif (defined('DB_NAME_HEMFRESH_INVOICESAB') && $dbName === DB_NAME_HEMFRESH_INVOICESAB) {
        $dbUser = defined('DB_USER_HEMFRESH_INVOICESAB') ? DB_USER_HEMFRESH_INVOICESAB : DB_USER;
        $dbPass = defined('DB_PASS_HEMFRESH_INVOICESAB') ? DB_PASS_HEMFRESH_INVOICESAB : DB_PASS;
    }

    $conn = new mysqli(DB_HOST, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        error_log('Database connection error (' . $dbName . '): ' . $conn->connect_error);
        throw new Exception('Database connection failed');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
