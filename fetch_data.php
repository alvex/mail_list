<?php
session_start();

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
}

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
    $latest = $users[count($users) - 1];
    $latestKundNr = (int)($latest['KundNr'] ?? 0);
    $latestName = (string)($latest['Name'] ?? '');
    $latestEmail = (string)($latest['Email'] ?? '');

    if ($latestKundNr > 0 && $latestName !== '' && $latestEmail !== '') {
        $sqlInsert = "INSERT INTO si_mail_history (customer_type, kundnr, name, email) VALUES (?, ?, ?, ?)";
        $stmtInsert = $conn->prepare($sqlInsert);
        if ($stmtInsert) {
            $stmtInsert->bind_param('iiss', $customers_type, $latestKundNr, $latestName, $latestEmail);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }
} catch (Exception $e) {
    error_log('fetch_data history insert error: ' . $e->getMessage());
}

echo json_encode($users);

$stmt->close();
$conn->close();
?>
