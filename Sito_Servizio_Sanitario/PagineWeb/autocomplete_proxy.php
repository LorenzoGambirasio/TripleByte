<?php
header('Content-Type: application/json');

if (!isset($_GET['q']) || strlen($_GET['q']) < 3) {
    echo json_encode(['error' => 'Query troppo corta o mancante']);
    exit;
}

$query = urlencode($_GET['q']);
$key = 'AIzaSyBYlYaeG_atv7epOOO8_7VaXhu_PqOavH4'; 
$url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?input={$query}&key={$key}&types=geocode&language=it";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $http_code !== 200) {
    echo json_encode(['error' => 'Errore nella richiesta a Google']);
    exit;
}

echo $response;
?>
