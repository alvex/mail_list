<?php
require_once __DIR__ . '/../db_multi.php';

function phone_list_selected_source() {
    $source = isset($_REQUEST['source']) ? strtolower(trim((string)$_REQUEST['source'])) : 'active';
    return $source === 'temp' ? 'temp' : 'active';
}

function phone_list_db_for_source($source) {
    return $source === 'temp' ? getDbConnectionFor(DB_NAME_USERS_MAIL) : getDbConnectionFor(DB_NAME_FRESH_BOKNING);
}

function phone_list_table_for_source($source) {
    return $source === 'temp' ? 'si_phone_list' : 'cal_phone_list';
}

function phone_list_table_has_column($conn, $table, $column) {
    static $cache = [];
    $key = spl_object_id($conn) . '|' . $table . '|' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $cache[$key] = $row && (int)$row['c'] > 0;
    return $cache[$key];
}

function phone_list_fetch_saved_rows($conn, $table, $qPhone, $qName, $sortBy, $sortDir, $limit) {
    $qPhone = trim((string)$qPhone);
    $qName = trim((string)$qName);
    $limit = (int)$limit;
    if ($limit < 1) {
        $limit = 20;
    }
    if ($limit > 2000) {
        $limit = 2000;
    }

    $hasName = phone_list_table_has_column($conn, $table, 'name');
    $hasPhone = phone_list_table_has_column($conn, $table, 'phone');
    $hasCreatedAt = phone_list_table_has_column($conn, $table, 'created_at');

    $where = [];
    $params = [];
    $types = '';

    if ($hasPhone && $qPhone !== '') {
        $where[] = 'phone LIKE ?';
        $params[] = '%' . $qPhone . '%';
        $types .= 's';
    }
    if ($hasName && $qName !== '') {
        $where[] = 'name LIKE ?';
        $params[] = '%' . $qName . '%';
        $types .= 's';
    }

    $selectCols = ['memberid'];
    if ($hasName) { $selectCols[] = 'name'; }
    if ($hasPhone) { $selectCols[] = 'phone'; }
    if ($hasCreatedAt) { $selectCols[] = 'created_at'; }

    $allowedSort = ['memberid'];
    if ($hasCreatedAt) { $allowedSort[] = 'created_at'; }
    if (!in_array($sortBy, $allowedSort, true)) {
        $sortBy = 'memberid';
    }
    $sortDir = strtolower((string)$sortDir) === 'asc' ? 'ASC' : 'DESC';

    $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM ' . $table;
    if (count($where) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortDir . ' LIMIT ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }

    if ($types !== '') {
        $typesWithLimit = $types . 'i';
        $params[] = $limit;
        $stmt->bind_param($typesWithLimit, ...$params);
    } else {
        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function phone_list_get_next_memberid_start($conn, $table) {
    $sql = 'SELECT MAX(memberid) AS max_memberid FROM ' . $table;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $max = $row && $row['max_memberid'] !== null ? (int)$row['max_memberid'] : 0;
    return $max + 1;
}

function phone_list_fetch_export_rows($conn, $table, $startMemberId, $exportLimit, $sortBy, $sortDir, $qPhone, $qName) {
    $startMemberId = (int)$startMemberId;
    if ($startMemberId < 0) {
        $startMemberId = 0;
    }

    $exportLimit = (int)$exportLimit;
    if ($exportLimit < 1) {
        $exportLimit = 20;
    }
    if ($exportLimit > 5000) {
        $exportLimit = 5000;
    }

    $hasName = phone_list_table_has_column($conn, $table, 'name');
    $hasPhone = phone_list_table_has_column($conn, $table, 'phone');
    $hasCreatedAt = phone_list_table_has_column($conn, $table, 'created_at');

    $qPhone = trim((string)$qPhone);
    $qName = trim((string)$qName);

    $where = ['memberid >= ?'];
    $params = [$startMemberId];
    $types = 'i';

    if ($hasPhone && $qPhone !== '') {
        $where[] = 'phone LIKE ?';
        $params[] = '%' . $qPhone . '%';
        $types .= 's';
    }
    if ($hasName && $qName !== '') {
        $where[] = 'name LIKE ?';
        $params[] = '%' . $qName . '%';
        $types .= 's';
    }

    $selectCols = ['memberid'];
    if ($hasName) { $selectCols[] = 'name'; }
    if ($hasPhone) { $selectCols[] = 'phone'; }
    if ($hasCreatedAt) { $selectCols[] = 'created_at'; }

    $allowedSort = ['memberid'];
    if ($hasCreatedAt) { $allowedSort[] = 'created_at'; }
    if (!in_array($sortBy, $allowedSort, true)) {
        $sortBy = 'memberid';
    }
    $sortDir = strtolower((string)$sortDir) === 'asc' ? 'ASC' : 'DESC';

    $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sortBy . ' ' . $sortDir . ' LIMIT ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }

    $params[] = $exportLimit;
    $typesWithLimit = $types . 'i';
    $stmt->bind_param($typesWithLimit, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}
