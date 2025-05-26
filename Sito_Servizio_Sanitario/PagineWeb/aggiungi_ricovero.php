<?php
$page = 'aggiungi_ricovero';
include '../db_connection.php';
$success_message = '';
$error_message = '';
$input_values = [];
$patologie_selezionate = [];
$success_action = null;

define('STATO_ATTIVO', 0);
define('MAX_DURATA_RICOVERO', 36500);
define('MAX_COSTO_RICOVERO', 99999999.99);
define('MAX_LUNGHEZZA_MOTIVO', 200);

function generaCodiceRicovero($conn) {
    $query = "SELECT cod FROM Ricovero ORDER BY cod DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ultimoCodice = $row['cod'];
        $numero = intval(substr($ultimoCodice, 1));
        $nuovoNumero = $numero + 1;
        $nuovoCodice = 'R' . str_pad($nuovoNumero, 4, '0', STR_PAD_LEFT);
    } else {
        $nuovoCodice = 'R0001';
    }
    return $nuovoCodice;
}

function verificaPazienteEsistente($conn, $cssn) {
    $query = $conn->prepare("SELECT nome, cognome, dataNascita FROM Cittadino WHERE CSSN = ?");
    if ($query === false) {
         error_log("Errore preparazione query verificaPazienteEsistente: " . $conn->error);
         return [false, '', null];
    }
    $query->bind_param("s", $cssn);
    $query->execute();
    $result = $query->get_result();
    $pazienteTrovato = false;
    $nomeCompleto = '';
    $dataNascita = null;
    if ($result->num_rows > 0) {
        $paziente = $result->fetch_assoc();
        $pazienteTrovato = true;
        $nomeCompleto = $paziente['nome'] . ' ' . $paziente['cognome'];
        $dataNascita = $paziente['dataNascita'];
    }
    $query->close();
    return [$pazienteTrovato, $nomeCompleto, $dataNascita];
}

function verificaRicoveroAttivo($conn, $cssn) {
    $query = $conn->prepare("SELECT cod, codOspedale FROM Ricovero WHERE paziente = ? AND stato = ?");
    if ($query === false) {
        error_log("Errore preparazione query verificaRicoveroAttivo: " . $conn->error);
        return [false, 'Errore interno'];
    }
    $statoAttivo = STATO_ATTIVO;
    $query->bind_param("si", $cssn, $statoAttivo);
    $query->execute();
    $result = $query->get_result();
    $ricoveroAttivo = false;
    $infoRicovero = '';
    if ($result->num_rows > 0) {
        $ricovero = $result->fetch_assoc();
        $ricoveroAttivo = true;
        $queryOspedale = $conn->prepare("SELECT nome FROM Ospedale WHERE codice = ?");
        if ($queryOspedale) {
            $queryOspedale->bind_param("s", $ricovero['codOspedale']);
            $queryOspedale->execute();
            $resultOspedale = $queryOspedale->get_result();
            if ($resultOspedale->num_rows > 0) {
                $ospedale = $resultOspedale->fetch_assoc();
                $infoRicovero = "Ricovero " . $ricovero['cod'] . " presso " . $ospedale['nome'];
            } else {
                $infoRicovero = "Ricovero " . $ricovero['cod'] . " (cod. ospedale: " . $ricovero['codOspedale'] . ")";
            }
            $queryOspedale->close();
        } else {
            $infoRicovero = "Ricovero " . $ricovero['cod'];
        }
    }
    $query->close();
    return [$ricoveroAttivo, $infoRicovero];
}

function aggiungiNuovoPaziente($conn, $cssn, $nome, $cognome, $dataNascita, $luogoNascita, $indirizzo) {
    $stmt = $conn->prepare("INSERT INTO Cittadino (CSSN, nome, cognome, dataNascita, luogoNascita, indirizzo) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        throw new Exception("Errore preparazione query inserimento paziente: " . $conn->error);
    }
    $nomeCapitalized = mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($nome, 1, null, 'UTF-8');
    $cognomeCapitalized = mb_strtoupper(mb_substr($cognome, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($cognome, 1, null, 'UTF-8');
    $stmt->bind_param("ssssss", $cssn, $nomeCapitalized, $cognomeCapitalized, $dataNascita, $luogoNascita, $indirizzo);
    $result = $stmt->execute();
    if ($result === false) {
        if ($conn->errno === 1062) {
             throw new Exception("Errore: Esiste già un paziente con il CSSN fornito (" . htmlspecialchars($cssn) . ").");
        } else {
             throw new Exception("Errore durante l'inserimento del nuovo paziente: " . $stmt->error);
        }
    }
    $stmt->close();
    return true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $azione = $_POST['azione'] ?? null;
    if ($azione === 'aggiungi_paziente') {
        $provenienza = $_POST['provenienza'] ?? 'Italia';
        if ($provenienza === 'Estero') {
            $input_values['nuovo_cssn'] = trim(strtoupper($_POST['nuovo_cssn'] ?? ''));
            if (empty($input_values['nuovo_cssn'])) {
                $result = $conn->query("SELECT CSSN FROM Cittadino WHERE CSSN LIKE 'EST%' ORDER BY CSSN DESC LIMIT 1");
                if ($result && $result->num_rows > 0) {
                    $last = $result->fetch_assoc()['CSSN'];
                    $number = intval(substr($last, 3)) + 1;
                } else {
                    $number = 1;
                }
                $input_values['nuovo_cssn'] = 'EST' . str_pad($number, 13, '0', STR_PAD_LEFT);
            }
        } else {
            $input_values['nuovo_cssn'] = trim(strtoupper($_POST['nuovo_cssn'] ?? ''));
        }
        $input_values['nuovo_nome'] = trim($_POST['nuovo_nome'] ?? '');
        $input_values['nuovo_cognome'] = trim($_POST['nuovo_cognome'] ?? '');
        $input_values['nuova_data_nascita'] = trim($_POST['nuova_data_nascita'] ?? '');
        $input_values['nuovo_luogo_nascita'] = trim($_POST['nuovo_luogo_nascita'] ?? '');
        $input_values['nuovo_indirizzo'] = trim($_POST['nuovo_indirizzo'] ?? '');

        if (empty($input_values['nuovo_cssn'])) {
            $error_message = "Il campo CSSN è obbligatorio per il nuovo paziente.";
        } elseif (strlen($input_values['nuovo_cssn']) !== 16) {
            $error_message = "Errore Server: Il CSSN deve essere esattamente di 16 caratteri.";
        } elseif (!preg_match('/^[A-Z0-9]{16}$/', $input_values['nuovo_cssn'])) {
            $error_message = "Errore Server: Il CSSN '" . htmlspecialchars($input_values['nuovo_cssn']) . "' non è valido. Deve contenere solo lettere maiuscole e numeri, per 16 caratteri.";
        } elseif (empty($input_values['nuovo_nome']) || empty($input_values['nuovo_cognome']) || empty($input_values['nuova_data_nascita']) || empty($input_values['nuovo_luogo_nascita']) || empty($input_values['nuovo_indirizzo'])) {
            $error_message = "I campi Nome, Cognome, Data di Nascita, Luogo di Nascita e Indirizzo sono obbligatori per il nuovo paziente.";
        } elseif (!empty($input_values['nuova_data_nascita'])) {
            try {
                $dataNascitaObj = new DateTime($input_values['nuova_data_nascita']);
                if ($dataNascitaObj->format('Y') < 1900) {
                    $error_message = "L'anno di nascita non può essere precedente al 1900.";
                }
            } catch (Exception $e) {
                $error_message = "Formato data di nascita non valido.";
            }
        }

        if (empty($error_message)) {
            try {
                aggiungiNuovoPaziente($conn, $input_values['nuovo_cssn'], $input_values['nuovo_nome'], $input_values['nuovo_cognome'], $input_values['nuova_data_nascita'], $input_values['nuovo_luogo_nascita'], $input_values['nuovo_indirizzo']);
                $success_message = "Nuovo paziente " . htmlspecialchars($input_values['nuovo_nome']) . " " . htmlspecialchars($input_values['nuovo_cognome']) . " aggiunto con successo! Ora puoi selezionarlo nel form ricovero.";
                $success_action = "paziente_aggiunto";
                $input_values['paziente'] = $input_values['nuovo_cssn'];
              } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }
    } elseif ($azione === 'salva_ricovero') {
        $input_values['paziente'] = trim($_POST['paziente'] ?? '');
        $input_values['paziente_nome'] = trim($_POST['paziente_nome'] ?? '');
        $input_values['cod_ospedale'] = trim($_POST['cod_ospedale'] ?? '');
        $input_values['data_ricovero'] = trim($_POST['data_ricovero'] ?? '');
        $input_values['durata'] = trim($_POST['durata'] ?? '');
        $input_values['motivo'] = trim($_POST['motivo'] ?? '');
        $input_values['costo'] = trim(str_replace(',', '.', $_POST['costo'] ?? ''));
        $patologie_selezionate = $_POST['patologie_selezionate'] ?? [];
        $errors = [];

        if (empty($input_values['paziente'])) { $errors[] = "Il campo Paziente (CSSN) è obbligatorio."; }
        if (empty($input_values['cod_ospedale'])) { $errors[] = "Il campo Ospedale è obbligatorio."; }
        if (empty($input_values['data_ricovero'])) { $errors[] = "Il campo Data Ricovero è obbligatorio."; }
        if (empty($input_values['durata'])) { $errors[] = "Il campo Durata è obbligatorio."; }
        if (empty($input_values['motivo'])) { $errors[] = "Il campo Motivo Ricovero è obbligatorio."; }
        if (empty($input_values['costo'])) { $errors[] = "Il campo Costo è obbligatorio."; }

        if (!empty($input_values['durata'])) {
            if (!is_numeric($input_values['durata']) || $input_values['durata'] <= 0) {
                $errors[] = "La durata deve essere un numero intero positivo maggiore di zero.";
            } elseif (intval($input_values['durata']) > MAX_DURATA_RICOVERO) {
                $errors[] = "La durata del ricovero non può superare " . MAX_DURATA_RICOVERO . " giorni.";
            }
        }
        if (!empty($input_values['costo'])) {
            if (!is_numeric($input_values['costo']) || $input_values['costo'] < 0) {
                 $errors[] = "Il costo deve essere un numero positivo.";
            } elseif (floatval($input_values['costo']) > MAX_COSTO_RICOVERO) {
                 $errors[] = "Il costo del ricovero non può superare " . number_format(MAX_COSTO_RICOVERO, 2, '.', '') . " €.";
            }
        }
        if (!empty($input_values['data_ricovero']) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $input_values['data_ricovero'])) {
             $errors[] = "Il formato della Data Ricovero non è valido (YYYY-MM-DD).";
        }
        if (mb_strlen($input_values['motivo'], 'UTF-8') > MAX_LUNGHEZZA_MOTIVO) {
            $errors[] = "Il motivo del ricovero non può superare " . MAX_LUNGHEZZA_MOTIVO . " caratteri.";
        }


        if (empty($errors)) {
             list($pazienteEsiste, $nomePazienteDB, $dataNascitaPaziente) = verificaPazienteEsistente($conn, $input_values['paziente']);
             if (!$pazienteEsiste) {
                  $errors[] = "Il CSSN del paziente ('" . htmlspecialchars($input_values['paziente']) . "') non esiste nel database.";
             } else {
                  $input_values['paziente_nome'] = $nomePazienteDB;
                  if ($dataNascitaPaziente && !empty($input_values['data_ricovero'])) {
                      try {
                          $dataRicoveroObj = new DateTime($input_values['data_ricovero']);
                          $dataNascitaObj = new DateTime($dataNascitaPaziente);
                          $dataRicoveroObj->setTime(0,0,0);
                          $dataNascitaObj->setTime(0,0,0);
                          if ($dataRicoveroObj < $dataNascitaObj) {
                              $errors[] = "La data di ricovero non può essere precedente alla data di nascita del paziente (" . $dataNascitaObj->format('d/m/Y') . ").";
                          }
                      } catch (Exception $e) {
                          $errors[] = "Formato data non valido per confronto con data di nascita.";
                      }
                  } elseif (!$dataNascitaPaziente && $pazienteEsiste) {
                       $errors[] = "Impossibile recuperare la data di nascita per il paziente selezionato per la validazione.";
                  }
             }
        }

        if (!empty($errors)) {
             $error_message = implode("<br>", $errors);
        } else {
            $checkOspedale = $conn->prepare("SELECT 1 FROM Ospedale WHERE codice = ?");
            if ($checkOspedale === false) {
                 $error_message = "Errore interno nella verifica dell'ospedale.";
                 error_log("Errore prepare checkOspedale: " . $conn->error);
            } else {
                 $checkOspedale->bind_param("s", $input_values['cod_ospedale']);
                 $checkOspedale->execute();
                 $ospedaleResult = $checkOspedale->get_result();
                 if ($ospedaleResult->num_rows === 0) {
                      $error_message = "Il codice ospedale ('" . htmlspecialchars($input_values['cod_ospedale']) . "') specificato non esiste.";
                 } else {
                      list($haRicoveroAttivo, $infoRicoveroAttivo) = verificaRicoveroAttivo($conn, $input_values['paziente']);
                      if ($haRicoveroAttivo) {
                          $error_message = "Impossibile aggiungere il ricovero: il paziente " . htmlspecialchars($input_values['paziente_nome']) . " ha già un ricovero attivo (" . htmlspecialchars($infoRicoveroAttivo) . "). Completare prima il ricovero esistente.";
                      } else {
                          $cod_ricovero_generato = generaCodiceRicovero($conn);
                          $stato_ricovero = STATO_ATTIVO;
                          $conn->begin_transaction();
                          try {
                              $insertRicoveroSql = "INSERT INTO Ricovero (codOspedale, cod, paziente, data, durata, motivo, costo, stato) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                              $stmtRicovero = $conn->prepare($insertRicoveroSql);
                              if ($stmtRicovero === false) throw new Exception("Errore preparazione query Ricovero: " . $conn->error);
                              $stmtRicovero->bind_param("ssssisdi", $input_values['cod_ospedale'], $cod_ricovero_generato, $input_values['paziente'], $input_values['data_ricovero'], $input_values['durata'], $input_values['motivo'], $input_values['costo'], $stato_ricovero);
                              if ($stmtRicovero->execute() === false) throw new Exception("Errore esecuzione query Ricovero: " . $stmtRicovero->error);
                              $stmtRicovero->close();
                              if (!empty($patologie_selezionate)) {
                                  $insertPatologieSql = "INSERT INTO PatologiaRicovero (codOspedale, codRicovero, codPatologia) VALUES (?, ?, ?)";
                                  $stmtPatologie = $conn->prepare($insertPatologieSql);
                                  if ($stmtPatologie === false) throw new Exception("Errore preparazione query PatologiaRicovero: " . $conn->error);
                                  foreach ($patologie_selezionate as $cod_patologia) {
                                      $stmtPatologie->bind_param("sss", $input_values['cod_ospedale'], $cod_ricovero_generato, $cod_patologia);
                                      if ($stmtPatologie->execute() === false) throw new Exception("Errore associazione patologia " . htmlspecialchars($cod_patologia) . ": " . $stmtPatologie->error);
                                  }
                                  $stmtPatologie->close();
                              }
                              $conn->commit();
                              $success_message = "Ricovero aggiunto con successo! Codice ricovero: " . htmlspecialchars($cod_ricovero_generato);
                              $success_action = "ricovero_aggiunto";
                              $input_values = [];
                              $patologie_selezionate = [];
                          } catch (Exception $e) {
                              $conn->rollback();
                              $error_message = "Errore durante l'aggiunta del ricovero: " . $e->getMessage();
                              if (isset($stmtRicovero) && $stmtRicovero !== null && property_exists($stmtRicovero, 'errno') && $stmtRicovero->errno) $stmtRicovero->close();
                              if (isset($stmtPatologie) && $stmtPatologie !== null && property_exists($stmtPatologie, 'errno') && $stmtPatologie->errno) $stmtPatologie->close();
                          }
                      }
                  }
                 $checkOspedale->close();
            }
        }
    }
}

$ospedali = []; $patologie = []; $pazienti = [];
$queryOspedali = "SELECT codice, nome FROM Ospedale ORDER BY nome";
$resultOspedali = $conn->query($queryOspedali);
if ($resultOspedali) { while ($row = $resultOspedali->fetch_assoc()) { $ospedali[] = $row; } $resultOspedali->free(); }
else { $error_message .= " Errore recupero ospedali: " . $conn->error; }

$queryPatologie = "SELECT cod, nome FROM Patologia ORDER BY nome";
$resultPatologie = $conn->query($queryPatologie);
if ($resultPatologie) { while ($row = $resultPatologie->fetch_assoc()) { $patologie[] = $row; } $resultPatologie->free(); }
else { $error_message .= " Errore recupero patologie: " . $conn->error; }

$queryPazienti = "SELECT CSSN, nome, cognome, dataNascita FROM Cittadino WHERE deceduto != 1 OR deceduto IS NULL ORDER BY cognome, nome";
$resultPazienti = $conn->query($queryPazienti);
if ($resultPazienti) { while ($row = $resultPazienti->fetch_assoc()) { $pazienti[] = $row; } }
else { $error_message .= " Errore recupero pazienti: " . $conn->error; }

$nuovo_codice_ricovero = generaCodiceRicovero($conn);
$selectedCssn = $input_values['paziente'] ?? null;
if ($selectedCssn && empty($input_values['paziente_nome'])) {
     foreach ($pazienti as $paz) {
          if ($paz['CSSN'] == $selectedCssn) { $input_values['paziente_nome'] = $paz['nome'] . ' ' . $paz['cognome']; break; }
     }
}
$patologieAttuali = $patologie_selezionate;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Aggiungi Nuovo Ricovero</title>
<link href="../CSS/cittadini.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/paginazione.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/base.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/header.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/menu.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/footer.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/crud.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="../CSS/aggiungi_ricovero.css?v=<?= time(); ?>" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<link rel="preload" href="autocomplete_proxy.php?q=init" as="fetch" crossorigin="anonymous">
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
<h1 class="titoloPalette">Aggiungi Nuovo Ricovero</h1>
<div class="codice-ricovero-box" style="margin-bottom: 1rem; text-align: center; font-style: italic;">
<p>Il ricovero verrà registrato con il codice: <strong><?= htmlspecialchars($nuovo_codice_ricovero) ?></strong></p>
</div>
<div style="text-align: center; margin-bottom: 1.5rem;">
<button class="toggle-nuovo-paziente" id="toggleNuovoPaziente" type="button">Aggiungi nuovo paziente</button>
</div>
<div class="nuovo-paziente-container hidden" id="nuovoPazienteForm">
<div style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
  <h2 style="margin: 0;">Inserisci i dati del nuovo paziente</h2>
  <div class="provenienza-wrapper">
  <span class="provenienza-label">Provenienza:</span>
  <div class="provenienza-options">
    <label><input type="radio" name="provenienza" value="Italia" checked> Italia</label>
    <label><input type="radio" name="provenienza" value="Estero"> Estero</label>
  </div>
</div>
</div>
<form action="aggiungi_ricovero.php" id="formAggiungiPaziente" method="post">
<input name="azione" type="hidden" value="aggiungi_paziente"/>
<div class="form-row">
<div class="form-group">
<label for="nuovo_cssn">Codice CSSN: *</label>
<input type="text" id="nuovo_cssn" name="nuovo_cssn" required minlength="16" maxlength="16" pattern="^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$" oninput="this.value = this.value.toUpperCase();" placeholder="Es: ABCDEF01G23H456I"/>
</div>
<div class="form-group">
<label for="nuovo_nome">Nome: *</label>
<input id="nuovo_nome" name="nuovo_nome" placeholder="Mario" required="" type="text" value="<?= htmlspecialchars($input_values['nuovo_nome'] ?? '') ?>"/>
</div>
<div class="form-group">
<label for="nuovo_cognome">Cognome: *</label>
<input id="nuovo_cognome" name="nuovo_cognome" placeholder="Rossi" required="" type="text" value="<?= htmlspecialchars($input_values['nuovo_cognome'] ?? '') ?>"/>
</div>
</div>
<div class="form-row">
<div class="form-group">
<label for="nuova_data_nascita">Data di nascita: *</label>
<input id="nuova_data_nascita" min="1900-01-01" max="<?= date('Y-m-d') ?>" name="nuova_data_nascita" required="" type="date" value="<?= htmlspecialchars($input_values['nuova_data_nascita'] ?? '') ?>"/>
</div>
<div class="form-group">
<label for="nuovo_luogo_nascita">Luogo di nascita: *</label>
<input id="nuovo_luogo_nascita" name="nuovo_luogo_nascita" placeholder="Milano" type="text" required value="<?= htmlspecialchars($input_values['nuovo_luogo_nascita'] ?? '') ?>"/>
<ul class="api-suggestions-list" id="suggestions_luogo"></ul></div>
</div>
<div class="form-group">
<label for="nuovo_indirizzo">Indirizzo: *</label>
<input id="nuovo_indirizzo" name="nuovo_indirizzo" placeholder="Via, Numero Civico, Città" type="text" required value="<?= htmlspecialchars($input_values['nuovo_indirizzo'] ?? '') ?>"/>
<ul class="api-suggestions-list-indirizzo" id="suggestions_indirizzo"></ul></div>
<div class="buttons-container">
<button class="btn-salva" type="submit">Aggiungi Paziente</button>
<button class="btn-annulla" id="nascondiNuovoPaziente" style="background-color: #6c757d;" type="button">Annulla</button>
</div>
<small style="display: block; text-align: center; margin-top: 1rem;">* Campi obbligatori</small>
</form>
</div>
<form action="aggiungi_ricovero.php" class="form-container" id="formAggiungiRicovero" method="post">
<input name="azione" type="hidden" value="salva_ricovero"/>
<div class="form-group">
<label for="paziente-toggle">Paziente (CSSN): *</label>
<div class="single-select-container">
<div class="single-select-combobox-toggle" id="paziente-toggle" tabindex="0">
<span class="selection-value" id="paziente-selected-value"><?= $selectedCssn ? htmlspecialchars($selectedCssn) : 'Seleziona un paziente...' ?></span>
</div>
<div class="paziente-dropdown" id="paziente-dropdown">
<input class="search-paziente" id="search-paziente" placeholder="Cerca per CSSN, Nome o Cognome..." type="text"/>
<div class="paziente-container">
<?php if (!empty($pazienti)): foreach ($pazienti as $paziente): ?>
<div class="paziente-item <?= ($paziente['CSSN'] == $selectedCssn) ? 'selected' : '' ?>" data-name="<?= htmlspecialchars($paziente['nome'] . ' ' . $paziente['cognome']) ?>" data-value="<?= htmlspecialchars($paziente['CSSN']) ?>" data-birthdate="<?= htmlspecialchars($paziente['dataNascita']) ?>">
<?= htmlspecialchars($paziente['nome'] . ' ' . $paziente['cognome'] . ' (' . $paziente['CSSN'] . ')') ?>
</div>
<?php endforeach; else: ?><p class="no-results">Nessun paziente disponibile</p><?php endif; ?>
</div>
</div>
<input id="paziente" name="paziente" type="hidden" value="<?= htmlspecialchars($selectedCssn ?? '') ?>"/>
</div>
</div>
<div class="form-group">
<label for="paziente_nome">Nome Paziente Selezionato:</label>
<input id="paziente_nome" name="paziente_nome" readonly="" style="background-color: #e9ecef; cursor: default;" type="text" value="<?= htmlspecialchars($input_values['paziente_nome'] ?? '') ?>"/>
<small>Questo campo si compilerà automaticamente selezionando il paziente.</small>
</div>
<div class="form-group">
<label for="ospedale-toggle">Ospedale: *</label>
<div class="single-select-container">
<div class="single-select-combobox-toggle" id="ospedale-toggle" tabindex="0">
<?php $selectedOspedaleText = 'Seleziona Ospedale'; $selectedOspedaleValue = $input_values['cod_ospedale'] ?? null; if ($selectedOspedaleValue && !empty($ospedali)) { foreach ($ospedali as $ospedale) { if ($ospedale['codice'] == $selectedOspedaleValue) { $selectedOspedaleText = htmlspecialchars($ospedale['nome']); break; } } } echo $selectedOspedaleText; ?>
</div>
<div class="single-select-dropdown" id="ospedale-dropdown">
<input class="search-ospedale" id="search-ospedale" placeholder="Cerca ospedale..." type="text"/>
<div class="ospedali-container">
<?php if (!empty($ospedali)): foreach ($ospedali as $ospedale): ?>
<div class="ospedale-item <?= ($ospedale['codice'] == $selectedOspedaleValue) ? 'selected' : '' ?>" data-value="<?= htmlspecialchars($ospedale['codice']) ?>">
<?= htmlspecialchars($ospedale['nome']) ?>
</div>
<?php endforeach; else: ?><p class="no-results">Nessun ospedale disponibile</p><?php endif; ?>
</div>
</div>
<input id="cod_ospedale" name="cod_ospedale" type="hidden" value="<?= htmlspecialchars($selectedOspedaleValue ?? '') ?>"/>
</div>
</div>
<div class="form-group">
<label for="data_ricovero">Data Ricovero: *</label>
<input id="data_ricovero" max="<?= date('Y-m-d') ?>" name="data_ricovero" required="" type="date" value="<?= htmlspecialchars($input_values['data_ricovero'] ?? date('Y-m-d')) ?>"/>
</div>
<div class="form-group">
<label for="durata">Durata (giorni): *</label>
<input id="durata" min="1" name="durata" placeholder="Es: 7" required="" type="number" value="<?= htmlspecialchars($input_values['durata'] ?? '') ?>"/>
</div>
<div class="form-group">
<label for="motivo">Motivo Ricovero: *</label>
<textarea id="motivo" name="motivo" placeholder="Descrivere brevemente il motivo del ricovero..." required="" rows="4" maxlength="200"><?= htmlspecialchars($input_values['motivo'] ?? '') ?></textarea>
</div>
<div class="form-group">
<label for="costo">Costo (€): *</label>
<input id="costo" name="costo" pattern="^\d+(\.\d{1,2})?$" placeholder="Es: 1500.50" required="" title="Inserire un numero positivo, es: 1500 o 1500.50" type="text" value="<?= htmlspecialchars(str_replace('.', ',', $input_values['costo'] ?? '')) ?>"/>
<small>Inserire il costo totale del ricovero.</small>
</div>
<div class="form-group">
<label for="patologie-toggle">Patologie associate:</label>
<div class="multi-select-container">
<div class="multi-select-combobox-toggle" id="patologie-toggle" tabindex="0">
<span id="patologie-placeholder">Seleziona una o più patologie...</span>
<span class="selection-count" id="patologie-count" style="display: none;"></span>
</div>
<div class="multi-select-dropdown" id="patologie-dropdown">
<input class="search-patologie" id="search-patologie" placeholder="Cerca patologia..." type="text"/>
<div class="patologie-container">
<?php if (!empty($patologie)): foreach ($patologie as $patologia): $isChecked = in_array($patologia['cod'], $patologieAttuali); ?>
<div class="patologia-item <?= $isChecked ? 'selected' : '' ?>">
<input type="checkbox" id="patologia_<?= htmlspecialchars($patologia['cod']) ?>" name="patologie_selezionate[]" value="<?= htmlspecialchars($patologia['cod']) ?>" <?= $isChecked ? 'checked' : '' ?>>
<label for="patologia_<?= htmlspecialchars($patologia['cod']) ?>"><?= htmlspecialchars($patologia['nome']) ?></label>
</div>
<?php endforeach; else: ?><p class="no-results">Nessuna patologia disponibile</p><?php endif; ?>
</div>
</div>
</div>
<div class="selected-patologie-badges" id="selected-patologie-badges"></div>
</div>
<div class="buttons-container">
<button class="btn-salva" type="submit">Salva Ricovero</button>
<a class="btn-annulla" href="ricoveri.php">Annulla</a>
</div>
<small style="display: block; text-align: center; margin-top: 1rem;">* Campi obbligatori</small>
</form>
</main>
</div>
<?php include '../MainLayout/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleNuovoPaziente');
    const nascondiBtn = document.getElementById('nascondiNuovoPaziente');
    const nuovoPazienteFormDiv = document.getElementById('nuovoPazienteForm');
    if (toggleBtn && nuovoPazienteFormDiv) {
        toggleBtn.addEventListener('click', function() {
            nuovoPazienteFormDiv.classList.toggle('hidden');
            toggleBtn.textContent = nuovoPazienteFormDiv.classList.contains('hidden')
                ? 'Aggiungi nuovo paziente' : 'Nascondi form nuovo paziente';
        });
    }
    if (nascondiBtn && nuovoPazienteFormDiv && toggleBtn) {
        nascondiBtn.addEventListener('click', function() {
            nuovoPazienteFormDiv.classList.add('hidden');
            toggleBtn.textContent = 'Aggiungi nuovo paziente';
        });
    }
    function capitalizeFirstLetter(element) {
        if (element.value.length > 0) {
            let value = element.value.trimStart();
            if (value.length > 0) {
                element.value = value.charAt(0).toUpperCase() + value.slice(1);
            } else {
                element.value = '';
            }
        }
    }
    const nuovoNomeInput = document.getElementById('nuovo_nome');
    const nuovoCognomeInput = document.getElementById('nuovo_cognome');
    if (nuovoNomeInput) {
        nuovoNomeInput.addEventListener('input', function() { capitalizeFirstLetter(this); });
    }
    if (nuovoCognomeInput) {
        nuovoCognomeInput.addEventListener('input', function() { capitalizeFirstLetter(this); });
    }
    const formAggiungiPaziente = document.getElementById('formAggiungiPaziente');
    const nuovoCssnInput = document.getElementById('nuovo_cssn');
    const nuovoCssnErrorSpan = document.getElementById('nuovo_cssn_error');
    if (nuovoCssnInput) {
        nuovoCssnInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (this.value.length > 16) { this.value = this.value.slice(0, 16); }
            if (nuovoCssnErrorSpan) {
                if (this.value.length > 0 && this.value.length < 16) {
                    nuovoCssnErrorSpan.textContent = 'Il CSSN deve essere di 16 caratteri.';
                    nuovoCssnErrorSpan.style.display = 'block';
                } else if (this.value.length === 16 && !/^[A-Z0-9]{16}$/.test(this.value)) {
                    nuovoCssnErrorSpan.textContent = 'Il CSSN contiene caratteri non validi.';
                    nuovoCssnErrorSpan.style.display = 'block';
                } else {
                    nuovoCssnErrorSpan.style.display = 'none';
                }
            }
        });
    }
    if (formAggiungiPaziente && nuovoCssnInput) {
        formAggiungiPaziente.addEventListener('submit', function(event) {
            let clientSidePazienteErrors = [];
            const cssnValue = nuovoCssnInput.value.trim();
            nuovoCssnInput.value = cssnValue;
            if (cssnValue.length === 0) {
                clientSidePazienteErrors.push('Il campo CSSN è obbligatorio.');
            } else if (cssnValue.length !== 16) {
                clientSidePazienteErrors.push('Il CSSN deve essere esattamente di 16 caratteri.');
            } else if (!/^[A-Z0-9]{16}$/.test(cssnValue)) {
                clientSidePazienteErrors.push('Il CSSN non è valido (solo 16 caratteri alfanumerici maiuscoli).');
            }
            const dataNascitaInput = document.getElementById('nuova_data_nascita');
            const dataNascitaValue = dataNascitaInput.value;
            if (dataNascitaValue) {
                const birthDate = new Date(dataNascitaValue);
                const birthYear = birthDate.getFullYear();
                if (birthYear < 1900) {
                    clientSidePazienteErrors.push("L'anno di nascita non può essere precedente al 1900.");
                }
            } else {
                 clientSidePazienteErrors.push("Il campo Data di Nascita è obbligatorio.");
            }
            const luogoNascitaInput = document.getElementById('nuovo_luogo_nascita');
            const indirizzoInput = document.getElementById('nuovo_indirizzo');
            if (luogoNascitaInput.value.trim() !== "" && luogoNascitaInput.dataset.selectedByUser !== "true") {
                clientSidePazienteErrors.push('Per il Luogo di Nascita, seleziona un valore valido dalla lista dei suggerimenti.');
            }
            if (indirizzoInput.value.trim() !== "" && indirizzoInput.dataset.selectedByUser !== "true") {
                clientSidePazienteErrors.push('Per l\'Indirizzo, seleziona un valore valido dalla lista dei suggerimenti.');
            }
            if (clientSidePazienteErrors.length > 0) {
                event.preventDefault();
                Swal.fire({ icon: 'error', title: 'Errore Validazione Dati Paziente', html: clientSidePazienteErrors.join('<br>'), confirmButtonColor: '#002080' });
                if (nuovoCssnErrorSpan && clientSidePazienteErrors.some(err => err.includes("CSSN"))) {
                     nuovoCssnErrorSpan.textContent = clientSidePazienteErrors.find(err => err.includes("CSSN"));
                     nuovoCssnErrorSpan.style.display = 'block';
                     nuovoCssnInput.focus();
                } else if (clientSidePazienteErrors.some(err => err.includes("nascita"))) {
                    dataNascitaInput.focus();
                }
                return;
            } else {
                if(nuovoCssnErrorSpan) nuovoCssnErrorSpan.style.display = 'none';
            }
        });
    }
    const formAggiungiRicovero = document.getElementById('formAggiungiRicovero');
    if (formAggiungiRicovero) {
        formAggiungiRicovero.addEventListener('submit', function(event) {
            const pazienteInput = document.getElementById('paziente');
            const ospedaleInput = document.getElementById('cod_ospedale');
            const dataRicoveroInput = document.getElementById('data_ricovero');
            const durataInput = document.getElementById('durata');
            const motivoInput = document.getElementById('motivo');
            const costoInput = document.getElementById('costo');
            let clientSideErrors = [];
            const MAX_DURATA_CLIENT = <?= MAX_DURATA_RICOVERO ?>;
            const MAX_COSTO_CLIENT = <?= MAX_COSTO_RICOVERO ?>;
            const MAX_LUNGHEZZA_MOTIVO_CLIENT = <?= MAX_LUNGHEZZA_MOTIVO ?>;

            if (!pazienteInput || pazienteInput.value.trim() === '') {
                clientSideErrors.push("Il campo Paziente (CSSN) è obbligatorio.");
            }
            if (!ospedaleInput || ospedaleInput.value.trim() === '') {
                clientSideErrors.push("Il campo Ospedale è obbligatorio.");
            }
            if (!dataRicoveroInput || dataRicoveroInput.value.trim() === '') {
                clientSideErrors.push("Il campo Data Ricovero è obbligatorio.");
            }
            if (!durataInput || durataInput.value.trim() === '') {
                clientSideErrors.push("Il campo Durata è obbligatorio.");
            } else {
                const durataVal = parseInt(durataInput.value);
                if (isNaN(durataVal) || durataVal <= 0) {
                    clientSideErrors.push("La durata deve essere un numero intero positivo.");
                } else if (durataVal > MAX_DURATA_CLIENT) {
                    clientSideErrors.push(`La durata del ricovero non può superare ${MAX_DURATA_CLIENT} giorni.`);
                }
            }
            if (!motivoInput || motivoInput.value.trim() === '') {
                clientSideErrors.push("Il campo Motivo Ricovero è obbligatorio.");
            } else if (motivoInput.value.trim().length > MAX_LUNGHEZZA_MOTIVO_CLIENT) {
                clientSideErrors.push(`Il motivo del ricovero non può superare ${MAX_LUNGHEZZA_MOTIVO_CLIENT} caratteri.`);
            }
            if (!costoInput || costoInput.value.trim() === '') {
                clientSideErrors.push("Il campo Costo è obbligatorio.");
            } else {
                 const costoVal = parseFloat(costoInput.value);
                 if (isNaN(costoVal) || costoVal < 0) {
                    clientSideErrors.push("Il costo deve essere un numero positivo.");
                 } else if (costoVal > MAX_COSTO_CLIENT) {
                    clientSideErrors.push(`Il costo del ricovero non può superare ${MAX_COSTO_CLIENT.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2})} €.`);
                 }
            }
            const dataRicoveroValue = dataRicoveroInput.value;
            const pazienteBirthDateValue = pazienteInput ? pazienteInput.dataset.birthdate : null;
            if (dataRicoveroValue && pazienteBirthDateValue) {
                const dataRicoveroDate = new Date(dataRicoveroValue);
                const pazienteNascitaDate = new Date(pazienteBirthDateValue);
                dataRicoveroDate.setHours(0,0,0,0);
                pazienteNascitaDate.setHours(0,0,0,0);
                if (dataRicoveroDate < pazienteNascitaDate) {
                    clientSideErrors.push("La data di ricovero non può essere precedente alla data di nascita del paziente.");
                }
            }
            if (clientSideErrors.length > 0) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Campi Obbligatori Mancanti o Errati',
                    html: clientSideErrors.join('<br>'),
                    confirmButtonColor: '#002080'
                });
                return;
            }
            const selectedDateValue = dataRicoveroInput.value;
            if (selectedDateValue) {
                const selectedDate = new Date(selectedDateValue);
                selectedDate.setHours(0, 0, 0, 0);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDate < today) {
                    event.preventDefault();
                    Swal.fire({
                        title: 'Conferma Data Ricovero',
                        text: "La data di ricovero inserita è precedente a quella odierna. Sei sicuro di voler continuare?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#002080',
                        cancelButtonColor: '#800020',
                        confirmButtonText: 'Sì, continua',
                        cancelButtonText: 'No, modifica data'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formAggiungiRicovero.submit();
                        } else {
                            dataRicoveroInput.focus();
                        }
                    });
                    return;
                }
            }
        });
    }
    <?php if (!empty($success_message) && $success_action === 'paziente_aggiunto'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Paziente Aggiunto!',
        text: '<?= addslashes(htmlspecialchars($success_message)) ?>',
        confirmButtonColor: '#002080'
    }).then(() => {
        document.getElementById('nuovo_cssn').value = '';
        document.getElementById('nuovo_nome').value = '';
        document.getElementById('nuovo_cognome').value = '';
        document.getElementById('nuova_data_nascita').value = '';
        document.getElementById('nuovo_luogo_nascita').value = '';
        document.getElementById('nuovo_indirizzo').value = '';
    });
    <?php elseif (!empty($success_message) && $success_action === 'ricovero_aggiunto'): ?>
    Swal.fire({
        icon: 'success',
        title: 'Successo!',
        text: '<?= addslashes(htmlspecialchars($success_message)) ?>',
        timer: 2000,
        showConfirmButton: false,
        willClose: () => {
            window.location.href = 'ricoveri.php';
        }
    });
    <?php elseif (!empty($error_message)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Errore',
        html: '<?= addslashes(nl2br(htmlspecialchars($error_message))) ?>',
        confirmButtonColor: '#002080'
    });
    <?php endif; ?>
   function setupDropdown(toggleId, dropdownId, searchInputId, itemsSelector, isMultiSelect = false, hiddenInputId = null, valueDisplayId = null, badgesContainerId = null, countDisplayId = null) {
        const toggle = document.getElementById(toggleId);
        const dropdown = document.getElementById(dropdownId);
        const searchInput = document.getElementById(searchInputId);
        const itemsContainer = dropdown ? dropdown.querySelector('.patologie-container, .ospedali-container, .paziente-container') : null;
        const hiddenInput = hiddenInputId ? document.getElementById(hiddenInputId) : null;
        const valueDisplay = valueDisplayId ? document.getElementById(valueDisplayId) : null;
        const placeholderSpan = isMultiSelect && toggleId === 'patologie-toggle' ? document.getElementById('patologie-placeholder') : null;
        const badgesContainer = badgesContainerId ? document.getElementById(badgesContainerId) : null;
        const countDisplay = countDisplayId ? document.getElementById(countDisplayId) : null;
        if (!toggle || !dropdown || !searchInput || !itemsContainer) { return; }
        const updateMultiSelectState = () => {
            if (!isMultiSelect || !badgesContainer || !countDisplay || !placeholderSpan) return;
            const selectedItems = dropdown.querySelectorAll(`${itemsSelector} input[type="checkbox"]:checked`);
            badgesContainer.innerHTML = '';
            if (selectedItems.length > 0) {
                placeholderSpan.style.display = 'none';
                countDisplay.textContent = selectedItems.length;
                countDisplay.style.display = 'inline';
                selectedItems.forEach(checkbox => {
                    const label = dropdown.querySelector(`label[for="${checkbox.id}"]`);
                    const badge = document.createElement('div');
                    badge.className = 'patologia-badge';
                    badge.textContent = label ? label.textContent : checkbox.value;
                    const removeBtn = document.createElement('span');
                    removeBtn.className = 'remove-badge';
                    removeBtn.innerHTML = '×';
                    removeBtn.dataset.value = checkbox.value;
                    removeBtn.onclick = (e) => {
                        e.stopPropagation(); checkbox.checked = false;
                        checkbox.closest('.patologia-item')?.classList.remove('selected');
                        updateMultiSelectState();
                    };
                    badge.appendChild(removeBtn); badgesContainer.appendChild(badge);
                });
            } else {
                placeholderSpan.style.display = 'inline';
                countDisplay.style.display = 'none';
            }
        };
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.toggle('open');
            toggle.classList.toggle('open');
            if (isOpen) searchInput.focus();
        });
        toggle.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle.click(); } });
        document.addEventListener('click', (e) => { if (!toggle.contains(e.target) && !dropdown.contains(e.target)) { dropdown.classList.remove('open'); toggle.classList.remove('open'); } });
        searchInput.addEventListener('input', () => {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const allItems = dropdown.querySelectorAll(itemsSelector);
            let visibleCount = 0;
            allItems.forEach(item => {
                const itemText = item.textContent.toLowerCase();
                const dataName = item.dataset.name ? item.dataset.name.toLowerCase() : '';
                const dataValue = item.dataset.value ? item.dataset.value.toLowerCase() : '';
                const matches = itemText.includes(searchTerm) || dataName.includes(searchTerm) || dataValue.includes(searchTerm);
                item.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });
            let noResultsMsg = itemsContainer.querySelector('.no-results');
            if (visibleCount === 0 && !noResultsMsg) {
                noResultsMsg = document.createElement('p'); noResultsMsg.className = 'no-results';
                noResultsMsg.textContent = 'Nessun risultato trovato'; itemsContainer.appendChild(noResultsMsg);
            } else if (visibleCount > 0 && noResultsMsg) { noResultsMsg.remove(); }
        });
        dropdown.addEventListener('click', (e) => {
            const clickedItem = e.target.closest(itemsSelector);
            if (!clickedItem) return;
            if (isMultiSelect) {
                const checkbox = clickedItem.querySelector('input[type="checkbox"]');
                if (checkbox && e.target !== checkbox && e.target.tagName !== 'LABEL') { checkbox.checked = !checkbox.checked; }
                if(checkbox) { clickedItem.classList.toggle('selected', checkbox.checked); updateMultiSelectState(); }
            } else {
                const value = clickedItem.dataset.value;
                let displayValue = clickedItem.textContent.trim();
                const patientName = clickedItem.dataset.name;
                if (hiddenInput) {
                    hiddenInput.value = value;
                    const birthdate = clickedItem.dataset.birthdate;
                    if (birthdate) {
                        hiddenInput.dataset.birthdate = birthdate;
                    } else {
                        delete hiddenInput.dataset.birthdate;
                    }
                }
                if (valueDisplay) { valueDisplay.textContent = (toggleId === 'paziente-toggle') ? value : displayValue; }
                else { toggle.firstChild.nodeValue = displayValue + ' '; }
                if (toggleId === 'paziente-toggle') {
                    const nomePazienteInput = document.getElementById('paziente_nome');
                    if (nomePazienteInput && patientName) { nomePazienteInput.value = patientName; }
                }
                dropdown.querySelectorAll(itemsSelector).forEach(item => item.classList.remove('selected'));
                clickedItem.classList.add('selected'); dropdown.classList.remove('open'); toggle.classList.remove('open');
            }
        });
        if (isMultiSelect) { updateMultiSelectState(); }
    }
    setupDropdown('paziente-toggle', 'paziente-dropdown', 'search-paziente', '.paziente-item', false, 'paziente', 'paziente-selected-value');
    setupDropdown('ospedale-toggle', 'ospedale-dropdown', 'search-ospedale', '.ospedale-item', false, 'cod_ospedale');
    setupDropdown('patologie-toggle', 'patologie-dropdown', 'search-patologie', '.patologia-item', true, null, null, 'selected-patologie-badges', 'patologie-count');
    const costoInput = document.getElementById('costo');
    if (costoInput) {
        costoInput.addEventListener('input', function(e) {
            let originalValue = this.value;
            let newValue = originalValue.replace(/,/g, '.').replace(/[^0-9.]/g, '');
            const parts = newValue.split('.');
            if (parts.length > 2) {
                newValue = parts[0] + '.' + parts.slice(1).join('');
            }
            if (parts[1] && parts[1].length > 2) {
                newValue = parts[0] + '.' + parts[1].substring(0, 2);
            }
            if (originalValue !== newValue) {
                this.value = newValue;
            }
        });
        costoInput.addEventListener('blur', function() {
            if (this.value) {
                let costoVal = parseFloat(this.value);
                if (!isNaN(costoVal)) {
                    if (costoVal < 0) costoVal = 0.00;
                    this.value = costoVal.toFixed(2);
                } else {
                    this.value = '';
                }
            }
        });
    }
});
</script>
<script>
    const input = document.getElementById('nuovo_luogo_nascita');
    const suggestions = document.getElementById('suggestions_luogo');
    let debounceTimeout;
    input.addEventListener('input', () => {
      clearTimeout(debounceTimeout);
      const query = input.value.trim();
      if (input.value !== input.dataset.selectedValueFromApi) {
    input.dataset.selectedByUser = "false";
}
if (query.length < 3) {
    suggestions.innerHTML = '';
    suggestions.classList.remove('visible');
    delete input.dataset.selectedValueFromApi;
    delete input.dataset.selectedByUser;
    return;
}
      debounceTimeout = setTimeout(() => {
        fetch(`autocomplete_proxy.php?q=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            suggestions.innerHTML = '';
            if (data.predictions && data.predictions.length > 0) {
              data.predictions.forEach(pred => {
                const li = document.createElement('li');
                li.textContent = pred.description;
                li.addEventListener('click', () => {
                  input.value = pred.description;
                  suggestions.innerHTML = '';
                  suggestions.classList.remove('visible');
                  input.dataset.selectedValueFromApi = pred.description;
  				  input.dataset.selectedByUser = "true";
                });
                suggestions.appendChild(li);
              });
              suggestions.classList.add('visible');
            } else {
              suggestions.classList.remove('visible');
            }
          })
          .catch(err => {
            console.error('Errore durante la richiesta al proxy:', err);
          });
      }, 100);
    });
    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !suggestions.contains(e.target)) {
        suggestions.classList.remove('visible');
      }
    });
</script>
<script>
    const indirizzoInput = document.getElementById('nuovo_indirizzo');
    const indirizzoSuggestions = document.getElementById('suggestions_indirizzo');
    let debounceIndirizzo;
    indirizzoInput.addEventListener('input', () => {
      clearTimeout(debounceIndirizzo);
      const query = indirizzoInput.value.trim();
      if (indirizzoInput.value !== indirizzoInput.dataset.selectedValueFromApi) {
        indirizzoInput.dataset.selectedByUser = "false";
    }
    if (query.length < 3) {
        indirizzoSuggestions.innerHTML = '';
        indirizzoSuggestions.classList.remove('visible');
        delete indirizzoInput.dataset.selectedValueFromApi;
        delete indirizzoInput.dataset.selectedByUser;
        return;
    }
      debounceIndirizzo = setTimeout(() => {
        fetch(`autocomplete_proxy.php?q=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            indirizzoSuggestions.innerHTML = '';
            if (data.predictions && data.predictions.length > 0) {
              data.predictions.forEach(pred => {
                const li = document.createElement('li');
                li.textContent = pred.description;
                li.addEventListener('click', () => {
                  indirizzoInput.value = pred.description;
                  indirizzoSuggestions.innerHTML = '';
                  indirizzoSuggestions.classList.remove('visible');
                  indirizzoInput.dataset.selectedValueFromApi = pred.description;
                  indirizzoInput.dataset.selectedByUser = "true";
                });
                indirizzoSuggestions.appendChild(li);
              });
              indirizzoSuggestions.classList.add('visible');
            } else {
              indirizzoSuggestions.classList.remove('visible');
            }
          })
          .catch(err => console.error('Errore indirizzo:', err));
      }, 100);
    });
    document.addEventListener('click', (e) => {
      if (!indirizzoInput.contains(e.target) && !indirizzoSuggestions.contains(e.target)) {
        indirizzoSuggestions.classList.remove('visible');
      }
    });
</script>
<script>
function gestisciProvenienza() {
    const cssnField = document.getElementById("cssn");
    const provenienza = document.querySelector('input[name="provenienza"]:checked').value;
    if (provenienza === "Estero") {
        cssnField.disabled = true;
        cssnField.value = "";
    } else {
        cssnField.disabled = false;
    }
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const cssnField = document.getElementById('nuovo_cssn');
    const hiddenCssnField = document.createElement('input');
    hiddenCssnField.type = 'hidden';
    hiddenCssnField.name = 'nuovo_cssn';
    hiddenCssnField.id = 'hidden_nuovo_cssn';
    if (cssnField && cssnField.parentNode) {
        cssnField.parentNode.appendChild(hiddenCssnField);
    }
    const provenienzaRadios = document.querySelectorAll('input[name="provenienza"]');
    async function generaCssnEstero() {
        try {
            const response = await fetch('get_cssn_estero.php');
            if (!response.ok) {
                throw new Error('Errore nella richiesta');
            }
            const data = await response.json();
            return data.cssn;
        } catch (error) {
            console.error('Errore durante la generazione del CSSN estero:', error);
            const timestamp = new Date().getTime().toString().slice(-9);
            return 'EST' + timestamp.padStart(13, '0');
        }
    }
    async function aggiornaStatoCSSN() {
        const provenienzaChecked = document.querySelector('input[name="provenienza"]:checked');
        if (!provenienzaChecked || !cssnField) return;
        const provenienza = provenienzaChecked.value;
        if (provenienza === "Estero") {
            const cssnEstero = await generaCssnEstero();
            cssnField.value = cssnEstero;
            if(hiddenCssnField) hiddenCssnField.value = cssnEstero;
            cssnField.readOnly = true;
            cssnField.style.backgroundColor = "#e9ecef";
            cssnField.placeholder = "CSSN autogenerato";
        } else {
            cssnField.readOnly = false;
            cssnField.style.backgroundColor = "";
            cssnField.placeholder = "Es: ABCDEF01G23H456I";
            if (cssnField.value.startsWith('EST')) {
                cssnField.value = '';
                if(hiddenCssnField) hiddenCssnField.value = '';
            } else {
                 if(hiddenCssnField) hiddenCssnField.value = cssnField.value;
            }
        }
    }
    if (provenienzaRadios.length > 0) {
      aggiornaStatoCSSN();
      provenienzaRadios.forEach(radio => {
          radio.addEventListener('change', aggiornaStatoCSSN);
      });
    }
    if (cssnField) {
      cssnField.addEventListener('input', function() {
          if(hiddenCssnField) hiddenCssnField.value = cssnField.value;
      });
    }
    const formAggiungiPaziente = document.getElementById('formAggiungiPaziente');
    if (formAggiungiPaziente && cssnField) {
        formAggiungiPaziente.addEventListener('submit', function() {
            if (!cssnField.readOnly && hiddenCssnField) {
                hiddenCssnField.value = cssnField.value;
            }
        });
    }
});
</script>
</body>
</html>
