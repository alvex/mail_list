<?php
require_once 'kundlista_repository.php';

// Syfte: Bygg ett visningsnamn av för-/efternamn.
function kundlista_build_name($fname, $lname) {
    $fname = trim((string)$fname);
    $lname = trim((string)$lname);
    $name = trim($fname . ' ' . $lname);
    return $name;
}

// Affärsregel: Endast svenska mobilnummer (som börjar med 07) är giltiga.
// OBS: Normaliserat format använder E.164 (+46...) för att undvika dubbla format i export.
function kundlista_normalize_phone($phoneRaw) {
    $phone = preg_replace('/\D+/', '', (string)$phoneRaw);
    if ($phone === null) {
        return null;
    }

    if (strpos($phone, '07') !== 0) {
        return null;
    }

    $withoutLeadingZero = substr($phone, 1);
    return '+46' . $withoutLeadingZero;
}

function kundlista_get_last_saved_memberid_active() {
    try {
        $last = kundlista_get_last_memberid(DB_NAME_FRESH_BOKNING, 'cal_phone_list');
        return $last !== null ? (int)$last : 0;
    } catch (Exception $e) {
        error_log('kundlista_get_last_saved_memberid_active error: ' . $e->getMessage());
        return 0;
    }
}

function kundlista_get_last_saved_memberid_temp() {
    try {
        $last = kundlista_get_last_memberid(DB_NAME_USERS_MAIL, 'si_phone_list');
        return $last !== null ? (int)$last : 0;
    } catch (Exception $e) {
        error_log('kundlista_get_last_saved_memberid_temp error: ' . $e->getMessage());
        return 0;
    }
}

function kundlista_save_exported_phones_to_cal_phone_list($customers) {
    if (!is_array($customers) || count($customers) === 0) {
        return;
    }

    $conn = getDbConnectionFor(DB_NAME_FRESH_BOKNING);

    $sql = "INSERT INTO cal_phone_list (memberid, name, phone, created_at) VALUES (?, ?, ?, NOW())\n            ON DUPLICATE KEY UPDATE\n                name = VALUES(name),\n                memberid = VALUES(memberid)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        error_log('Prepare failed: ' . $err);
        throw new Exception('Database query failed');
    }

    foreach ($customers as $c) {
        $memberid = isset($c['memberid']) ? (int)$c['memberid'] : 0;
        $name = (string)($c['name'] ?? '');
        $phone = (string)($c['phone'] ?? '');

        if ($phone === '') {
            continue;
        }

        $stmt->bind_param('iss', $memberid, $name, $phone);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();
}

function kundlista_save_exported_phones_to_si_phone_list($customers) {
    if (!is_array($customers) || count($customers) === 0) {
        return;
    }

    $conn = getDbConnectionFor(DB_NAME_USERS_MAIL);

    $sql = "INSERT INTO si_phone_list (memberid, name, phone, created_at) VALUES (?, ?, ?, NOW())\n            ON DUPLICATE KEY UPDATE\n                name = VALUES(name),\n                memberid = VALUES(memberid)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $err = $conn->error;
        $conn->close();
        error_log('Prepare failed: ' . $err);
        throw new Exception('Database query failed');
    }

    foreach ($customers as $c) {
        $memberid = isset($c['memberid']) ? (int)$c['memberid'] : 0;
        $name = (string)($c['name'] ?? '');
        $phone = (string)($c['phone'] ?? '');

        if ($phone === '') {
            continue;
        }

        $stmt->bind_param('iss', $memberid, $name, $phone);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();
}

// Syfte: Hämta aktiva kunder inkrementellt och spara cursor så nästa körning fortsätter från senaste memberid.
function kundlista_get_active_customers() {
    $lastSaved = kundlista_get_last_saved_memberid_active();
    $rows = kundlista_fetch_cal_login_since($lastSaved);

    $customers = [];
    foreach ($rows as $row) {
        $memberid = (int)$row['memberid'];
        // OBS: Vi returnerar fname/lname separat (för integrationsbehov) men behåller även sammanslaget name för UI.
        $fname = (string)($row['fname'] ?? '');
        $lname = (string)($row['lname'] ?? '');
        $name = kundlista_build_name($fname, $lname);
        $phone = kundlista_normalize_phone($row['phone'] ?? '');

        if ($phone === null) {
            continue;
        }

        $customers[] = [
            'memberid' => $memberid,
            'fname' => $fname,
            'lname' => $lname,
            'name' => $name,
            'phone' => $phone,
            'type' => 'Aktiv'
        ];
    }

    return $customers;
}

// Syfte: Används av UI för att visa synkstatus (senast importerad rad).
function kundlista_get_latest_active_history() {
    return null;
}

// Syfte: Används av UI för att visa synkstatus (senast importerad rad).
function kundlista_get_latest_temp_history() {
    return null;
}

// Syfte: Hämta temp-kunder inkrementellt och spara cursor så nästa körning fortsätter från senaste memberid.
function kundlista_get_temp_customers() {
    $lastSaved = kundlista_get_last_saved_memberid_temp();
    $rows = kundlista_fetch_si_customers_since($lastSaved);

    $customers = [];
    foreach ($rows as $row) {
        $memberid = (int)$row['memberid'];
        $name = kundlista_build_name($row['fname'] ?? '', $row['lname'] ?? '');
        $phone = kundlista_normalize_phone($row['phone'] ?? '');

        if ($phone === null) {
            continue;
        }

        $customers[] = [
            'memberid' => $memberid,
            'name' => $name,
            'phone' => $phone,
            'type' => 'Temp'
        ];
    }

    return $customers;
}

// OBS: Semikolon används som delimiter för att passa vanliga svenska Excel-inställningar.
function kundlista_customers_to_csv($customers) {
    $lines = [];
    $lines[] = '"Nummer";"Namn";"Typ"';

    foreach ($customers as $c) {
        $phone = str_replace('"', '""', (string)$c['phone']);
        $name = str_replace('"', '""', (string)$c['name']);
        $type = str_replace('"', '""', (string)$c['type']);

        $lines[] = '"' . $phone . '";"' . $name . '";"' . $type . '"';
    }

    return implode("\n", $lines);
}
