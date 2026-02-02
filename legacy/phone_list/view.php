<?php
$source = $phone_list_view_data['source'];
$qName = $phone_list_view_data['qName'];
$qPhone = $phone_list_view_data['qPhone'];
$sortBy = $phone_list_view_data['sortBy'];
$sortDir = $phone_list_view_data['sortDir'];
$viewLimit = $phone_list_view_data['viewLimit'];
$errorMessage = $phone_list_view_data['errorMessage'];
$rows = $phone_list_view_data['rows'];
$hasNameCol = $phone_list_view_data['hasNameCol'];
$hasPhoneCol = $phone_list_view_data['hasPhoneCol'];
$hasCreatedAtCol = $phone_list_view_data['hasCreatedAtCol'];
$suggestedStartMemberId = $phone_list_view_data['suggestedStartMemberId'];
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
        body { background-color: #f0f0f0; }
        .container { max-width: 1100px; margin: 20px auto; padding: 0; background: transparent; border: none; box-shadow: none; }
        .page-wrap { padding: 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        .header-row { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }

        .page-title { display:flex; align-items:center; gap:12px; font-size: 32px; font-weight: 800; margin: 6px 0 0; color: #0f172a; }
        .page-title .title-icon { color: #d11a1a; font-size: 32px; }

        .btn { border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; font-weight: 700; }
        .btn-primary { background-color: #0d6efd; color: #fff; }
        .btn-secondary { background-color: #6c757d; color: #fff; }
        .btn-dark { background-color: #6c757d; color: #fff; }
        .btn-link { text-decoration:none; display:inline-flex; align-items:center; gap:8px; }

        .card-title { display:flex; align-items:center; gap:10px; font-size: 22px; font-weight: 800; margin: 0; color: #0f172a; }
        .card-title .card-icon { color: #0f172a; }

        .alert { padding: 10px; margin-bottom: 12px; border-radius: 6px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }

        .grid-2 { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .field label { display:block; font-weight:700; margin-bottom:8px; color: #0f172a; text-align: left; }
        .field input, .field select { width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; text-align: left; }

        .btn-stack { display:flex; flex-direction:column; gap:10px; align-items:flex-start; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border: 1px solid #e5e7eb; text-align:left; white-space:nowrap; }
        th { background: #ffffff; color: #0f172a; font-weight: 800; }

        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <?php $activePage = ''; require_once 'top_menu.php'; ?>

        <div class="page-wrap">
            <div class="header-row" style="margin-bottom: 14px;">
                <div class="page-title">
                    <i class="fa fa-phone title-icon" aria-hidden="true"></i>
                    <span>Telefonlista (Admin)</span>
                </div>
                <a class="btn btn-secondary btn-link" href="kundlista.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Till kundlista</a>
            </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

            <div class="card">
                <div class="header-row" style="margin-bottom: 14px;">
                    <h3 class="card-title"><i class="fa fa-search card-icon" aria-hidden="true"></i> Filter / val</h3>
                </div>

                <form method="GET" action="">
                    <div class="grid-2">
                        <div class="field">
                            <label for="source">Lista</label>
                            <select id="source" name="source">
                                <option value="active" <?php echo $source === 'active' ? 'selected' : ''; ?>>Aktiva (cal_phone_list)</option>
                                <option value="temp" <?php echo $source === 'temp' ? 'selected' : ''; ?>>Temp (si_phone_list)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="sort_by">Sortera efter</label>
                            <select id="sort_by" name="sort_by">
                                <option value="memberid" <?php echo $sortBy === 'memberid' ? 'selected' : ''; ?>>memberid</option>
                                <?php if ($hasCreatedAtCol): ?>
                                    <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>created_at</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="q_name">Namn<?php echo $hasNameCol ? '' : ' (saknas i tabell)'; ?></label>
                            <input id="q_name" name="q_name" type="text" placeholder="Namn" value="<?php echo htmlspecialchars($qName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasNameCol ? '' : 'disabled'; ?>>
                        </div>
                        <div class="field">
                            <label for="q_phone">Telefon<?php echo $hasPhoneCol ? '' : ' (saknas i tabell)'; ?></label>
                            <input id="q_phone" name="q_phone" type="text" placeholder="Telefon" value="<?php echo htmlspecialchars($qPhone, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasPhoneCol ? '' : 'disabled'; ?>>
                        </div>

                        <div class="field">
                            <label for="sort_dir">Ordning</label>
                            <select id="sort_dir" name="sort_dir">
                                <option value="asc" <?php echo $sortDir === 'asc' ? 'selected' : ''; ?>>ASC</option>
                                <option value="desc" <?php echo $sortDir === 'desc' ? 'selected' : ''; ?>>DESC</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="view_limit">Visa max</label>
                            <input id="view_limit" name="view_limit" type="number" min="1" max="2000" value="<?php echo (int)$viewLimit; ?>">
                        </div>

                        <div class="btn-stack">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search" aria-hidden="true"></i> Visa</button>
                            <a class="btn btn-dark btn-link" href="phone_list.php"><i class="fa fa-times" aria-hidden="true"></i> Rensa</a>
                        </div>
                        <div></div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="header-row" style="margin-bottom: 14px;">
                    <h3 class="card-title"><i class="fa fa-upload card-icon" aria-hidden="true"></i> Exportera (Endast läsning)</h3>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="export">
                    <input type="hidden" name="source" value="<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sortBy, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sortDir, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="q_name" value="<?php echo htmlspecialchars($qName, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="q_phone" value="<?php echo htmlspecialchars($qPhone, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="grid-2">
                        <div class="field">
                            <label for="start_memberid">Exportera från memberid</label>
                            <input id="start_memberid" name="start_memberid" type="number" min="0" value="<?php echo (int)$suggestedStartMemberId; ?>">
                        </div>
                        <div class="field">
                            <label for="export_limit">Antal</label>
                            <input id="export_limit" name="export_limit" type="number" min="1" max="5000" value="20">
                        </div>

                        <div class="field">
                            <label for="format">Format</label>
                            <select id="format" name="format">
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                            </select>
                        </div>
                        <div class="field" style="display:flex; align-items:flex-end;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-folder-open" aria-hidden="true"></i> Exportera</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="header-row" style="margin-bottom: 14px;">
                    <h3 class="card-title"><i class="fa fa-file-text-o card-icon" aria-hidden="true"></i> Sparade poster (<?php echo htmlspecialchars(phone_list_table_for_source($source), ENT_QUOTES, 'UTF-8'); ?>)</h3>
                </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>memberid</th>
                            <?php if ($hasNameCol): ?><th>name</th><?php endif; ?>
                            <?php if ($hasPhoneCol): ?><th>phone</th><?php endif; ?>
                            <?php if ($hasCreatedAtCol): ?><th>created_at</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($r['memberid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php if ($hasNameCol): ?><td><?php echo htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><?php endif; ?>
                                <?php if ($hasPhoneCol): ?><td><?php echo htmlspecialchars((string)($r['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><?php endif; ?>
                                <?php if ($hasCreatedAtCol): ?><td><?php echo htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
</body>
</html>
