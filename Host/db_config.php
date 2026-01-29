<?php

// Databasserver som applikationen ansluter mot
define('DB_HOST', 'localhost');

// Inloggningsuppgifter (användare/lösen) för den "generella" databasanvändaren
// Namnet antyder USERS_MAIL men används nedan som bas för flera delar av systemet
define('DB_USER_USERS_MAIL', 'hemfresh_invoices0909');
define('DB_PASS_USERS_MAIL', 'MisVainas0909');

// Standard-användare/lösen som övriga konstanter pekar på
define('DB_USER', DB_USER_USERS_MAIL);
define('DB_PASS', DB_PASS_USERS_MAIL);

// Databasanvändare för fakturadelen (invoices)
// OBS: pekar just nu på samma användare/lösen som DB_USER
define('DB_USER_INVOICES', DB_USER);
define('DB_PASS_INVOICES', DB_PASS);


// Separat databasanvändare för FRESH_BOKNING-modulen
define('DB_USER_FRESH_BOKNING', 'hemfresh_schedule');
define('DB_PASS_FRESH_BOKNING', 'MisVainas0909');

// Databasanvändare för bokningsdelen
// OBS: pekar just nu på samma användare/lösen som DB_USER
define('DB_USER_BOKNING', DB_USER_FRESH_BOKNING);
define('DB_PASS_BOKNING', DB_PASS_FRESH_BOKNING);

// Standarddatabas för befintliga delar av applikationen (fakturor m.m.)
define('DB_NAME', 'hemfresh_invoicesab');

// Databas som används för "users mail"-delen
// OBS: samma databasnamn som DB_NAME i nuläget
define('DB_NAME_USERS_MAIL', 'hemfresh_invoicesab');

// Databas som används för FRESH_BOKNING-modulen
define('DB_NAME_FRESH_BOKNING', 'hemfresh_bokning');

