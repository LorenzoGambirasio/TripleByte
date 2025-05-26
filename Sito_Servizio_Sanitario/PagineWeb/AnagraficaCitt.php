<?php
$page = 'cittadini';
include '../db_connection.php';

define('STATO_ATTIVO_RICOVERO', 0);


$filtro_nome_get = $_GET['filtro_nome'] ?? '';
$filtro_nome = trim($filtro_nome_get);

$filtro_cognome_get = $_GET['filtro_cognome'] ?? '';
$filtro_cognome = trim($filtro_cognome_get);

$filtro_luogo_get = $_GET['filtro_luogo'] ?? '';
$filtro_luogo = trim($filtro_luogo_get);

$filtro_indirizzo_get = $_GET['filtro_indirizzo'] ?? '';
$filtro_indirizzo = trim($filtro_indirizzo_get);

$filtro_cssn_get = $_GET['filtro_cssn'] ?? '';
$filtro_cssn = trim($filtro_cssn_get);
$filtro_stato_cittadino = $_GET['filtro_stato_cittadino'] ?? null; 


$filtri_attuali = [
    'filtro_nome' => $filtro_nome,
    'filtro_cognome' => $filtro_cognome,
    'filtro_luogo' => $filtro_luogo,
    'filtro_indirizzo' => $filtro_indirizzo,
    'filtro_cssn' => $filtro_cssn,
    'filtro_stato_cittadino' => $filtro_stato_cittadino,
];


$filters_active = !empty($filtro_nome) ||
                  !empty($filtro_cognome) ||
                  !empty($filtro_luogo) ||
                  !empty($filtro_indirizzo) ||
                  !empty($filtro_cssn) ||
                  !empty($filtro_stato_cittadino);


$whereClauses_main = []; 
$params_main = [];     
$types_main = "";      
$havingClauses_main = [];


$whereClauses_count = [];
$params_count = [];
$types_count = "";


if (!empty($filtro_nome)) {
    $clause = "c.nome LIKE ?";
    $param_val = "%" . $filtro_nome . "%";
    $type_char = "s";
    $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
    $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
}
if (!empty($filtro_cognome)) {
    $clause = "c.cognome LIKE ?";
    $param_val = "%" . $filtro_cognome . "%";
    $type_char = "s";
    $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
    $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
}
if (!empty($filtro_luogo)) {
    $clause = "c.luogoNascita LIKE ?";
    $param_val = "%" . $filtro_luogo . "%";
    $type_char = "s";
    $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
    $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
}
if (!empty($filtro_indirizzo)) {
    $clause = "c.indirizzo LIKE ?";
    $param_val = "%" . $filtro_indirizzo . "%";
    $type_char = "s";
    $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
    $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
}
if (!empty($filtro_cssn)) {
    $clause = "c.CSSN LIKE ?";
    $param_val = "%" . $filtro_cssn . "%";
    $type_char = "s";
    $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
    $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
}


$stato_attivo_ricovero_const = STATO_ATTIVO_RICOVERO;

if (!empty($filtro_stato_cittadino)) {
    if ($filtro_stato_cittadino === 'deceduto') {
        $clause = "c.deceduto = ?";
        $param_val = 1;
        $type_char = "i";
        $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
        $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
    } elseif ($filtro_stato_cittadino === 'ricoverato') {
        $clause = "c.deceduto = ?";
        $param_val = 0;
        $type_char = "i";
        $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
        $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
        $havingClauses_main[] = "is_ricoverato_attivo = 1"; 
    } elseif ($filtro_stato_cittadino === 'attivo') {
        $clause = "c.deceduto = ?";
        $param_val = 0;
        $type_char = "i";
        $whereClauses_main[] = $clause; $params_main[] = $param_val; $types_main .= $type_char;
        $whereClauses_count[] = $clause; $params_count[] = $param_val; $types_count .= $type_char;
        $havingClauses_main[] = "is_ricoverato_attivo = 0"; 
    }
}


$whereSql_main = "";
if (!empty($whereClauses_main)) {
    $whereSql_main = " WHERE " . implode(" AND ", $whereClauses_main);
}
$havingSql_main = "";
if (!empty($havingClauses_main)) {
    $havingSql_main = " HAVING " . implode(" AND ", $havingClauses_main);
}


$whereSql_count = "";
if (!empty($whereClauses_count)) {
    $whereSql_count = " WHERE " . implode(" AND ", $whereClauses_count);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title> Anagrafica Cittadini </title>
    <link rel="stylesheet" href="../CSS/cittadini.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/paginazione.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/base.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/header.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/menu.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/footer.css?v=<?= time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="home">

    <?php include '../MainLayout/header.php'; ?>
    <div class="container">
        <aside class="sidebar">
            <div class="menu">
                <?php include '../MainLayout/menu.php'; ?>
            </div>

            <?php if ($page !== 'home'): ?>
                <div class="filtro">
                    <?php include '../MainLayout/filtro.php'; // $filtri_attuali is used here ?>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content">
            <h1 class="titoloPalette">Anagrafica dei Cittadini</h1>
            <?php
            
            $recordsPerPage = 20;
            $currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            if ($currentPage < 1) $currentPage = 1;
            $startFrom = ($currentPage - 1) * $recordsPerPage;

            
            $countQueryFinal = "";
            $finalParams_count = [];
            $finalTypes_count = "";

            if ($filtro_stato_cittadino === 'ricoverato' || $filtro_stato_cittadino === 'attivo') {
                $status_check_value = ($filtro_stato_cittadino === 'ricoverato' ? 1 : 0);
               
                $countQueryFinal = "
                    SELECT COUNT(*) AS total FROM (
                        SELECT c.CSSN
                        FROM Cittadino c
                        LEFT JOIN Ricovero r ON c.CSSN = r.paziente AND r.stato = ?
                        {$whereSql_count}
                        GROUP BY c.CSSN
                        HAVING MAX(CASE WHEN r.paziente IS NOT NULL THEN 1 ELSE 0 END) = {$status_check_value}
                    ) AS subquery_count";
                $finalTypes_count = "i" . $types_count; 
                $finalParams_count = array_merge([$stato_attivo_ricovero_const], $params_count);
            } else {
                
                $countQueryFinal = "SELECT COUNT(*) as total FROM Cittadino c" . $whereSql_count;
                $finalTypes_count = $types_count;
                $finalParams_count = $params_count;
            }
            
            $stmtCount = $conn->prepare($countQueryFinal);
            if ($stmtCount === false) {
                die("Errore preparazione query conteggio: " . $conn->error . " Query: " . $countQueryFinal);
            }
            if (!empty($finalTypes_count)) {
                $stmtCount->bind_param($finalTypes_count, ...$finalParams_count);
            }
            $stmtCount->execute();
            $countResult = $stmtCount->get_result();
            $totalRecords = 0;
            if ($countResult) {
                $totalRecordsRow = $countResult->fetch_assoc();
                if ($totalRecordsRow) {
                    $totalRecords = $totalRecordsRow['total'];
                }
            }
            $totalPages = ceil($totalRecords / $recordsPerPage);
            if (isset($stmtCount)) $stmtCount->close();


           
            $orderBySql = " ORDER BY c.cognome ASC, c.nome ASC";
            $query = "
                SELECT
                    c.CSSN, c.nome, c.cognome, c.dataNascita, c.luogoNascita, c.indirizzo, c.deceduto,
                    MAX(CASE WHEN r.paziente IS NOT NULL THEN 1 ELSE 0 END) AS is_ricoverato_attivo
                FROM Cittadino c
                LEFT JOIN Ricovero r ON c.CSSN = r.paziente AND r.stato = ? 
                " . $whereSql_main . "
                GROUP BY c.CSSN, c.nome, c.cognome, c.dataNascita, c.luogoNascita, c.indirizzo, c.deceduto
                " . $havingSql_main . "
                " . $orderBySql . "
                LIMIT ?, ?";
            
            $finalTypes_main = "i" . $types_main . "ii"; 
            $finalParams_main = array_merge([$stato_attivo_ricovero_const], $params_main, [$startFrom, $recordsPerPage]);

            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                die("Errore preparazione query principale: " . $conn->error);
            }
            if (!empty($finalTypes_main)) { 
                $stmt->bind_param($finalTypes_main, ...$finalParams_main);
            }
            $stmt->execute();
            $result = $stmt->get_result();
             
            
            if ($result && $result->num_rows > 0): ?>
                <div class="tabella-wrapper">
                    <table class="tabella-cittadini">
                       <thead>
                           <tr>
                               <th>CSSN</th>
                               <th>Nome</th>
                               <th>Cognome</th>
                               <th>Data di Nascita</th>
                               <th>Luogo di Nascita</th>
                               <th>Indirizzo</th>
                               <th>Stato</th>
                           </tr>
                       </thead>
                       <tbody>
                         <?php while ($row = $result->fetch_assoc()):
                         $urlRicoveri = 'ricoveri.php?filtro_paziente_cssn=' . urlencode($row['CSSN']);
                         
                         $testoDefault = 'Domicilio'; 
                         $statoCittadinoText = $testoDefault;
                         $classeCssStato = 'stato-cittadino-domicilio'; 

                         if (isset($row['deceduto']) && $row['deceduto'] == 1) {
                           $statoCittadinoText = 'Deceduto';
                           $classeCssStato = 'stato-cittadino-deceduto';
                         } elseif (isset($row['is_ricoverato_attivo']) && $row['is_ricoverato_attivo'] == 1) {
                           $statoCittadinoText = 'Ricoverato';
                           $classeCssStato = 'stato-cittadino-ricoverato';
                         }
                         ?>
                         <tr onclick="window.location.href='<?= $urlRicoveri ?>'" style="cursor: pointer;">
                           <td><?= htmlspecialchars($row['CSSN']) ?></td>
                           <td><?= htmlspecialchars($row['nome']) ?></td>
                           <td><?= htmlspecialchars($row['cognome']) ?></td>
                           <td><?= htmlspecialchars(date("d/m/Y", strtotime($row['dataNascita']))) ?></td> 
                           <td><?= htmlspecialchars($row['luogoNascita']) ?></td>
                           <td><?= htmlspecialchars($row['indirizzo']) ?></td>
                           <td>
                             <span class="<?php echo htmlspecialchars($classeCssStato); ?>">
                               <?php echo htmlspecialchars($statoCittadinoText); ?>
                             </span>
                           </td>

                         </tr>
                         <?php endwhile; ?>
                      </tbody>
                   </table>
               </div>

                <div class="paginazione-footer">
                    <div class="total-records-display">
                        <?php if ($totalRecords > 0): ?>
                            Totale: <strong><?= htmlspecialchars($totalRecords) ?></strong> <?= ($totalRecords == 1) ? 'cittadino' : 'cittadini' ?>
                        <?php endif; ?>
                    </div>
                    <div class="paginazione">
                        <?php
                        
                        $queryParams_pagination = $_GET; 
                        unset($queryParams_pagination['p']);
                        $queryStringBase = http_build_query($queryParams_pagination);
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
    <?php if ($filters_active && $totalRecords == 0): ?>
        <div class="no-results-container" style="text-align: center; margin-top: 40px; padding: 40px 20px;">
            <div class="search-icon" style="margin-bottom: 20px;">
                <i class="fas fa-search" style="font-size: 4rem; color: #002080;"></i>
            </div>
            <h2 style="color: #333; margin-bottom: 15px; font-size: 1.8rem;">Nessun cittadino trovato</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 1.1rem;">Non sono stati trovati cittadini con i filtri specificati.</p>
            <div class="total-records-display" style="margin-bottom: 20px;">
                <p>Totale: <b>0</b> cittadini</p>
            </div>
           
        </div>
    <?php elseif (!$filters_active && $totalRecords == 0): ?>
        <div class="no-results-container" style="text-align: center; margin-top: 40px; padding: 40px 20px;">
            <div class="search-icon" style="margin-bottom: 20px;">
                <i class="fas fa-users" style="font-size: 4rem; color: #002080; opacity: 0.3;"></i>
            </div>
            <h2 style="color: #333; margin-bottom: 15px; font-size: 1.8rem;">Nessun cittadino presente</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 1.1rem;">Nessun cittadino presente nel database.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>
        </main>
    </div>
    <?php include '../MainLayout/footer.php'; ?>
</body>
</html>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (<?= ($filters_active && $totalRecords == 0) ? 'true' : 'false' ?>) {
      Swal.fire({
        title: 'Nessun risultato',
        text: 'Non sono stati trovati cittadini con i filtri specificati.',
        icon: 'error', 
        confirmButtonColor: '#002080',
        confirmButtonText: 'Reimposta filtri'
      }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'AnagraficaCitt.php'; 
        }
      });
    }
  });
  
  function resetFilters() {
    window.location.href = 'AnagraficaCitt.php';
}
</script>

<?php
 if (isset($stmt)) $stmt->close();
 $conn->close(); 
?>