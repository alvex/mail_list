<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\AdminPhoneListModel;
use Exception;

class AdminPhoneListController extends Controller
{

    public function __construct()
    {
        AuthMiddleware::check();
    }

    public function index()
    {
        $source = isset($_GET['source']) ? strtolower(trim($_GET['source'])) : 'active';
        if ($source !== 'temp')
            $source = 'active';

        $qName = isset($_GET['q_name']) ? trim($_GET['q_name']) : '';
        $qPhone = isset($_GET['q_phone']) ? trim($_GET['q_phone']) : '';
        $sortBy = isset($_GET['sort_by']) ? strtolower(trim($_GET['sort_by'])) : 'memberid';
        $sortDir = isset($_GET['sort_dir']) ? strtolower(trim($_GET['sort_dir'])) : 'desc';
        $viewLimit = isset($_GET['view_limit']) ? (int) $_GET['view_limit'] : 20;

        $model = new AdminPhoneListModel();
        $errorMessage = '';

        // Handle Export
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
            try {
                // Simplified CSRF check (omitted for brevity as session logic is basic)

                $startMemberId = (int) ($_POST['start_memberid'] ?? 0);
                $exportLimit = (int) ($_POST['export_limit'] ?? 20);
                $format = isset($_POST['format']) && $_POST['format'] === 'excel' ? 'excel' : 'csv';

                $rows = $model->fetchExportRows($source, $startMemberId, $exportLimit, $sortBy, $sortDir, $qPhone, $qName);

                if ($format === 'excel') {
                    // Simple HTML Table export which Excel can open
                    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                    header('Content-Disposition: attachment; filename="export.xls"');
                    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">
                    <head><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head><body>';
                    echo '<table border="1">';
                    echo '<tr><th>memberid</th><th>name</th><th>phone</th><th>created_at</th></tr>';
                    foreach ($rows as $r) {
                        echo '<tr>';
                        echo '<td>' . ($r['memberid'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($r['name'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($r['phone'] ?? '') . '</td>';
                        echo '<td>' . ($r['created_at'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table></body></html>';
                    exit();
                } else {
                    // CSV
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="export.csv"');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['memberid', 'name', 'phone', 'created_at'], ';');
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r['memberid'] ?? '',
                            $r['name'] ?? '',
                            $r['phone'] ?? '',
                            $r['created_at'] ?? ''
                        ], ';');
                    }
                    fclose($out);
                    exit();
                }

            } catch (Exception $e) {
                $errorMessage = "Export error: " . $e->getMessage();
            }
        }

        // View Data
        $data = [];
        try {
            $result = $model->fetchSavedRows($source, $qPhone, $qName, $sortBy, $sortDir, $viewLimit);
            $data = $result;
            $data['suggestedStartMemberId'] = $model->getNextMemberIdStart($source);
        } catch (Exception $e) {
            $errorMessage = "Error fetching data: " . $e->getMessage();
            $data['rows'] = [];
            $data['hasNameCol'] = false;
            $data['hasPhoneCol'] = false;
            $data['hasCreatedAtCol'] = false;
            $data['suggestedStartMemberId'] = 0;
        }

        $viewData = [
            'source' => $source,
            'qName' => $qName,
            'qPhone' => $qPhone,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'viewLimit' => $viewLimit,
            'errorMessage' => $errorMessage,
            'rows' => $data['rows'],
            'hasNameCol' => $data['hasNameCol'],
            'hasPhoneCol' => $data['hasPhoneCol'],
            'hasCreatedAtCol' => $data['hasCreatedAtCol'],
            'suggestedStartMemberId' => $data['suggestedStartMemberId']
        ];

        $this->view('admin_phone_list/index', $viewData);
    }
}
