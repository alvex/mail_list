<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/export.php';

phone_list_require_csrf();

$source = phone_list_selected_source();
$qName = isset($_GET['q_name']) ? (string)$_GET['q_name'] : '';
$qPhone = isset($_GET['q_phone']) ? (string)$_GET['q_phone'] : '';
$sortBy = isset($_GET['sort_by']) ? strtolower(trim((string)$_GET['sort_by'])) : 'memberid';
$sortDir = isset($_GET['sort_dir']) ? strtolower(trim((string)$_GET['sort_dir'])) : 'desc';
$viewLimit = isset($_GET['view_limit']) ? (int)$_GET['view_limit'] : 20;

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    $source = isset($_POST['source']) ? strtolower(trim((string)$_POST['source'])) : $source;
    $exportStart = isset($_POST['start_memberid']) ? (int)$_POST['start_memberid'] : 0;
    $exportLimit = isset($_POST['export_limit']) ? (int)$_POST['export_limit'] : 20;
    $format = isset($_POST['format']) ? strtolower(trim((string)$_POST['format'])) : 'csv';
    $exportSortBy = isset($_POST['sort_by']) ? strtolower(trim((string)$_POST['sort_by'])) : $sortBy;
    $exportSortDir = isset($_POST['sort_dir']) ? strtolower(trim((string)$_POST['sort_dir'])) : $sortDir;

    if (isset($_POST['q_name'])) {
        $qName = (string)$_POST['q_name'];
    }
    if (isset($_POST['q_phone'])) {
        $qPhone = (string)$_POST['q_phone'];
    }

    try {
        $conn = phone_list_db_for_source($source);
        $table = phone_list_table_for_source($source);
        $rows = phone_list_fetch_export_rows($conn, $table, $exportStart, $exportLimit, $exportSortBy, $exportSortDir, $qPhone, $qName);
        $conn->close();

        $date = date('Ymd');
        $base = $source === 'temp' ? 'telefonlista_temp_' : 'telefonlista_aktiva_';
        if ($format === 'excel') {
            phone_list_export_excel_html($rows, $base . $date . '.xls');
        }
        phone_list_export_csv($rows, $base . $date . '.csv');
    } catch (Exception $e) {
        error_log('phone_list export error: ' . $e->getMessage());
        $errorMessage = 'Serverfel vid export.';
    }
}

try {
    $conn = phone_list_db_for_source($source);
    $table = phone_list_table_for_source($source);
    $rows = phone_list_fetch_saved_rows($conn, $table, $qPhone, $qName, $sortBy, $sortDir, $viewLimit);
    $suggestedStartMemberId = phone_list_get_next_memberid_start($conn, $table);
    $hasNameCol = phone_list_table_has_column($conn, $table, 'name');
    $hasPhoneCol = phone_list_table_has_column($conn, $table, 'phone');
    $hasCreatedAtCol = phone_list_table_has_column($conn, $table, 'created_at');
    $conn->close();
} catch (Exception $e) {
    error_log('phone_list load error: ' . $e->getMessage());
    $rows = [];
    $suggestedStartMemberId = 0;
    $hasNameCol = true;
    $hasPhoneCol = true;
    $hasCreatedAtCol = true;
    if ($errorMessage === '') {
        $errorMessage = 'Serverfel.';
    }
}

$phone_list_view_data = [
    'source' => $source,
    'qName' => $qName,
    'qPhone' => $qPhone,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'viewLimit' => $viewLimit,
    'errorMessage' => $errorMessage,
    'rows' => $rows,
    'hasNameCol' => $hasNameCol,
    'hasPhoneCol' => $hasPhoneCol,
    'hasCreatedAtCol' => $hasCreatedAtCol,
    'suggestedStartMemberId' => $suggestedStartMemberId,
];
