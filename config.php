<?php
require_once 'db_multi.php';

try {
    $conn = getDbConnectionFor(DB_NAME_USERS_MAIL);
} catch (Exception $e) {
    die('Connection failed');
}
?>
