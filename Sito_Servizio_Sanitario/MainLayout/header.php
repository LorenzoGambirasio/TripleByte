<header class="main-header">
    <div class="header-container">
    
   <div class="navigation-buttons">
            
            <a href="javascript:history.back()" class="back-button" title="Torna indietro">
                <img src="../Risorse/back_icon_svg.svg" alt="Indietro" class="back-icon">
            </a>
            
            
            <a href="../PagineWeb/homepage.php" class="home-button" title="Vai alla home">
                <img src="../Risorse/home-icon.svg" alt="Home" class="home-icon">
            </a>
        </div>
    
        <h1><?php
                switch ($page ?? '') {
                    case 'home':
                        echo 'Gestionale Servizio Sanitario';
                        break;
                    case 'ospedali':
                        echo 'Gestionale Servizio Sanitario - Elenco Ospedali';
                        break;
                    case 'ricoveri':
                        echo ' Gestionale Servizio Sanitario - Ricoveri';
                        break;
                    case 'aggiungi_ricovero':
                        echo 'Gestionale Servizio Sanitario - Aggiungi Ricovero';
                        break;
                    case 'modifica_ricovero':
                        echo 'Gestionale Servizio Sanitario - Modifica Ricovero';
                        break;
                    case 'trasferisci_ricovero':
                        echo 'Gestionale Servizio Sanitario - Trasferisci Ricovero';
                        break;
                    case 'cittadini':
                        echo 'Gestionale Servizio Sanitario - Anagrafica Cittadini';
                        break;
                    case 'patologie':
                        echo 'Gestionale Servizio Sanitario - Archivio Patologie';
                        break;
                    default:
                        echo 'Gestionale Sanitario';
                }
            ?></h1>
        
        <img src="../Risorse/UniBG-Logo-Bianco.png" alt="Logo" class="logo">
    </div>
</header>
