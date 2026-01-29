<?php
session_start();

$timeout = 30 * 60; // 30 minutes

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['last_activity'] = time();

require_once 'db_multi.php';
require_once 'kundlista_service.php';

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function phone_list_redirect($url) {
    header('Location: ' . $url);
    exit();
}

function phone_list_set_flash($type, $message) {
    $_SESSION['phone_list_flash_' . $type] = (string)$message;
}

function phone_list_get_flash($type) {
    $key = 'phone_list_flash_' . $type;
    if (!isset($_SESSION[$key])) {
        return '';
    }
    $value = (string)$_SESSION[$key];
    unset($_SESSION[$key]);
    return $value;
}

function phone_list_require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        echo 'Bad request';
        exit();
    }
}

function phone_list_validate_phone($phoneRaw) {
    $phoneRaw = trim((string)$phoneRaw);
    if ($phoneRaw === '') {
        return [null, 'Telefonnummer krävs.'];
    }

    if (!preg_match('/^[0-9+\-\s]+$/', $phoneRaw)) {
        return [null, 'Ogiltigt telefonformat. Endast siffror, +, -, mellanslag tillåts.'];
    }

    $normalized = preg_replace('/[\s\-]+/', '', $phoneRaw);
    $digits = preg_replace('/\D+/', '', (string)$normalized);
    if ($digits === '' || strlen($digits) < 5) {
        return [null, 'Telefonnumret verkar vara för kort.'];
    }

    return [$normalized, ''];
}

function phone_list_validate_customer_id($customerIdRaw) {
    if ($customerIdRaw === null || $customerIdRaw === '') {
        return [null, ''];
    }

    if (!preg_match('/^\d+$/', (string)$customerIdRaw)) {
        return [null, 'Ogiltigt kund-ID.'];
    }

    $id = (int)$customerIdRaw;
    if ($id <= 0) {
        return [null, 'Ogiltigt kund-ID.'];
    }

    return [$id, ''];
}

function phone_list_db() {
    return getDbConnectionFor(DB_NAME_FRESH_BOKNING);
}

function phone_list_insert_or_update($conn, $phone, $name, $memberId) {
    if ($memberId === null) {
        $sql = "INSERT INTO cal_phone_list (phone, name, memberid, created_at) VALUES (?, ?, 0, NOW())\n                ON DUPLICATE KEY UPDATE\n                    name = VALUES(name)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database query failed');
        }
        $stmt->bind_param('ss', $phone, $name);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            throw new Exception('Database query failed');
        }
        return;
    }

    $sql = "INSERT INTO cal_phone_list (phone, name, memberid, created_at) VALUES (?, ?, ?, NOW())\n            ON DUPLICATE KEY UPDATE\n                name = VALUES(name),\n                memberid = COALESCE(VALUES(memberid), memberid)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }
    $stmt->bind_param('ssi', $phone, $name, $memberId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new Exception('Database query failed');
    }
}

function phone_list_update_row($conn, $id, $phone, $name, $memberId) {
    if ($memberId === null) {
        $sql = 'UPDATE cal_phone_list SET phone = ?, name = ?, memberid = 0 WHERE id = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database query failed');
        }
        $stmt->bind_param('ssi', $phone, $name, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            throw new Exception('Database query failed');
        }
        return;
    }

    $sql = 'UPDATE cal_phone_list SET phone = ?, name = ?, memberid = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }
    $stmt->bind_param('ssii', $phone, $name, $memberId, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new Exception('Database query failed');
    }
}

function phone_list_delete_row($conn, $id) {
    $sql = 'DELETE FROM cal_phone_list WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new Exception('Database query failed');
    }
}

function phone_list_fetch_rows($conn, $qPhone, $qName) {
    $qPhone = trim((string)$qPhone);
    $qName = trim((string)$qName);

    $where = [];
    $params = [];
    $types = '';

    if ($qPhone !== '') {
        $where[] = 'phone LIKE ?';
        $params[] = '%' . $qPhone . '%';
        $types .= 's';
    }

    if ($qName !== '') {
        $where[] = 'name LIKE ?';
        $params[] = '%' . $qName . '%';
        $types .= 's';
    }

    $sql = 'SELECT id, phone, name, memberid, created_at FROM cal_phone_list';
    if (count($where) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 500';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query failed');
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function phone_list_export_csv($rows, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $lines = [];
    $lines[] = '"Telefon";"Namn";"MemberID";"Skapad"';

    foreach ($rows as $r) {
        $phone = str_replace('"', '""', (string)($r['phone'] ?? ''));
        $name = str_replace('"', '""', (string)($r['name'] ?? ''));
        $cid = str_replace('"', '""', (string)($r['memberid'] ?? ''));
        $created = str_replace('"', '""', (string)($r['created_at'] ?? ''));
        $lines[] = '"' . $phone . '";"' . $name . '";"' . $cid . '";"' . $created . '"';
    }

    echo implode("\n", $lines);
    exit();
}

function phone_list_export_excel_html($rows, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "<table border=\"1\">";
    echo "<tr><th>Telefon</th><th>Namn</th><th>MemberID</th><th>Skapad</th></tr>";

    foreach ($rows as $r) {
        $phone = htmlspecialchars((string)($r['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cid = htmlspecialchars((string)($r['memberid'] ?? ''), ENT_QUOTES, 'UTF-8');
        $created = htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo "<tr><td>{$phone}</td><td>{$name}</td><td>{$cid}</td><td>{$created}</td></tr>";
    }

    echo "</table>";
    exit();
}

phone_list_require_csrf();

$flashSuccess = phone_list_get_flash('success');
$flashError = phone_list_get_flash('error');

$action = isset($_GET['action']) ? strtolower(trim((string)$_GET['action'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = isset($_POST['action']) ? strtolower(trim((string)$_POST['action'])) : '';

    try {
        $conn = phone_list_db();

        if ($postAction === 'create') {
            list($phone, $phoneErr) = phone_list_validate_phone($_POST['phone'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            list($memberId, $cidErr) = phone_list_validate_customer_id($_POST['memberid'] ?? null);

            if ($phoneErr !== '' || $cidErr !== '' || $name === '') {
                $msg = $phoneErr !== '' ? $phoneErr : ($cidErr !== '' ? $cidErr : 'Namn krävs.');
                phone_list_set_flash('error', $msg);
                $conn->close();
                phone_list_redirect('phone_list.php');
            }

            phone_list_insert_or_update($conn, $phone, $name, $memberId);
            $conn->close();
            phone_list_set_flash('success', 'Telefonnummer sparat.');
            phone_list_redirect('phone_list.php');
        }

        if ($postAction === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                throw new Exception('Invalid id');
            }

            list($phone, $phoneErr) = phone_list_validate_phone($_POST['phone'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            list($memberId, $cidErr) = phone_list_validate_customer_id($_POST['memberid'] ?? null);

            if ($phoneErr !== '' || $cidErr !== '' || $name === '') {
                $msg = $phoneErr !== '' ? $phoneErr : ($cidErr !== '' ? $cidErr : 'Namn krävs.');
                phone_list_set_flash('error', $msg);
                $conn->close();
                phone_list_redirect('phone_list.php?edit=' . urlencode((string)$id));
            }

            phone_list_update_row($conn, $id, $phone, $name, $memberId);
            $conn->close();
            phone_list_set_flash('success', 'Telefonnummer uppdaterat.');
            phone_list_redirect('phone_list.php');
        }

        if ($postAction === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                throw new Exception('Invalid id');
            }

            phone_list_delete_row($conn, $id);
            $conn->close();
            phone_list_set_flash('success', 'Telefonnummer borttaget.');
            phone_list_redirect('phone_list.php');
        }

        if ($postAction === 'export_dynamic') {
            $source = isset($_POST['source']) ? strtolower(trim((string)$_POST['source'])) : '';
            $format = isset($_POST['format']) ? strtolower(trim((string)$_POST['format'])) : 'csv';

            if ($source !== 'active' && $source !== 'temp') {
                $conn->close();
                phone_list_set_flash('error', 'Ogiltig källa för export.');
                phone_list_redirect('phone_list.php');
            }

            $customers = $source === 'active' ? kundlista_get_active_customers() : kundlista_get_temp_customers();

            foreach ($customers as $c) {
                $phone = (string)($c['phone'] ?? '');
                $name = (string)($c['name'] ?? '');
                $memberId = isset($c['memberid']) ? (int)$c['memberid'] : null;

                list($phoneNorm, $phoneErr) = phone_list_validate_phone($phone);
                if ($phoneErr !== '' || $phoneNorm === null) {
                    continue;
                }

                $cid = $memberId !== null && $memberId > 0 ? $memberId : null;
                $safeName = trim($name) !== '' ? trim($name) : ('Kund ' . (string)$memberId);

                phone_list_insert_or_update($conn, $phoneNorm, $safeName, $cid);
            }

            $qPhone = isset($_POST['q_phone']) ? (string)$_POST['q_phone'] : '';
            $qName = isset($_POST['q_name']) ? (string)$_POST['q_name'] : '';
            $rows = phone_list_fetch_rows($conn, $qPhone, $qName);
            $conn->close();

            $date = date('Ymd');
            $base = $source === 'active' ? 'telefonlista_aktiva_' : 'telefonlista_temp_';

            if ($format === 'excel') {
                phone_list_export_excel_html($rows, $base . $date . '.xls');
            }

            phone_list_export_csv($rows, $base . $date . '.csv');
        }

        if ($postAction === 'export_saved') {
            $format = isset($_POST['format']) ? strtolower(trim((string)$_POST['format'])) : 'csv';
            $qPhone = isset($_POST['q_phone']) ? (string)$_POST['q_phone'] : '';
            $qName = isset($_POST['q_name']) ? (string)$_POST['q_name'] : '';

            $rows = phone_list_fetch_rows($conn, $qPhone, $qName);
            $conn->close();

            $date = date('Ymd');
            if ($format === 'excel') {
                phone_list_export_excel_html($rows, 'telefonlista_sparad_' . $date . '.xls');
            }
            phone_list_export_csv($rows, 'telefonlista_sparad_' . $date . '.csv');
        }

        $conn->close();
        phone_list_set_flash('error', 'Okänd åtgärd.');
        phone_list_redirect('phone_list.php');
    } catch (Exception $e) {
        error_log('phone_list error: ' . $e->getMessage());
        phone_list_set_flash('error', 'Serverfel.');
        phone_list_redirect('phone_list.php');
    }
}

$qPhone = isset($_GET['q_phone']) ? (string)$_GET['q_phone'] : '';
$qName = isset($_GET['q_name']) ? (string)$_GET['q_name'] : '';

$editRow = null;
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

try {
    $conn = phone_list_db();

    if ($editId > 0) {
        $stmt = $conn->prepare('SELECT id, phone, name, memberid, created_at FROM cal_phone_list WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $editId);
            $stmt->execute();
            $res = $stmt->get_result();
            $editRow = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
    }

    $rows = phone_list_fetch_rows($conn, $qPhone, $qName);
    $conn->close();
} catch (Exception $e) {
    error_log('phone_list load error: ' . $e->getMessage());
    $rows = [];
    if ($flashError === '') {
        $flashError = 'Serverfel.';
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telefonlista (Admin)</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .header-row { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .btn { border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary { background-color: #007bff; color: #fff; }
        .btn-success { background-color: #28a745; color: #fff; }
        .btn-danger { background-color: #dc3545; color: #fff; }
        .btn-secondary { background-color: #6c757d; color: #fff; }
        .btn-link { text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .alert { padding: 10px; margin-bottom: 12px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .grid-2 { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .field label { display:block; font-weight:600; margin-bottom:6px; }
        .field input, .field select { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align:left; white-space:nowrap; }
        th { background: #f2f2f2; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .grid, .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <?php $activePage = ''; require_once 'top_menu.php'; ?>

        <div class="header-row" style="margin-bottom: 12px;">
            <h1 style="margin: 0;">📞 Telefonlista (Admin)</h1>
            <a class="btn btn-secondary btn-link" href="kundlista.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Till kundlista</a>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header-row" style="margin-bottom: 10px;">
                <h3 style="margin: 0;">Sök & filter</h3>
            </div>
            <form method="GET" action="">
                <div class="grid">
                    <div class="field">
                        <label for="q_name">Kundnamn</label>
                        <input id="q_name" name="q_name" type="text" value="<?php echo htmlspecialchars($qName, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field">
                        <label for="q_phone">Telefonnummer</label>
                        <input id="q_phone" name="q_phone" type="text" value="<?php echo htmlspecialchars($qPhone, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field" style="display:flex; align-items:flex-end; gap:10px;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search" aria-hidden="true"></i> Sök</button>
                        <a class="btn btn-secondary btn-link" href="phone_list.php"><i class="fa fa-times" aria-hidden="true"></i> Rensa</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="header-row" style="margin-bottom: 10px;">
                <h3 style="margin: 0;"><?php echo $editRow ? 'Redigera nummer' : 'Lägg till nummer'; ?></h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="create">
                <?php endif; ?>

                <div class="grid">
                    <div class="field">
                        <label for="phone">Telefonnummer</label>
                        <input id="phone" name="phone" type="text" required value="<?php echo htmlspecialchars((string)($editRow['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field">
                        <label for="name">Kundnamn</label>
                        <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars((string)($editRow['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field">
                        <label for="memberid">MemberID (valfritt)</label>
                        <input id="memberid" name="memberid" type="text" value="<?php echo htmlspecialchars((string)($editRow['memberid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field" style="display:flex; align-items:flex-end; gap:10px;">
                        <button type="submit" class="btn btn-success"><i class="fa fa-save" aria-hidden="true"></i> <?php echo $editRow ? 'Spara' : 'Lägg till'; ?></button>
                        <?php if ($editRow): ?>
                            <a class="btn btn-secondary btn-link" href="phone_list.php"><i class="fa fa-ban" aria-hidden="true"></i> Avbryt</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="header-row" style="margin-bottom: 10px;">
                <h3 style="margin: 0;">Export</h3>
            </div>

            <form method="POST" action="" style="margin-bottom: 10px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="export_dynamic">
                <input type="hidden" name="q_name" value="<?php echo htmlspecialchars($qName, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="q_phone" value="<?php echo htmlspecialchars($qPhone, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="grid-2">
                    <div class="field">
                        <label for="source">Källa (hämtas endast vid export)</label>
                        <select id="source" name="source">
                            <option value="active">Aktiva kunder</option>
                            <option value="temp">Temp kunder</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="format">Format</label>
                        <select id="format" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 12px;">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-download" aria-hidden="true"></i> Exportera (och spara nummer)</button>
                </div>
            </form>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="export_saved">
                <input type="hidden" name="q_name" value="<?php echo htmlspecialchars($qName, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="q_phone" value="<?php echo htmlspecialchars($qPhone, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="grid-2">
                    <div class="field">
                        <label for="format_saved">Format</label>
                        <select id="format_saved" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                    <div class="field" style="display:flex; align-items:flex-end;">
                        <button type="submit" class="btn btn-secondary"><i class="fa fa-download" aria-hidden="true"></i> Exportera sparad lista</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="header-row" style="margin-bottom: 10px;">
                <h3 style="margin: 0;">Telefonnummer (max 500)</h3>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Telefon</th>
                            <th>Namn</th>
                            <th>MemberID</th>
                            <th>Skapad</th>
                            <th>Åtgärd</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($r['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['memberid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a class="btn btn-primary btn-link" style="padding: 6px 10px;" href="phone_list.php?edit=<?php echo (int)$r['id']; ?>"><i class="fa fa-pencil" aria-hidden="true"></i> Edit</a>

                                    <form method="POST" action="" onsubmit="return confirm('Är du säker på att du vill ta bort detta nummer?');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px;"><i class="fa fa-trash" aria-hidden="true"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
