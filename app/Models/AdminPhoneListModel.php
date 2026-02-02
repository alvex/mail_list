<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use Exception;

class AdminPhoneListModel extends Model
{

    public function getDbConnectionForSource($source)
    {
        $dbName = ($source === 'temp') ? DB_NAME_USERS_MAIL : DB_NAME_FRESH_BOKNING;
        return Database::getConnection($dbName);
    }

    public function getTableForSource($source)
    {
        return ($source === 'temp') ? 'si_phone_list' : 'cal_phone_list';
    }

    public function hasColumn($conn, $table, $column)
    {
        // Simple check avoiding cache complexity for now, or implement simple cache
        $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            return false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row && (int) $row['c'] > 0;
    }

    public function fetchSavedRows($source, $qPhone, $qName, $sortBy, $sortDir, $limit)
    {
        $conn = $this->getDbConnectionForSource($source);
        $table = $this->getTableForSource($source);

        $limit = max(1, min((int) $limit, 2000));

        $hasName = $this->hasColumn($conn, $table, 'name');
        $hasPhone = $this->hasColumn($conn, $table, 'phone');
        $hasCreatedAt = $this->hasColumn($conn, $table, 'created_at');

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
        if ($hasName) {
            $selectCols[] = 'name';
        }
        if ($hasPhone) {
            $selectCols[] = 'phone';
        }
        if ($hasCreatedAt) {
            $selectCols[] = 'created_at';
        }

        $allowedSort = ['memberid'];
        if ($hasCreatedAt) {
            $allowedSort[] = 'created_at';
        }
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'memberid';
        }
        $sortDir = strtolower((string) $sortDir) === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM ' . $table;
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortDir . ' LIMIT ?';

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Database query failed: ' . $conn->error);

        $typesWithLimit = $types . 'i';
        $params[] = $limit;
        $stmt->bind_param($typesWithLimit, ...$params);

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return [
            'rows' => $rows,
            'hasNameCol' => $hasName,
            'hasPhoneCol' => $hasPhone,
            'hasCreatedAtCol' => $hasCreatedAt
        ];
    }

    public function getNextMemberIdStart($source)
    {
        $conn = $this->getDbConnectionForSource($source);
        $table = $this->getTableForSource($source);

        $sql = 'SELECT MAX(memberid) AS max_memberid FROM ' . $table;
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Database query failed');

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $max = $row && $row['max_memberid'] !== null ? (int) $row['max_memberid'] : 0;
        return $max + 1;
    }

    public function fetchExportRows($source, $startMemberId, $exportLimit, $sortBy, $sortDir, $qPhone, $qName)
    {
        $conn = $this->getDbConnectionForSource($source);
        $table = $this->getTableForSource($source);

        $startMemberId = max(0, (int) $startMemberId);
        $exportLimit = max(1, min((int) $exportLimit, 5000));

        $hasName = $this->hasColumn($conn, $table, 'name');
        $hasPhone = $this->hasColumn($conn, $table, 'phone');
        $hasCreatedAt = $this->hasColumn($conn, $table, 'created_at');

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
        if ($hasName) {
            $selectCols[] = 'name';
        }
        if ($hasPhone) {
            $selectCols[] = 'phone';
        }
        if ($hasCreatedAt) {
            $selectCols[] = 'created_at';
        }

        $allowedSort = ['memberid'];
        if ($hasCreatedAt) {
            $allowedSort[] = 'created_at';
        }
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'memberid';
        }
        $sortDir = strtolower((string) $sortDir) === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sortBy . ' ' . $sortDir . ' LIMIT ?';

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Database query failed');

        $params[] = $exportLimit;
        $stmt->bind_param($types . 'i', ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}
