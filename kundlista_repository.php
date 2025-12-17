<?php
require_once 'db_multi.php';

function kundlista_get_last_memberid($dbName, $historyTable) {
    $conn = getDbConnectionFor($dbName);
    $sql = "SELECT MAX(memberid) AS max_memberid FROM {$historyTable}";
    $result = $conn->query($sql);
    if (!$result) {
        $err = $conn->error;
        $conn->close();
        error_log('Error reading last memberid: ' . $err);
        throw new Exception('Database query failed');
    }
    $row = $result->fetch_assoc();
    $conn->close();
    return $row && $row['max_memberid'] !== null ? (int)$row['max_memberid'] : null;
}

function kundlista_get_latest_history_row($dbName, $historyTable) {
    $conn = getDbConnectionFor($dbName);
    $sql = "SELECT memberid, name, created_at FROM {$historyTable} ORDER BY created_at DESC, memberid DESC LIMIT 1";
    $result = $conn->query($sql);
    if (!$result) {
        $err = $conn->error;
        $conn->close();
        error_log('Error reading latest history row: ' . $err);
        throw new Exception('Database query failed');
    }
    $row = $result->fetch_assoc();
    $conn->close();

    if (!$row) {
        return null;
    }

    return [
        'memberid' => isset($row['memberid']) ? (int)$row['memberid'] : null,
        'name' => (string)($row['name'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? '')
    ];
}

function kundlista_fetch_cal_login_since($lastMemberId) {
    $conn = getDbConnectionFor(DB_NAME_FRESH_BOKNING);

    $sql = "SELECT memberid, fname, lname, phone FROM cal_login WHERE memberid > ? ORDER BY memberid ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        error_log('Prepare failed: ' . $err);
        throw new Exception('Database query failed');
    }

    $limit = isset($GLOBALS['kundlista_limit']) ? (int)$GLOBALS['kundlista_limit'] : 50;
    $stmt->bind_param('ii', $lastMemberId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    $conn->close();
    return $rows;
}
//Om vi vill hämta "Temp" kunder då hämtas data från  'users_mail' database 
function kundlista_fetch_si_customers_since($lastMemberId) {
    $conn = getDbConnectionFor(DB_NAME_USERS_MAIL);

    $sql = "SELECT id AS memberid, name AS fname, '' AS lname, CASE WHEN phone IS NOT NULL AND TRIM(phone) <> '' AND phone LIKE '07%' THEN phone WHEN mobile_phone IS NOT NULL AND TRIM(mobile_phone) <> '' AND mobile_phone LIKE '07%' THEN mobile_phone ELSE NULL END AS phone FROM si_customers WHERE id > ? AND enabled = ? ORDER BY id ASC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        error_log('Prepare failed: ' . $err);
        throw new Exception('Database query failed');
    }

    $limit = isset($GLOBALS['kundlista_limit']) ? (int)$GLOBALS['kundlista_limit'] : 50;
    $enabled = 2;
    $stmt->bind_param('iii', $lastMemberId, $enabled, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    $conn->close();
    return $rows;
}

function kundlista_insert_history_rows($dbName, $historyTable, $rows) {
    if (count($rows) === 0) {
        return;
    }

    $conn = getDbConnectionFor($dbName);
    $sql = "INSERT INTO {$historyTable} (memberid, name, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        error_log('Prepare failed: ' . $err);
        throw new Exception('Database query failed');
    }

    foreach ($rows as $row) {
        $memberid = (int)$row['memberid'];
        $name = (string)$row['name'];
        $stmt->bind_param('is', $memberid, $name);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();
}
