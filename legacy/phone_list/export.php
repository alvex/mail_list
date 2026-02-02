<?php

function phone_list_export_csv($rows, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $header = ['memberid'];
    if (isset($rows[0]) && array_key_exists('name', $rows[0])) { $header[] = 'name'; }
    if (isset($rows[0]) && array_key_exists('phone', $rows[0])) { $header[] = 'phone'; }
    if (isset($rows[0]) && array_key_exists('created_at', $rows[0])) { $header[] = 'created_at'; }

    $lines = [];
    $escapedHeader = array_map(function ($h) { return '"' . str_replace('"', '""', (string)$h) . '"'; }, $header);
    $lines[] = implode(';', $escapedHeader);

    foreach ($rows as $r) {
        $line = [];
        foreach ($header as $h) {
            $val = str_replace('"', '""', (string)($r[$h] ?? ''));
            $line[] = '"' . $val . '"';
        }
        $lines[] = implode(';', $line);
    }

    echo implode("\n", $lines);
    exit();
}

function phone_list_export_excel_html($rows, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $header = ['memberid'];
    if (isset($rows[0]) && array_key_exists('name', $rows[0])) { $header[] = 'name'; }
    if (isset($rows[0]) && array_key_exists('phone', $rows[0])) { $header[] = 'phone'; }
    if (isset($rows[0]) && array_key_exists('created_at', $rows[0])) { $header[] = 'created_at'; }

    echo "<table border=\"1\">";
    echo "<tr>";
    foreach ($header as $h) {
        $th = htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8');
        echo "<th>{$th}</th>";
    }
    echo "</tr>";

    foreach ($rows as $r) {
        echo "<tr>";
        foreach ($header as $h) {
            $td = htmlspecialchars((string)($r[$h] ?? ''), ENT_QUOTES, 'UTF-8');
            echo "<td>{$td}</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
    exit();
}
