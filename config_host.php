<?php
require_once 'db_multi.php';

try {
    $conn = getDbConnectionFor(DB_NAME_HEMFRESH_INVOICESAB);
} catch (Exception $e) {
    die('Connection failed');
}
?>
