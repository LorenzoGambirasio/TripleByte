<?php

$page = 'ricoveri';

include '../db_connection.php'; 

define('STATO_ATTIVO', 0);
define('STATO_TRASFERITO', 1);
define('STATO_DIMESSO', 2);
define('STATO_DECEDUTO', 3); 

$filtro_paziente_cssn_get = $_GET['filtro_paziente_cssn'] ?? ''; 
$filtro_paziente_cssn = trim($filtro_paziente_cssn_get);     

$filtro_nome_get = $_GET['filtro_nome'] ?? '';                   
$filtro_nome = trim($filtro_nome_get);                         

$filtro_cognome_get = $_GET['filtro_cognome'] ?? '';          
$filtro_cognome = trim($filtro_cognome_get); 
$filtro_ospedale_cod = $_GET['filtro_ospedale_cod'] ?? null;
$filtro_data_inizio_get = $_GET['filtro_data_inizio'] ?? '';
$filtro_data_inizio = trim($filtro_data_inizio_get); 

$filtro_data_fine_get = $_GET['filtro_data_fine'] ?? '';
$filtro_data_fine = trim($filtro_data_fine_get);     

$errore_intervallo_date = null;
$filtro_motivo_get = $_GET['filtro_motivo'] ?? '';            
$filtro_motivo = trim($filtro_motivo_get);    
$filtro_patologia_cod = $_GET['filtro_patologia_cod'] ?? null;
$filtro_stato = isset($_GET['filtro_stato']) && $_GET['filtro_stato'] !== '' ? (int)$_GET['filtro_stato'] : null;

if ($filtro_data_inizio !== '' && $filtro_data_fine !== '') {
    try {
        $dataDaObj = new DateTime($filtro_data_inizio);
        $dataAObj = new DateTime($filtro_data_fine);

        $dataDaObj->setTime(0, 0, 0); 
        $dataAObj->setTime(0, 0, 0);  

        if ($dataAObj < $dataDaObj) {
            $errore_intervallo_date = 'La data "A data" non può essere precedente alla data "Da data". La ricerca per questo intervallo di date non è stata eseguita.';
            $filtro_data_inizio = null;
            $filtro_data_fine = null;
        }
    } catch (Exception $e) {
        
        $errore_intervallo_date = 'Formato data non valido. La ricerca per data non è stata eseguita.';
        $filtro_data_inizio = null;
        $filtro_data_fine = null;
    }
}

if (isset($_GET['mostra_deceduti'])) {
    
    $mostra_deceduti = ($_GET['mostra_deceduti'] == '1');
} else {
    $mostra_deceduti = true;
}

$sortableColumns = [
    'ospedale' => 'nomeOspedale',
    'codice'   => 'codRicovero',
    'paziente' => 'cognomePaziente, nomePaziente',
    'cssn'     => 'pazienteCSSN',
    'data'     => 'r.data',
    'durata'   => 'r.durata',
    'motivo'   => 'r.motivo',
    'costo'    => 'r.costo',
    'stato'    => 'r.stato' 
];

$currentSortColumn = $_GET['sort'] ?? 'codice'; 
$currentSortDir = $_GET['dir'] ?? 'desc';

if (!array_key_exists($currentSortColumn, $sortableColumns)) {
    $currentSortColumn = 'data';
}

if (!in_array(strtolower($currentSortDir), ['asc', 'desc'])) {
    $currentSortDir = 'desc';
}


$columnToSortBy = $sortableColumns[$currentSortColumn];
$direction = strtoupper($currentSortDir);

$orderByFieldsArray = explode(', ', $columnToSortBy);
$orderedFieldsWithDirection = [];

foreach ($orderByFieldsArray as $field) {
    $orderedFieldsWithDirection[] = trim($field) . " " . $direction;
}

$orderBySql = "ORDER BY " . implode(', ', $orderedFieldsWithDirection);

if ($currentSortColumn !== 'data') {
    $orderBySql .= ", r.data DESC";

} else {
    $orderBySql .= ", r.codOspedale ASC, r.cod ASC";
}

function aggiornaStatoSeDimesso(&$row, $conn) {
    if ($row['stato'] == STATO_TRASFERITO || $row['stato'] == STATO_DIMESSO || $row['stato'] == STATO_DECEDUTO || $row['pazienteDeceduto']) { 
        return;
    }

    $dataRicovero = new DateTime($row['data']);
    $durataRicovero = (int)$row['durata'];
    $dataFineRicoveroPrevista = clone $dataRicovero;
    $dataFineRicoveroPrevista->add(new DateInterval("P{$durataRicovero}D"));
    $oggi = new DateTime();

    if ($oggi > $dataFineRicoveroPrevista && $row['stato'] == STATO_ATTIVO) {
        $row['stato'] = STATO_DIMESSO; 
        try {
            $updateStmt = $conn->prepare("UPDATE Ricovero SET stato = ? WHERE codOspedale = ? AND cod = ? AND stato = ?");
            if ($updateStmt) {
                $statoAttivo = STATO_ATTIVO; 
                $statoDimesso = STATO_DIMESSO;
                $updateStmt->bind_param("issi", $statoDimesso, $row['codOspedale'], $row['codRicovero'], $statoAttivo);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } catch (Exception $e) {
     }
   }
}

if (isset($_POST['delete']) && isset($_POST['codOspedale']) && isset($_POST['codRicovero'])) {
    $codOspedale = $_POST['codOspedale'];
    $codRicovero = $_POST['codRicovero'];

    $conn->begin_transaction();

    try {
        $deletePatologieStmt = $conn->prepare("DELETE FROM PatologiaRicovero WHERE codOspedale = ? AND codRicovero = ?");
        $deletePatologieStmt->bind_param("ss", $codOspedale, $codRicovero);
        $deletePatologieStmt->execute();
        $deletePatologieStmt->close();

        $deleteRicoveroStmt = $conn->prepare("DELETE FROM Ricovero WHERE codOspedale = ? AND cod = ?");
        $deleteRicoveroStmt->bind_param("ss", $codOspedale, $codRicovero);
        $deleteRicoveroStmt->execute();

        if ($deleteRicoveroStmt->affected_rows > 0) {
            $conn->commit();
            $redirectParams = $_GET;
            $redirectParams['deleted'] = 'success';
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
            exit;
        } else {
            $conn->rollback();
            $redirectParams = $_GET;
            $redirectParams['deleted'] = 'error';
            $redirectParams['error_msg'] = urlencode("Impossibile eliminare il ricovero.");
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
            exit;
        }
        $deleteRicoveroStmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $redirectParams = $_GET;
        $redirectParams['deleted'] = 'error';
        $redirectParams['error_msg'] = urlencode("Errore durante l'eliminazione del ricovero.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;
    }
}

if (isset($_POST['declare_deceased']) && isset($_POST['pazienteCSSN']) && isset($_POST['codOspedale']) && isset($_POST['codRicovero'])) {
    $pazienteCSSN = $_POST['pazienteCSSN'];
    $codOspedale = $_POST['codOspedale'];
    $codRicovero = $_POST['codRicovero'];
    $dataOraDecesso = $_POST['dataOraDecesso'] ?? null;
    $causaDecesso = $_POST['causaDecesso'] ?? null;
    $statoDecedutoVal = STATO_DECEDUTO;

    if (empty($dataOraDecesso) || empty($causaDecesso)) {
        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update']);
        $redirectParams['deceased_update'] = 'error';
        $redirectParams['error_msg'] = urlencode("Data/ora e causa del decesso sono obbligatori.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;
    }
    
     if (!empty($causaDecesso)) {
        $causaDecesso = trim($causaDecesso); 
        $causaDecesso = ucfirst($causaDecesso);
    }

    $conn->begin_transaction();
    try {
        $stmtCittadino = $conn->prepare("UPDATE Cittadino SET deceduto = 1, dataOraDecesso = ?, causaDecesso = ? WHERE CSSN = ? AND (deceduto = 0 OR deceduto IS NULL)");
        if (!$stmtCittadino) {
            throw new Exception("Errore preparazione query Cittadino: " . $conn->error);
        }
        $stmtCittadino->bind_param("sss", $dataOraDecesso, $causaDecesso, $pazienteCSSN);
        $stmtCittadino->execute();
        $cittadinoAffectedRows = $stmtCittadino->affected_rows;
        $stmtCittadino->close();

        $stmtRicovero = $conn->prepare("UPDATE Ricovero SET stato = ? WHERE codOspedale = ? AND cod = ?");
        if (!$stmtRicovero) {
            throw new Exception("Errore preparazione query Ricovero: " . $conn->error);
        }
        $stmtRicovero->bind_param("iss", $statoDecedutoVal, $codOspedale, $codRicovero);
        $stmtRicovero->execute();
        $stmtRicovero->close();

        $conn->commit();
        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update']);
        $redirectParams['deceased_update'] = 'success';
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update']);
        $redirectParams['deceased_update'] = 'error';
        $redirectParams['error_msg'] = urlencode("Errore: " . $e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;
    }
}

if (isset($_POST['update_causa_decesso']) && isset($_POST['pazienteCSSN_causa']) && isset($_POST['nuovaCausaDecesso'])) {
    $pazienteCSSN = $_POST['pazienteCSSN_causa'];
    $nuovaCausa = trim($_POST['nuovaCausaDecesso']);

    if (empty($nuovaCausa)) {
        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update'], $redirectParams['causa_updated']);
        $redirectParams['causa_updated'] = 'error';
        $redirectParams['error_msg'] = urlencode("La nuova causa del decesso non può essere vuota.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;
    }
    
    if (strlen($nuovaCausa) < 3) {
        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update'], $redirectParams['causa_updated']);
        $redirectParams['causa_updated'] = 'error';
        $redirectParams['error_msg'] = urlencode("La causa del decesso deve contenere almeno 3 caratteri.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;
    }

   
    $nuovaCausa = ucfirst($nuovaCausa);

    $conn->begin_transaction();
    try {
        $stmtUpdateCausa = $conn->prepare("UPDATE Cittadino SET causaDecesso = ? WHERE CSSN = ? AND deceduto = 1");
        if (!$stmtUpdateCausa) {
            throw new Exception("Errore preparazione query aggiornamento causa: " . $conn->error);
        }
        $stmtUpdateCausa->bind_param("ss", $nuovaCausa, $pazienteCSSN);
        $stmtUpdateCausa->execute();

        if ($stmtUpdateCausa->affected_rows > 0) {
            $conn->commit();
            $updateSuccess = true;
        } else {
            $conn->commit(); 
            $updateSuccess = true; 
        }
        $stmtUpdateCausa->close();

        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update'], $redirectParams['causa_updated']);
        $redirectParams['causa_updated'] = 'success';
        $redirectParams['success_msg'] = urlencode("Causa del decesso aggiornata con successo.");
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $redirectParams = $_GET;
        unset($redirectParams['deleted'], $redirectParams['error_msg'], $redirectParams['deceased_update'], $redirectParams['causa_updated']);
        $redirectParams['causa_updated'] = 'error';
        $redirectParams['error_msg'] = urlencode("Errore aggiornamento causa: " . $e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($redirectParams));
        exit;
    }
}

$whereClauses = [];
$params = [];
$types = "";

if (!$mostra_deceduti) {
    
    $whereClauses[] = "(c.deceduto = 0 OR c.deceduto IS NULL)";
    
}

if (!empty($filtro_paziente_cssn)) {
    $whereClauses[] = "r.paziente LIKE ?";
    $params[] = "%" . $filtro_paziente_cssn . "%";
    $types .= "s";
}

if (!empty($filtro_nome)) {
    $whereClauses[] = "c.nome LIKE ?";
    $params[] = "%" . $filtro_nome . "%";
    $types .= "s";
}

if (!empty($filtro_cognome)) {
    $whereClauses[] = "c.cognome LIKE ?";
    $params[] = "%" . $filtro_cognome . "%";
    $types .= "s";
}

if (!empty($filtro_ospedale_cod)) {
    $whereClauses[] = "r.codOspedale = ?";
    $params[] = $filtro_ospedale_cod;
    $types .= "s";
}

if (!$errore_intervallo_date) {
    if (!empty($filtro_data_inizio)) { 
        $whereClauses[] = "r.data >= ?";
        $params[] = $filtro_data_inizio;
        $types .= "s";
    }
    if (!empty($filtro_data_fine)) { 
        $whereClauses[] = "r.data <= ?";
        $params[] = $filtro_data_fine;
        $types .= "s";
    }
}

if (!empty($filtro_motivo)) {
    $whereClauses[] = "r.motivo LIKE ?";
    $params[] = "%" . $filtro_motivo . "%";
    $types .= "s";
}

if (!empty($filtro_patologia_cod)) {
    $whereClauses[] = "EXISTS (SELECT 1 FROM PatologiaRicovero pr WHERE pr.codOspedale = r.codOspedale AND pr.codRicovero = r.cod AND pr.codPatologia = ?)";
    $params[] = $filtro_patologia_cod;
    $types .= "s";
}

if ($filtro_stato !== null) {
    $whereClauses[] = "r.stato = ?";
    $params[] = $filtro_stato;
    $types .= "i"; 
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=yes">
    <title>Elenco Ricoveri</title>
    <link rel="stylesheet" href="../CSS/cittadini.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/paginazione.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/base.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/header.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/menu.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/footer.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/crud.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="home">
<?php include '../MainLayout/header.php'; ?>
<div class="container">
    <aside class="sidebar">
        <div class="menu">
            <?php include '../MainLayout/menu.php'; ?>
        </div>
        <div class="filtro">
            <?php 
                include '../MainLayout/filtro.php'; 
            ?>
        </div>
    </aside>

    <main class="content">
        <div class="header-with-button-container">
            <h1 class="titoloPalette">Elenco dei Ricoveri</h1>
            <?php if ($errore_intervallo_date): ?>
                <div style="color: white; background-color: #c82333; border: 1px solid #bd2130; padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center;">
                    <strong>Attenzione:</strong> <?php echo htmlspecialchars($errore_intervallo_date); ?>
                </div>
            <?php endif; ?>
            <div class="table-actions">
                <a href="aggiungi_ricovero.php" class="add-btn" title="Aggiungi Nuovo Ricovero">
                    <i class="fa-solid fa-plus"></i> </a>
            </div>
        </div>

        <?php
        $recordsPerPage = 20;
        $currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $startFrom = ($currentPage - 1) * $recordsPerPage;
        $countQuery = "
            SELECT COUNT(*) as total
            FROM Ricovero r
            LEFT JOIN Cittadino c ON r.paziente = c.CSSN
            LEFT JOIN Ospedale o ON r.codOspedale = o.codice
            " . $whereSql;

        $stmtCount = $conn->prepare($countQuery);
        if ($stmtCount === false) {
            die("Errore preparazione query conteggio: " . $conn->error);
        }

        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }

        $execCount = $stmtCount->execute();
        if ($execCount === false) {
            die("Errore esecuzione query conteggio: " . $stmtCount->error);
        }

        $countResult = $stmtCount->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);
        $query = "
    SELECT
        r.codOspedale,
        r.cod AS codRicovero,
        r.paziente AS pazienteCSSN,
        c.nome AS nomePaziente,
        c.cognome AS cognomePaziente,
        c.deceduto AS pazienteDeceduto,
        c.dataOraDecesso,
        c.causaDecesso,
        o.nome AS nomeOspedale,
        r.data,
        r.durata,
        r.motivo,
        r.costo,
        r.stato, 
        (SELECT GROUP_CONCAT(p.nome SEPARATOR ', ')
         FROM PatologiaRicovero pr
         JOIN Patologia p ON pr.codPatologia = p.cod
         WHERE pr.codOspedale = r.codOspedale AND pr.codRicovero = r.cod) AS patologieAssociate
    FROM Ricovero r
    LEFT JOIN Cittadino c ON r.paziente = c.CSSN
    LEFT JOIN Ospedale o ON r.codOspedale = o.codice
" . $whereSql . "
" . $orderBySql . "
LIMIT ?, ?";

        $limitTypes = $types . "ii";
        $limitParams = array_merge($params, [$startFrom, $recordsPerPage]);
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            die("Errore preparazione query principale: " . $conn->error);
        }

        if (!empty($limitParams)) {
            $stmt->bind_param($limitTypes, ...$limitParams);
        }

        $exec = $stmt->execute();

        if ($exec === false) {
            die("Errore esecuzione query principale: " . $stmt->error);
        }

        $result = $stmt->get_result();

      function getSortUrl($column, $currentSortColumn, $currentSortDir) {
    $params = $_GET;
    
    unset($params['deleted']);
    unset($params['error_msg']);
    
    $params['sort'] = $column;
    $params['dir'] = ($currentSortColumn === $column && $currentSortDir === 'asc') ? 'desc' : 'asc';
    if (!isset($params['p'])) {
        $params['p'] = 1;
    }
    return '?' . http_build_query($params);
}

        function getSortIcon($column, $currentSortColumn, $currentSortDir) {
            if ($currentSortColumn !== $column) {
                return '<i class="fas fa-sort"></i>';
            }

            return ($currentSortDir === 'asc')
                ? '<i class="fas fa-sort-up"></i>'
                : '<i class="fas fa-sort-down"></i>';
        }

        function displayStato($stato) {
    $statusClass = '';
    $statusText = '';
    switch ($stato) {
        case STATO_ATTIVO:
            $statusClass = 'status-attivo';
            $statusText = 'Attivo';
            break;
        case STATO_TRASFERITO:
            $statusClass = 'status-trasferito';
            $statusText = 'Trasferito';
            break;
        case STATO_DIMESSO:
            $statusClass = 'status-dimesso';
            $statusText = 'Dimesso';
            break;
        case STATO_DECEDUTO: 
            $statusClass = 'status-deceduto';
            $statusText = 'Deceduto';
            break;
        default:
            $statusText = 'Sconosciuto';
            break;
    }

        	return '<span class="status-dot ' . $statusClass . '" title="' . $statusText . '"></span>';
        }

        ?>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="tabella-wrapper">
                <table class="tabella-cittadini tabella-ricoveri">
                   <thead>
                    <tr>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('ospedale', $currentSortColumn, $currentSortDir) ?>'">
                            Ospedale <?= getSortIcon('ospedale', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('paziente', $currentSortColumn, $currentSortDir) ?>'">
                            Paziente <?= getSortIcon('paziente', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('cssn', $currentSortColumn, $currentSortDir) ?>'">
                            CSSN <?= getSortIcon('cssn', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('data', $currentSortColumn, $currentSortDir) ?>'">
                            Data <?= getSortIcon('data', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('durata', $currentSortColumn, $currentSortDir) ?>'">
                            Durata (gg) <?= getSortIcon('durata', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('stato', $currentSortColumn, $currentSortDir) ?>'">
                            Stato <?= getSortIcon('stato', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('motivo', $currentSortColumn, $currentSortDir) ?>'">
                            Motivo <?= getSortIcon('motivo', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('costo', $currentSortColumn, $currentSortDir) ?>'">
                            Costo (€) <?= getSortIcon('costo', $currentSortColumn, $currentSortDir) ?>
                        </th>
                        <th>Patologie</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                           aggiornaStatoSeDimesso($row, $conn);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nomeOspedale'] ?? $row['codOspedale']) ?></td>
                            <td><?= htmlspecialchars($row['nomePaziente'] . ' ' . $row['cognomePaziente']) ?></td>
                            <td><?= htmlspecialchars($row['pazienteCSSN']) ?></td>
                            <td><?= htmlspecialchars(date("d/m/Y", strtotime($row['data']))) ?></td>
                            <td><?= htmlspecialchars($row['durata']) ?></td>
                            <td><?= displayStato($row['stato']) // Visualizza lo stato con pallino ?></td>
                            <td><?= htmlspecialchars($row['motivo']) ?></td>
                            <td><?= htmlspecialchars(number_format($row['costo'], 2, ',', '.')) ?></td>
                            <td><?= htmlspecialchars($row['patologieAssociate'] ?? 'Nessuna') ?></td>
                            <td style="white-space: nowrap;">
<?php
$isPazienteDeceduto = $row['pazienteDeceduto'] || $row['stato'] == STATO_DECEDUTO;
if ($isPazienteDeceduto): ?>
    <div class="azioni-deceduto-container">
        <button type="button" class="info-btn info-decesso-btn" 
                title="Informazioni Decesso"
                data-dataora="<?= htmlspecialchars($row['dataOraDecesso'] ?? 'Non disponibile') ?>"
                data-causa="<?= htmlspecialchars($row['causaDecesso'] ?? 'Non disponibile') ?>"
                data-nome="<?= htmlspecialchars($row['nomePaziente'] . ' ' . $row['cognomePaziente']) ?>"
                data-cssn="<?= htmlspecialchars($row['pazienteCSSN']) ?>">
            <i class="fa-solid fa-info-circle"></i>
        </button>
        
        <form method="post" class="form-elimina" style="display:inline-block;">
            <input type="hidden" name="codOspedale" value="<?= htmlspecialchars($row['codOspedale']) ?>">
            <input type="hidden" name="codRicovero" value="<?= htmlspecialchars($row['codRicovero']) ?>">
            <input type="hidden" name="delete" value="1">
            <button type="button" class="delete-btn swal-confirm-btn" title="Elimina Ricovero">
                <i class="fa-solid fa-trash"></i>
            </button>
        </form>
    </div>

<?php else: ?>
    <?php                            
    $modificaDisabled = $row['stato'] == STATO_TRASFERITO || $row['stato'] == STATO_DECEDUTO;
    $modificaTitle = $modificaDisabled ? "Modifica non disponibile (Ricovero trasferito/deceduto)" : "Modifica Ricovero";
    ?>
    <?php if (!$modificaDisabled): ?>
        <a href="modifica_ricovero.php?codOspedale=<?= htmlspecialchars($row['codOspedale']) ?>&codRicovero=<?= htmlspecialchars($row['codRicovero']) ?>" class="edit-btn" title="<?= $modificaTitle ?>">
            <i class="fa-solid fa-pen"></i>
        </a>
    <?php else: ?>
        <button class="edit-btn disabled-btn" disabled title="<?= $modificaTitle ?>">
            <i class="fa-solid fa-pen"></i>
        </button>
    <?php endif; ?>

    <?php
    $trasferisciDisabled = $row['stato'] != STATO_ATTIVO;
    $statoTextTrasf = '';
    switch ($row['stato']) {
        case STATO_TRASFERITO: $statoTextTrasf = 'Trasferito'; break;
        case STATO_DIMESSO: $statoTextTrasf = 'Dimesso'; break;
        case STATO_DECEDUTO: $statoTextTrasf = 'Deceduto'; break;
        default: $statoTextTrasf = 'Non attivo'; break;
    }
    $trasferisciTitle = $trasferisciDisabled ? "Trasferimento non disponibile (Stato: " . $statoTextTrasf . ")" : "Trasferisci Ricovero";
    ?>

    <?php if (!$trasferisciDisabled): ?>
        <a href="trasferisci_ricovero.php?codOspedale=<?= htmlspecialchars($row['codOspedale']) ?>&codRicovero=<?= htmlspecialchars($row['codRicovero']) ?>" class="transfer-btn" title="<?= $trasferisciTitle ?>">
            <i class="fa-solid fa-right-left"></i>
        </a>
    <?php else: ?>
        <button class="transfer-btn disabled-btn" disabled title="<?= $trasferisciTitle ?>">
            <i class="fa-solid fa-right-left"></i>
        </button>
    <?php endif; ?>

    <?php
    $deceaseDisabled = $row['stato'] != STATO_ATTIVO;
    $deceaseTitle = '';
    switch ($row['stato']) {
        case STATO_TRASFERITO: 
            $deceaseTitle = 'Dichiarazione decesso non disponibile (Paziente trasferito)'; 
            break;
        case STATO_DIMESSO: 
            $deceaseTitle = 'Dichiarazione decesso non disponibile (Paziente dimesso)'; 
            break;
        case STATO_DECEDUTO: 
            $deceaseTitle = 'Paziente già dichiarato deceduto'; 
            break;
        default: 
            $deceaseTitle = 'Dichiara Deceduto';
            break;
    }
    ?>

    <?php if (!$deceaseDisabled): ?>
        <form method="post" class="form-deceduto" style="display:inline-block; margin: 0 2px;">
            <input type="hidden" name="pazienteCSSN" value="<?= htmlspecialchars($row['pazienteCSSN']) ?>">
            <input type="hidden" name="codOspedale" value="<?= htmlspecialchars($row['codOspedale']) ?>">
            <input type="hidden" name="codRicovero" value="<?= htmlspecialchars($row['codRicovero']) ?>">
            <input type="hidden" name="declare_deceased" value="1">
            <input type="hidden" name="dataOraDecesso" value="">
            <input type="hidden" name="causaDecesso" value="">
            <input type="hidden" name="dataRicovero" value="<?= htmlspecialchars($row['data']) ?>">
            <button type="button" class="decease-btn swal-decease-btn" title="<?= $deceaseTitle ?>">
                <i class="fa-solid fa-skull-crossbones"></i>
            </button>
        </form>
    <?php else: ?>
        <button class="decease-btn disabled-btn" disabled title="<?= $deceaseTitle ?>" >
            <i class="fa-solid fa-skull-crossbones"></i>
        </button>
    <?php endif; ?>

    
    <form method="post" class="form-elimina" style="display:inline-block;">
        <input type="hidden" name="codOspedale" value="<?= htmlspecialchars($row['codOspedale']) ?>">
        <input type="hidden" name="codRicovero" value="<?= htmlspecialchars($row['codRicovero']) ?>">
        <input type="hidden" name="delete" value="1">
        <button type="button" class="delete-btn swal-confirm-btn" title="Elimina Ricovero">
            <i class="fa-solid fa-trash"></i>
        </button>
    </form>
<?php endif; ?>
</td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="paginazione-footer">
                <div class="total-records-display">
                    <?php if ($totalRecords > 0): ?>
                        Totale: <strong><?= htmlspecialchars($totalRecords) ?></strong> <?= ($totalRecords == 1) ? 'ricovero' : 'ricoveri' ?>
                    <?php endif; ?>
                </div>

                <div class="paginazione">
                    <?php
                    $queryParams = $_GET;

                    unset($queryParams['p']);
                    unset($queryParams['deleted']);
                    unset($queryParams['error_msg']);
                    $queryStringBase = http_build_query($queryParams);
                    if (!empty($queryStringBase)) $queryStringBase .= '&';

                    if ($currentPage > 1) {
                        echo '<a href="?' . $queryStringBase . 'p=' . ($currentPage - 1) . '">&laquo; Precedente</a>';
                    } else {
                        echo '<span class="disabled">&laquo; Precedente</span>';
                    }

                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);

                    if ($startPage > 1) {
                        echo '<a href="?' . $queryStringBase . 'p=1">1</a>';
                        if ($startPage > 2) echo '<span>...</span>';
                    }

                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $currentPage) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?' . $queryStringBase . 'p=' . $i . '">' . $i . '</a>';
                        }
                    }

                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span>...</span>';
                        echo '<a href="?' . $queryStringBase . 'p=' . $totalPages . '">' . $totalPages . '</a>';
                    }

                    if ($currentPage < $totalPages) {
                        echo '<a href="?' . $queryStringBase . 'p=' . ($currentPage + 1) . '">Successiva &raquo;</a>';
                    } else {
                        echo '<span class="disabled">Successiva &raquo;</span>';
                    }
                    ?>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($whereSql)): ?>
                <div id="no-results">
                    <div class="empty-state-container" style="text-align: center; padding: 40px 20px;">
                        <i class="fa-solid fa-search fa-3x" style="color: #002080; margin-bottom: 20px;"></i>
                        <h2>Nessun ricovero trovato</h2>
                        <p>Non sono stati trovati ricoveri con i filtri specificati.</p>
                        <div class="total-records-display" style="margin-top:10px;">
                            Totale: <strong>0</strong> ricoveri
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p>Nessun ricovero presente nel database.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<script>
document.querySelectorAll('.swal-confirm-btn').forEach(button => {
    button.addEventListener('click', function () {
        const form = this.closest('form');
        Swal.fire({
            title: 'Sei sicuro?',
            text: 'Questa azione eliminerà definitivamente il ricovero.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#002080',
            cancelButtonColor: '#800000',
            confirmButtonText: 'Sì, elimina',
            cancelButtonText: 'Annulla'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    if (<?= !empty($whereSql) && $totalRecords == 0 ? 'true' : 'false' ?>) {
        Swal.fire({
            title: 'Nessun risultato',
            text: 'Non sono stati trovati ricoveri con i filtri specificati.',
            icon: 'error',
            confirmButtonColor: '#002080',
            confirmButtonText: 'Reimposta filtri'
        }).then((result) => {
            if (result.isConfirmed) {
                const baseUrl = window.location.href.split('?')[0];
                let newQuery = '';
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('sort')) newQuery += (newQuery ? '&' : '?') + 'sort=' + urlParams.get('sort');
                if (urlParams.has('dir')) newQuery += (newQuery ? '&' : '?') + 'dir=' + urlParams.get('dir');
                window.location.href = baseUrl + newQuery;
            }
        });
    }
  
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('deleted')) {
    const deleteStatus = urlParams.get('deleted');
    
    if (deleteStatus === 'success') {
        Swal.fire({
            icon: 'success',
            title: 'Ricovero Eliminato!',
            text: 'Il ricovero è stato eliminato con successo.',
            timer: 2000,
            showConfirmButton: false
        });
        
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('deleted');
        cleanUrl.searchParams.delete('error_msg');
        window.history.replaceState({}, document.title, cleanUrl.toString());
        } else if (deleteStatus === 'error') {
            const errorMsg = urlParams.get('error_msg') || 'Si è verificato un errore durante l\'eliminazione.';
            Swal.fire({
                icon: 'error',
                title: 'Errore',
                text: decodeURIComponent(errorMsg),
                confirmButtonColor: '#002080'
            });
            
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('deleted');
            cleanUrl.searchParams.delete('error_msg');
            window.history.replaceState({}, document.title, cleanUrl.toString());
        }
    }
    
    if (urlParams.has('deceased_update')) {
        const updateStatus = urlParams.get('deceased_update');
        const cleanUrl = new URL(window.location.href); 
        cleanUrl.searchParams.delete('deceased_update');
        cleanUrl.searchParams.delete('error_msg');

        if (updateStatus === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Paziente Dichiarato Deceduto',
                text: 'Lo stato del paziente e del ricovero sono stati aggiornati con successo.',
                timer: 3000,
                showConfirmButton: false
            });
        } else if (updateStatus === 'error') {
            const errorMsg = urlParams.get('error_msg') || 'Si è verificato un errore durante l\'aggiornamento.';
            Swal.fire({
                icon: 'error',
                title: 'Errore Aggiornamento',
                text: decodeURIComponent(errorMsg), 
                confirmButtonColor: '#d33' 
            });
        }
        window.history.replaceState({}, document.title, cleanUrl.toString()); 
    }
    
    if (urlParams.has('causa_updated')) {
    const updateStatus = urlParams.get('causa_updated');
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('causa_updated');
    cleanUrl.searchParams.delete('error_msg');
    cleanUrl.searchParams.delete('success_msg');

    if (updateStatus === 'success') {
        let successMsg = urlParams.get('success_msg') || 'Operazione completata con successo.';
        successMsg = successMsg.replace(/\+/g, ' ');
        Swal.fire({
            icon: 'success',
            title: 'Aggiornamento Completato',
            text: decodeURIComponent(successMsg),
            timer: 3000,
            showConfirmButton: false
        });
    } else if (updateStatus === 'error') {
        let errorMsg = urlParams.get('error_msg') || 'Si è verificato un errore.';
        errorMsg = errorMsg.replace(/\+/g, ' ');
        Swal.fire({
            icon: 'error',
            title: 'Errore Aggiornamento Causa',
            text: decodeURIComponent(errorMsg),
            confirmButtonColor: '#d33'
        });
    }
    window.history.replaceState({}, document.title, cleanUrl.toString());
}
});

document.querySelectorAll('.swal-decease-btn').forEach(button => {
    button.addEventListener('click', function (event) {
        const form = this.closest('form');
        const dataRicovero = form.querySelector('input[name="dataRicovero"]').value;
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        const dataRicoveroFormatted = dataRicovero.includes('T') ? dataRicovero : dataRicovero + 'T00:00';
        const dataRicoveroObj = new Date(dataRicovero);
        const dataRicoveroDisplay = dataRicoveroObj.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric'
        });

        
        function showDeceaseForm() {
            Swal.fire({
                title: 'Dichiarazione di Decesso',
                html: `
                    <div style="text-align: center; margin: 20px 0; margin-bottom: -0.75em;">
                        <div style="background: #e8f4fd; border: 1px solid #b8daff; border-radius: 6px; padding: 12px; margin-bottom: 20px; max-width: 450px; margin-left: auto; margin-right: auto;">
                            <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 5px;">
                                <i class="fa-solid fa-info-circle" style="color: #0c5460; margin-right: 8px; font-size: 16px;"></i>
                                <strong style="color: #0c5460; font-size: 14px;">Data Ricovero: ${dataRicoveroDisplay}</strong>
                            </div>
                            <p style="margin: 0; color: #0c5460; font-size: 13px;">
                                La data del decesso non può essere precedente alla data di ricovero
                            </p>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label for="swal-dataOraDecesso" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                Data e Ora del Decesso <span style="color: #e74c3c;">*</span>
                            </label>
                            <input type="datetime-local" id="swal-dataOraDecesso" class="swal2-input swal-death-input" 
                                   value="${currentDateTime}" max="${currentDateTime}" min="${dataRicoveroFormatted}"
                                   style="width: 100%; max-width: 400px; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; font-family: Arial, sans-serif !important; transition: border-color 0.3s ease; margin: 0 auto; display: block;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="swal-causaDecesso" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                Causa del Decesso <span style="color: #e74c3c;">*</span>
                            </label>
                            <textarea id="swal-causaDecesso" class="swal2-textarea swal-death-textarea" placeholder="Inserisci la causa del decesso..."
                                      style="width: 100%; max-width: 400px; min-height: 80px; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; font-family: Arial, sans-serif !important; resize: vertical; transition: border-color 0.3s ease; margin: 0 auto; display: block;"></textarea>
                        </div>
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin-top: 20px; max-width: 450px; margin-left: auto; margin-right: auto;">
                            <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                <i class="fa-solid fa-exclamation-triangle" style="color: #f39c12; margin-right: 10px; font-size: 18px;"></i>
                                <strong style="color: #856404;">Attenzione</strong>
                            </div>
                            <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.4; text-align: center;">
                                Questa azione dichiarerà il paziente deceduto e imposterà lo stato del ricovero come tale. 
                                I dati del paziente e questo ricovero non saranno più modificabili.
                            </p>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2c3e50',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa-solid fa-check"></i> Conferma Decesso',
                cancelButtonText: '<i class="fa-solid fa-times"></i> Annulla',
                width: '600px',
                customClass: {
                    popup: 'swal-decesso-popup',
                    confirmButton: 'swal-decesso-confirm',
                    cancelButton: 'swal-decesso-cancel'
                },
                 didOpen: () => { 
                    const inputs = document.querySelectorAll('#swal-dataOraDecesso, #swal-causaDecesso');
                    inputs.forEach(input => {
                        input.addEventListener('focus', function() {
                            this.style.borderColor = '#3498db';
                            this.style.boxShadow = '0 0 0 3px rgba(52, 152, 219, 0.1)';
                        });
                        input.addEventListener('blur', function() {
                            this.style.borderColor = '#ddd';
                            this.style.boxShadow = 'none';
                        });
                    });
                    
                    const dateInput = document.getElementById('swal-dataOraDecesso');
                    dateInput.addEventListener('input', function() {
                        const selectedDate = new Date(this.value);
                       
                        
                        if (selectedDate < dataRicoveroObj) { 
                            this.style.borderColor = '#e74c3c';
                            this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                        } else {
                            this.style.borderColor = '#27ae60'; 
                            this.style.boxShadow = '0 0 0 3px rgba(39, 174, 96, 0.1)';
                        }
                    });

                    const causaDecessoTextarea = document.getElementById('swal-causaDecesso');
                    if (causaDecessoTextarea) {
                        causaDecessoTextarea.addEventListener('input', function(e) {
                            let value = this.value;
                            if (value.length > 0) {
                                const selectionStart = this.selectionStart;
                                const selectionEnd = this.selectionEnd;
                                const newValue = value.charAt(0).toUpperCase() + value.slice(1);
                                if (this.value !== newValue) {
                                    this.value = newValue;
                                    this.setSelectionRange(selectionStart, selectionEnd);
                                }
                            }
                        });
                    }
                },
                preConfirm: () => { 
                    const dataOraDecesso = document.getElementById('swal-dataOraDecesso').value;
                    let causaDecesso = document.getElementById('swal-causaDecesso').value; 
                    
                    if (!dataOraDecesso) {
                        Swal.showValidationMessage('La data e ora del decesso sono obbligatorie'); 
                        return false;
                    }
                    
                    if (!causaDecesso || causaDecesso.trim() === '') {
                        Swal.showValidationMessage('La causa del decesso è obbligatoria'); 
                        document.getElementById('swal-causaDecesso').value = ''; 
                        return false;
                    }
                    
                    causaDecesso = causaDecesso.trim(); 

                    if (causaDecesso.length < 3) {
                        Swal.showValidationMessage('La causa del decesso deve contenere almeno 3 caratteri'); 
                        return false;
                    }
                                        
                    if (causaDecesso.length > 0) {
                        causaDecesso = causaDecesso.charAt(0).toUpperCase() + causaDecesso.slice(1);
                    }
                                   
                    const selectedDate = new Date(dataOraDecesso);
                    
                    if (selectedDate < dataRicoveroObj) { 
                        Swal.showValidationMessage(`La data del decesso non può essere precedente alla data di ricovero (${dataRicoveroDisplay})`); 
                        return false;
                    }
                    
                    return { dataOraDecesso, causaDecesso };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.querySelector('input[name="dataOraDecesso"]').value = result.value.dataOraDecesso;
                    form.querySelector('input[name="causaDecesso"]').value = result.value.causaDecesso;
                    form.submit();
                }
            });
        }


        Swal.fire({
            title: 'Verifica Autorizzazione',
            input: 'password',
            inputPlaceholder: 'Inserisci la password amministratore',
            inputAttributes: {
                autocapitalize: 'off',
                autocorrect: 'off'
            },
            showCancelButton: true,
            confirmButtonColor: '#800020',
            confirmButtonText: 'Conferma',
            cancelButtonText: 'Annulla',
            showLoaderOnConfirm: true,
            customClass: {
                popup: 'swal-password-popup',
                input: 'swal-password-input'
            },
            preConfirm: (password) => {
                const definedPassword = "admin"; 
                if (password !== definedPassword) {
                    Swal.showValidationMessage('Password errata');
                    return false; 
                }
                return password; 
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showDeceaseForm(); 
            }
        });
    });
});
</script>


<script>

document.querySelectorAll('.info-decesso-btn').forEach(button => {
    button.addEventListener('click', function() {
        const dataOra = this.getAttribute('data-dataora');
        const causaOriginale = this.getAttribute('data-causa');
        const nomePaziente = this.getAttribute('data-nome');
        const cssnPaziente = this.getAttribute('data-cssn'); 

        
        if (!cssnPaziente) {
            console.error("CSSN del paziente non trovato sull'attributo data-cssn del pulsante info.");
            Swal.fire('Errore Interno', 'Impossibile recuperare l\'identificativo del paziente. Contattare l\'assistenza.', 'error');
            return; 
        }

        let dataOraFormatted = 'Non disponibile';
        if (dataOra && dataOra !== 'Non disponibile' && dataOra !== '0000-00-00 00:00:00') {
            try {
                const date = new Date(dataOra);
                if (!isNaN(date.getTime())) {
                    dataOraFormatted = date.toLocaleString('it-IT', {
                        day: '2-digit', month: '2-digit', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', second: '2-digit'
                    });
                }
            } catch (e) {
                dataOraFormatted = dataOra;
            }
        }
        
        const infoPopupHtml = `
            <div style="text-align: left; max-width: 500px; margin: 0 auto;">
                <div style="background: #f8f9fa; border-left: 4px solid #2c3e50; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <i class="fa-solid fa-user" style="color: #2c3e50; margin-right: 10px; font-size: 16px;"></i>
                        <strong style="color: #2c3e50; font-size: 16px;">Paziente:</strong>
                        <span style="margin-left: 8px; font-size: 16px;">${nomePaziente}</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                        <i class="fa-solid fa-calendar-times" style="color: #e74c3c; margin-right: 10px; font-size: 16px; margin-top: 2px;"></i>
                        <div>
                            <strong style="color: #2c3e50; font-size: 14px;">Data e Ora Decesso:</strong>
                            <div style="margin-top: 5px; padding: 8px 12px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px; font-family: monospace; font-size: 14px;">
                                ${dataOraFormatted}
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-start;">
                        <i class="fa-solid fa-clipboard-list" style="color: #f39c12; margin-right: 10px; font-size: 16px; margin-top: 2px;"></i>
                        <div style="flex: 1;">
                            <strong style="color: #2c3e50; font-size: 14px;">Causa del Decesso:</strong>
                            <div id="display-causa-decesso" style="margin-top: 5px; padding: 12px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px; min-height: 50px; line-height: 1.4; font-size: 14px; word-wrap: break-word;">
                                ${causaOriginale || 'Non specificata'}
                            </div>
                        </div>
                    </div>
                </div>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 12px; text-align: center;">
                    <i class="fa-solid fa-exclamation-triangle" style="color: #f39c12; margin-right: 8px;"></i>
                    <small style="color: #856404; font-style: italic;">
                        Questo paziente è stato dichiarato deceduto.
                    </small>
                </div>
            </div>
        `;

        Swal.fire({
            title: '<i class="fa-solid fa-info-circle" style="color: #2c3e50;"></i> Informazioni Decesso',
            html: infoPopupHtml,
            icon: null,
            showCancelButton: true,
            cancelButtonText: '<i class="fa-solid fa-pen-to-square"></i> Modifica Causa',
            confirmButtonColor: '#2c3e50',
            confirmButtonText: '<i class="fa-solid fa-check"></i> Chiudi',
            width: '600px',
            customClass: {
                popup: 'swal-info-decesso-popup',
                cancelButton: 'swal-edit-causa-button' 
            }
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) { 

                Swal.fire({
                    title: 'Verifica Autorizzazione Modifica',
                    input: 'password',
                    inputPlaceholder: 'Inserisci password amministratore',
                    inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
                    showCancelButton: true,
                    confirmButtonColor: '#800020',
                    confirmButtonText: 'Conferma',
                    cancelButtonText: 'Annulla',
                    customClass: { input: 'swal-password-input' },
                    preConfirm: (password) => {
                        if (password !== "admin") { 
                            Swal.showValidationMessage('Password errata!');
                            return false;
                        }
                        return password;
                    }
                }).then((passwordResult) => {
                    if (passwordResult.isConfirmed) {
                        Swal.fire({
                            title: 'Modifica Causa Decesso',
                            html: `
                                <div style="text-align:left; margin-bottom:15px;">
                                  <p style="font-size: 1em; margin-bottom: 5px;">Paziente: <strong>${nomePaziente}</strong></p>
                                  <p style="font-size: 0.9em; margin-bottom: 15px;">Data Decesso: ${dataOraFormatted} (non modificabile)</p>
                                </div>
                                <textarea id="swal-nuovaCausaDecesso" class="swal2-textarea" placeholder="Nuova causa del decesso..." style="min-height: 100px;">${causaOriginale || ''}</textarea>
                            `,
                            showCancelButton: true,
                            confirmButtonColor: '#800020',
                            confirmButtonText: 'Salva Modifiche',
                            cancelButtonText: 'Annulla',
                            customClass: { textarea: 'swal-causa-textarea' },
                            didOpen: () => {
                                const textarea = document.getElementById('swal-nuovaCausaDecesso');
                                if (textarea) {
                                    textarea.focus();
                                    textarea.addEventListener('input', function() {
                                        let value = this.value;
                                        if (value.length > 0) {
                                            const selectionStart = this.selectionStart;
                                            const selectionEnd = this.selectionEnd;
                                            const newValue = value.charAt(0).toUpperCase() + value.slice(1);
                                            if (this.value !== newValue) {
                                                this.value = newValue;
                                                this.setSelectionRange(selectionStart, selectionEnd);
                                            }
                                        }
                                    });
                                }
                            },
                            preConfirm: () => {
                                let nuovaCausa = document.getElementById('swal-nuovaCausaDecesso').value.trim();
                                if (!nuovaCausa) {
                                    Swal.showValidationMessage('La causa non può essere vuota.');
                                    return false;
                                }
                                if (nuovaCausa.length < 3) {
                                     Swal.showValidationMessage('La causa deve contenere almeno 3 caratteri.');
                                     return false;
                                }
                                return nuovaCausa.charAt(0).toUpperCase() + nuovaCausa.slice(1);
                            }
                        }).then((editResult) => {
                            if (editResult.isConfirmed) {
                                const nuovaCausaValorizzata = editResult.value;
                                const postForm = document.createElement('form');
                                postForm.method = 'post';
                                const currentSearchParams = new URLSearchParams(window.location.search);
                                postForm.action = 'ricoveri.php?' + currentSearchParams.toString();

                                const hiddenCSSN = document.createElement('input');
                                hiddenCSSN.type = 'hidden';
                                hiddenCSSN.name = 'pazienteCSSN_causa';
                                hiddenCSSN.value = cssnPaziente; 
                                postForm.appendChild(hiddenCSSN);

                                const hiddenCausa = document.createElement('input');
                                hiddenCausa.type = 'hidden';
                                hiddenCausa.name = 'nuovaCausaDecesso';
                                hiddenCausa.value = nuovaCausaValorizzata;
                                postForm.appendChild(hiddenCausa);

                                const hiddenAction = document.createElement('input');
                                hiddenAction.type = 'hidden';
                                hiddenAction.name = 'update_causa_decesso';
                                hiddenAction.value = '1';
                                postForm.appendChild(hiddenAction);

                                document.body.appendChild(postForm);
                                postForm.submit();
                            }
                        });
                    }
                });
            }
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const formFiltri = document.querySelector('aside.sidebar .filtro form');

    if (formFiltri) {
        formFiltri.addEventListener('submit', function(event) {
           
            const dataInizioInput = formFiltri.querySelector('input[name="filtro_data_inizio"]');
            const dataFineInput = formFiltri.querySelector('input[name="filtro_data_fine"]');

            if (dataInizioInput && dataFineInput) {
                const dataInizioValue = dataInizioInput.value;
                const dataFineValue = dataFineInput.value;

               
                if (dataInizioValue && dataFineValue) {
                    const dataInizio = new Date(dataInizioValue);
                    const dataFine = new Date(dataFineValue);

                    
                    dataInizio.setHours(0, 0, 0, 0);
                    dataFine.setHours(0, 0, 0, 0);

                    if (dataFine < dataInizio) {
                        event.preventDefault(); 

                        Swal.fire({
                            icon: 'error',
                            title: 'Intervallo Date Non Valido',
                            text: 'La data "A data" non può essere precedente alla data "Da data". Si prega di correggere.',
                            confirmButtonColor: '#002080', 
                            confirmButtonText: 'Conferma'
                        });
                    }
                }
            }
        });
    } else {
        console.warn('Attenzione: Form dei filtri non trovato. La validazione delle date potrebbe non funzionare.');
    }
});
</script>

<?php include '../MainLayout/footer.php'; ?>
</body>
</html>

<?php
if (isset($stmt)) $stmt->close();
if (isset($stmtCount)) $stmtCount->close();
if (isset($conn)) $conn->close();
?>
