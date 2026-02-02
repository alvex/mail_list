<?php

/**
 * Funktionell auth-middleware för legacy-/procedural PHP-sidor.
 *
 * Denna fil innehåller fristående funktioner för att säkerställa att
 * en PHP-sida endast körs för inloggade användare och att sessions-
 * tidsgräns respekteras, både för HTML-svar och JSON-API:er.
 */

/**
 * Säkerställ att en PHP-session är startad.
 *
 * Startar `session_start()` om ingen session är aktiv ännu.
 * Detta kapslar session-hanteringen så att övrig kod slipper
 * upprepa samma kontroll.
 *
 * @return void
 */
function auth_require_session_started() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Kontrollera om aktuell session har gått ut baserat på timeout.
 *
 * @param int $timeoutSeconds Antal sekunder som får passera sedan
 *                            senaste aktivitet innan sessionen anses utgången.
 *
 * @return bool True om sessionen har gått ut, annars false.
 */
function auth_is_session_expired($timeoutSeconds) {
    if (!isset($_SESSION['last_activity'])) {
        // Ingen aktivitet loggad ännu → kan inte avgöra timeout, betraktas som aktiv.
        return false;
    }

    return (time() - (int)$_SESSION['last_activity']) > (int)$timeoutSeconds;
}

/**
 * Uppdatera sessionens "senaste aktivitet"-timestamp.
 *
 * Bör anropas efter en lyckad auth-kontroll för att förlänga
 * användarens aktiva session.
 *
 * @return void
 */
function auth_touch_activity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Rensa session och omdirigera användaren till inloggningssidan.
 *
 * @param string $loginPath Relativ eller absolut URL till login-sidan.
 *
 * @return void
 */
function auth_logout_and_redirect($loginPath) {
    session_unset();
    session_destroy();
    header('Location: ' . $loginPath);
    exit();
}

/**
 * Kräver att användaren är inloggad för HTML-sidor.
 *
 * - Startar session vid behov.
 * - Omdirigerar till login om ingen användare är inloggad.
 * - Loggar ut och omdirigerar om sessionen har gått ut.
 * - Uppdaterar "senaste aktivitet" vid godkänd access.
 *
 * @param string $loginPath       URL till login-sidan (default 'login.php').
 * @param int    $timeoutSeconds  Timeout i sekunder (default 1800 = 30 min).
 *
 * @return void
 */
function auth_require_html($loginPath = 'login.php', $timeoutSeconds = 1800) {
    auth_require_session_started();

    // Ingen aktiv användare → tvinga omdirigering till login.
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $loginPath);
        exit();
    }

    // Sessionen har gått ut → nollställ och omdirigera.
    if (auth_is_session_expired($timeoutSeconds)) {
        auth_logout_and_redirect($loginPath);
    }

    // Förläng sessionens aktiva livstid.
    auth_touch_activity();
}

/**
 * Kräver att användaren är inloggad för JSON-API:er.
 *
 * - Startar session vid behov.
 * - Returnerar 403 + JSON-body om ingen användare är inloggad.
 * - Returnerar 403 + JSON-body om sessionen har gått ut.
 * - Uppdaterar "senaste aktivitet" vid godkänd access.
 *
 * @param int $timeoutSeconds Timeout i sekunder (default 1800 = 30 min).
 *
 * @return void
 */
function auth_require_json($timeoutSeconds = 1800) {
    auth_require_session_started();

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }

    if (auth_is_session_expired($timeoutSeconds)) {
        session_unset();
        session_destroy();
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Session expired']);
        exit();
    }

    auth_touch_activity();
}
