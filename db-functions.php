<?php
// Asegurarse de que el archivo de configuración esté incluido
require_once 'db_config.php';

/**
 * Establece una conexión con la base de datos
 * @return mysqli La conexión a la base de datos
 * @throws Exception Si la conexión falla
 */
function getDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Guarda el historial de búsqueda en la base de datos
 * @param int $highestCustomerNumber El número de cliente más alto encontrado
 * @param int $customerType El tipo de cliente (1 para Aktiv, 2 para Temporär)
 * @return bool True si se guardó correctamente, False si hubo un error
 */
function saveSearchHistory($highestCustomerNumber, $customerType) {
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("INSERT INTO search_history (highest_customer_number, customer_type) VALUES (?, ?)");
        $stmt->bind_param("ii", $highestCustomerNumber, $customerType);
        $success = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $success;
    } catch (Exception $e) {
        error_log("Error saving search history: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene las búsquedas recientes de la base de datos
 * @return array Un array con las últimas búsquedas (2 de cada tipo de cliente)
 */
function getRecentSearches() {
    try {
        $conn = getDbConnection();
        $sql = "
            (SELECT * FROM search_history WHERE customer_type = 1 ORDER BY search_date DESC LIMIT 2)
            UNION ALL
            (SELECT * FROM search_history WHERE customer_type = 2 ORDER BY search_date DESC LIMIT 2)
            ORDER BY customer_type, search_date DESC
        ";
        $result = $conn->query($sql);
        $searches = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $searches[] = $row;
            }
        }
        
        $conn->close();
        return $searches;
    } catch (Exception $e) {
        error_log("Error getting recent searches: " . $e->getMessage());
        return [];
    }
}

/**
 * Limpia y valida los datos de entrada
 * @param mixed $data Los datos a limpiar
 * @return mixed Los datos limpiados
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Verifica si una búsqueda es válida
 * @param int $customerNumber El número de cliente
 * @param int $customerType El tipo de cliente
 * @return bool True si la búsqueda es válida, False si no lo es
 */
function isValidSearch($customerNumber, $customerType) {
    return (
        is_numeric($customerNumber) && 
        $customerNumber > 0 && 
        is_numeric($customerType) && 
        ($customerType == 1 || $customerType == 2)
    );
}

/**
 * Elimina registros antiguos para mantener la tabla limpia
 * Se sugiere ejecutar periódicamente, no en cada búsqueda
 * @param int $daysToKeep Número de días de registros a mantener
 * @return bool True si se ejecutó correctamente, False si hubo un error
 */
function cleanOldRecords($daysToKeep = 30) {
    try {
        $conn = getDbConnection();
        $sql = "DELETE FROM search_history WHERE search_date < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $daysToKeep);
        $success = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $success;
    } catch (Exception $e) {
        error_log("Error cleaning old records: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene estadísticas de búsqueda
 * @return array Un array con estadísticas de búsqueda
 */
function getSearchStats() {
    try {
        $conn = getDbConnection();
        $sql = "
            SELECT 
                customer_type,
                COUNT(*) as total_searches,
                MAX(highest_customer_number) as max_customer_number,
                MIN(highest_customer_number) as min_customer_number
            FROM search_history
            GROUP BY customer_type
        ";
        $result = $conn->query($sql);
        $stats = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $stats[$row['customer_type']] = $row;
            }
        }
        
        $conn->close();
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting search stats: " . $e->getMessage());
        return [];
    }
}
?>
