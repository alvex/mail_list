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

// Syfte: Hämta aktiva kunder inkrementellt och spara cursor så nästa körning fortsätter från senaste memberid.
function kundlista_get_active_customers() {
    $lastMemberId = kundlista_get_last_memberid(DB_NAME_FRESH_BOKNING, 'cal_phone_list');
    $lastMemberIdValue = $lastMemberId !== null ? $lastMemberId : 0;

    $rows = kundlista_fetch_cal_login_since($lastMemberIdValue);

    $customers = [];
    $historyRows = [];

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

        $historyRows[] = [
            'memberid' => $memberid,
            'name' => $name
        ];
    }

    kundlista_insert_history_rows(DB_NAME_FRESH_BOKNING, 'cal_phone_list', $historyRows);

    return $customers;
}

// Syfte: Används av UI för att visa synkstatus (senast importerad rad).
function kundlista_get_latest_active_history() {
    return kundlista_get_latest_history_row(DB_NAME_FRESH_BOKNING, 'cal_phone_list');
}

// Syfte: Används av UI för att visa synkstatus (senast importerad rad).
function kundlista_get_latest_temp_history() {
    return kundlista_get_latest_history_row(DB_NAME_USERS_MAIL, 'si_phone_list');
}

// Syfte: Hämta temp-kunder inkrementellt och spara cursor så nästa körning fortsätter från senaste memberid.
function kundlista_get_temp_customers() {
    $lastMemberId = kundlista_get_last_memberid(DB_NAME_USERS_MAIL, 'si_phone_list');
    $lastMemberIdValue = $lastMemberId !== null ? $lastMemberId : 0;

    $rows = kundlista_fetch_si_customers_since($lastMemberIdValue);

    $customers = [];
    $historyRows = [];

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

        $historyRows[] = [
            'memberid' => $memberid,
            'name' => $name
        ];
    }

    kundlista_insert_history_rows(DB_NAME_USERS_MAIL, 'si_phone_list', $historyRows);

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
