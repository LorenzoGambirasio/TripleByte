<?php
$page = 'trasferisci_ricovero';
include '../db_connection.php'; 

if (!isset($_GET['codOspedale']) || !isset($_GET['codRicovero'])) {
    header('Location: ricoveri.php');
    exit;
}

$codOspedaleVecchio = $_GET['codOspedale'];
$codRicoveroVecchio = $_GET['codRicovero'];

$updateMessage = null;
$updateError = null;

$ospedaleSelezionato = '';
$durataRicovero = '';
$motivoRicovero = '';
$costoRicovero = '';
$patologieSelezionate = []; 

$nuovoCodRicoveroGenerato = '';
if (!$conn->query("LOCK TABLES Ricovero WRITE")) {
    error_log("Errore nel bloccare la tabella Ricovero per la generazione codice: " . $conn->error);
    $nuovoCodRicoveroGenerato = 'ERR_GEN_COD'; 
} else {
    $queryUltimoCodice = "SELECT cod FROM Ricovero ORDER BY cod DESC LIMIT 1";
    $resultUltimoCodice = $conn->query($queryUltimoCodice);

    if ($resultUltimoCodice === false) {
         error_log("Errore nel recupero dell'ultimo codice ricovero: " . $conn->error);
         $nuovoCodRicoveroGenerato = 'ERR_QUERY_COD';
    } elseif ($resultUltimoCodice->num_rows > 0) {
        $ultimoCodiceRow = $resultUltimoCodice->fetch_assoc();
        $ultimoCodice = $ultimoCodiceRow['cod'];
        if (preg_match('/^R(\d+)$/', $ultimoCodice, $matches)) {
            $numeroUltimoCodice = (int)$matches[1];
            $nuovoNumeroCodice = $numeroUltimoCodice + 1;
            $nuovoCodRicoveroGenerato = 'R' . str_pad($nuovoNumeroCodice, strlen($matches[1]), '0', STR_PAD_LEFT);
        } else {
            $nuovoCodRicoveroGenerato = 'R0001'; 
            error_log("Avviso: L'ultimo codice ricovero ('$ultimoCodice') non segue il formato atteso. Inizio da R0001.");
        }
    } else {
        $nuovoCodRicoveroGenerato = 'R0001'; 
    }
    if (isset($resultUltimoCodice) && $resultUltimoCodice instanceof mysqli_result) {
        $resultUltimoCodice->free();
    }
    $conn->query("UNLOCK TABLES"); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuovoOspedale = $_POST['ospedale'] ?? null;
    $nuovaDurata = $_POST['durata'] ?? null;
    $pazienteCSSN = $_POST['paziente'] ?? null; 
    $dataNuovoRicovero = date('Y-m-d'); 
    $motivo = $_POST['motivo'] ?? null;
    $costo = $_POST['costo'] ?? null;
    $patologieDaInserire = isset($_POST['patologie']) && is_array($_POST['patologie']) ? $_POST['patologie'] : [];

    $nuovoCodRicovero = $nuovoCodRicoveroGenerato;
    if (strpos($nuovoCodRicovero, 'ERR_') === 0) { 
        $updateError = "Impossibile procedere: errore nella generazione del codice ricovero. Ricaricare la pagina.";
    }

    if (empty($nuovoOspedale) || empty($nuovaDurata) || empty($pazienteCSSN) || empty($motivo) || !isset($costo)) {
        $updateError = "Tutti i campi (Nuovo Ospedale, Nuova Durata, Motivo, Costo) sono obbligatori.";
    }
    if (!is_numeric($nuovaDurata) || $nuovaDurata < 1) {
        $updateError = ($updateError ? $updateError . "<\n>" : "") . "La durata deve essere un numero maggiore di zero.";
    }
    if (!is_numeric($costo) || $costo < 0) {
        $updateError = ($updateError ? $updateError . "<\n>" : "") . "Il costo deve essere un valore numerico non negativo.";
    }
    if ($nuovoOspedale === $codOspedaleVecchio) { 
        $updateError = ($updateError ? $updateError . "<\n>" : "") . "Non è possibile trasferire un ricovero allo stesso ospedale di origine. Selezionare un ospedale diverso.";
    }


    if (!$updateError) { 
        $conn->begin_transaction();
        $stmtCheckExisting = null;
        $stmtInsertRicovero = null;
        $stmtUpdateVecchioRicovero = null; 
        $stmtInsertPatologia = null;

        try {
            if (!$conn->query("LOCK TABLES Ricovero WRITE, PatologiaRicovero WRITE")) {
                throw new Exception("Errore nel bloccare le tabelle per l'inserimento: " . $conn->error);
            }

            $stmtCheckExisting = $conn->prepare("SELECT 1 FROM Ricovero WHERE codOspedale = ? AND cod = ?");
            if ($stmtCheckExisting === false) {
                throw new Exception("Errore (prepare) verifica esistenza ricovero: " . $conn->error);
            }
            $stmtCheckExisting->bind_param("ss", $nuovoOspedale, $nuovoCodRicovero);
            $stmtCheckExisting->execute();
            $existingResult = $stmtCheckExisting->get_result();
            if ($existingResult->num_rows > 0) {
                throw new Exception("Errore: Il codice ricovero generato ('".htmlspecialchars($nuovoCodRicovero)."') risulta già utilizzato per l'ospedale selezionato. Si prega di ricaricare la pagina e riprovare.");
            }
            $stmtCheckExisting->close(); 
                $stmtInsertRicovero = $conn->prepare("
                INSERT INTO Ricovero (codOspedale, cod, paziente, data, durata, motivo, costo, stato) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ");

            if ($stmtInsertRicovero === false) {
                throw new Exception("Errore (prepare) inserimento Ricovero: " . $conn->error);
            }
            $costoFloat = floatval($costo); 
            $durataInt = intval($nuovaDurata); 
            $statoNuovoRicovero = 0; 

            $stmtInsertRicovero->bind_param("ssssisdi", $nuovoOspedale, $nuovoCodRicovero, $pazienteCSSN, $dataNuovoRicovero, $durataInt, $motivo, $costoFloat, $statoNuovoRicovero);
            
            $stmtInsertRicovero->execute();

            if ($stmtInsertRicovero->affected_rows <= 0) { 
                throw new Exception("Errore nell'inserimento del nuovo ricovero: " . $stmtInsertRicovero->error);
            }
            $stmtInsertRicovero->close(); 

            $stmtUpdateVecchioRicovero = $conn->prepare("
                UPDATE Ricovero SET stato = ? 
                WHERE codOspedale = ? AND cod = ?
            ");
            if ($stmtUpdateVecchioRicovero === false) {
                throw new Exception("Errore (prepare) aggiornamento vecchio Ricovero: " . $conn->error);
            }
            $statoVecchioRicovero = 1; 
            $stmtUpdateVecchioRicovero->bind_param("iss", $statoVecchioRicovero, $codOspedaleVecchio, $codRicoveroVecchio);
            $stmtUpdateVecchioRicovero->execute();

            if ($stmtUpdateVecchioRicovero->affected_rows <= 0) {
                throw new Exception("Errore nell'aggiornamento dello stato del vecchio ricovero o ricovero non trovato: " . $stmtUpdateVecchioRicovero->error);
            }
            $stmtUpdateVecchioRicovero->close();

            if (!empty($patologieDaInserire)) {
                $stmtInsertPatologia = $conn->prepare("INSERT INTO PatologiaRicovero (codOspedale, codRicovero, codPatologia) VALUES (?, ?, ?)");
                if ($stmtInsertPatologia === false) {
                    throw new Exception("Errore (prepare) inserimento PatologiaRicovero: " . $conn->error);
                }
                foreach ($patologieDaInserire as $patologiaCod) {
                    $stmtInsertPatologia->bind_param("sss", $nuovoOspedale, $nuovoCodRicovero, $patologiaCod);
                    $stmtInsertPatologia->execute();
                    if ($stmtInsertPatologia->error) { 
                        throw new Exception("Errore nell'inserimento della patologia '" . htmlspecialchars($patologiaCod) . "': " . $stmtInsertPatologia->error);
                    }
                    if ($stmtInsertPatologia->affected_rows <= 0) { 
                        throw new Exception("L'inserimento della patologia '" . htmlspecialchars($patologiaCod) . "' non ha prodotto righe affette (possibile duplicato o dato non valido).");
                    }
                }
                $stmtInsertPatologia->close(); 
            }

            $conn->commit();
            $updateMessage = "Ricovero trasferito con successo! Nuovo codice: " . htmlspecialchars($nuovoCodRicovero); 
            
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'ricoveri.php';
                }, 3000); // 3 secondi
            </script>";
            

        } catch (Exception $e) {
            $conn->rollback();
            $updateError = "Errore durante il trasferimento: " . $e->getMessage();

            $ospedaleSelezionato = $_POST['ospedale'] ?? $ospedaleSelezionato; 
            $durataRicovero = $_POST['durata'] ?? $durataRicovero;
            $motivoRicovero = $_POST['motivo'] ?? $motivoRicovero;
            $costoRicovero = $_POST['costo'] ?? $costoRicovero;
            $patologieSelezionate = $_POST['patologie'] ?? $patologieSelezionate;

        } finally {
            $conn->query("UNLOCK TABLES"); 
        }
    } else {
        $ospedaleSelezionato = $_POST['ospedale'] ?? $ospedaleSelezionato;
        $durataRicovero = $_POST['durata'] ?? $durataRicovero;
        $motivoRicovero = $_POST['motivo'] ?? $motivoRicovero;
        $costoRicovero = $_POST['costo'] ?? $costoRicovero;
        $patologieSelezionate = $_POST['patologie'] ?? $patologieSelezionate;
    }
}

$queryRicoveroOriginale = "
    SELECT
        r.codOspedale,
        r.cod AS codRicovero,
        r.paziente AS pazienteCSSN_originale,
        c.nome AS nomePaziente_originale,
        c.cognome AS cognomePaziente_originale,
        o.nome AS nomeOspedale_originale,
        r.data AS data_originale,
        r.durata AS durata_originale,
        r.motivo AS motivo_originale,
        r.costo AS costo_originale,
        r.stato AS stato_originale, -- Potrebbe essere utile visualizzare lo stato corrente
        (SELECT GROUP_CONCAT(pr.codPatologia)
         FROM PatologiaRicovero pr
         WHERE pr.codOspedale = r.codOspedale AND pr.codRicovero = r.cod) AS patologieCod_originali
    FROM Ricovero r
    LEFT JOIN Cittadino c ON r.paziente = c.CSSN
    LEFT JOIN Ospedale o ON r.codOspedale = o.codice
    WHERE r.codOspedale = ? AND r.cod = ?
";

$stmtRicovero = $conn->prepare($queryRicoveroOriginale);
if ($stmtRicovero === false) {
    die("Errore critico nella preparazione della query per recuperare i dati del ricovero: " . $conn->error);
}
$stmtRicovero->bind_param("ss", $codOspedaleVecchio, $codRicoveroVecchio);
$stmtRicovero->execute();
$resultRicovero = $stmtRicovero->get_result();

if ($resultRicovero->num_rows === 0) {
    header('Location: ricoveri.php?error=RicoveroNonTrovato');
    exit;
}
$ricoveroOriginaleDati = $resultRicovero->fetch_assoc();
$stmtRicovero->close(); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($updateError)) {
    if (empty($updateError)) { 
        $ospedaleSelezionato = ''; 
        $durataRicovero = $ricoveroOriginaleDati['durata_originale'];
        $motivoRicovero = $ricoveroOriginaleDati['motivo_originale']; 
        $costoRicovero = $ricoveroOriginaleDati['costo_originale'];   
        $patologieSelezionate = !empty($ricoveroOriginaleDati['patologieCod_originali']) ? explode(',', $ricoveroOriginaleDati['patologieCod_originali']) : [];
    }
}


$queryOspedali = "SELECT codice, nome FROM Ospedale ORDER BY nome";
$resultOspedali = $conn->query($queryOspedali);
if ($resultOspedali === false) {
     $formError = ($formError ?? "") . "<br>Errore nel recupero degli ospedali: " . $conn->error; 
}

$queryPatologie = "SELECT cod, nome FROM Patologia ORDER BY nome";
$resultPatologie = $conn->query($queryPatologie);
if ($resultPatologie === false) {
      $formError = ($formError ?? "") . "<br>Errore nel recupero delle patologie: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Trasferisci Ricovero</title>
    <link rel="stylesheet" href="../CSS/cittadini.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/paginazione.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/base.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/header.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/menu.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/footer.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/crud.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/aggiungi_ricovero.css?v=<?= time(); ?>"> 
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
        <h1 class="titoloPalette">Trasferisci Ricovero</h1>
        <p class="info-text">
            Verrà creato un nuovo ricovero per il paziente <strong><?= htmlspecialchars($ricoveroOriginaleDati['nomePaziente_originale'] . ' ' . $ricoveroOriginaleDati['cognomePaziente_originale']) ?></strong> (CSSN: <?= htmlspecialchars($ricoveroOriginaleDati['pazienteCSSN_originale']) ?>).<br>
            Il nuovo codice ricovero generato sarà: <strong><?= htmlspecialchars($nuovoCodRicoveroGenerato) ?></strong>.<br>
            Il paziente sarà trasferito da <?= htmlspecialchars($ricoveroOriginaleDati['nomeOspedale_originale']) ?> presso il nuovo ospedale.
        </p>

        <?php if ($updateMessage): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Successo!',
            text: '<?= addslashes(htmlspecialchars($updateMessage)) ?>',
            timer: 2000,
            showConfirmButton: false,
            willClose: () => {
                window.location.href = 'ricoveri.php';
            }
        });
    });
</script>
<?php endif; ?>

        <?php if ($updateError): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Errore',
            html: '<?= addslashes($updateError) ?>',
            confirmButtonColor: '#002080'
        });
    });
</script>
<?php endif; ?>
        
        <?php if (isset($formError)): ?>
            <div class="alert alert-warning">
                <?= $formError  ?>
            </div>
        <?php endif; ?>


        <div class="form-container">
            <form method="post" action="trasferisci_ricovero.php?codOspedale=<?= htmlspecialchars($codOspedaleVecchio) ?>&codRicovero=<?= htmlspecialchars($codRicoveroVecchio) ?>">
                
                <div class="form-group">
                    <label for="paziente_nome_display">Paziente (Originale):</label>
                    <input type="text" id="paziente_nome_display" value="<?= htmlspecialchars($ricoveroOriginaleDati['nomePaziente_originale'] . ' ' . $ricoveroOriginaleDati['cognomePaziente_originale']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="cssn_display">CSSN (Originale):</label>
                    <input type="text" id="cssn_display" value="<?= htmlspecialchars($ricoveroOriginaleDati['pazienteCSSN_originale']) ?>" readonly>
                    <input type="hidden" name="paziente" value="<?= htmlspecialchars($ricoveroOriginaleDati['pazienteCSSN_originale']) ?>">
                </div>

                <div class="form-group">
                    <label for="data_nuovo_ricovero_display">Data Nuovo Ricovero:</label>
                    <input type="date" id="data_nuovo_ricovero_display" value="<?= htmlspecialchars(date('Y-m-d')) ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="motivo">Motivo Nuovo Ricovero:</label>
                    <textarea id="motivo" name="motivo" required><?= htmlspecialchars($motivoRicovero) ?></textarea>
                </div>

                <div class="form-group cost-input-group">
                    <label for="costo">Costo Nuovo Ricovero (€):</label>
                    <input type="number" id="costo" name="costo" class="cost-input" value="<?= htmlspecialchars($costoRicovero) ?>" min="0" step="0.01" required>
                </div>

                 <div class="form-group">
                    <label for="ospedale">Nuovo Ospedale:</label>
                     <div class="single-select-container">
                        <div class="single-select-combobox-toggle" id="ospedale-toggle">
                            <span class="selection-value" id="ospedale-selected-value">
                                <?php
                                $selectedText = "Seleziona un ospedale...";
                                if ($resultOspedali && $resultOspedali->num_rows > 0 && !empty($ospedaleSelezionato)) {
                                    mysqli_data_seek($resultOspedali, 0); 
                                    while ($ospedale_loop = mysqli_fetch_assoc($resultOspedali)) {
                                        if ($ospedale_loop['codice'] == $ospedaleSelezionato) {
                                            $selectedText = $ospedale_loop['nome'];
                                            break;
                                        }
                                    }
                                    mysqli_data_seek($resultOspedali, 0); 
                                }
                                echo htmlspecialchars($selectedText);
                                ?>
                            </span>
                        </div>
                        <div class="single-select-dropdown" id="ospedale-dropdown">
                             <input type="text" class="search-ospedale" id="search-ospedale" placeholder="Cerca ospedale...">
                            <div class="ospedale-container">
                                <?php if ($resultOspedali && $resultOspedali->num_rows > 0): ?>
                                    <?php while ($ospedale_item = $resultOspedali->fetch_assoc()): ?>
                                        <div class="ospedale-item <?= ($ospedale_item['codice'] == $ospedaleSelezionato) ? 'selected' : '' ?>"
                                             data-value="<?= htmlspecialchars($ospedale_item['codice']) ?>">
                                            <?= htmlspecialchars($ospedale_item['nome']) ?>
                                        </div>
                                    <?php endwhile; ?>
                                <?php elseif ($resultOspedali === false): ?>
                                    <p class="no-results error-message">Impossibile caricare gli ospedali.</p>
                                <?php else: ?>
                                    <p class="no-results">Nessun ospedale disponibile</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="hidden" id="ospedale" name="ospedale" value="<?= htmlspecialchars($ospedaleSelezionato) ?>" required>
                    </div>
                </div>

                <div class="form-group duration-input-group">
                    <label for="durata">Nuova Durata (giorni):</label>
                    <input type="number" id="durata" name="durata" class="duration-input" value="<?= htmlspecialchars($durataRicovero) ?>" min="1" required>
                </div>

                 <div class="form-group">
                    <label>Patologie associate al Nuovo Ricovero:</label>
                    <div class="multi-select-container">
                        <div class="multi-select-combobox-toggle" id="patologie-toggle">
                            Seleziona una patologia...
                            <span class="selection-count" id="patologie-count" style="display: none;"></span>
                        </div>
                        <div class="multi-select-dropdown" id="patologie-dropdown">
                            <input type="text" class="search-patologie" id="search-patologie" placeholder="Cerca patologia...">
                            <div class="patologie-container">
                                <?php if ($resultPatologie && $resultPatologie->num_rows > 0): ?>
                                    <?php mysqli_data_seek($resultPatologie, 0); ?>
                                    <?php while ($patologia_item = $resultPatologie->fetch_assoc()): ?>
                                        <div class="patologia-item <?= in_array($patologia_item['cod'], $patologieSelezionate) ? 'selected' : '' ?>">
                                            <input type="checkbox" id="patologia_<?= htmlspecialchars($patologia_item['cod']) ?>"
                                                name="patologie[]" value="<?= htmlspecialchars($patologia_item['cod']) ?>"
                                                <?= in_array($patologia_item['cod'], $patologieSelezionate) ? 'checked' : '' ?>>
                                            <label for="patologia_<?= htmlspecialchars($patologia_item['cod']) ?>"><?= htmlspecialchars($patologia_item['nome']) ?></label>
                                        </div>
                                    <?php endwhile; ?>
                                <?php elseif ($resultPatologie === false): ?>
                                    <p class="no-results error-message">Impossibile caricare le patologie.</p>
                                <?php else: ?>
                                    <p class="no-results">Nessuna patologia disponibile</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="selected-patologie-badges" id="selected-patologie-badges"></div>
                </div>

                <div class="buttons-container">
                    <button type="submit" class="btn-salva" id="btnTrasferisci">Trasferisci</button>
                    <a href="ricoveri.php" class="btn-annulla">Annulla</a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include '../MainLayout/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ospedaleToggle = document.getElementById('ospedale-toggle');
    const ospedaleDropdown = document.getElementById('ospedale-dropdown');
    const ospedaleSelectedValue = document.getElementById('ospedale-selected-value');
    const ospedaleHiddenInput = document.getElementById('ospedale');
    const searchOspedale = document.getElementById('search-ospedale');
    const ospedaleItemsContainer = ospedaleDropdown ? ospedaleDropdown.querySelector('.ospedale-container') : null;
    let ospedaleItems = ospedaleItemsContainer ? ospedaleItemsContainer.querySelectorAll('.ospedale-item') : null;


    if (ospedaleToggle && ospedaleDropdown && ospedaleHiddenInput && ospedaleItemsContainer) {
        if (ospedaleItems && ospedaleItems.length > 0) { 
             ospedaleItems.forEach((item, index) => {
                 item.style.setProperty('--item-index', index + 1); 
             });
        }

        ospedaleToggle.addEventListener('click', function() {
            ospedaleToggle.classList.toggle('open');
            ospedaleDropdown.classList.toggle('open');
            if (ospedaleDropdown.classList.contains('open') && searchOspedale) {
               searchOspedale.focus();
            }
        });

        document.addEventListener('click', function(e) {
            if (ospedaleToggle && ospedaleDropdown && !ospedaleToggle.contains(e.target) && !ospedaleDropdown.contains(e.target)) {
                ospedaleToggle.classList.remove('open');
                ospedaleDropdown.classList.remove('open');
            }
        });

        if (searchOspedale) {
            searchOspedale.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                let foundItems = 0;
                ospedaleItems = ospedaleItemsContainer.querySelectorAll('.ospedale-item');
                let noResults = ospedaleItemsContainer.querySelector('.no-results.filter-message');

                if (ospedaleItems) {
                    ospedaleItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            item.style.display = 'flex'; 
                            foundItems++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }


                if (foundItems === 0 && searchTerm) {
                    if (!noResults) {
                        const noResultsElement = document.createElement('p');
                        noResultsElement.className = 'no-results filter-message'; 
                        noResultsElement.textContent = 'Nessun ospedale trovato';
                        ospedaleItemsContainer.appendChild(noResultsElement);
                    } else {
                        noResults.style.display = 'block'; // o 'flex'
                    }
                } else {
                    if (noResults) {
                        noResults.style.display = 'none';
                    }
                }
            });
        }
        
        if (ospedaleItems) {
            ospedaleItems.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    const text = this.textContent.trim(); 

                    if (ospedaleSelectedValue) ospedaleSelectedValue.textContent = text;
                    if (ospedaleHiddenInput) ospedaleHiddenInput.value = value;
                    
                    ospedaleItems.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');

                    if (ospedaleToggle) ospedaleToggle.classList.remove('open');
                    if (ospedaleDropdown) ospedaleDropdown.classList.remove('open');

                });
            });
        }


        const initialOspedaleValue = ospedaleHiddenInput ? ospedaleHiddenInput.value : null;
         if (initialOspedaleValue && ospedaleItems) {
             ospedaleItems.forEach(item => {
                if (item.getAttribute('data-value') === initialOspedaleValue) {
                     if(ospedaleSelectedValue) ospedaleSelectedValue.textContent = item.textContent.trim();
                     item.classList.add('selected');
                }
             });
         }
    } 

    const patologieToggle = document.getElementById('patologie-toggle');
    const patologieDropdown = document.getElementById('patologie-dropdown');
    const searchPatologie = document.getElementById('search-patologie');
    const patologieItemsContainer = patologieDropdown ? patologieDropdown.querySelector('.patologie-container') : null;
    let patologieItems = patologieItemsContainer ? patologieItemsContainer.querySelectorAll('.patologia-item') : null;
    const selectedBadgesContainer = document.getElementById('selected-patologie-badges');
    const patologieCountSpan = document.getElementById('patologie-count'); 
    const patologieToggleTextNode = patologieToggle ? patologieToggle.childNodes[0] : null; 


    if (patologieToggle && patologieDropdown && searchPatologie && patologieItemsContainer && selectedBadgesContainer && patologieCountSpan && patologieToggleTextNode) {

        function updatePatologieSelection() {
            const selectedCheckboxes = patologieItemsContainer.querySelectorAll('input[type="checkbox"]:checked');
            const selectedCount = selectedCheckboxes.length;

            if (selectedCount === 0) {
                patologieToggleTextNode.nodeValue = "Seleziona una patologia... "; 
                patologieCountSpan.style.display = "none";
            } else {
                patologieToggleTextNode.nodeValue = "Patologie selezionate "; 
                patologieCountSpan.textContent = selectedCount;
                patologieCountSpan.style.display = "inline";
            }

            selectedBadgesContainer.innerHTML = ''; 
            selectedCheckboxes.forEach(checkbox => {
                const patologiaLabelElement = patologieItemsContainer.querySelector(`label[for="${checkbox.id}"]`);
                const patologiaLabel = patologiaLabelElement ? patologiaLabelElement.textContent.trim() : 'Sconosciuta';
                const patologiaCod = checkbox.value;

                const badge = document.createElement('div');
                badge.className = 'patologia-badge';
                badge.innerHTML = `${patologiaLabel} <span class="remove-badge" data-cod="${patologiaCod}">&times;</span>`;
                selectedBadgesContainer.appendChild(badge);
            });

            document.querySelectorAll('.remove-badge').forEach(removeBtn => {
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation(); 
                    const patologiaCod = this.getAttribute('data-cod');
                    const checkbox = patologieItemsContainer.querySelector(`input[value="${patologiaCod}"]`);
                    if (checkbox) {
                        checkbox.checked = false;
                        const patologiaItem = checkbox.closest('.patologia-item');
                        if(patologiaItem) patologiaItem.classList.remove('selected');
                       
                        updatePatologieSelection();
                    }
                });
            });
        }

        patologieToggle.addEventListener('click', function() {
            patologieDropdown.classList.toggle('open');
            patologieToggle.classList.toggle('open');
            if (patologieDropdown.classList.contains('open')) {
                searchPatologie.focus();
            }
        });

        document.addEventListener('click', function(event) {
             if (!patologieToggle.contains(event.target) && 
                 !patologieDropdown.contains(event.target) &&
                 !selectedBadgesContainer.contains(event.target)) { 
                patologieDropdown.classList.remove('open');
                patologieToggle.classList.remove('open');
            }
        });

        searchPatologie.addEventListener('input', function() {
            const searchText = this.value.toLowerCase().trim();
            let hasResults = false;
            patologieItems = patologieItemsContainer.querySelectorAll('.patologia-item'); 
            let noResults = patologieItemsContainer.querySelector('.no-results.filter-message');


            if (patologieItems) {
                patologieItems.forEach(item => {
                    const patologiaNameElement = item.querySelector('label');
                    const patologiaName = patologiaNameElement ? patologiaNameElement.textContent.toLowerCase() : '';
                    if (patologiaName.includes(searchText)) {
                        item.style.display = 'flex'; 
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
            
            if (!hasResults && searchText) {
                 if (!noResults) {
                    const noResultsElement = document.createElement('p');
                    noResultsElement.className = 'no-results filter-message';
                    noResultsElement.textContent = 'Nessun risultato trovato';
                    patologieItemsContainer.appendChild(noResultsElement);
                } else {
                    noResults.style.display = 'block'; 
                }
            } else {
                if (noResults) {
                    noResults.style.display = 'none';
                }
            }
        });

        if (patologieItems) {
            patologieItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;

                    if (e.target !== checkbox && e.target !== checkbox.labels[0]) {
                        checkbox.checked = !checkbox.checked;
                    }
                    
                    this.classList.toggle('selected', checkbox.checked);
                   
                    updatePatologieSelection(); 
                });
            });
        }
        updatePatologieSelection(); 
    } 

    const btnTrasferisci = document.getElementById('btnTrasferisci');
    const form = btnTrasferisci ? btnTrasferisci.closest('form') : null; 

    if (form && btnTrasferisci) {
        btnTrasferisci.addEventListener('click', function(event) {
            event.preventDefault(); 

            const nuovoOspedaleSelezionato = document.getElementById('ospedale').value;
            const codOspedaleOriginaleJS = "<?= htmlspecialchars($codOspedaleVecchio, ENT_QUOTES, 'UTF-8') ?>";

            let formValido = true;
            let messaggioErroreValidazione = "";

            if (!document.getElementById('ospedale').value) {
                formValido = false;
                messaggioErroreValidazione += "Seleziona un nuovo ospedale.<br>";
            }
            if (!document.getElementById('durata').value || parseInt(document.getElementById('durata').value) < 1) {
                formValido = false;
                messaggioErroreValidazione += "La durata deve essere un numero maggiore di zero.<br>";
            }
            if (!document.getElementById('motivo').value.trim()) {
                formValido = false;
                messaggioErroreValidazione += "Il motivo è obbligatorio.<br>";
            }
            const costoVal = document.getElementById('costo').value;
            if (costoVal === '' || isNaN(parseFloat(costoVal)) || parseFloat(costoVal) < 0) {
                 formValido = false;
                 messaggioErroreValidazione += "Il costo deve essere un valore numerico non negativo.<br>";
            }


            if (nuovoOspedaleSelezionato === codOspedaleOriginaleJS) {
                 formValido = false;
                 messaggioErroreValidazione += 'Non puoi trasferire il ricovero allo stesso ospedale di origine. Seleziona un ospedale diverso.<br>';
            }
            
            if (!formValido) {
    Swal.fire({
        icon: 'error',
        title: 'Errore',
        html: messaggioErroreValidazione,
        confirmButtonColor: '#002080'
    });
    return;
}
            

            Swal.fire({
    title: 'Sei sicuro?',
    text: "Confermi il trasferimento del ricovero? Verrà creato un nuovo record attivo e il precedente verrà marcato come trasferito.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#002080',
    cancelButtonColor: '#800020',
    confirmButtonText: 'Sì, Trasferisci!',
    cancelButtonText: 'Annulla'
}).then((result) => {
    if (result.isConfirmed) {
        form.submit(); 
    }
});
        });
    } 

    const btnAnnulla = document.querySelector('.btn-annulla');
    if (btnAnnulla) {
        btnAnnulla.addEventListener('click', function() {
           
        });
    }
});
</script>

</body>
</html>

<?php
if (isset($resultOspedali) && $resultOspedali instanceof mysqli_result) $resultOspedali->free();
if (isset($resultPatologie) && $resultPatologie instanceof mysqli_result) $resultPatologie->free();
if (isset($conn) && $conn instanceof mysqli) $conn->close();
?>