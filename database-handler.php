<?php
require_once 'db_multi.php';

function getDbConnection() {
    try {
        return getDbConnectionFor(DB_NAME_USERS_MAIL);
    } catch (Exception $e) {
        die('Connection failed');
    }
}

function saveSearchHistory($highestCustomerNumber, $customerType) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO search_history (highest_customer_number, customer_type) VALUES (?, ?)");
    $stmt->bind_param("ii", $highestCustomerNumber, $customerType);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function getRecentSearches() {
    $conn = getDbConnection();
    $sql = "
        (SELECT * FROM search_history WHERE customer_type = 1 ORDER BY search_date DESC LIMIT 2)
        UNION ALL
        (SELECT * FROM search_history WHERE customer_type = 2 ORDER BY search_date DESC LIMIT 2)
        ORDER BY customer_type, search_date DESC
    ";
    $result = $conn->query($sql);
    $searches = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $searches[] = $row;
        }
    }
    $conn->close();
    return $searches;
}
