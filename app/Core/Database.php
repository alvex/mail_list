<?php
namespace App\Core;

use mysqli;
use Exception;

// Ladda DB-konfiguration (env-vänlig, plattformsoberoende) via __DIR__.
require_once __DIR__ . '/../../config/database.php';

/**
 * Database
 *
 * Central fabrik för att skapa mysqli-anslutningar till olika databaser.
 *
 * Använder de konstanter som definieras i config/database.php, vilka i
 * sin tur kan styras via miljövariabler (ingen hårdkodad plattforms-path).
 */
class Database
{
    /**
     * Hämta en delad mysqli-anslutning för angiven databas.
     *
     * Väljer användare/lösen baserat på vilket DB-namn som efterfrågas
     * (users_mail, fresh_bokning, ev. invoices m.m.).
     *
     * @param string $dbName Databasnamn; default är DB_NAME.
     *
     * @return mysqli Öppen mysqli-anslutning med utf8mb4.
     *
     * @throws Exception Vid anslutningsfel.
     */
    public static function getConnection($dbName = DB_NAME)
    {
        // Grundvärden (standard-användare/lösen).
        $dbUser = DB_USER;
        $dbPass = DB_PASS;

        // Växla credentials beroende på valt DB-namn.
        if ($dbName === DB_NAME_USERS_MAIL) {
            $dbUser = DB_USER_USERS_MAIL;
            $dbPass = DB_PASS_USERS_MAIL;
        } elseif ($dbName === DB_NAME_FRESH_BOKNING) {
            $dbUser = DB_USER_FRESH_BOKNING;
            $dbPass = DB_PASS_FRESH_BOKNING;
        } elseif (defined('DB_NAME_HEMFRESH_INVOICESAB') && $dbName === constant('DB_NAME_HEMFRESH_INVOICESAB')) {
            $dbUser = defined('DB_USER_HEMFRESH_INVOICESAB') ? constant('DB_USER_HEMFRESH_INVOICESAB') : DB_USER;
            $dbPass = defined('DB_PASS_HEMFRESH_INVOICESAB') ? constant('DB_PASS_HEMFRESH_INVOICESAB') : DB_PASS;
        }

        // Anslutning via host/credentials från config (plattformsoberoende).
        $conn = new mysqli(DB_HOST, $dbUser, $dbPass, $dbName);

        if ($conn->connect_error) {
            error_log('Database connection error (' . $dbName . '): ' . $conn->connect_error);
            throw new Exception('Database connection failed');
        }

        // Säkerställ konsekvent teckenkodning.
        $conn->set_charset('utf8mb4');
        return $conn;
    }
}
