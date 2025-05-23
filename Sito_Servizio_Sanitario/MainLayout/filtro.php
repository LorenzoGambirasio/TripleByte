<?php
if (!isset($page)) {
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    $page = pathinfo($scriptName, PATHINFO_FILENAME);
}

$filtri_attuali = $_GET ?? [];

$ospedali = [];
if (isset($conn)) {
    $ospedaleQuery = "SELECT codice, nome FROM Ospedale ORDER BY nome";
    $ospedaleResult = $conn->query($ospedaleQuery);
    if ($ospedaleResult) {
        while ($ospedaleRow = $ospedaleResult->fetch_assoc()) {
            $ospedali[] = $ospedaleRow;
        }
    }
}

$patologie = [];
if (isset($conn)) {
    $ospedaleQuery_pat = "SELECT codice, nome FROM Ospedale ORDER BY nome"; 
    $ospedaleResult_pat = $conn->query($ospedaleQuery_pat);
    if ($ospedaleResult_pat) {
            while ($ospedaleRow_pat = $ospedaleResult_pat->fetch_assoc()) { 
                $found = false;
                foreach ($ospedali as $existing_ospedale) {
                    if ($existing_ospedale['codice'] === $ospedaleRow_pat['codice']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $ospedali[] = $ospedaleRow_pat;
                }
            }
        
        $ospedaleResult_pat->free();
    }
    
    $patologiaQuery = "SELECT cod, nome FROM Patologia ORDER BY nome";
    $patologiaResult = $conn->query($patologiaQuery);
    if ($patologiaResult) {
        while ($patologiaRow = $patologiaResult->fetch_assoc()) {
            $patologie[] = $patologiaRow;
        }
        $patologiaResult->free();
    }
}

if (!defined('STATO_ATTIVO')) define('STATO_ATTIVO', 0);
if (!defined('STATO_TRASFERITO')) define('STATO_TRASFERITO', 1);
if (!defined('STATO_DIMESSO')) define('STATO_DIMESSO', 2);
if (!defined('STATO_DECEDUTO')) define('STATO_DECEDUTO', 3);

$statiRicoveroPerFiltro = [
    STATO_ATTIVO => ['testo' => 'Attivo', 'classe_css' => 'status-attivo'],
    STATO_TRASFERITO => ['testo' => 'Trasferito', 'classe_css' => 'status-trasferito'],
    STATO_DIMESSO => ['testo' => 'Dimesso', 'classe_css' => 'status-dimesso'],
    STATO_DECEDUTO => ['testo' => 'Deceduto', 'classe_css' => 'status-deceduto'],
];

$filtro_stato_attuale_per_select = isset($filtri_attuali['filtro_stato']) && $filtri_attuali['filtro_stato'] !== '' ? (int)$filtri_attuali['filtro_stato'] : null;

?>

<link rel="stylesheet" href="../CSS/filtro.css?v=<?= time(); ?>">

<div class="filtro-contenuto">
    <h3>Filtri</h3>
    <form action="" method="get" class="filtro-form">
        <?php switch ($page):
            case 'cittadini': ?>
                <div class="filtro-gruppo">
                    <label for="filtro_nome">Nome:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_nome']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_nome" name="filtro_nome" value="<?= htmlspecialchars($filtri_attuali['filtro_nome'] ?? '') ?>" placeholder="Es: Mario..." >
                        <button type="button" class="clear-button" aria-label="Cancella campo">
  							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <line x1="18" y1="6" x2="6" y2="18"/>
                              <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
						 </button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_cognome">Cognome:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_cognome']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_cognome" name="filtro_cognome" value="<?= htmlspecialchars($filtri_attuali['filtro_cognome'] ?? '') ?>"placeholder= "Es: Rossi...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                           <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <line x1="18" y1="6" x2="6" y2="18"/>
                              <line x1="6" y1="6" x2="18" y2="18"/>
                           </svg>
                        </button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_luogo">Luogo Nascita:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_luogo']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_luogo" name="filtro_luogo" value="<?= htmlspecialchars($filtri_attuali['filtro_luogo'] ?? '') ?>" placeholder="Es: Milano...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
  						   <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    						  <line x1="18" y1="6" x2="6" y2="18"/>
    						  <line x1="6" y1="6" x2="18" y2="18"/>
  						   </svg>
						</button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_indirizzo">Indirizzo:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_indirizzo']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_indirizzo" name="filtro_indirizzo" value="<?= htmlspecialchars($filtri_attuali['filtro_indirizzo'] ?? '') ?>" placeholder="Es: Via Roma 1, Milano">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
  							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    						    <line x1="18" y1="6" x2="6" y2="18"/>
    							<line x1="6" y1="6" x2="18" y2="18"/>
  							</svg>
						</button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_cssn">CSSN:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_cssn']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_cssn" name="filtro_cssn" value="<?= htmlspecialchars($filtri_attuali['filtro_cssn'] ?? '' )  ?>"  placeholder="Es: ABC...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
  							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    							<line x1="18" y1="6" x2="6" y2="18"/>
    							<line x1="6" y1="6" x2="18" y2="18"/>
  							</svg>
						</button>
                    </div>
                </div>
            <?php break; ?>

            <?php case 'ospedali': ?>
                <div class="filtro-gruppo">
                    <label for="filtro_nome">Nome Ospedale:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_nome']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_nome" name="filtro_nome" value="<?= htmlspecialchars($filtri_attuali['filtro_nome'] ?? '') ?>" placeholder="Es: Ospedale San Raffaele...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <line x1="18" y1="6" x2="6" y2="18"/>
                              <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
						</button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_citta">Città:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_citta']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_citta" name="filtro_citta" value="<?= htmlspecialchars($filtri_attuali['filtro_citta'] ?? '') ?>" placeholder="Es: Bergamo...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_direttore">Direttore Sanitario:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_direttore']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_direttore" name="filtro_direttore" value="<?= htmlspecialchars($filtri_attuali['filtro_direttore'] ?? '') ?>" placeholder="Es: Anna Lombardi...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>
            <?php break; ?>

            <?php case 'ricoveri': ?>
                <div class="filtro-gruppo">
                    <label for="filtro_paziente_cssn">CSSN Paziente:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_paziente_cssn']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_paziente_cssn" name="filtro_paziente_cssn" value="<?= htmlspecialchars($filtri_attuali['filtro_paziente_cssn'] ?? '') ?>" placeholder="Es: ABC...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>

                <div class="filtro-gruppo">
                    <label for="filtro_nome">Nome:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_nome']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_nome" name="filtro_nome" value="<?= htmlspecialchars($filtri_attuali['filtro_nome'] ?? '') ?>" placeholder="Es: Mario...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_cognome">Cognome:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_cognome']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_cognome" name="filtro_cognome" value="<?= htmlspecialchars($filtri_attuali['filtro_cognome'] ?? '') ?>"placeholder= "Es: Rossi...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>

                 <div class="filtro-gruppo">
                    <label for="filtro_ospedale_cod">Ospedale:</label>
                    <select id="filtro_ospedale_cod" name="filtro_ospedale_cod">
                        <option value="" <?= (!isset($filtri_attuali['filtro_ospedale_cod']) || $filtri_attuali['filtro_ospedale_cod'] === '') ? 'selected' : '' ?>>Tutti gli ospedali</option>
                        <?php foreach ($ospedali as $ospedale): ?>
                            <option value="<?= htmlspecialchars($ospedale['codice']) ?>" <?= (isset($filtri_attuali['filtro_ospedale_cod']) && $filtri_attuali['filtro_ospedale_cod'] === $ospedale['codice']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ospedale['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-gruppo">
                    <label for="filtro_data_inizio">Da Data:</label>
                    <input type="date" id="filtro_data_inizio" name="filtro_data_inizio" value="<?= htmlspecialchars($filtri_attuali['filtro_data_inizio'] ?? '') ?>">
                </div>
                 <div class="filtro-gruppo">
                    <label for="filtro_data_fine">A Data:</label>
                    <input type="date" id="filtro_data_fine" name="filtro_data_fine" value="<?= htmlspecialchars($filtri_attuali['filtro_data_fine'] ?? '') ?>">
                </div>
                <div class="filtro-gruppo">
                    <label for="filtro_motivo">Motivo:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_motivo']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_motivo" name="filtro_motivo" value="<?= htmlspecialchars($filtri_attuali['filtro_motivo'] ?? '') ?>" placeholder="Cerca nel motivo...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>

                <div class="filtro-gruppo">
                    <label for="filtro_patologia_cod">Patologia Associata:</label>
                    <select id="filtro_patologia_cod" name="filtro_patologia_cod">
                        <option value="" <?= (!isset($filtri_attuali['filtro_patologia_cod']) || $filtri_attuali['filtro_patologia_cod'] === '') ? 'selected' : '' ?>>Tutte le patologie</option>
                        <?php foreach ($patologie as $patologia): ?>
                            <option value="<?= htmlspecialchars($patologia['cod']) ?>" <?= (isset($filtri_attuali['filtro_patologia_cod']) && $filtri_attuali['filtro_patologia_cod'] === $patologia['cod']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patologia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-gruppo"> 
                    <label for="filtro_stato">Stato:</label>
                    <select name="filtro_stato" id="filtro_stato"> 
                        <option value="">Tutti gli stati</option>
                        <?php foreach ($statiRicoveroPerFiltro as $valoreStato => $dettagliStato): ?>
                            <option value="<?= $valoreStato ?>" <?= ($filtro_stato_attuale_per_select === $valoreStato) ? 'selected' : '' ?>>
                                <span class="status-dot <?= htmlspecialchars($dettagliStato['classe_css']) ?>" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; vertical-align: middle;"></span>
                                <?= htmlspecialchars($dettagliStato['testo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php break; ?>

            <?php case 'patologie': ?>
                <div class="filtro-gruppo">
                    <label for="filtro_nome">Nome:</label>
                    <div class="input-with-clear <?= !empty($filtri_attuali['filtro_nome']) ? 'has-content' : '' ?>">
                        <input type="text" id="filtro_nome" name="filtro_nome" value="<?= isset($filtri_attuali['filtro_nome']) ? htmlspecialchars($filtri_attuali['filtro_nome']) : '' ?>" placeholder="Es: Polmonite...">
                        <button type="button" class="clear-button" aria-label="Cancella campo">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                          </svg>
                        </button>
                    </div>
                </div>
                
                <div class="filtro-gruppo">
                    <label for="filtro_criticita">Livello di Criticità:</label>
                    <select id="filtro_criticita" name="filtro_criticita">
                        <option value="" <?= (!isset($filtri_attuali['filtro_criticita']) || $filtri_attuali['filtro_criticita'] === '') ? 'selected' : '' ?>>Tutti i livelli</option>
                        <?php
                        for ($i = 1; $i <= 10; $i++) {
                            $isSelected = (isset($filtri_attuali['filtro_criticita']) && (string)$filtri_attuali['filtro_criticita'] === (string)$i) ? 'selected' : '';
                            echo "<option value=\"{$i}\" {$isSelected}>{$i}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="filtro-gruppo">
                    <label for="filtro_tipologia">Tipologia:</label>
                    <select id="filtro_tipologia" name="filtro_tipologia">
                        <option value="" <?= (!isset($filtri_attuali['filtro_tipologia']) || $filtri_attuali['filtro_tipologia'] === '') ? 'selected' : '' ?>>Tutte</option>
                        <option value="Cronica" <?= (isset($filtri_attuali['filtro_tipologia']) && $filtri_attuali['filtro_tipologia'] === 'Cronica') ? 'selected' : '' ?>>Cronica</option>
                        <option value="Mortale" <?= (isset($filtri_attuali['filtro_tipologia']) && $filtri_attuali['filtro_tipologia'] === 'Mortale') ? 'selected' : '' ?>>Mortale</option>
                        <option value="Cronica e Mortale" <?= (isset($filtri_attuali['filtro_tipologia']) && $filtri_attuali['filtro_tipologia'] === 'Cronica e Mortale') ? 'selected' : '' ?>>Cronica e Mortale</option>
                        <option value="Nessuna" <?= (isset($filtri_attuali['filtro_tipologia']) && $filtri_attuali['filtro_tipologia'] === 'Nessuna') ? 'selected' : '' ?>>Nessuna</option>
                    </select>
                </div>

            <?php break; ?>

            <?php default: ?>
                <p>Nessun filtro disponibile per questa pagina.</p>
                <?php break; ?>
        <?php endswitch; ?>

        <?php if ($page !== 'default' && $page !== ''):  ?>
            <div class="filtro-azioni">
                <button type="submit" class="btn-filtra">Applica Filtri</button>
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn-reset">Resetta Filtri</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function clearInput(inputId) {
    const input = document.getElementById(inputId);
    if (input) { 
        const container = input.closest('.input-with-clear');
        input.value = ''; 

        if (container) { 
            container.classList.remove('has-content');
        }
        
        const form = input.closest('.filtro-form');
        if (form) {
            form.submit(); 
        } else {
            console.error("Modulo dei filtri non trovato per l'invio automatico.");
            
        }

    } else {
        console.error("Campo input con ID '" + inputId + "' non trovato.");
    }
}


function toggleClearButton(input) {
    if (!input) return;
    const container = input.closest('.input-with-clear');
    if (container) {
        if (input.value.trim() !== '') {
            container.classList.add('has-content');
        } else {
            container.classList.remove('has-content');
        }
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const inputsWithClear = document.querySelectorAll('.input-with-clear input');

    inputsWithClear.forEach(function(inputElement) {
        inputElement.addEventListener('input', function() {
            toggleClearButton(this);
        });

        inputElement.addEventListener('keyup', function() {
            toggleClearButton(this);
        });

        toggleClearButton(inputElement); 

        const clearButton = inputElement.nextElementSibling;
        if (clearButton && clearButton.classList.contains('clear-button')) {
            clearButton.addEventListener('click', function() {
                
                clearInput(inputElement.id);
            });
        }
    });
});
</script>