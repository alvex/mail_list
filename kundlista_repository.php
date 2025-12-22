<?php
require_once 'db_multi.php';

// Syfte: Read/write-operationer för synk-historik för telefonlistor.
// OBS: "history"-tabellerna används som en enkel cursor (senast behandlade memberid).
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

// Syfte: Hämta senast importerad historikrad (för status/övervakning i UI).
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

// Syfte: Hämta nya aktiva kunder sedan senast kända cursor.
// OBS: Sidstorlek styrs av $GLOBALS['kundlista_limit'] (sätts i API-lagret).
function kundlista_fetch_cal_login_since($lastMemberId) {
    $conn = getDbConnectionFor(DB_NAME_FRESH_BOKNING);

    // Affärsregel: Aktiv kund = har minst en schemalagd (framtida) bokning och kundens framtida bokningar
    // ligger inom en sammanhängande tre-månadersperiod.
    //
    // Tolkning av "sammanhängande tre-månadersperiod":
    // - För varje kund: MIN(start_date) och MAX(end_date) för framtida bokningar får spänna max 3 månader.
    // TODO: Bekräfta om "tre-månadersperiod" ska räknas från första start_date (nuvarande implementation).
    //
    // OBS: Vi behåller befintlig cursor på memberid för att inte ändra övrig applikationslogik.
    $sql = "SELECT users.memberid, users.fname, users.lname, users.phone, " .
        "MIN(res.start_date) AS first_start, " .
        "MAX(res.end_date) AS last_end " .
        "FROM cal_reservations AS res " .
        "INNER JOIN cal_resources AS rs ON rs.machid = res.machid " .
        "INNER JOIN cal_reservation_users AS resusers ON resusers.resid = res.resid " .
        "INNER JOIN cal_login AS users ON users.memberid = resusers.memberid " .
        "WHERE rs.status = 'a' " .
        "AND res.start_date >= ? " .
        "AND users.memberid > ? " .
        "GROUP BY users.memberid, users.fname, users.lname, users.phone " .
        "HAVING last_end <= UNIX_TIMESTAMP(DATE_ADD(FROM_UNIXTIME(first_start), INTERVAL 3 MONTH)) " .
        "ORDER BY users.memberid ASC " .
        "LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        error_log('Prepare failed: ' . $err);
        throw new Exception('Database query failed');
    }

    $limit = isset($GLOBALS['kundlista_limit']) ? (int)$GLOBALS['kundlista_limit'] : 50;

    $nowTs = time();
    $stmt->bind_param('iii', $nowTs, $lastMemberId, $limit);
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

// Syfte: Hämta "Temp"-kunder från users_mail.
// Affärsregel: Endast svenska mobilnummer som börjar med 07 är giltiga; välj phone först, annars mobile_phone.
// TODO: Bekräfta vad enabled=2 betyder i si_customers (status-mappning).
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

// Syfte: Spara behandlade rader så nästa körning kan fortsätta från senaste memberid.
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
