<?php
include '../db_connection.php';

header('Content-Type: application/json');

try {
    $result = $conn->query("SELECT CSSN FROM Cittadino WHERE CSSN LIKE 'EST%' ORDER BY CSSN DESC LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $last = $result->fetch_assoc()['CSSN'];
        $number = intval(substr($last, 3)) + 1;
    } else {
        $number = 1;
    }
    
    $newCssn = 'EST' . str_pad($number, 13, '0', STR_PAD_LEFT);
    
    echo json_encode(['cssn' => $newCssn]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore nel generare il CSSN estero: ' . $e->getMessage()]);
}
?>