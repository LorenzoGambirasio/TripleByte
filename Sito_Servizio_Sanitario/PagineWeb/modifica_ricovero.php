<?php
$page = 'modifica_ricovero';
include '../db_connection.php'; 

define('STATO_ATTIVO', 0); 
define('STATO_DIMESSO', 2);
define('STATO_TRASFERITO', 1);
define('STATO_DECEDUTO', 3);
define('MAX_DURATA_RICOVERO_MOD', 36500);
define('MAX_COSTO_RICOVERO_MOD', 99999999.99);
define('MAX_LUNGHEZZA_MOTIVO_MOD', 499);

if (!isset($_GET['codOspedale']) || !isset($_GET['codRicovero'])) {
    header('Location: ricoveri.php');
    exit;
}

$codOspedale = $_GET['codOspedale'];
$codRicovero = $_GET['codRicovero'];

$updateMessage = null;
$updateError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  
    $paziente_form = trim($_POST['paziente'] ?? '');
    $data_form = trim($_POST['data'] ?? ''); 
    $durata_form = trim($_POST['durata'] ?? '');
    $motivo_form = trim($_POST['motivo'] ?? '');
    $costo_form = trim(str_replace(',', '.', $_POST['costo'] ?? '')); 
    $patologie_selezionate_form = $_POST['patologie'] ?? [];

    

    $form_validation_errors = []; 

   
    if (empty($paziente_form)) { $form_validation_errors[] = "Il campo Paziente è obbligatorio."; }
    if (empty($data_form)) { $form_validation_errors[] = "Il campo Data Ricovero (originale) risulta mancante."; }
    if (empty($durata_form)) { $form_validation_errors[] = "Il campo Durata è obbligatorio."; }
    if (empty($motivo_form)) { $form_validation_errors[] = "Il campo Motivo Ricovero è obbligatorio."; }
    if ($costo_form === '' || $costo_form === null) { $form_validation_errors[] = "Il campo Costo è obbligatorio."; }

    if (!empty($durata_form)) {
        if (!is_numeric($durata_form) || intval($durata_form) <= 0) {
            $form_validation_errors[] = "La durata deve essere un numero intero positivo maggiore di zero.";
        } elseif (intval($durata_form) > MAX_DURATA_RICOVERO_MOD) {
            $form_validation_errors[] = "La durata del ricovero non può superare " . MAX_DURATA_RICOVERO_MOD . " giorni.";
        }
    }

    if (mb_strlen($motivo_form, 'UTF-8') > MAX_LUNGHEZZA_MOTIVO_MOD) {
        $form_validation_errors[] = "Il motivo del ricovero non può superare " . MAX_LUNGHEZZA_MOTIVO_MOD . " caratteri. Inseriti: " . mb_strlen($motivo_form, 'UTF-8') . ".";
    }

    if ($costo_form !== '' && $costo_form !== null) {
        if (!is_numeric($costo_form) || floatval($costo_form) < 0) {
            $form_validation_errors[] = "Il costo deve essere un numero positivo.";
        } elseif (floatval($costo_form) > MAX_COSTO_RICOVERO_MOD) {
            $form_validation_errors[] = "Il costo del ricovero non può superare " . number_format(MAX_COSTO_RICOVERO_MOD, 2, '.', '') . " €.";
        }
    }
    
    if (!empty($data_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $data_form)) {
        $form_validation_errors[] = "Il formato della Data Ricovero (originale) non è valido.";
    }


    if (empty($form_validation_errors) && !empty($paziente_form) && !empty($data_form)) {
        $stmtPazNascitaVal = $conn->prepare("SELECT dataNascita FROM Cittadino WHERE CSSN = ?");
        if ($stmtPazNascitaVal) {
            $stmtPazNascitaVal->bind_param("s", $paziente_form);
            $stmtPazNascitaVal->execute();
            $resultPazNascitaVal = $stmtPazNascitaVal->get_result();
            if ($pazienteSelezionatoVal = $resultPazNascitaVal->fetch_assoc()) {
                $dataNascitaPazDBVal = $pazienteSelezionatoVal['dataNascita'];
                if ($dataNascitaPazDBVal) {
                    try {
                        $dataRicoveroObjVal = new DateTime($data_form); 
                        $dataNascitaObjVal = new DateTime($dataNascitaPazDBVal);
                        $dataRicoveroObjVal->setTime(0,0,0);
                        $dataNascitaObjVal->setTime(0,0,0);
                        if ($dataRicoveroObjVal < $dataNascitaObjVal) {
                            $form_validation_errors[] = "La data di ricovero (" . $dataRicoveroObjVal->format('d/m/Y') . ") non può essere precedente alla data di nascita del paziente (" . $dataNascitaObjVal->format('d/m/Y') . ").";
                        }
                    } catch (Exception $e) {
                        $form_validation_errors[] = "Errore nel formato delle date per il confronto (server).";
                    }
                } else {
                     $form_validation_errors[] = "Data di nascita non disponibile per il paziente selezionato.";
                }
            } else {
                $form_validation_errors[] = "Paziente (" . htmlspecialchars($paziente_form) . ") non trovato per validazione data nascita (server).";
            }
            $stmtPazNascitaVal->close();
        } else {
             $form_validation_errors[] = "Errore query data nascita paziente (server).";
        }
    }

    
    if (!empty($form_validation_errors)) {
        $updateError = implode("<br>", $form_validation_errors);
    } else {
        
        $dataRicoveroPHP = new DateTime($data_form); 
        $oggiPHP = new DateTime();
        $dataFinePHP = clone $dataRicoveroPHP;
        $dataFinePHP->modify("+" . intval($durata_form) . " days"); 

        $stmtStatoOriginalePHP = $conn->prepare("SELECT stato FROM Ricovero WHERE codOspedale = ? AND cod = ?");
        $statoOriginaleDBPHP = STATO_ATTIVO;
        if ($stmtStatoOriginalePHP) {
            $stmtStatoOriginalePHP->bind_param("ss", $codOspedale, $codRicovero);
            $stmtStatoOriginalePHP->execute();
            $resStatoOrigPHP = $stmtStatoOriginalePHP->get_result();
            if ($resStatoOrigPHP && $resStatoOrigPHP->num_rows > 0) {
                 $statoRowPHP = $resStatoOrigPHP->fetch_assoc();
                 $statoOriginaleDBPHP = (int)$statoRowPHP['stato'];
            }
            $stmtStatoOriginalePHP->close();
        }
        
        $statoCalcolato = $statoOriginaleDBPHP; 
        if ($statoOriginaleDBPHP == STATO_ATTIVO) { 
            if ($dataFinePHP < $oggiPHP) {
                $statoCalcolato = STATO_DIMESSO; 
            } else {
                $statoCalcolato = STATO_ATTIVO;
            }
        } 

        $conn->begin_transaction();
        try {
            $updateStmt = $conn->prepare("
                UPDATE Ricovero
                SET paziente = ?, durata = ?, motivo = ?, costo = ?, stato = ?
                WHERE codOspedale = ? AND cod = ?
            ");
            if ($updateStmt === false) throw new Exception("Errore DB (prepare Ricovero): " . $conn->error);
            $updateStmt->bind_param("sississ", $paziente_form, $durata_form, $motivo_form, $costo_form, $statoCalcolato, $codOspedale, $codRicovero);
            if (!$updateStmt->execute()) throw new Exception("Errore DB (execute Ricovero): " . $updateStmt->error);
            $updateStmt->close();
            
            $deletePatologieStmt = $conn->prepare("DELETE FROM PatologiaRicovero WHERE codOspedale = ? AND codRicovero = ?");
            if ($deletePatologieStmt === false) throw new Exception("Errore DB (prepare delete Patologie): " . $conn->error);
            $deletePatologieStmt->bind_param("ss", $codOspedale, $codRicovero);
            $deletePatologieStmt->execute();
            $deletePatologieStmt->close();

            if (!empty($patologie_selezionate_form)) {
                $insertPatologiaStmt = $conn->prepare("INSERT INTO PatologiaRicovero (codOspedale, codRicovero, codPatologia) VALUES (?, ?, ?)");
                if ($insertPatologiaStmt === false) throw new Exception("Errore DB (prepare insert Patologie): " . $conn->error);
                foreach ($patologie_selezionate_form as $patologiaCod) {
                    $insertPatologiaStmt->bind_param("sss", $codOspedale, $codRicovero, $patologiaCod);
                    if(!$insertPatologiaStmt->execute()) throw new Exception("Errore DB (execute insert Patologia " .htmlspecialchars($patologiaCod). "): " . $insertPatologiaStmt->error);
                }
                $insertPatologiaStmt->close();
            }
            
            $conn->commit();
            $updateMessage = "Ricovero aggiornato con successo!";
            $success_action = "ricovero_aggiornato";
            $_GET['update_success'] = '1'; 

        } catch (Exception $e) {
            $conn->rollback();
            $updateError = "Errore durante l'aggiornamento: " . $e->getMessage();
        }
    }
}

$query = "
    SELECT
        r.codOspedale,
        r.cod AS codRicovero,
        r.paziente AS pazienteCSSN,
        c.nome AS nomePaziente,
        c.cognome AS cognomePaziente,
        c.dataNascita AS dataNascitaPaziente, /* <-- RIGA MODIFICATA/AGGIUNTA */
        o.nome AS nomeOspedale,
        r.data,
        r.durata,
        r.motivo,
        r.costo,
        r.stato,
        (SELECT GROUP_CONCAT(pr.codPatologia)
         FROM PatologiaRicovero pr
         WHERE pr.codOspedale = r.codOspedale AND pr.codRicovero = r.cod) AS patologieCod
    FROM Ricovero r
    LEFT JOIN Cittadino c ON r.paziente = c.CSSN
    LEFT JOIN Ospedale o ON r.codOspedale = o.codice
    WHERE r.codOspedale = ? AND r.cod = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $codOspedale, $codRicovero);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ricoveri.php');
    exit;
}

$ricovero = $result->fetch_assoc();
$currentPazienteDataNascitaValueHTML = $ricovero['dataNascitaPaziente'] ?? '';

$queryPazienti = "SELECT CSSN, nome, cognome, dataNascita FROM Cittadino WHERE deceduto = 0 OR deceduto IS NULL ORDER BY cognome, nome";
$resultPazienti = $conn->query($queryPazienti);

$queryOspedali = "SELECT codice, nome FROM Ospedale ORDER BY nome";
$resultOspedali = $conn->query($queryOspedali);
 
$queryPatologie = "SELECT cod, nome FROM Patologia ORDER BY nome";
$resultPatologie = $conn->query($queryPatologie);

$patologieAttuali = [];
if (!empty($ricovero['patologieCod'])) {
    $patologieAttuali = explode(',', $ricovero['patologieCod']);
}


$queryPatologieAttualiReload = "
    SELECT GROUP_CONCAT(pr.codPatologia) AS patologieCod
    FROM PatologiaRicovero pr
    WHERE pr.codOspedale = ? AND pr.codRicovero = ?
";
$stmtPatologieReload = $conn->prepare($queryPatologieAttualiReload);
$stmtPatologieReload->bind_param("ss", $codOspedale, $codRicovero);
$stmtPatologieReload->execute();
$resultPatologieReload = $stmtPatologieReload->get_result()->fetch_assoc();
$patologieAttuali = []; 
if (!empty($resultPatologieReload['patologieCod'])) {
    $patologieAttuali = explode(',', $resultPatologieReload['patologieCod']);
}
$stmtPatologieReload->close();

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Ricovero</title>
    <link rel="stylesheet" href="../CSS/cittadini.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/paginazione.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/base.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/header.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/menu.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/footer.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/crud.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/modifica_ricovero.css?v=<?= time(); ?>">
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
    </aside>

    <main class="content">
        <h1 class="titoloPalette">Modifica Ricovero</h1>

        <div class="form-container">
            <form method="post" action="">
                <div class="form-group">
                <label for="ospedale_nome">Ospedale:</label>
                <input type="text" id="ospedale_nome" value="<?= htmlspecialchars($ricovero['nomeOspedale'] ?? 'N/D') ?>" readonly>
                <input type="hidden" name="ospedale" value="<?= htmlspecialchars($ricovero['codOspedale'] ?? '') ?>">
                <?php if (isset($resultOspedali) && $resultOspedali !== false) $resultOspedali->free();  ?>
                </div>

                <div class="form-group">
                    <label for="codRicovero">Codice Ricovero:</label>
                    <input type="text" id="codRicovero" value="<?= htmlspecialchars($ricovero['codRicovero']) ?>" readonly>
                    <input type="hidden" name="codRicovero" value="<?= htmlspecialchars($ricovero['codRicovero']) ?>">
                </div>

                <div class="form-group">
                    <label for="stato_ricovero">Stato Ricovero:</label>
                    <div class="stato-indicator">
                        <?php
                        $statoClasses = ['status-attivo', 'status-trasferito', 'status-dimesso'];
                        $statoLabels = ['ATTIVO', 'TRASFERITO', 'DIMESSO'];
                        $stato = (int)$ricovero['stato'];
                        $statoClass = isset($statoClasses[$stato]) ? $statoClasses[$stato] : 'status-sconosciuto';
                        $statoLabel = isset($statoLabels[$stato]) ? $statoLabels[$stato] : 'Sconosciuto';
                        ?>
                        <div class="stato-display">
                            
                            <span class="stato-text"><?= $statoLabel ?></span>
                            <span class="status-dot <?= $statoClass ?>"></span>
                        </div>                     
                    </div>
                </div>

                <div class="form-group">
                    <label for="paziente">Paziente:</label>
                    <div class="single-select-container">
                        <div class="single-select-combobox-toggle" id="paziente-toggle">
                            <span class="selection-value" id="paziente-selected-value">
                                <?php 
                                $selectedText = "Seleziona un paziente...";
                                if ($resultPazienti && $resultPazienti->num_rows > 0):
                                    $resultPazienti->data_seek(0);
                                    while ($paziente = $resultPazienti->fetch_assoc()):
                                        if ($paziente['CSSN'] == $ricovero['pazienteCSSN']):
                                            $selectedText = $paziente['cognome'] . ' ' . $paziente['nome'] . ' (' . $paziente['CSSN'] . ')';
                                            break;
                                        endif;
                                    endwhile;
                                    $resultPazienti->data_seek(0);
                                endif;
                                echo htmlspecialchars($selectedText);
                                ?>
                            </span>
                        </div>
                        <div class="single-select-dropdown" id="paziente-dropdown">
                            <input type="text" class="search-ospedale" id="search-paziente" placeholder="Cerca paziente...">
                            <div class="ospedale-container">
                                <?php if ($resultPazienti && $resultPazienti->num_rows > 0): ?>
                                    <?php while ($paziente = $resultPazienti->fetch_assoc()): ?>
                                        <div class="ospedale-item <?= ($paziente['CSSN'] == $ricovero['pazienteCSSN']) ? 'selected' : '' ?>"
                                             data-value="<?= htmlspecialchars($paziente['CSSN']) ?>"
                                             data-birthdate="<?= htmlspecialchars($paziente['dataNascita']) ?>"> 
                                            <?= htmlspecialchars($paziente['cognome'] . ' ' . $paziente['nome'] . ' (' . $paziente['CSSN'] . ')') ?>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="no-results">Nessun paziente disponibile</p>
                                <?php endif; ?>
                                <?php if (isset($resultPazienti) && $resultPazienti !== false) $resultPazienti->data_seek(0); ?>
                            </div>
                        </div>
                        <input type="hidden" id="paziente" name="paziente" value="<?= htmlspecialchars($ricovero['pazienteCSSN']) ?>" required data-birthdate="<?= htmlspecialchars($currentPazienteDataNascitaValueHTML) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="data">Data Ricovero:</label>
                    <input type="date" id="data" name="data" value="<?= htmlspecialchars($ricovero['data']) ?>" readonly>
                    <small class="field-note">La data di ricovero non può essere modificata</small>
                </div>

                <div class="form-group duration-input-group"> 
                    <label for="durata">Durata (giorni):</label>
                    <input type="number" id="durata" name="durata" class="duration-input" value="<?= htmlspecialchars($ricovero['durata']) ?>" min="1" max="<?= MAX_DURATA_RICOVERO_MOD ?>" required>
                </div>

                <div class="form-group">
                    <label for="motivo">Motivo Ricovero:</label>
                    <textarea id="motivo" name="motivo" required maxlength="<?= MAX_LUNGHEZZA_MOTIVO_MOD ?>"><?= htmlspecialchars($ricovero['motivo']) ?></textarea>
                </div>

                <div class="form-group cost-input-group"> 
                    <label for="costo">Costo (€):</label>
                    <input type="number" id="costo" name="costo" class="cost-input" value="<?= htmlspecialchars($ricovero['costo']) ?>" min="0" step="0.01" max="<?= MAX_COSTO_RICOVERO_MOD ?>" required>
                </div>

                <div class="form-group">
                    <label>Patologie associate:</label>
                    <div class="multi-select-container">
                        <div class="multi-select-combobox-toggle" id="patologie-toggle">
                            Seleziona una patologia...
                            <span class="selection-count" id="patologie-count" style="display: none;"></span>
                        </div>

                        <div class="multi-select-dropdown" id="patologie-dropdown">
                            <input type="text" class="search-patologie" id="search-patologie" placeholder="Cerca patologia...">
                            <div class="patologie-container">
                                <?php
                                if ($resultPatologie && $resultPatologie->num_rows > 0): ?>
                                    <?php while ($patologia = $resultPatologie->fetch_assoc()): ?>
                                        <div class="patologia-item <?= in_array($patologia['cod'], $patologieAttuali) ? 'selected' : '' ?>">
                                            <input type="checkbox" id="patologia_<?= $patologia['cod'] ?>"
                                                name="patologie[]" value="<?= htmlspecialchars($patologia['cod']) ?>" 
                                                <?= in_array($patologia['cod'], $patologieAttuali) ? 'checked' : '' ?>>
                                            <label for="patologia_<?= $patologia['cod'] ?>"><?= htmlspecialchars($patologia['nome']) ?></label>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="no-results">Nessuna patologia disponibile</p>
                                <?php endif; ?>
                                <?php if (isset($resultPatologie) && $resultPatologie !== false) $resultPatologie->free(); // Libera il risultato ?>
                            </div>
                        </div>
                    </div>
                    <div class="selected-patologie-badges" id="selected-patologie-badges"></div>
                </div>
                
                <input type="hidden" name="update" value="1">

                <div class="buttons-container">
                    <button type="submit" name="update" class="btn-salva" id="btnSalva">Salva</button>
                    <a href="ricoveri.php" class="btn-annulla">Annulla</a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include '../MainLayout/footer.php'; ?>

<script>
let formModificato = false;

<?php if (!empty($updateMessage) && isset($success_action) && $success_action === 'ricovero_aggiornato'): ?>
Swal.fire({
    icon: 'success',
    title: 'Ricovero Aggiornato!',
    text: '<?= addslashes(htmlspecialchars($updateMessage)) ?>',
    timer: 2000,
    showConfirmButton: false,
    willClose: () => {
        window.location.href = 'ricoveri.php';
    }
});
<?php elseif (!empty($updateError)): ?>
Swal.fire({
    icon: 'error',
    title: 'Errore',
    html: '<?= addslashes(nl2br(htmlspecialchars($updateError))) ?>',
    confirmButtonColor: '#002080'
});
<?php endif; ?>

document.querySelectorAll('input:not([readonly]), textarea, select').forEach(element => {
    element.addEventListener('change', function() {
        formModificato = true;
    });
});

function updateStatoIndicator() {
    const dataInput = document.getElementById('data');
    const durataInput = document.getElementById('durata');
    
    if (!dataInput.value || !durataInput.value) return;
    
    const dataRicovero = new Date(dataInput.value);
    const durata = parseInt(durataInput.value);
    const oggi = new Date();
    
    const dataFine = new Date(dataRicovero);
    dataFine.setDate(dataFine.getDate() + durata);
    
    let statoClass = 'status-attivo';
    let statoLabel = 'ATTIVO';
    
    if (dataFine < oggi) {
        statoClass = 'status-dimesso';
        statoLabel = 'DIMESSO';
    }
    
    const statoDot = document.querySelector('.status-dot');
    const statoText = document.querySelector('.stato-text');
    
    if (statoDot && statoText) {
        statoDot.classList.remove('status-attivo', 'status-trasferito', 'status-dimesso', 'status-sconosciuto');
        statoDot.classList.add(statoClass);
        statoText.textContent = statoLabel;
    }
}

document.getElementById('durata').addEventListener('change', updateStatoIndicator);

const patologieToggle = document.getElementById('patologie-toggle');
const patologieDropdown = document.getElementById('patologie-dropdown');
const searchPatologie = document.getElementById('search-patologie');
const patologieItems = document.querySelectorAll('.patologia-item');
const selectedBadgesContainer = document.getElementById('selected-patologie-badges');
const patologieCount = document.getElementById('patologie-count');
const patologiePlaceholder = document.getElementById('patologie-placeholder');

function updatePatologieSelection() {
    const selectedCheckboxes = document.querySelectorAll('.patologie-container input[type="checkbox"]:checked');
    selectedBadgesContainer.innerHTML = '';
    
    if (selectedCheckboxes.length > 0) {
        if (patologiePlaceholder) patologiePlaceholder.style.display = 'none';
        patologieCount.textContent = selectedCheckboxes.length;
        patologieCount.style.display = 'inline';
        
            selectedCheckboxes.forEach(checkbox => {
            const label = document.querySelector(`label[for="${checkbox.id}"]`);
            const badge = document.createElement('div');
            badge.className = 'patologia-badge';
            badge.textContent = label ? label.textContent : checkbox.value;
            
            const removeBtn = document.createElement('span');
            removeBtn.className = 'remove-badge';
            removeBtn.innerHTML = '×';
            removeBtn.dataset.value = checkbox.value;
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                checkbox.checked = false;
                checkbox.closest('.patologia-item')?.classList.remove('selected');
                updatePatologieSelection();
            };
            
            badge.appendChild(removeBtn);
            selectedBadgesContainer.appendChild(badge);
        });
    } else {
        if (patologiePlaceholder) patologiePlaceholder.style.display = 'inline';
        patologieCount.style.display = 'none';
    }
}

patologieToggle.addEventListener('click', function(e) {
    e.stopPropagation();
    const isOpen = patologieDropdown.classList.toggle('open');
    patologieToggle.classList.toggle('open');
    if (isOpen) {
        searchPatologie.focus();
    }
});

patologieToggle.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        patologieToggle.click();
    }
});

document.addEventListener('click', function(event) {
    if (!patologieToggle.contains(event.target) && !patologieDropdown.contains(event.target)) {
        patologieDropdown.classList.remove('open');
        patologieToggle.classList.remove('open');
    }
});

searchPatologie.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const patologieContainer = document.querySelector('.patologie-container');
    let visibleCount = 0;
    
    patologieItems.forEach(item => {
        const itemText = item.textContent.toLowerCase();
        const dataName = item.dataset.name ? item.dataset.name.toLowerCase() : '';
        const dataValue = item.dataset.value ? item.dataset.value.toLowerCase() : '';
        const matches = itemText.includes(searchTerm) || dataName.includes(searchTerm) || dataValue.includes(searchTerm);
        
        item.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });
    
    let noResultsMsg = patologieContainer.querySelector('.no-results');
    if (visibleCount === 0 && !noResultsMsg) {
        noResultsMsg = document.createElement('p');
        noResultsMsg.className = 'no-results';
        noResultsMsg.textContent = 'Nessun risultato trovato';
        patologieContainer.appendChild(noResultsMsg);
    } else if (visibleCount > 0 && noResultsMsg) {
        noResultsMsg.remove();
    }
});

patologieDropdown.addEventListener('click', (e) => {
    const clickedItem = e.target.closest('.patologia-item');
    if (!clickedItem) return;
    
    const checkbox = clickedItem.querySelector('input[type="checkbox"]');
    if (checkbox && e.target !== checkbox && e.target.tagName !== 'LABEL') {
        checkbox.checked = !checkbox.checked;
    }
    
    if (checkbox) {
        clickedItem.classList.toggle('selected', checkbox.checked);
        updatePatologieSelection();
    }
});

updatePatologieSelection();

document.addEventListener('DOMContentLoaded', function() {
    const pazienteToggle = document.getElementById('paziente-toggle');
    const pazienteDropdown = document.getElementById('paziente-dropdown');
    const pazienteSelectedValue = document.getElementById('paziente-selected-value');
    const pazienteHiddenInput = document.getElementById('paziente');
    const searchPaziente = document.getElementById('search-paziente');
    const pazienteItems = document.querySelectorAll('.ospedale-item');
    
    pazienteItems.forEach((item, index) => {
        item.style.setProperty('--item-index', index + 1);
    });
  
    pazienteToggle.addEventListener('click', function() {
        pazienteToggle.classList.toggle('open');
        pazienteDropdown.classList.toggle('open');
        if (pazienteDropdown.classList.contains('open')) {
            searchPaziente.focus();
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!pazienteToggle.contains(e.target) && !pazienteDropdown.contains(e.target)) {
            pazienteToggle.classList.remove('open');
            pazienteDropdown.classList.remove('open');
        }
    });
    
    searchPaziente.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        let foundItems = 0;
        
        pazienteItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = 'flex';
                foundItems++;
            } else {
                item.style.display = 'none';
            }
        });
        
        const noResults = pazienteDropdown.querySelector('.no-results');
        if (foundItems === 0) {
            if (!noResults) {
                const noResultsElement = document.createElement('p');
                noResultsElement.className = 'no-results';
                noResultsElement.textContent = 'Nessun paziente trovato';
                pazienteDropdown.querySelector('.ospedale-container').appendChild(noResultsElement);
            }
        } else {
            if (noResults) {
                noResults.remove();
            }
        }
    });
    
    pazienteItems.forEach(item => {
        item.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            const text = this.textContent;
            
            pazienteSelectedValue.textContent = text;
            pazienteHiddenInput.value = value;
             const birthdate = this.getAttribute('data-birthdate');
        if (birthdate) {
            pazienteHiddenInput.dataset.birthdate = birthdate;
        } else {
            delete pazienteHiddenInput.dataset.birthdate;
        }

            
            pazienteItems.forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            
            pazienteToggle.classList.remove('open');
            pazienteDropdown.classList.remove('open');
            
            formModificato = true; 
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const btnSalva = document.getElementById('btnSalva');
    const form = btnSalva.closest('form');

    if (form && btnSalva) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            
            let clientSideErrors = [];
    const durataInputJS = document.getElementById('durata');
    const motivoInputJS = document.getElementById('motivo');
    const costoInputJS = document.getElementById('costo');
    const pazienteHiddenInputJS = document.getElementById('paziente');
    const dataRicoveroOriginaleJS = document.getElementById('data').value; 

    const MAX_DURATA_JS = <?= MAX_DURATA_RICOVERO_MOD ?>;
    const MAX_COSTO_JS = <?= MAX_COSTO_RICOVERO_MOD ?>;
    const MAX_LUNGHEZZA_MOTIVO_JS = <?= MAX_LUNGHEZZA_MOTIVO_MOD ?>;

    if (!pazienteHiddenInputJS || pazienteHiddenInputJS.value.trim() === '') {
         clientSideErrors.push("Il campo Paziente è obbligatorio.");
    }

    if (!durataInputJS || durataInputJS.value.trim() === '') {
        clientSideErrors.push("Il campo Durata è obbligatorio.");
    } else {
        const durataVal = parseInt(durataInputJS.value);
        if (isNaN(durataVal) || durataVal <= 0) {
            clientSideErrors.push("La durata deve essere un numero intero positivo.");
        } else if (durataVal > MAX_DURATA_JS) {
            clientSideErrors.push(`La durata del ricovero non può superare ${MAX_DURATA_JS} giorni.`);
        }
    }

    if (!motivoInputJS || motivoInputJS.value.trim() === '') {
        clientSideErrors.push("Il campo Motivo Ricovero è obbligatorio.");
    } else if (motivoInputJS.value.trim().length > MAX_LUNGHEZZA_MOTIVO_JS) {
         clientSideErrors.push(`Il motivo del ricovero non può superare ${MAX_LUNGHEZZA_MOTIVO_JS} caratteri. Inseriti: ${motivoInputJS.value.trim().length}.`);
    }

    if (!costoInputJS || costoInputJS.value.trim() === '') {
        clientSideErrors.push("Il campo Costo è obbligatorio.");
    } else {
        const costoVal = parseFloat(costoInputJS.value.replace(',', '.'));
        if (isNaN(costoVal) || costoVal < 0) {
            clientSideErrors.push("Il costo deve essere un numero positivo.");
        } else if (costoVal > MAX_COSTO_JS) {
            clientSideErrors.push(`Il costo del ricovero non può superare ${MAX_COSTO_JS.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €.`);
        }
    }

    const pazienteBirthDateValue = pazienteHiddenInputJS ? pazienteHiddenInputJS.dataset.birthdate : null;
    if (dataRicoveroOriginaleJS && pazienteBirthDateValue) {
        const dataRicoveroDate = new Date(dataRicoveroOriginaleJS);
        const pazienteNascitaDate = new Date(pazienteBirthDateValue);
        dataRicoveroDate.setHours(0,0,0,0);
        pazienteNascitaDate.setHours(0,0,0,0);
        if (dataRicoveroDate < pazienteNascitaDate) {
            clientSideErrors.push("La data di ricovero ("+ dataRicoveroDate.toLocaleDateString('it-IT') +") non può essere precedente alla data di nascita del paziente ("+ pazienteNascitaDate.toLocaleDateString('it-IT') +").");
        }
    }

    if (clientSideErrors.length > 0) {
        
        Swal.fire({
            icon: 'error',
            title: 'Errori di Validazione',
            html: clientSideErrors.join('<br>'),
            confirmButtonColor: '#002080'
        });
        return; 
    }
            
            Swal.fire({
                title: 'Sei sicuro?',
                text: "Confermi la modifica del ricovero? Se hai cambiato l\'ospedale o il codice ricovero, il record verrà riassegnato.",
                icon: 'warning', 
                showCancelButton: true,
                confirmButtonColor: '#002080', 
                cancelButtonColor: '#800000',  
                confirmButtonText: 'Sì, Salva!',
                cancelButtonText: 'Annulla'
            }).then((result) => {
                if (result.isConfirmed) {
                    formModificato = false;
                    form.submit();
                }
            });
        });
    }

    document.querySelector('.btn-annulla').addEventListener('click', function() {
        formModificato = false;
    });
});
</script>
