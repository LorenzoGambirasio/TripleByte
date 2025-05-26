# TripleByte – Servizio Sanitario 📊🩺

![Last Commit](https://img.shields.io/github/last-commit/LorenzoGambirasio/TripleByte?style=for-the-badge)
![Language: PHP](https://img.shields.io/badge/php-80.1%25-blueviolet?style=for-the-badge)
![Languages Count](https://img.shields.io/github/languages/count/LorenzoGambirasio/TripleByte?style=for-the-badge)


> 🖥️ **Live demo disponibile senza installazione:**  
> 👉 [https://serviziosanitario.altervista.org](https://serviziosanitario.altervista.org)

---

## 📌 Descrizione

**TripleByte** è un progetto sviluppato nell'ambito di un corso universitario di *Programmazione Web*.  
Il sito web ha come tema un **servizio sanitario** pubblico e ha l'obiettivo di fornire agli utenti una piattaforma semplice, responsive e ricca di funzionalità informative.

---

## 🛠️ Tecnologie Utilizzate

Il progetto è stato costruito con gli strumenti e le tecnologie seguenti:

![Markdown](https://img.shields.io/badge/Markdown-000?logo=markdown&logoColor=white&style=for-the-badge)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black&style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-777BB4?logo=php&logoColor=white&style=for-the-badge)

---

## 🧾 Contesto e Obiettivi del Progetto

Questo progetto nasce nell'ambito della modellazione e implementazione di una **base di dati regionale** destinata alla **gestione informatizzata dei ricoveri ospedalieri**.

La Regione ha già a disposizione informazioni anagrafiche sui cittadini, identificati tramite il **Codice del Servizio Sanitario Nazionale (CSSN)**. Ogni cittadino è caratterizzato da dati personali quali nome, cognome, data e luogo di nascita, e indirizzo di residenza.

Anche gli **ospedali** sono registrati preventivamente: ciascuno identificato da un codice univoco, e descritto attraverso nome, città, indirizzo e **nome del Direttore Sanitario**. Una **persona può essere Direttore Sanitario di un solo ospedale**.

I **ricoveri** rappresentano il fulcro del sistema. Ogni ricovero è associato a:
- un ospedale,
- un cittadino (paziente),
- una data di inizio,
- una durata,
- un motivo,
- un costo,
- un codice univoco (relativamente all’ospedale).

Durante un ricovero, un paziente può essere curato per **una o più patologie**, ciascuna nota alla Regione. Ogni **patologia** è identificata da un codice, ed è associata a un nome e a un livello di **criticità** (es. da 1 a 10).  
Il sistema distingue due **sottoinsiemi** di patologie:
- **Patologie croniche**
- **Patologie mortali**  
Questi sottoinsiemi **non sono disgiunti né esaustivi**: una patologia può appartenere ad entrambi o a nessuno dei due.

---

## ✨ Funzionalità Principali

- 🌐 Navigazione responsive e intuitiva
- 📧 Gestione dei Ricoveri
- 📄 Informazioni sanitarie ben organizzate

---

## 🚀 Come Visualizzare il Sito

### ✅ Opzione 1 – Accesso Online (Consigliato)
👉 Visita: [https://serviziosanitario.altervista.org](https://serviziosanitario.altervista.org)

### 🧪 Opzione 2 – In locale
1. Clona la repository:

```bash
git clone https://github.com/LorenzoGambirasio/TripleByte.git
```

2. Posiziona i file nella cartella `htdocs` (se usi XAMPP)
3. Avvia Apache e visita: `http://localhost/TripleByte`

---

## 👤 Autori

**Lorenzo Umberto Gambirasio**  
📧 [lorenzo.gambirasio@example.com](mailto:l.gambirasio3@studenti.unibg.it)  
🌐 [GitHub – @LorenzoGambirasio](https://github.com/LorenzoGambirasio)

**Alessandro Biscaro**  
📧 [a.biscaro@studenti.unibg.it](mailto:a.biscaro@studenti.unibg.it)  
🌐 [GitHub – @AlessandroBiscaro](https://github.com/AlessandroBiscaro)

**Marco Valceschini**  
📧 [m.valceschini1@studenti.unibg.it](mailto:m.valceschini1@studenti.unibg.it)  
🌐 [GitHub – @MarcoValceschini](https://github.com/MarcoValceschini)



