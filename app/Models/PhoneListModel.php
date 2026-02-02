<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use Exception;

class PhoneListModel extends Model
{

    public function getLastMemberId($dbName, $historyTable)
    {
        $conn = Database::getConnection($dbName);
        $sql = "SELECT MAX(memberid) AS max_memberid FROM {$historyTable}";
        $result = $conn->query($sql);
        if (!$result) {
            error_log('Error reading last memberid: ' . $conn->error);
            return null; // Return null on error or empty table
        }
        $row = $result->fetch_assoc();
        return $row && $row['max_memberid'] !== null ? (int) $row['max_memberid'] : 0;
    }

    public function fetchActiveSince($lastMemberId, $limit = 50)
    {
        $conn = Database::getConnection(DB_NAME_FRESH_BOKNING);
        $sql = "SELECT users.memberid, users.fname, users.lname, users.phone, " .
            "MIN(res.start_date) AS first_start, " .
            "MAX(res.end_date) AS last_end " .
            "FROM cal_reservations AS res " .
            "INNER JOIN cal_resources AS rs ON rs.machid = res.machid " .
            "INNER JOIN cal_reservation_users AS resusers ON resusers.resid = res.resid " .
            "INNER JOIN cal_login AS users ON users.memberid = resusers.memberid " .
            "WHERE rs.status = 'a' " .
            // Begränsa mängden data vi tittar på till senaste året av bokningar.
            "AND res.start_date >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 YEAR)) " .
            "AND users.phone IS NOT NULL " .
            "AND TRIM(users.phone) <> '' " .
            "AND TRIM(users.phone) LIKE '07%' " .
            "AND users.memberid > ? " .
            "GROUP BY users.memberid, users.fname, users.lname, users.phone " .
            // Villkor: minst en bokning senaste 3 månaderna OCH minst en bokning kommande 3 månader.
            "HAVING " .
            "SUM(res.start_date BETWEEN " .
            "UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) " .
            "AND UNIX_TIMESTAMP(CURDATE())) > 0 " .
            "AND SUM(res.start_date BETWEEN " .
            "UNIX_TIMESTAMP(CURDATE()) " .
            "AND UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 MONTH))) > 0 " .
            "ORDER BY users.memberid ASC " .
            "LIMIT ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('ii', $lastMemberId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $phone = $this->normalizePhone($row['phone']);
            if (!$phone)
                continue;

            $name = trim($row['fname'] . ' ' . $row['lname']);

            $rows[] = [
                'memberid' => (int) $row['memberid'],
                'fname' => $row['fname'],
                'lname' => $row['lname'],
                'name' => $name,
                'phone' => $phone,
                'type' => 'Aktiv'
            ];
        }
        $stmt->close();
        return $rows;
    }

    public function fetchTempSince($lastMemberId, $limit = 50)
    {
        $conn = Database::getConnection(DB_NAME_USERS_MAIL);
        $sql = "SELECT id AS memberid, name AS fname, '' AS lname, CASE " .
            "WHEN phone IS NOT NULL AND TRIM(phone) <> '' AND TRIM(phone) LIKE '07%' THEN TRIM(phone) " .
            "WHEN mobile_phone IS NOT NULL AND TRIM(mobile_phone) <> '' AND TRIM(mobile_phone) LIKE '07%' THEN TRIM(mobile_phone) " .
            "ELSE NULL END AS phone " .
            "FROM si_customers " .
            "WHERE id > ? AND enabled = ? " .
            "AND ((phone IS NOT NULL AND TRIM(phone) <> '' AND TRIM(phone) LIKE '07%') " .
            "OR (mobile_phone IS NOT NULL AND TRIM(mobile_phone) <> '' AND TRIM(mobile_phone) LIKE '07%')) " .
            "ORDER BY id ASC LIMIT ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Prepare failed: ' . $conn->error);

        $enabled = 2; // Temp customers
        $stmt->bind_param('iii', $lastMemberId, $enabled, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $phone = $this->normalizePhone($row['phone']);
            if (!$phone)
                continue;

            $rows[] = [
                'memberid' => (int) $row['memberid'],
                'fname' => $row['fname'],
                'lname' => '',
                'name' => $row['fname'],
                'phone' => $phone,
                'type' => 'Temp'
            ];
        }
        $stmt->close();
        return $rows;
    }

    public function saveExportedPhones($dbName, $table, $customers)
    {
        if (!is_array($customers) || count($customers) === 0)
            return;

        $conn = Database::getConnection($dbName);
        $sql = "INSERT INTO {$table} (memberid, name, phone, created_at) VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                memberid = VALUES(memberid)";
        $stmt = $conn->prepare($sql);
        if (!$stmt)
            throw new Exception('Prepare failed: ' . $conn->error);

        foreach ($customers as $c) {
            $memberid = isset($c['memberid']) ? (int) $c['memberid'] : 0;
            $name = (string) ($c['name'] ?? '');
            $phone = (string) ($c['phone'] ?? ''); // Already normalized

            if ($phone === '')
                continue;

            $stmt->bind_param('iss', $memberid, $name, $phone);
            $stmt->execute();
        }
        $stmt->close();
    }

    public function normalizePhone($phoneRaw)
    {
        $phone = preg_replace('/\D+/', '', (string) $phoneRaw);
        if ($phone === null)
            return null;
        if (strpos($phone, '07') !== 0)
            return null;
        $withoutLeadingZero = substr($phone, 1);
        return '+46' . $withoutLeadingZero;
    }

    public function toCsv($customers)
    {
        $lines = [];
        $lines[] = '"Nummer";"Namn";"Typ"';

        foreach ($customers as $c) {
            $phone = str_replace('"', '""', (string) $c['phone']);
            $name = str_replace('"', '""', (string) $c['name']);
            $type = str_replace('"', '""', (string) $c['type']);

            $lines[] = '"' . $phone . '";"' . $name . '";"' . $type . '"';
        }

        return implode("\n", $lines);
    }
}
