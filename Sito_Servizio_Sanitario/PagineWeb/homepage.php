<?php
$page = 'home';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Gestionale Servizio Sanitario</title>
    <link rel="stylesheet" href="../CSS/base.css?v=<?= time(); ?>">
	<link rel="stylesheet" href="../CSS/header.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/menu.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/footer.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="../CSS/home.css?v=<?= time(); ?>">
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
                    <?php include '../MainLayout/filtro.php'; ?>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content">
             <div class="homepage-content">
  				<h2 class="homepage-title">
    				Benvenuto nel sistema regionale di gestione dei ricoveri ospedalieri
  				</h2>

  			<hr class="homepage-separator">

              <p class="homepage-intro">
                Questo portale consente la <strong>consultazione</strong> e la <strong>gestione</strong> delle informazioni sanitarie relative ai ricoveri ospedalieri della Regione. È uno strumento a supporto della trasparenza, dell’efficienza e del monitoraggio dei servizi sanitari.
              </p>

              <ul class="homepage-list">
                <li><strong>Cittadini</strong>: identificati tramite il codice del Servizio Sanitario Nazionale</li>
                <li><strong>Ospedali</strong>: registrati con nome, città, indirizzo e Direttore Sanitario</li>
                <li><strong>Ricoveri</strong>: con data di inizio, durata, costo, motivo e paziente coinvolto</li>
                <li><strong>Patologie</strong>: trattate durante i ricoveri, con distinzione tra quelle <em>mortali</em> e <em>croniche</em></li>
              </ul>

              <p class="homepage-footer">
                Per una sanità più integrata, efficace e incentrata sul paziente.
              </p>
            </div>

        </main>
    </div>

    <?php include '../MainLayout/footer.php'; ?>
</body>
</html>
