<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\MailHistory;
use App\Models\Customer;
use Exception;

/**
 * ApiController
 *
 * JSON-API för e-postlistan.
 *
 * Endpoints:
 *  - latest(): returnerar senaste behandlade kunder (aktiv + temp) från
 *    historiktabellen si_mail_history.
 *  - fetch(): hämtar nästa batch kunder och uppdaterar historik-cursorn.
 */
class ApiController extends Controller
{
    /**
     * Skydda alla API-anrop med JSON-baserad auth-middleware.
     */
    public function __construct()
    {
        AuthMiddleware::checkJson();
    }

    /**
     * Hämta senaste importerade kunder per kundtyp.
     *
     * GET /api/latest
     *
     * @return void JSON-svar med nycklarna 'active' och 'temp'.
     */
    public function latest()
    {
        try {
            $history = new MailHistory();
            $history->ensureTable(); // Säkerställ att tabellen finns innan läsning.

            $latestActive = $history->getLatest(1);
            $latestTemp = $history->getLatest(2);

            $this->json([
                'active' => $latestActive,
                'temp' => $latestTemp
            ]);
        } catch (Exception $e) {
            error_log('ApiController latest error: ' . $e->getMessage());
            http_response_code(500);
            $this->json(['error' => 'Server error']);
        }
    }

    /**
     * Hämta nästa batch av kunder och uppdatera historik-cursorn.
     *
     * POST /api/fetch
     * Body (JSON): { customers_id?, customers_type }
     *  - customers_type: 1 = aktiv, 2 = temp.
     *
     * @return void JSON-svar med lista av kunder eller felmeddelande.
     */
    public function fetch()
    {
        $postData = json_decode(file_get_contents("php://input"));
        $customers_id = isset($postData->customers_id) ? intval($postData->customers_id) : 0;
        $customers_type = isset($postData->customers_type) ? intval($postData->customers_type) : 0;

        if ($customers_type <= 0 || ($customers_type >= 3)) {
            $this->json(['error' => 'Valores inválidos para customers_type.']);
            return;
        }

        try {
            $history = new MailHistory();
            $history->ensureTable();

            // Leta upp senaste kundnummer för angiven typ (cursor).
            $latestHistory = $history->getLatest($customers_type);
            $lastKundNr = 0;
            if ($latestHistory && isset($latestHistory['kundnr'])) {
                $lastKundNr = (int) $latestHistory['kundnr'];
            }

            $customerModel = new Customer();
            $users = $customerModel->getBatch($customers_type, $lastKundNr, 50);

            if (empty($users)) {
                $this->json(['message' => 'No se encontraron resultados.']);
                return;
            }

            // Spara sista raden i batchen som ny cursor i historiken.
            $latest = $users[count($users) - 1];
            $latestKundNr = (int) ($latest['KundNr'] ?? 0);
            $latestName = (string) ($latest['Name'] ?? '');
            $latestEmail = (string) ($latest['Email'] ?? '');

            if ($latestKundNr > 0 && $latestName !== '' && $latestEmail !== '') {
                $history->add($customers_type, $latestKundNr, $latestName, $latestEmail);
            }

            $this->json($users);

        } catch (Exception $e) {
            error_log('ApiController fetch error: ' . $e->getMessage());
            http_response_code(500);
            $this->json(['error' => 'Server error']);
        }
    }
}
