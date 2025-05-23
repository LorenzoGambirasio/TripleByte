document.addEventListener('DOMContentLoaded', function() {
        initVerificaPaziente();
});

function initVerificaPaziente() {
    const cssnInput = document.getElementById('paziente_cssn');
    const nomeInput = document.getElementById('paziente_nome');
    
    if (cssnInput && nomeInput) {       
        cssnInput.addEventListener('blur', function() {
            const cssn = this.value.trim();
            
            if (cssn === '') {
                nomeInput.value = '';
                return;
            }
                       
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'verifica_paziente.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.trovato) {
                            nomeInput.value = response.nome;
                        } else {                            
                            nomeInput.value = '';                          
                            alert('Paziente non trovato. Utilizza il form "Aggiungi nuovo paziente" per registrarlo.');                            
                            const toggleBtn = document.getElementById('toggleNuovoPaziente');
                            const nuovoPazienteForm = document.getElementById('nuovoPazienteForm');
                            
                            if (toggleBtn && nuovoPazienteForm && nuovoPazienteForm.classList.contains('hidden')) {
                                nuovoPazienteForm.classList.remove('hidden');
                                toggleBtn.textContent = 'Nascondi form nuovo paziente';
                                
                                document.getElementById('nuovo_cssn').value = cssn;
                            }
                        }
                    } catch (e) {
                        console.error('Errore nel parsing della risposta JSON:', e);
                        alert('Errore durante la verifica del paziente.');
                    }
                } else {
                    console.error('Errore nella richiesta AJAX:', xhr.status);
                    alert('Errore durante la verifica del paziente.');
                }
            };
            
            xhr.onerror = function() {
                console.error('Errore di rete nella richiesta AJAX');
                alert('Errore di connessione durante la verifica del paziente.');
            };
            
            
            xhr.send('cssn=' + encodeURIComponent(cssn));
        });
    }
}