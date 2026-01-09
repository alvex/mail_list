<?php
session_start();

// OBS: Denna endpoint returnerar kunders e-post; sessionskydd krävs.
// Check if user is logged in
$timeout = 30 * 60; // 30 minutes

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Content-Type: application/json');
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Session expired']);
    exit();
}

$_SESSION['last_activity'] = time();

header('Content-Type: application/json');
include 'config.php';

$debug = (isset($_GET['debug']) && (string)$_GET['debug'] === '1');
$debugInfo = [];

function debug_log_event($message, $context = []) {
    $payload = $context;
    $payload['message'] = $message;
    error_log('fetch_data debug: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// Syfte: Säkerställ att mail-historiktabellen finns.
// OBS: Tabellen används som en enkel cursor för att undvika att hämta samma kunder igen.
function ensure_mail_history_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS si_mail_history (" .
        "id INT AUTO_INCREMENT PRIMARY KEY," .
        "customer_type TINYINT NOT NULL," .
        "kundnr INT NOT NULL," .
        "name VARCHAR(255) NOT NULL," .
        "email VARCHAR(255) NOT NULL," .
        "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP," .
        "INDEX idx_customer_type_created_at (customer_type, created_at)" .
    ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        error_log('Error creating si_mail_history table: ' . $conn->error);
        throw new Exception('Database query failed');
    }

    $idCol = null;
    $idRes = $conn->query("SHOW COLUMNS FROM si_mail_history LIKE 'id'");
    if ($idRes) {
        $idCol = $idRes->fetch_assoc();
        $idRes->free();
    }

    if ($idCol && (!isset($idCol['Extra']) || stripos((string)$idCol['Extra'], 'auto_increment') === false)) {
        if (!$conn->query('ALTER TABLE si_mail_history MODIFY id INT NOT NULL AUTO_INCREMENT')) {
            error_log('Error altering si_mail_history.id to AUTO_INCREMENT: ' . $conn->error);
            throw new Exception('Database query failed');
        }
    }

    $hasPrimary = false;
    $pkRes = $conn->query("SHOW INDEX FROM si_mail_history WHERE Key_name = 'PRIMARY'");
    if ($pkRes) {
        $hasPrimary = ($pkRes->num_rows > 0);
        $pkRes->free();
    }

    if (!$hasPrimary) {
        if (!$conn->query('ALTER TABLE si_mail_history ADD PRIMARY KEY (id)')) {
            error_log('Error adding PRIMARY KEY to si_mail_history: ' . $conn->error);
            throw new Exception('Database query failed');
        }
    }
}

// Syfte: Hämta senast behandlade kund för given typ (används för UI-status + cursor).
function get_latest_mail_history($conn, $customerType) {
    $sql = "SELECT kundnr, name, email, created_at FROM si_mail_history WHERE customer_type = ? ORDER BY created_at DESC, id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }
    $stmt->bind_param('i', $customerType);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

try {
    ensure_mail_history_table($conn);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    $conn->close();
    exit();
}

if (isset($_GET['action']) && strtolower((string)$_GET['action']) === 'latest') {
    try {
        // Syfte: Status-endpoint för UI.
        $latestActive = get_latest_mail_history($conn, 1);
        $latestTemp = get_latest_mail_history($conn, 2);
        echo json_encode([
            'active' => $latestActive,
            'temp' => $latestTemp
        ]);
        $conn->close();
        exit();
    } catch (Exception $e) {
        error_log('fetch_data latest error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
        $conn->close();
        exit();
    }
}

// Syfte: Huvud-endpoint (POST). Returnerar nästa batch av kunder sedan senast kända cursor.
// OBS: customers_id finns kvar i request men används inte här; cursor kommer från historiken.
// Obtener datos del formulario enviado vía POST
$postData = json_decode(file_get_contents("php://input"));
//echo json_encode(['error' => 'Faltan datos requeridos: customers_id o customers_type no están definidos.']);
// Asegurarnos de que ambas variables estén definidas
/*if (!isset($postData->customers_id) || !isset($postData->customers_type)) {
    echo json_encode(['error' => 'Faltan datos requeridos: customers_id o customers_type no están definidos.']);
    exit();
}*/

$customers_id = isset($postData->customers_id) ? intval($postData->customers_id) : 0;
$customers_type = isset($postData->customers_type) ? intval($postData->customers_type) : 0;

//$customers_id = 7354;
//$customers_type = 2;

// Validar los valores recibidos
if ($customers_type <= 0 || ($customers_type >=3)) {
    echo json_encode(['error' => 'Valores inválidos para customers_type.']);
    exit();
}

$latestHistory = null;
try {
    $latestHistory = get_latest_mail_history($conn, $customers_type);
} catch (Exception $e) {
    error_log('fetch_data latest history read error: ' . $e->getMessage());
}

$lastKundNr = 0;
if ($latestHistory && isset($latestHistory['kundnr'])) {
    $lastKundNr = (int)$latestHistory['kundnr'];
}

// Affärsregel: Endast kunder som har minst en faktura (JOIN si_invoices) inkluderas.
// Affärsregel: Exkludera kunder med namn som matchar '%Uppköpet%'.
// OBS: Batchstorlek är fast för att undvika långkörande requests.
$sql = "SELECT DISTINCT
    CONVERT(si_customers.name USING utf8) AS 'Name',
    si_customers.email AS 'Email',
    si_customers.id AS 'KundNr'
FROM si_customers
JOIN si_invoices ON si_customers.id = si_invoices.customer_id
WHERE si_customers.enabled = ?
    AND si_customers.id > ?
    AND si_customers.name NOT LIKE '%Uppköpet%'
ORDER BY si_customers.id ASC
LIMIT ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Error preparando la declaración: ' . $conn->error]);
    exit();
}

$limit = 50;
$stmt->bind_param("iii", $customers_type, $lastKundNr, $limit);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['error' => 'Error en la consulta: ' . $stmt->error]);
    exit();
}

$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    echo json_encode(['message' => 'No se encontraron resultados.']);
    exit();
}

try {
    // Syfte: Spara sista raden i batchen som ny cursor.
    $latest = $users[count($users) - 1];
    $latestKundNr = (int)($latest['KundNr'] ?? 0);
    $latestName = (string)($latest['Name'] ?? '');
    $latestEmail = (string)($latest['Email'] ?? '');

    if ($debug) {
        $dbRow = null;
        $dbRes = $conn->query('SELECT DATABASE() AS db');
        if ($dbRes) {
            $dbRow = $dbRes->fetch_assoc();
            $dbRes->free();
        }
        $debugInfo['database'] = $dbRow['db'] ?? null;
        $debugInfo['latest'] = [
            'KundNr' => $latestKundNr,
            'NameLen' => strlen($latestName),
            'EmailLen' => strlen($latestEmail)
        ];
    }

    if ($latestKundNr > 0 && $latestName !== '' && $latestEmail !== '') {
        $sqlInsert = "INSERT INTO si_mail_history (customer_type, kundnr, name, email) VALUES (?, ?, ?, ?)";
        $stmtInsert = $conn->prepare($sqlInsert);
        if (!$stmtInsert) {
            if ($debug) {
                $debugInfo['insert'] = [
                    'prepared' => false,
                    'errno' => $conn->errno,
                    'error' => $conn->error
                ];
            }
            debug_log_event('insert prepare failed', [
                'errno' => $conn->errno,
                'error' => $conn->error
            ]);
        } else {
            $stmtInsert->bind_param('iiss', $customers_type, $latestKundNr, $latestName, $latestEmail);
            $ok = $stmtInsert->execute();

            if (!$ok) {
                if ($debug) {
                    $debugInfo['insert'] = [
                        'prepared' => true,
                        'executed' => false,
                        'errno' => $stmtInsert->errno,
                        'error' => $stmtInsert->error
                    ];
                }
                debug_log_event('insert execute failed', [
                    'errno' => $stmtInsert->errno,
                    'error' => $stmtInsert->error,
                    'customer_type' => $customers_type,
                    'kundnr' => $latestKundNr
                ]);
            } else {
                if ($debug) {
                    $debugInfo['insert'] = [
                        'prepared' => true,
                        'executed' => true,
                        'affected_rows' => $stmtInsert->affected_rows,
                        'insert_id' => $stmtInsert->insert_id
                    ];
                }
            }

            $stmtInsert->close();
        }
    }
} catch (Exception $e) {
    error_log('fetch_data history insert error: ' . $e->getMessage());
    if ($debug) {
        $debugInfo['exception'] = $e->getMessage();
    }
}

if ($debug) {
    echo json_encode(['users' => $users, 'debug' => $debugInfo]);
} else {
    echo json_encode($users);
}

$stmt->close();
$conn->close();
?>
