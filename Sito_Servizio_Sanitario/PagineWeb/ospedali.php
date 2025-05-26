<?php
$page = 'ospedali';
include '../db_connection.php';


$filtro_codice_get = $_GET['filtro_codice'] ?? '';       
$filtro_codice = trim($filtro_codice_get);              

$filtro_nome_get = $_GET['filtro_nome'] ?? '';           
$filtro_nome = trim($filtro_nome_get);                  

$filtro_citta_get = $_GET['filtro_citta'] ?? '';         
$filtro_citta = trim($filtro_citta_get);                

$filtro_indirizzo_get = $_GET['filtro_indirizzo'] ?? ''; 
$filtro_indirizzo = trim($filtro_indirizzo_get);        

$filtro_direttore_get = $_GET['filtro_direttore'] ?? ''; 
$filtro_direttore = trim($filtro_direttore_get);  

$whereClauses = [];
$params = [];
$types = "";

if (!empty($filtro_codice)) { 
    $whereClauses[] = "codice LIKE ?";
    $params[] = "%" . $filtro_codice . "%";
    $types .= "s";
}
if (!empty($filtro_nome)) { 
    $whereClauses[] = "nome LIKE ?";
    $params[] = "%" . $filtro_nome . "%";
    $types .= "s";
}
if (!empty($filtro_citta)) { 
    $whereClauses[] = "città LIKE ?";
    $params[] = "%" . $filtro_citta . "%";
    $types .= "s";
}
if (!empty($filtro_indirizzo)) {
    $whereClauses[] = "indirizzo LIKE ?";
    $params[] = "%" . $filtro_indirizzo . "%";
    $types .= "s";
}
if (!empty($filtro_direttore)) { 
    $whereClauses[] = "direttoreSanitario LIKE ?";
    $params[] = "%" . $filtro_direttore . "%";
    $types .= "s";
}

$orderBy = $_GET['order_by'] ?? 'nome';
$orderDir = $_GET['order_dir'] ?? 'asc';

$validOrderColumns = [
    'nome' => 'nome',
    'città' => 'città',
    'indirizzo' => 'indirizzo',
    'direttore' => 'direttoreSanitario'
];

$orderByColumn = $validOrderColumns[$orderBy] ?? 'nome';
$orderDirection = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';



$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
}




?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Elenco Ospedali</title>
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
        <h1 class="titoloPalette">Elenco degli Ospedali</h1>
        <?php
        $recordsPerPage = 20;
        $currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $startFrom = ($currentPage - 1) * $recordsPerPage;

        
        $countQuery = "SELECT COUNT(*) as total FROM Ospedale" . $whereSql;
        $stmtCount = $conn->prepare($countQuery);
        if (!empty($params)) $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);

        
        $query = "SELECT codice, nome, città, indirizzo, direttoreSanitario FROM Ospedale"
   		. $whereSql
    	. " ORDER BY $orderByColumn $orderDirection, codice ASC LIMIT ?, ?";
		$limitTypes = $types . "ii";
        $limitParams = array_merge($params, [$startFrom, $recordsPerPage]);
        $stmt = $conn->prepare($query);
        if (!empty($limitParams)) $stmt->bind_param($limitTypes, ...$limitParams);
        $stmt->execute();
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
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('nome', $orderBy, $orderDir) ?>'">
                          Nome <?= getSortIcon('nome', $orderBy, $orderDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('città', $orderBy, $orderDir) ?>'">
                          Città <?= getSortIcon('città', $orderBy, $orderDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('indirizzo', $orderBy, $orderDir) ?>'">
                          Indirizzo <?= getSortIcon('indirizzo', $orderBy, $orderDir) ?>
                        </th>
                        <th class="sortable" onclick="window.location.href='<?= getSortUrl('direttore', $orderBy, $orderDir) ?>'">
                          Direttore Sanitario <?= getSortIcon('direttore', $orderBy, $orderDir) ?>
                        </th>
                      </tr>
                     </thead>

                   <tbody>
                     <?php while ($row = $result->fetch_assoc()):
                         $urlRicoveriOspedale = 'ricoveri.php?filtro_ospedale_cod=' . urlencode($row['codice']);
                     ?>
                         <tr onclick="window.location.href='<?= $urlRicoveriOspedale ?>'" style="cursor: pointer;">
                             <!--<td><?//= htmlspecialchars($row['codice']) ?></td>-->
                             <td><?= htmlspecialchars($row['nome']) ?></td>
                             <td><?= htmlspecialchars($row['città']) ?></td>
                             <td><?= htmlspecialchars($row['indirizzo']) ?></td>
                             <td><?= htmlspecialchars($row['direttoreSanitario']) ?></td>
                         </tr>
                     <?php endwhile; ?>
                     </tbody>
                </table>
            </div>

           <div class="paginazione-footer">
                <div class="total-records-display">
                    <?php if ($totalRecords > 0): ?>
                        Totale: <strong><?= htmlspecialchars($totalRecords) ?></strong> <?= ($totalRecords == 1) ? 'ospedale' : 'ospedali' ?>
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
                <div id="no-results"> <div class="empty-state-container" style="text-align: center; padding: 40px 20px;">
                        <i class="fa-solid fa-search fa-3x" style="color: #002080; margin-bottom: 20px;"></i>
                        <h2>Nessun ospedale trovato</h2>
                        <p>Non sono stati trovati ospedali con i filtri specificati.</p>
                        <div class="total-records-display" style="margin-top:10px;">
                            Totale: <strong>0</strong> ospedali
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p>Nessun ospedale presente.</p>
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

     
      Swal.fire({
      title: 'Nessun risultato',
      text: 'Non sono stati trovati ospedali con i filtri specificati.',
      icon: 'error',
      confirmButtonColor: '#002080',
      confirmButtonText: 'Reimposta filtri'
    }).then((result) => {
      if (result.isConfirmed) { 
         window.location.href = 'ospedali.php';
      }
    });
    }
  });
</script>

<?php
if (isset($stmt)) $stmt->close();
if (isset($stmtCount)) $stmtCount->close();
?>
