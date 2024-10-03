<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<title>Lista de Usuarios</title>

<!-- Dependencias -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>
<div class="container">
    <h1>Lista de Usuarios</h1>
    <form id="user-form">
        <div>
            <label for="customers_id">Customer ID:</label>
            <input type="number" id="customers_id" name="customers_id" required>
        </div>
        <div>
            <label for="customers_type">Customer Type:</label>
            <select id="customers_type" name="customers_type" required>
                <option value="1">Aktiv Kund</option>
                <option value="2">Temporär Kund</option>
            </select>
        </div>
        <button type="submit">Enviar</button>
    </form>
    <div id="message" style="color: red;"></div>
    <table id="user-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>ID</th>
            </tr>
        </thead>
        <tbody>
            <!-- Datos se llenarán aquí con JavaScript -->
        </tbody>
    </table>
    <button id="exportCsvBtn" class="fa fa-file-text-o" style="margin-top: 10px;"> Exportar CSV (User y Email)</button>
    <div id="results" style="white-space: pre-wrap; margin-top: 20px;"></div>
</div>

<script>
document.getElementById('user-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const customers_id = document.getElementById('customers_id').value;
    const customers_type = document.getElementById('customers_type').value;
    try {
        const response = await fetch('fetch_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customers_id, customers_type })
        });
        const result = await response.json();
        const messageDiv = document.getElementById('message');
        const userTable = document.getElementById('user-table').getElementsByTagName('tbody')[0];
        messageDiv.textContent = '';
        userTable.innerHTML = '';
        
        if (result.error) {
            messageDiv.textContent = result.error;
            console.error(result.error);
            return;
        }

        if (result.message) {
            messageDiv.textContent = result.message;
            return;
        }

        result.forEach((user) => {
            const row = userTable.insertRow();
            row.insertCell(0).textContent = user.Name;
            row.insertCell(1).textContent = user.Email;
            row.insertCell(2).textContent = user.KundNr;
        });

    } catch (error) {
        document.getElementById('message').textContent = 'Error al obtener los datos';
        console.error('Error fetching data:', error);
    }
});

// Función para convertir datos seleccionados de la tabla a CSV
function tableToCSV(table) {
    const rows = table.querySelectorAll('tr');
    const csvRows = [];
    
    // Procesar encabezados (solo User y Email)
    const headers = rows[0].querySelectorAll('th');
    csvRows.push([headers[0].textContent, headers[1].textContent].map(header => {
        if (header.includes(',') || header.includes('"') || header.includes('\n')) {
            return `"${header.replace(/"/g, '""')}"`;
        }
        return header;
    }).join(','));
    
    // Procesar filas de datos (solo User y Email)
    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].querySelectorAll('td');
        if (cells.length > 0) {
            const rowData = [cells[0].textContent, cells[1].textContent].map(text => {
                if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                    return `"${text.replace(/"/g, '""')}"`;
                }
                return text;
            });
            csvRows.push(rowData.join(','));
        }
    }
    
    return csvRows.join('\n');
}

// Función para descargar el CSV
function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

document.getElementById('exportCsvBtn').addEventListener('click', function() {
    const userTable = document.getElementById('user-table');
    const userTableBody = userTable.getElementsByTagName('tbody')[0];
    const messageDiv = document.getElementById('message');
    
    // Verificar si hay datos en la tabla
    if (userTableBody.rows.length === 0) {
        messageDiv.textContent = 'No hay datos para exportar. Por favor, busque usuarios primero.';
        messageDiv.style.color = 'red';
        return;
    }
    
    try {
        const csv = tableToCSV(userTable);
        downloadCSV(csv, 'user_data.csv');
        
        messageDiv.textContent = 'Exportación exitosa! (Solo User y Email)';
        messageDiv.style.color = 'green';
    } catch (error) {
        console.error('Error during export:', error);
        messageDiv.textContent = 'Error al exportar los datos: ' + error.message;
        messageDiv.style.color = 'red';
    }
});
</script>
</body>
</html>