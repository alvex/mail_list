<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\PhoneListModel;
use Exception;

class PhoneListController extends Controller
{

    public function __construct()
    {
        // Auth check is done inside methods or via constructor if consistent.
        // Page load needs HTML auth, API needs JSON auth.
    }

    public function index()
    {
        AuthMiddleware::check();
        $this->view('phone_list/index');
    }

    public function api()
    {
        AuthMiddleware::checkJson(); // Ensure JSON auth

        $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
        $action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 'list';

        $limitRaw = $_GET['per_page'] ?? $_GET['limit'] ?? 10;
        $limit = (int) $limitRaw;
        if ($limit < 1)
            $limit = 10;
        if ($limit > 500)
            $limit = 500;

        $model = new PhoneListModel();

        if ($action === 'latest') {
            $this->json([
                'active' => null,
                'temp' => null
            ]);
            return;
        }

        if ($type !== 'active' && $type !== 'temp') {
            http_response_code(400);
            $this->json(['error' => 'Invalid type']);
            return;
        }

        try {
            $customers = [];
            if ($type === 'active') {
                $lastSaved = $model->getLastMemberId(DB_NAME_FRESH_BOKNING, 'cal_phone_list');
                $customers = $model->fetchActiveSince($lastSaved, $limit);
            } else {
                $lastSaved = $model->getLastMemberId(DB_NAME_USERS_MAIL, 'si_phone_list');
                $customers = $model->fetchTempSince($lastSaved, $limit);
            }

            if ($action === 'csv') {
                // Save phone list on export
                if ($type === 'active') {
                    $model->saveExportedPhones(DB_NAME_FRESH_BOKNING, 'cal_phone_list', $customers);
                } else {
                    $model->saveExportedPhones(DB_NAME_USERS_MAIL, 'si_phone_list', $customers);
                }

                $csv = $model->toCsv($customers);
                $date = date('Ymd');
                $filename = $type === 'active' ? ('aktiva_kunder_' . $date . '.csv') : ('temp_kunder_' . $date . '.csv');

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $csv;
                exit();
            }

            $this->json($customers);

        } catch (Exception $e) {
            error_log('PhoneListController error: ' . $e->getMessage());
            http_response_code(500);
            $this->json(['error' => 'Server error']);
        }
    }
}
