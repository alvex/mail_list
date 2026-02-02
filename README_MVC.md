# Mail List – MVC-lager

Detta dokument beskriver den nya MVC-strukturen i projektet, hur routing fungerar, hur databaskopplingar och autentisering är uppsatta samt hur legacy-koden förhåller sig till MVC-lagret.

## Översikt

- **Webroot:** `public/` (t.ex. `public/index.php`, `public/login.php`), som fungerar som tunna wrappers runt befintliga entrypoints.
- **MVC-kod:** ligger under `app/`
  - `app/Core` – ramverk (Controller, Model, Router, Database)
  - `app/Controllers` – sid-/API-kontrollers
  - `app/Models` – affärslogik + databasåtkomst
  - `app/Views` – vyer (HTML + lite JS)
  - `app/middleware` – delad auth-middleware (procedural + klassbaserad)
- **Legacy-kod:** ligger kvar på rotnivå (t.ex. `index.php`, `kundlista.php`, `manage_users.php`) och anropas via `public/`-wrappers.

Målet är att successivt kunna flytta funktionalitet till MVC-lagret utan att bryta befintliga URL:er eller beteende.

---

## Core-komponenter (`app/Core`)  

### `Controller`

Bas-controller som alla MVC-kontrollers ärver från.

- `view(string $view, array $data = [])`
  - Renderar en view-fil genom att:
    - extrahera `$data` till lokala variabler
    - försöka ladda `app/Views/<view>.php`
    - om den inte finns, fall-back till `app/views/<view>.php` (case-fall back för olika filsystem)
- `json(mixed $data)`
  - Skickar JSON-svar med `Content-Type: application/json` och avslutar scriptet.

### `Model`

Abstrakt basmodell som ger tillgång till en delad `mysqli`-anslutning via `Database`.

- Konstruktorn tar ett valfritt `$dbName` (default `DB_NAME`) och hämtar anslutning via `Database::getConnection()`.

### `Database`

Central fabrik för databaskopplingar.

- Läser in `config/database.php` (som i sin tur använder miljövariabler när de finns).
- `getConnection(string $dbName = DB_NAME): mysqli`
  - Väljer användare/lösen baserat på `dbName` (`users_mail`, `fresh_bokning`, ev. invoices DB).
  - Returnerar `mysqli` med `utf8mb4` satt.

### `Router`

Enkel router för MVC-delen.

- `add($method, $path, $controller, $action)`
  - Registrerar rader i `$routes` (t.ex. `GET /`, `HomeController@index`).
- `dispatch($uri, $method)`
  - Normaliserar URI:
    - tar bort katalogprefix baserat på `dirname($_SERVER['SCRIPT_NAME'])`
    - normaliserar backslashes till `/` (Windows/Linux)
    - tar bort `/index.php` i början om det finns
  - Matchar mot registrerade routes och instansierar controller + anropar metod.
  - Returnerar enkel 404-sida om ingen route matchar.

> **Obs:** Själva bootstrap/router-entrypoint-fil (där Router-instansen skapas och routes registreras) ligger utanför `app/Core` och bör följas för att se exakt vilka routes som är aktiva.

---

## Middleware (`app/middleware`)

Det finns två varianter av auth-middleware:

1. **Procedural (`auth.php`)**
   - Används av legacy-sidor (`index.php`, `kundlista.php`, `manage_users.php`, `fetch_data.php`, `api_kundlista.php`).
   - Funktioner t.ex. `auth_require_html()` och `auth_require_json()`:
     - startar session
     - ser om användaren är inloggad
     - hanterar timeout (sessionens `last_activity`)
     - redirectar eller returnerar JSON-fel vid behov.

2. **Klassbaserad (`AuthMiddleware.php`)**
   - Används i nya MVC-controllers.
   - `AuthMiddleware::check()` – skyddar HTML-sidor
   - `AuthMiddleware::checkJson()` – skyddar JSON-/API-routes
   - Intern metod `isSessionExpired($timeout)` avgör om sessionen gått ut.

---

## Controllers (`app/Controllers`)

### `HomeController`

- `index()`
  - `AuthMiddleware::check()` → kräver inloggning.
  - Renderar `home/index` (e-postlista med API-integration mot `/api/latest` och `/api/fetch`).

### `AuthController`

- `login()`
  - Startar session om nödvändigt.
  - Om användare redan är inloggad → redirect till `/`.
  - Vid `POST`:
    - läser `username`/`password`
    - hämtar användare via `User::findByUsername()`
    - verifierar lösenord med `password_verify`
    - sätter `$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['last_activity']`
    - redirect till `/` vid lyckad inloggning
  - Vid fel → visar `auth/login` med felmeddelande.

- `logout()`
  - Startar session om nödvändigt.
  - `session_unset()` + `session_destroy()`.
  - Redirect till `/login`.

### `UserController`

- Konstruktorn:
  - `AuthMiddleware::check()` → alla actions kräver inloggning.

- `index()`
  - Sköter både formulärpost (create/update/delete) och vy-rendering.
  - Form-fall:
    - `add_user` → skapar ny admin-användare
    - `update_user` → uppdaterar befintlig användare (ev. nytt lösenord)
    - `delete_user` → tar bort användare (blockerar att ta bort sig själv)
  - Hämtar ev. `edit_user` via `User::findById()` för att förifylla formuläret.
  - Hämtar alla användare via `User::getAll()`.
  - Renderar `users/index` med tabell + formulär.

### `PhoneListController`

- `index()`
  - `AuthMiddleware::check()`
  - Renderar `phone_list/index` (UI för telefonlistor).

- `api()`
  - `AuthMiddleware::checkJson()`
  - Tar emot queryparametrar:
    - `type` – `active` eller `temp`
    - `action` – t.ex. `list` eller `csv`
    - `per_page`/`limit` – antal rader (begränsat till 1–500)
  - Använder `PhoneListModel` för att hämta kunder från rätt tabell beroende på `type`.
  - Om `action === 'csv'`:
    - sparar exporterade rader via `saveExportedPhones()`
    - genererar CSV via `toCsv()`
    - skickar fil-download.
  - Annars returneras JSON-lista med kunder.

### `AdminPhoneListController`

- Konstruktorn:
  - `AuthMiddleware::check()`.

- `index()`
  - Hanterar både:
    - visningsfilter (`GET`): `source` (active/temp), `q_name`, `q_phone`, `sort_by`, `sort_dir`, `view_limit`
    - export (`POST` med `action=export`): `start_memberid`, `export_limit`, `format` (csv/excel)
  - Använder `AdminPhoneListModel`:
    - `fetchSavedRows()` – lista av redan exporterade/sparade nummer
    - `getNextMemberIdStart()` – föreslagen startpunkt för export
    - `fetchExportRows()` – rader som ska exporteras
  - Vid export:
    - skickar antingen CSV (via `fputcsv`) eller en enkel HTML-tabell som Excel kan öppna.
  - Renderar `admin_phone_list/index` med tabell och filter.

### `ApiController`

- Konstruktorn:
  - `AuthMiddleware::checkJson()` – alla API-anrop är skyddade.

- `latest()` (GET `/api/latest`)
  - Använder `MailHistory`:
    - `ensureTable()` – skapar tabell om den saknas.
    - `getLatest(1)`/`getLatest(2)` – hämtar senaste behandlade kund per typ.
  - Returnerar JSON: `{ active: <rad eller null>, temp: <rad eller null> }`.

- `fetch()` (POST `/api/fetch`)
  - Läser JSON-body: `customers_type` (1/2) och valfri `customers_id`.
  - Använder `MailHistory` + `Customer`:
    - hämtar senast behandlad kund (`MailHistory::getLatest()`)
    - kallar `Customer::getBatch($customers_type, $lastKundNr, 50)`
    - om tomt resultat → JSON med meddelande
    - annars sparas sista kund i batchen som ny cursor via `MailHistory::add()`
  - Returnerar JSON-lista med kunder.

---

## Models (`app/Models`)

### `User`

Arbetar mot `admin_users`-tabellen.

- `findByUsername($username)` – används vid login.
- `findById($id)` – används när en användare ska redigeras.
- `checkUsernameExists($username, $excludeId = null)` – kontrollerar unika användarnamn.
- `create($username, $hashed_password)` – lägger till ny admin-användare.
- `update($id, $username, $hashed_password = null)` – uppdaterar namn och ev. lösen.
- `delete($id)` – tar bort användare.
- `getAll()` – listar alla admin-användare (för tabell i `users/index`).

### `MailHistory`

Hantera tabellen `si_mail_history` (cursor per kundtyp).

- `ensureTable()` – skapar tabell om den inte finns.
- `getLatest($customerType)` – senaste rad för angiven kundtyp.
- `add($customerType, $kundnr, $name, $email)` – lägger till ny historikrad.

### `Customer`

Läser från kunddatabas(er) för e-postlistan.

- `getBatch($customers_type, $lastKundNr, $limit)` – hämtar nästa batch av kunder efter angiven cursor (kundnummer), separerat på aktiv/temp.

### `PhoneListModel`

Arbetar mot telefonlisttabellerna `cal_phone_list` (active) och `si_phone_list` (temp).

Vanliga ansvarsområden:
- ta reda på senaste sparade `memberid` per tabell (`getLastMemberId()`)
- hämta nya rader sedan dess (`fetchActiveSince()`, `fetchTempSince()`) med begränsning
- normalisera data (bl.a. telefonnummer)
- spara exporterade rader tillbaka till tabellerna (`saveExportedPhones()`)
- generera CSV för export (`toCsv()`).

### `AdminPhoneListModel`

Stöd för admin-vyn av telefonlistan.

- `fetchSavedRows($source, $qPhone, $qName, $sortBy, $sortDir, $viewLimit)`
  - Hämtar redan sparade rader från vald tabell.
  - Detekterar om tabellen har `name`, `phone` och `created_at`-kolumner.
- `getNextMemberIdStart($source)`
  - Föreslår start-`memberid` för export.
- `fetchExportRows($source, $startMemberId, $exportLimit, $sortBy, $sortDir, $qPhone, $qName)`
  - Hämtar rader att exportera baserat på filter, sortering och start.

---

## Views (`app/Views`)

### `home/index.php`

- Visar e-postlista för API-flödet.
- Använder `top_menu`-partial.
- JS:
  - `loadLatestSummary()` – anropar `/api/latest` och renderar senaste aktiva och temporära kunder.
  - Form-submission – POST till `/api/fetch` med `customers_type` och skapar tabell i klienten.
  - CSV-export – exporterar tabellen på klientsidan till CSV (namn + e-post).

### `auth/login.php`

- Standard login-formulär.
- Visar `$error` (HTML-escapat) om inloggning misslyckas.

### `users/index.php`

- Admin-view för hantering av användare.
- Visar meddelanden (`$message`, `$error`).
- Form för skapa/uppdatera användare samt tabell över befintliga användare.

### `phone_list/index.php`

- UI för att hämta/exportera telefonlistor via `/api/kundlista`.
- Låter användaren välja typ (`active`/`temp`) och per-page-limit.
- Exportknapp som triggar CSV-export (servergenererad) från samma API.

### `admin_phone_list/index.php`

- Admin-UI för filtrering, visning och export av telefonlistor.
- Två huvudsakliga sektioner:
  - Filter/val för att se sparade rader.
  - Exportsektion som skapar CSV/“Excel”-fil.

### `partials/top_menu.php`

- Gemensam toppmeny använd av flera MVC-views.
- Funktion `topmenu_active_class($page, $activePage)` sätter CSS-klass för aktiv länk.
- Länkar går alltid via `BASE_PATH` (t.ex. `BASE_PATH . '/kundlista'`).

---

## Auth- och sessionflöde

- MVC-routes:
  - Använder `App\Middleware\AuthMiddleware`.
  - `HomeController`, `UserController`, `PhoneListController`, `AdminPhoneListController`, `ApiController` använder auth-middleware direkt.
- Legacy-routes:
  - Använder funktionerna i `app/middleware/auth.php`.

Båda varianterna förlitar sig på `$_SESSION['user_id']` samt `$_SESSION['last_activity']` för timeout.

---

## Plattformoberoende

- Inga hårdkodade Windows-paths i MVC-lagret.
- Alla `require` inom `app/` använder `__DIR__` + relativa paths.
- Router normaliserar backslashes till framåtsnedstreck.
- Webblänkar går via `BASE_PATH`.
- DB-konfiguration styrs via `config/database.php` som i sin tur läser miljövariabler där det är möjligt.

---

## Vidare arbete / tips

- **Migrering av legacy-sidor:**
  - Stegvis flytta logik från t.ex. `kundlista.php` och `manage_users.php` till motsvarande MVC-controller + views.
  - Behåll `public/*.php`-wrappers för att inte bryta inlänkade URL:er.

- **Enhetstester / smoke tests:**
  - Lägg gärna till enkla HTTP-smoke tests (t.ex. med cURL eller ett testverktyg) för att säkerställa att /, /login, /users, /kundlista, /phone_list, /api/* svarar som förväntat efter framtida ändringar.
