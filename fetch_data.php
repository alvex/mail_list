<?php
header('Content-Type: application/json');
include 'config.php';

// Obtener datos del formulario enviado vía POST
$postData = json_decode(file_get_contents("php://input"));
//echo json_encode(['error' => 'Faltan datos requeridos: customers_id o customers_type no están definidos.']);
// Asegurarnos de que ambas variables estén definidas
/*if (!isset($postData->customers_id) || !isset($postData->customers_type)) {
    echo json_encode(['error' => 'Faltan datos requeridos: customers_id o customers_type no están definidos.']);
    exit();
}*/

$customers_id = intval($postData->customers_id);
$customers_type = intval($postData->customers_type);

//$customers_id = 7354;
//$customers_type = 2;

// Validar los valores recibidos
if ($customers_id <= 1 || ($customers_type >=3)) {
    echo json_encode(['error' => 'Valores inválidos para customers_id o customers_type33.']);
    exit();
}

$sql = "SELECT DISTINCT
    CONVERT(si_customers.name USING utf8) AS 'Name',
    si_customers.email AS 'Email',
    si_customers.id AS 'KundNr'
    FROM si_customers
    JOIN si_invoices ON si_customers.id = si_invoices.customer_id
    WHERE si_customers.enabled = ?
    AND si_customers.id > ?
    AND si_customers.name NOT LIKE '%Uppköpet%'";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Error preparando la declaración: ' . $conn->error]);
    exit();
}

$stmt->bind_param("ii", $customers_type, $customers_id);
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

echo json_encode($users);

$stmt->close();
$conn->close();
?>
