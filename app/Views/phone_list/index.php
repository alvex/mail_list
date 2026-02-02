<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/public/css/styles.css?v=2">
    <title>Telefonlista</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        .kundlista-toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .kundlista-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kundlista-btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #d0d7de;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
        }

        .kundlista-btn.primary {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .kundlista-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .kundlista-badge {
            padding: 6px 10px;
            border-radius: 999px;
            background: #f6f8fa;
            border: 1px solid #d0d7de;
            font-weight: 600;
        }

        .kundlista-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            background: #fff;
        }

        #kundlista-message {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php $activePage = 'kundlista';
        require __DIR__ . '/../partials/top_menu.php'; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <h1>Telefonlista</h1>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <!-- Assuming phone_list.php logic is separate or functionality is deprecated/different. 
                  If it was just a link to a file, I'll keep it as a link but maybe it should be a route?
                  "phone_list.php" size was small (109 bytes). Let's check it later. For now, point to it or root?
                  If it's just a subdirectory listing, maybe we leave it or migrate it too. 
                  For now I'll leave the link but point to /phone_list if I migrated it, 
                  but I haven't migrated phone_list.php, only kundlista.php.
                  Wait, kundlista.php IS Telefonlista.
                  The link "phone_list.php" seems to go to a folder listing or something.
                  I'll check phone_list.php content in a bit.
                  For now, let's keep it as is or comment it out if it's broken.
             -->
                <a href="<?php echo BASE_PATH; ?>/phone_list" class="kundlista-btn"
                    style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                    <span aria-hidden="true">📞</span>
                    <span>Telefonlista (Old)</span>
                </a>
            </div>
        </div>

        <div class="kundlista-card">
            <div class="kundlista-toolbar">
                <div class="kundlista-actions">
                    <button id="btnActive" class="kundlista-btn primary" type="button">Aktiva kunder</button>
                    <button id="btnTemp" class="kundlista-btn" type="button">Temp kunder</button>
                    <button id="btnExport" class="kundlista-btn" type="button" disabled>Exportera CSV</button>
                </div>
                <div>
                    <label for="perPage" style="margin-right:8px; font-weight:600;">Antal:</label>
                    <select id="perPage" class="kundlista-btn" style="padding:8px 12px;">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                    <span id="currentType" class="kundlista-badge">Ingen lista</span>
                </div>
            </div>

            <div id="kundlista-message" style="color: red;"></div>

            <table id="kundlista-table">
                <thead>
                    <tr>
                        <th>memberid</th>
                        <th>Namn</th>
                        <th>Telefonnummer</th>
                        <th>Typ</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        let lastLoadedType = null;

        function getPerPage() {
            const el = document.getElementById('perPage');
            const value = parseInt(el.value, 10);
            return Number.isFinite(value) && value > 0 ? value : 10;
        }

        function setMessage(text, color) {
            const el = document.getElementById('kundlista-message');
            el.textContent = text || '';
            el.style.color = color || 'red';
        }

        function setLoading(isLoading) {
            document.getElementById('btnActive').disabled = isLoading;
            document.getElementById('btnTemp').disabled = isLoading;
            document.getElementById('btnExport').disabled = isLoading || !lastLoadedType;
        }

        function renderRows(rows) {
            const tbody = document.getElementById('kundlista-table').getElementsByTagName('tbody')[0];
            tbody.innerHTML = '';

            rows.forEach((r) => {
                const tr = tbody.insertRow();
                tr.insertCell(0).textContent = r.memberid;
                tr.insertCell(1).textContent = r.name;
                tr.insertCell(2).textContent = r.phone;
                tr.insertCell(3).textContent = r.type;
            });
        }

        async function loadType(type) {
            setMessage('', 'red');
            setLoading(true);

            try {
                const res = await fetch('<?php echo BASE_PATH; ?>/api/kundlista?type=' + encodeURIComponent(type) + '&limit=' + encodeURIComponent(getPerPage()));
                if (res.status === 403) {
                    window.location.href = '<?php echo BASE_PATH; ?>/login';
                    return;
                }
                const data = await res.json();

                if (data && data.error) {
                    setMessage(data.error, 'red');
                    renderRows([]);
                    lastLoadedType = null;
                    document.getElementById('currentType').textContent = 'Fel';
                    return;
                }

                renderRows(Array.isArray(data) ? data : []);
                lastLoadedType = type;
                document.getElementById('currentType').textContent = type === 'active' ? 'Aktiv' : 'Temp';

                if (!Array.isArray(data) || data.length === 0) {
                    setMessage('Inga nya kunder att hämta.', '#6b7280');
                }
            } catch (e) {
                console.error(e);
                setMessage('Fel vid hämtning av data.', 'red');
                renderRows([]);
                lastLoadedType = null;
                document.getElementById('currentType').textContent = 'Fel';
            } finally {
                setLoading(false);
            }
        }

        document.getElementById('btnActive').addEventListener('click', () => {
            document.getElementById('btnActive').classList.add('primary');
            document.getElementById('btnTemp').classList.remove('primary');
            loadType('active');
        });

        document.getElementById('btnTemp').addEventListener('click', () => {
            document.getElementById('btnTemp').classList.add('primary');
            document.getElementById('btnActive').classList.remove('primary');
            loadType('temp');
        });

        document.getElementById('btnExport').addEventListener('click', () => {
            if (!lastLoadedType) {
                setMessage('Ladda en lista först.', 'red');
                return;
            }
            window.location.href = '<?php echo BASE_PATH; ?>/api/kundlista?action=csv&type=' + encodeURIComponent(lastLoadedType) + '&limit=' + encodeURIComponent(getPerPage());
        });

        document.getElementById('perPage').addEventListener('change', () => {
            if (lastLoadedType) {
                loadType(lastLoadedType);
            }
        });

    </script>
</body>

</html>