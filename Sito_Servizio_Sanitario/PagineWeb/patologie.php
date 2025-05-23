<?php
$page = 'patologie';
include '../db_connection.php';


$filtro_nome = $_GET['filtro_nome'] ?? null;
$filtro_criticita = $_GET['filtro_criticita'] ?? null;
$filtro_tipologia = $_GET['filtro_tipologia'] ?? null; 


$orderBy = $_GET['order_by'] ?? 'cod'; 
$orderDir = $_GET['order_dir'] ?? 'asc'; 

$whereClauses = [];
$params = [];
$types = "";

if (!empty($filtro_nome)) {
    $whereClauses[] = "p.nome LIKE ?";
    $params[] = "%" . $filtro_nome . "%";
    $types .= "s";
}
if (!empty($filtro_criticita)) {
    $whereClauses[] = "p.criticità = ?";
    $params[] = $filtro_criticita;
    $types .= "s";
}
if (!empty($filtro_tipologia)) {
    switch ($filtro_tipologia) {
        case 'Cronica':
            $whereClauses[] = "pc.codPatologia IS NOT NULL AND pm.codPatologia IS NULL";
            break;
        case 'Mortale':
            $whereClauses[] = "pc.codPatologia IS NULL AND pm.codPatologia IS NOT NULL";
            break;
        case 'Cronica e Mortale':
            $whereClauses[] = "pc.codPatologia IS NOT NULL AND pm.codPatologia IS NOT NULL";
            break;
        case 'Nessuna':
            $whereClauses[] = "pc.codPatologia IS NULL AND pm.codPatologia IS NULL";
            break;
    }
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
    <title>Elenco Patologie</title>
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
        <div class="filtro">
            <?php include '../MainLayout/filtro.php'; ?>
        </div>
    </aside>

    <main class="content">
        <h1 class="titoloPalette">Elenco delle Patologie</h1>
        <?php
        $recordsPerPage = 20;
        $currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $startFrom = ($currentPage - 1) * $recordsPerPage;

        // Count query
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM Patologia p
            LEFT JOIN PatologiaCronica pc ON p.cod = pc.codPatologia
            LEFT JOIN PatologiaMortale pm ON p.cod = pm.codPatologia
        " . $whereSql;
        
        $stmtCount = $conn->prepare($countQuery);
        if ($stmtCount === false) {
            die("Errore nella preparazione della query di conteggio: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        
        $execCount = $stmtCount->execute();
        if ($execCount === false) {
            die("Errore nell'esecuzione della query di conteggio: " . $stmtCount->error);
        }
        
        $countResult = $stmtCount->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);

        
        $validColumns = [
            'nome' => 'p.nome',
            'criticita' => 'p.criticità',
            'tipologia' => 'tipologia'
        ];
        
        $orderByColumn = $validColumns[$orderBy] ?? 'p.cod';
        $orderDirection = (strtolower($orderDir) === 'desc') ? 'DESC' : 'ASC';
        
        
        $query = "
    SELECT 
        p.cod, 
        p.nome, 
        p.criticità, 
        IF(pc.codPatologia IS NOT NULL, 'Sì', 'No') AS cronica,
        IF(pm.codPatologia IS NOT NULL, 'Sì', 'No') AS mortale,
        CASE
            WHEN pc.codPatologia IS NOT NULL AND pm.codPatologia IS NOT NULL THEN 'Cronica e Mortale'
            WHEN pc.codPatologia IS NOT NULL THEN 'Cronica'
            WHEN pm.codPatologia IS NOT NULL THEN 'Mortale'
            ELSE 'Nessuna'
        END AS tipologia
    FROM Patologia p
    LEFT JOIN PatologiaCronica pc ON p.cod = pc.codPatologia
    LEFT JOIN PatologiaMortale pm ON p.cod = pm.codPatologia
" . $whereSql . " 
ORDER BY " . $orderByColumn . " " . $orderDirection . ", p.cod ASC
LIMIT ?, ?";
        
        $limitTypes = $types . "ii";
        $limitParams = array_merge($params, [$startFrom, $recordsPerPage]);
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            die("Errore nella preparazione della query principale: " . $conn->error);
        }
        
        if (!empty($limitParams)) {
            $stmt->bind_param($limitTypes, ...$limitParams);
        }
        
        $exec = $stmt->execute();
        if ($exec === false) {
            die("Errore nell'esecuzione della query principale: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        
        function getSortUrl($column, $currentOrderBy, $currentOrderDir) {
            $params = $_GET;
            $params['order_by'] = $column;
            
            
            $params['order_dir'] = ($currentOrderBy === $column && $currentOrderDir === 'asc') ? 'desc' : 'asc';
            
            
            if (!isset($params['p'])) {
                $params['p'] = 1;
            }
            
            return '?' . http_build_query($params);
        }
        
        
        function getSortIcon($column, $currentOrderBy, $currentOrderDir) {
            if ($currentOrderBy !== $column) {
                return '<i class="fas fa-sort"></i>';
            }
            
            return ($currentOrderDir === 'asc') 
                ? '<i class="fas fa-sort-up"></i>' 
                : '<i class="fas fa-sort-down"></i>';
        }
        ?>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="tabella-wrapper">
                <table class="tabella-cittadini">
                    <thead>
                    <tr>
                       <!-- <th>Codice</th>-->
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('nome', $orderBy, $orderDir) ?>'">
                            Nome <?= getSortIcon('nome', $orderBy, $orderDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('criticita', $orderBy, $orderDir) ?>'">
                            Criticità <?= getSortIcon('criticita', $orderBy, $orderDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('tipologia', $orderBy, $orderDir) ?>'">
                            Tipologia <?= getSortIcon('tipologia', $orderBy, $orderDir) ?>
                        </th>
                    </tr>
                    </thead>
                   <tbody>
                      <?php  
                      if ($result) {
                          while ($row = $result->fetch_assoc()):
                              
                              $urlRicoveriPatologia = 'ricoveri.php?filtro_patologia_cod=' . urlencode($row['cod']);
                          ?>
                              <tr onclick="window.location.href='<?= $urlRicoveriPatologia ?>'" style="cursor: pointer;">
                                  <!--<td><?//= htmlspecialchars($row['cod']) ?></td>-->
                                  <td><?= htmlspecialchars($row['nome']) ?></td>
                                  <td><?= htmlspecialchars($row['criticità']) ?></td>
                                  <td>
                                      <?php
                                      $isCronica = ($row['cronica'] === 'Sì');
                                      $isMortale = ($row['mortale'] === 'Sì');

                                      if ($isCronica && $isMortale) {
                                          echo 'Cronica e Mortale';
                                      } elseif ($isCronica) {
                                          echo 'Cronica';
                                      } elseif ($isMortale) {
                                          echo 'Mortale';
                                      } else {
                                          echo 'Nessuna';
                                      }
                                      ?>
                                  </td>
                              </tr>
                          <?php endwhile;
                      } else {
                          echo "<tr><td colspan='4'>Errore nel recupero dei dati</td></tr>"; 
                      }
                      ?>
                      </tbody>
                </table>
            </div>

            <div class="paginazione-footer">
                <div class="total-records-display">
                    <?php if ($totalRecords > 0): ?>
                        Totale: <strong><?= htmlspecialchars($totalRecords) ?></strong> <?= ($totalRecords == 1) ? 'patologia' : 'patologie' ?>
                    <?php endif; ?>
                </div>
            
			<div class="paginazione">
                <?php
                $queryParams = $_GET;
                unset($queryParams['p']);
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
                    <h2>Nessuna patologia trovata</h2>
                    <p>Non sono state trovate patologie con i filtri specificati.</p>
                    <div class="total-records-display" style="margin-top:10px;">
                        Totale: <strong>0</strong> patologie
                    </div>
                </div>
            </div>
            <?php else: ?>
                <p>Nessuna patologia presente.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php include '../MainLayout/footer.php'; ?>
</body>
</html>


<script>
    
    document.addEventListener('DOMContentLoaded', function() {
        if (<?= !empty($whereSql) && $totalRecords == 0 ? 'true' : 'false' ?>) {
            
            document.getElementById('no-results').style.display = 'none';
            Swal.fire({
                title: 'Nessun risultato',
                text: 'Non sono state trovate patologie con i filtri specificati.',
                icon: 'error',
                confirmButtonColor: '#002080',
                confirmButtonText: 'Reimposta filtri'
            }).then((result) => {
                
                window.location.href = 'patologie.php';
            });
        }
    });
</script>

<?php
if (isset($stmt)) $stmt->close();
if (isset($stmtCount)) $stmtCount->close();
?>