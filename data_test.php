<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users_mail";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connection successful<br>";
}

// Consulta de prueba
$sql = "SELECT DISTINCT
CONVERT(si_customers.name USING utf8) AS 'Name',
si_customers.email AS 'Email',
si_customers.id AS 'KundNr'
FROM si_customers
JOIN si_invoices ON si_customers.id = si_invoices.customer_id
WHERE si_customers.enabled = 2
AND si_customers.id > 7354
AND si_customers.name NOT LIKE '%Uppköpet%'"; // Cambia 'some_table' por el nombre de una tabla real
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Name: " . $row["Name"]. " - Email: " . $row["Email"]. "<br>"; // Ajusta los nombres de campo según tu tabla
    }
} else {
    echo "0 results";
}

$conn->close();
?>
