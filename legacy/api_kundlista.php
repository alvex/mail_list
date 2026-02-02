<?php
require_once __DIR__ . '/app/middleware/auth.php';
auth_require_json(30 * 60);

require_once 'kundlista_service.php';

// Syfte: API för telefonlistor.
// Förväntade query params:
// - type: active|temp
// - action: list|csv|latest
// - per_page/limit: sidstorlek (begränsad)
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 'list';

$limitRaw = null;
if (isset($_GET['per_page'])) {
    $limitRaw = $_GET['per_page'];
} elseif (isset($_GET['limit'])) {
    $limitRaw = $_GET['limit'];
}

$limit = (int)($limitRaw !== null ? $limitRaw : 10);
if ($limit < 1) {
    $limit = 10;
}
if ($limit > 500) {
    $limit = 500;
}

try {
    if ($action === 'latest') {
        // OBS: List-endpointen skriver inte längre till DB. Ingen "senaste importerad" finns.
        $latestActive = null;
        $latestTemp = null;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'active' => $latestActive,
            'temp' => $latestTemp
        ]);
        exit();
    }

    if ($type !== 'active' && $type !== 'temp') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type']);
        exit();
    }

    // OBS: Repository läser limit från global för att slippa tråda parametern genom alla lager.
    $GLOBALS['kundlista_limit'] = $limit;

    $customers = $type === 'active' ? kundlista_get_active_customers() : kundlista_get_temp_customers();

    if ($action === 'csv') {
        // Syfte: Exportera samma dataset som list-endpointen, men som nedladdningsbar CSV.
        // Affärsregel: Telefonnummer sparas endast i samband med export.
        if ($type === 'active') {
            kundlista_save_exported_phones_to_cal_phone_list($customers);
        } else {
            kundlista_save_exported_phones_to_si_phone_list($customers);
        }
        $csv = kundlista_customers_to_csv($customers);
        $date = date('Ymd');
        $filename = $type === 'active' ? ('aktiva_kunder_' . $date . '.csv') : ('temp_kunder_' . $date . '.csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($customers);
} catch (Exception $e) {
    error_log('api_kundlista error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
