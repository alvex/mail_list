<?php
namespace App\Middleware;

/**
 * AuthMiddleware
 *
 * Klass-baserad middleware för den nya MVC-delen av applikationen.
 * Används från controllers för att skydda routes (HTML eller JSON)
 * bakom session-baserad inloggning med enkel timeout-hantering.
 */
class AuthMiddleware
{
    /**
     * Kontrollera att användaren är inloggad för HTML-routes.
     *
     * - Startar session om nödvändigt.
     * - Omdirigerar till login om ingen användare är inloggad.
     * - Loggar ut och omdirigerar om sessionen har gått ut.
     * - Uppdaterar "senaste aktivitet" på lyckad kontroll.
     *
     * @return void
     */
    public static function check()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Ingen användare i sessionen → skicka till login-routen.
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_PATH . '/login');
            exit();
        }

        // Sessionen har gått ut enligt timeout-regeln.
        if (self::isSessionExpired(1800)) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_PATH . '/login');
            exit();
        }

        // Förläng sessionens livslängd vid aktiv användning.
        $_SESSION['last_activity'] = time();
    }

    /**
     * Kontrollera att användaren är inloggad för JSON-API-routes.
     *
     * - Startar session om nödvändigt.
     * - Returnerar HTTP 403 + JSON body vid obehörig eller utgången session.
     * - Uppdaterar "senaste aktivitet" på lyckad kontroll.
     *
     * @return void
     */
    public static function checkJson()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access']);
            exit();
        }

        if (self::isSessionExpired(1800)) {
            session_unset();
            session_destroy();
            http_response_code(403);
            echo json_encode(['error' => 'Session expired']);
            exit();
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Hjälpfunktion för att avgöra om sessionen har gått ut.
     *
     * @param int $timeout Timeout i sekunder.
     *
     * @return bool True om sessionen anses utgången, annars false.
     */
    private static function isSessionExpired($timeout)
    {
        if (!isset($_SESSION['last_activity'])) {
            // Ingen aktivitet registrerad ännu → betrakta sessionen som aktiv.
            return false;
        }

        return (time() - (int) $_SESSION['last_activity']) > (int) $timeout;
    }
}
