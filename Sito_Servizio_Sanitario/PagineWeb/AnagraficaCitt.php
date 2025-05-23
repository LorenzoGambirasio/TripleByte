<?php
$page = 'cittadini';
include '../db_connection.php'; 

$filtro_nome = $_GET['filtro_nome'] ?? null;
$filtro_cognome = $_GET['filtro_cognome'] ?? null;
$filtro_luogo = $_GET['filtro_luogo'] ?? null;
$filtro_indirizzo = $_GET['filtro_indirizzo'] ?? null;
$filtro_cssn = $_GET['filtro_cssn'] ?? null; 


$whereClauses = [];
$params = [];
$types = ""; 


if (!empty($filtro_nome)) {
    $whereClauses[] = "nome LIKE ?";
    $params[] = "%" . $filtro_nome . "%"; 
    $types .= "s";
}
if (!empty($filtro_cognome)) {
    $whereClauses[] = "cognome LIKE ?";
    $params[] = "%" . $filtro_cognome . "%";
    $types .= "s";
}
if (!empty($filtro_luogo)) {
    $whereClauses[] = "luogoNascita LIKE ?";
    $params[] = "%" . $filtro_luogo . "%";
    $types .= "s";
}
if (!empty($filtro_indirizzo)) {
    $whereClauses[] = "indirizzo LIKE ?";
    $params[] = "%" . $filtro_indirizzo . "%";
    $types .= "s";
}
if (!empty($filtro_cssn)) {
    $whereClauses[] = "CSSN LIKE ?"; 
    $params[] = "%" .$filtro_cssn. "%";
    $types .= "s";
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
                    <?php include '../MainLayout/filtro.php';?>
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

            
            $countQuery = "SELECT COUNT(*) as total FROM Cittadino" . $whereSql;
            $stmtCount = $conn->prepare($countQuery);
            if (!empty($params)) {
                $stmtCount->bind_param($types, ...$params);
            }
            $stmtCount->execute();
            $countResult = $stmtCount->get_result();
        
            $totalRecords = $countResult->fetch_assoc()['total'];
            $totalPages = ceil($totalRecords / $recordsPerPage);

            $query = "SELECT CSSN, nome, cognome, dataNascita, luogoNascita, indirizzo FROM Cittadino" . $whereSql . " LIMIT ?, ?";
     
            $limitTypes = $types . "ii"; 
            $limitParams = array_merge($params, [$startFrom, $recordsPerPage]);

            $stmt = $conn->prepare($query);
             if (!empty($limitParams)) {
                $stmt->bind_param($limitTypes, ...$limitParams);
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
                           </tr>
                       </thead>
                       <tbody>
                         <?php while ($row = $result->fetch_assoc()):
                         $urlRicoveri = 'ricoveri.php?filtro_paziente_cssn=' . urlencode($row['CSSN']);
                         ?>
                         <tr onclick="window.location.href='<?= $urlRicoveri ?>'" style="cursor: pointer;">
                           <td><?= htmlspecialchars($row['CSSN']) ?></td>
                           <td><?= htmlspecialchars($row['nome']) ?></td>
                           <td><?= htmlspecialchars($row['cognome']) ?></td>
                           <td><?= htmlspecialchars($row['dataNascita']) ?></td>
                           <td><?= htmlspecialchars($row['luogoNascita']) ?></td>
                           <td><?= htmlspecialchars($row['indirizzo']) ?></td>
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
                
            </div>
            <?php else: ?>
                <p>Nessun cittadino presente.</p>
            <?php endif; ?>
        <?php endif; ?>
        </main>
    </div>
    <?php include '../MainLayout/footer.php'; ?>
</body>
</html>

<script>

  document.addEventListener('DOMContentLoaded', function() {
    if (<?= !empty($whereSql) ? 'true' : 'false' ?>) {
      document.getElementById('no-results').style.display = 'none';
      Swal.fire({
        title: 'Nessun risultato',
        text: 'Non sono stati trovati cittadini con i filtri specificati.',
        icon: 'error',
        confirmButtonColor: '#002080',
        confirmButtonText: 'Reimposta filtri'
      }).then((result) => {
        
        window.location.href = 'AnagraficaCitt.php';
      });
    }
  });
</script>

<?php
 if (isset($stmt)) $stmt->close();
 if (isset($stmtCount)) $stmtCount->close();
?>