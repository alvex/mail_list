<?php

/**
 * Databas-konfiguration.
 *
 * Den här filen definierar konstanter för databasanslutningar.
 * Alla värden kan överskridas via miljövariabler för att undvika
 * hårdkodade credentials och för att förenkla drift i olika miljöer
 * (Windows/Linux, dev/stage/prod).
 *
 * Miljövariabler (om satta) har företräde framför defaultvärdena nedan:
 *  - DB_HOST
 *  - DB_USER_USERS_MAIL, DB_PASS_USERS_MAIL
 *  - DB_USER_FRESH_BOKNING, DB_PASS_FRESH_BOKNING
 *  - DB_NAME (default-databas)
 *  - DB_NAME_USERS_MAIL, DB_NAME_FRESH_BOKNING
 */

// Standardvärden med möjlighet att överskrida via miljövariabler.
$dbHost = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
define('DB_HOST', $dbHost);

$dbUserUsersMail = getenv('DB_USER_USERS_MAIL') !== false ? getenv('DB_USER_USERS_MAIL') : 'root';
$dbPassUsersMail = getenv('DB_PASS_USERS_MAIL') !== false ? getenv('DB_PASS_USERS_MAIL') : '';
define('DB_USER_USERS_MAIL', $dbUserUsersMail);
define('DB_PASS_USERS_MAIL', $dbPassUsersMail);

define('DB_USER', DB_USER_USERS_MAIL);
define('DB_PASS', DB_PASS_USERS_MAIL);

define('DB_USER_INVOICES', DB_USER);
define('DB_PASS_INVOICES', DB_PASS);
define('DB_USER_BOKNING', DB_USER);
define('DB_PASS_BOKNING', DB_PASS);

$dbUserFreshBokning = getenv('DB_USER_FRESH_BOKNING') !== false ? getenv('DB_USER_FRESH_BOKNING') : 'root';
$dbPassFreshBokning = getenv('DB_PASS_FRESH_BOKNING') !== false ? getenv('DB_PASS_FRESH_BOKNING') : '';
define('DB_USER_FRESH_BOKNING', $dbUserFreshBokning);
define('DB_PASS_FRESH_BOKNING', $dbPassFreshBokning);

// Default database used by existing app parts
$dbNameDefault = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'users_mail';
define('DB_NAME', $dbNameDefault);

$dbNameUsersMail = getenv('DB_NAME_USERS_MAIL') !== false ? getenv('DB_NAME_USERS_MAIL') : 'users_mail';
$dbNameFreshBokning = getenv('DB_NAME_FRESH_BOKNING') !== false ? getenv('DB_NAME_FRESH_BOKNING') : 'fresh_bokning';
define('DB_NAME_USERS_MAIL', $dbNameUsersMail);
define('DB_NAME_FRESH_BOKNING', $dbNameFreshBokning);
