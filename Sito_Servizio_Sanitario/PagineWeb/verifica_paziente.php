<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['errore' => 'Metodo non consentito']);
    exit;
}

if (!isset($_POST['cssn']) || empty($_POST['cssn'])) {
    echo json_encode(['errore' => 'CSSN mancante']);
    exit;
}

require_once '../db_connection.php';

$cssn = trim($_POST['cssn']);

$query = $conn->prepare("SELECT nome, cognome FROM Cittadino WHERE CSSN = ?");
$query->bind_param("s", $cssn);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) {
    $paziente = $result->fetch_assoc();
    echo json_encode([
        'trovato' => true,
        'nome' => $paziente['nome'] . ' ' . $paziente['cognome']
    ]);
} else {
    echo json_encode([
        'trovato' => false,
        'messaggio' => 'Paziente non trovato'
    ]);
}

$query->close();
$conn->close();
?>
