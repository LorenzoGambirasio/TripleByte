<?php

$current_page = basename($_SERVER['PHP_SELF']);


function isActive($page) {
    global $current_page;
    return ($current_page === $page) ? 'active' : '';
}
?>

<nav class="menu-vertical">
  <h3 class="menu-title">Menu</h3>
  <a href="../PagineWeb/AnagraficaCitt.php" class="<?php echo isActive('AnagraficaCitt.php'); ?>">Cittadini</a>
  <a href="../PagineWeb/ospedali.php" class="<?php echo isActive('ospedali.php'); ?>">Ospedali</a>
  <a href="../PagineWeb/ricoveri.php" class="<?php echo isActive('ricoveri.php'); ?>">Ricoveri</a>
  <a href="../PagineWeb/patologie.php" class="<?php echo isActive('patologie.php'); ?>">Patologie</a>
</nav>
