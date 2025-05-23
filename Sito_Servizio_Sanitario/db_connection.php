<?php
// Parametri di connessione al database Altervista
$host = "localhost"; // Solitamente è "localhost" per Altervista
$username = "serviziosanitario"; // Il tuo nome utente di Altervista
$password = "rnxWpUV7nU7R"; // La tua password di Altervista
$database = "my_serviziosanitario"; // Il nome del tuo database

// Creazione della connessione
$conn = new mysqli($host, $username, $password, $database);

// Verifica della connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Imposta il charset a utf8
$conn->set_charset("utf8");

// Funzione per eseguire query SQL in modo sicuro
function eseguiQuery($connessione, $query, $params = []) {
    $stmt = $connessione->prepare($query);
    
    if (!$stmt) {
        die("Errore nella preparazione della query: " . $connessione->error);
    }
    
    if (!empty($params)) {
        $tipi = "";
        foreach ($params as $param) {
            if (is_int($param)) {
                $tipi .= "i"; // integer
            } elseif (is_double($param)) {
                $tipi .= "d"; // double
            } else {
                $tipi .= "s"; // string
            }
        }
        
        $stmt->bind_param($tipi, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result;
}
?>
