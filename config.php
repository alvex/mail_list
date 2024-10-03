<?php
$servername = "localhost"; // Cambia por tu servidor de base de datos
$username = "root"; // Cambia por tu usuario de base de datos
$password = ""; // Cambia por tu contraseña de base de datos
$dbname = "users_mail"; // Cambia por el nombre de tu base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
  //  echo "Connection successful";
    // exit(); // Evitar el uso de exit en este punto, ya que detendría la ejecución
}

// A partir de aquí, puedes añadir código para realizar consultas a tu base de datos.

// Cerrar la conexión al final del script
//$conn->close();
?>
