# MZC Landing Newsletter — Documentazione

## Versione 1.0.0

## Autore: Marcin Zbijowski Consulting

## Compatibilità: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Indice

1. Panoramica
2. Requisiti
3. Installazione
4. Configurazione
5. Preset CSS
6. Riferimento classi CSS
7. Conformità GDPR
8. Google Tag Manager e analisi
9. Configurazione Multi-Negozio
10. Gestione iscritti
11. Configurazione SEO
12. Interazione con la modalità manutenzione
13. Bypass per amministratori
14. Limitazione di frequenza
15. Funzionalità di sicurezza
16. Traduzioni
17. Risoluzione problemi
18. Disinstallazione
19. Supporto

---

## 1. Panoramica

MZC Landing Newsletter aggiunge una modalità Pagina di destinazione al tuo negozio PrestaShop. Quando attivata, tutti i visitatori del front-office vedono una pagina brandizzata con il logo del negozio, un messaggio personalizzato e un modulo di iscrizione alla newsletter. Gli amministratori e gli indirizzi IP in lista bianca bypassano la pagina di destinazione e accedono normalmente al negozio.

Questa funzionalità è indipendente dalla modalità di manutenzione integrata di PrestaShop. Usala quando il tuo negozio non è ancora pronto, durante una migrazione, configurazione del catalogo, rebranding, o in qualsiasi momento desideri raccogliere indirizzi e-mail prima del lancio.

Gli iscritti vengono salvati nella tabella nativa della newsletter di PrestaShop (ps_emailsubscription), quindi appaiono automaticamente nei tuoi strumenti di newsletter esistenti senza necessità di sincronizzazione o esportazione.

---

## 2. Requisiti

- PrestaShop 8.0.0 o successivo (compatibile fino a 9.x)
- PHP 8.1 o successivo
- Modulo ps_emailsubscription installato (fornito di default con PrestaShop)
- Modulo psgdpr installato e configurato (opzionale, per la casella di consenso GDPR)

---

## 3. Installazione

### Da PrestaShop Addons

1. Scarica il file ZIP del modulo dal tuo account Addons
2. Accedi al back-office del tuo PrestaShop
3. Naviga verso Moduli > Gestore moduli
4. Clicca su Carica un modulo
5. Seleziona il file ZIP e attendi il completamento dell'installazione
6. Clicca su Configura per configurare il modulo

### Installazione manuale

1. Estrai il file ZIP
2. Carica la cartella mzc_landing_newsletter nella directory modules del tuo PrestaShop via FTP
3. Accedi a Moduli > Gestore moduli nel back-office
4. Cerca MZC Landing Newsletter
5. Clicca su Installa, poi su Configura

---

## 4. Configurazione

Naviga verso Moduli > Gestore moduli, trova MZC Landing Newsletter e clicca su Configura.

### Attivare la modalità pagina di destinazione

Imposta l'opzione Attiva pagina di destinazione su Sì per attivare la pagina per tutti i visitatori. Imposta su No per disattivare e mostrare il tuo negozio normale.

### Messaggio della pagina di destinazione

Inserisci il messaggio visualizzato sulla pagina di destinazione. Questo campo supporta:

- Modifica di testo formattato (grassetto, corsivo, link, formattazione)
- Contenuto multilingue — usa il selettore di lingua per inserire messaggi diversi per ogni lingua
- Contenuto HTML — per formattazione avanzata

Messaggio predefinito: Stiamo arrivando! Il nostro negozio è in costruzione. Iscriviti alla nostra newsletter per essere avvisato quando lanceremo.

### CSS personalizzato

Inserisci regole CSS personalizzate per cambiare l'aspetto della pagina di destinazione. Lascia vuoto per usare lo stile predefinito. Vedi la Sezione 6 per l'elenco completo delle classi CSS disponibili.

---

## 5. Preset CSS

Tre preset integrati sono disponibili nel pannello Preset CSS nella pagina di configurazione. Clicca su Carica preset per popolare il campo CSS personalizzato con gli stili del preset.

### Modern Dark (Moderno scuro)

Sfondo con gradiente viola con effetto glassmorphism sulla scheda. Pulsante di iscrizione con gradiente viola-blu, campi di inserimento traslucidi e logo invertito per sfondi scuri. Ideale per brand tecnologici, gaming o lifestyle moderno.

### Modern Light (Moderno chiaro)

Sfondo con gradiente caldo da pesca a bianco con una grande scheda arrotondata e ombre profonde. Pulsante di iscrizione con gradiente arancione con tipografia elegante e spaziatura delle lettere. Ideale per brand di moda, bellezza o lifestyle.

### Soft Gray (Grigio morbido)

Sfondo piatto grigio chiaro con una scheda con bordo sottile. Toni grigi attenuati ovunque con un pulsante di iscrizione scuro discreto. Minimalista ed elegante. Ideale per brand professionali, B2B o minimalisti.

Ogni preset può essere usato così com'è o modificato ulteriormente nel campo CSS personalizzato dopo il caricamento.

Importante: Il caricamento di un preset sostituisce qualsiasi CSS personalizzato esistente. Se hai stili personalizzati, copiali prima di caricare un preset.

---

## 6. Riferimento classi CSS

Le seguenti classi CSS sono disponibili per la personalizzazione. Una tabella di riferimento completa con descrizioni è visualizzata nel pannello Riferimento classi CSS nella pagina di configurazione.

### Classi di layout

- .mzc-landing-container — wrapper esterno, copre l'intero viewport, controlla il colore di sfondo o il gradiente
- .mzc-landing-content — la scheda centrata o box di contenuto, controlla max-width, padding, sfondo, border-radius e ombra

### Logo

- .mzc-landing-logo — div wrapper del logo del negozio
- .mzc-landing-logo img — l'immagine del logo stessa, controlla altezza e larghezza massime

### Messaggio

- .mzc-landing-message — div wrapper per il titolo e il testo del paragrafo
- .mzc-landing-message h1 — il titolo principale
- .mzc-landing-message h2 — stile di titolo alternativo
- .mzc-landing-message h3 — stile di titolo alternativo
- .mzc-landing-message p — testo del paragrafo sotto il titolo

### Modulo

- .mzc-landing-form-wrapper — wrapper per l'intera area del modulo
- .mzc-form-group — il contenitore della riga campo-pulsante
- .mzc-form-input — il campo di inserimento e-mail
- .mzc-form-button — il pulsante di iscrizione

### Feedback e GDPR

- .mzc-form-feedback — l'area del messaggio di successo o errore sotto il modulo
- .mzc-gdpr-consent — wrapper per la casella di consenso GDPR e l'etichetta

### Esempio

```css
.mzc-landing-container {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.mzc-landing-content {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.mzc-form-button {
  background: #764ba2;
  color: #ffffff;
}

.mzc-form-button:hover {
  background: #667eea;
}
```

---

## 7. Conformità GDPR

Il modulo si integra con il modulo ufficiale GDPR di PrestaShop (psgdpr) attraverso tre hook.

### Casella di consenso

Quando psgdpr è installato e configurato, una casella di consenso con il tuo messaggio configurato appare sotto il campo e-mail nella pagina di destinazione. Il pulsante di iscrizione è disabilitato finché il visitatore non spunta la casella. Questo è gestito automaticamente dal JavaScript del modulo psgdpr.

Per configurare il messaggio di consenso, accedi a Moduli > Gestore moduli > Conformità GDPR ufficiale > Configura, e imposta il messaggio di consenso per MZC Landing Newsletter.

### Cancellazione dati

Quando viene elaborata una richiesta di cancellazione dati GDPR, il modulo elimina qualsiasi indirizzo e-mail corrispondente dalla tabella di iscrizione alla newsletter e pulisce i record di limitazione di frequenza correlati.

### Esportazione dati

Quando viene elaborata una richiesta di esportazione dati GDPR, il modulo restituisce tutti i record di iscrizione alla newsletter corrispondenti all'indirizzo e-mail richiesto, inclusa la data di iscrizione e l'indirizzo IP di registrazione.

---

## 8. Google Tag Manager e analisi

La pagina di destinazione renderizza tre hook standard di PrestaShop che i moduli di tracciamento utilizzano:

- displayHeader — renderizzato all'interno della sezione head dell'HTML. Usato da Google Tag Manager, Google Analytics, Facebook Pixel e moduli simili per iniettare i loro script di tracciamento e l'inizializzazione del dataLayer.
- displayAfterBodyOpeningTag — renderizzato immediatamente dopo il tag body di apertura. Usato da Google Tag Manager per il suo iframe noscript di fallback.
- displayBeforeBodyClosingTag — renderizzato prima del tag body di chiusura. Usato da alcuni moduli di tracciamento per il caricamento differito degli script.

Questo significa che qualsiasi modulo di tracciamento che usa il sistema di hook standard di PrestaShop funzionerà sulla pagina di destinazione senza configurazione aggiuntiva. Compatibilità verificata con:

- Google Tag Manager (gtmmodule)
- PrestaShop Google Analytics (ps_googleanalytics)
- PrestaShop Marketing with Google (psxmarketingwithgoogle)

---

## 9. Configurazione Multi-Negozio

Il modulo supporta completamente la funzionalità multi-negozio di PrestaShop.

### Configurazione per negozio

Quando il multi-negozio è attivo e selezioni un negozio specifico nel selettore di contesto:

- Ogni campo di configurazione mostra una casella di override
- Spunta la casella per impostare un valore specifico per il negozio che sovrascrive il valore globale predefinito
- Deseleziona la casella per ereditare il valore dalla configurazione Tutti i negozi

### Scenari comuni

- Attivare la modalità landing per un nuovo negozio mantenendo gli altri attivi: Imposta MZC_LANDING_ENABLED su No a livello di Tutti i negozi, poi sovrascrivi a Sì per il negozio specifico
- Usare messaggi diversi per negozio: Imposta un messaggio predefinito in Tutti i negozi, poi sovrascrivi con messaggi specifici dove necessario
- Usare CSS diverso per negozio: Ogni negozio può avere il proprio stile visivo sovrascrivendo il campo CSS personalizzato

---

## 10. Gestione iscritti

### Lista iscritti

Il pannello Iscritti nella pagina di configurazione mostra tutti gli indirizzi e-mail raccolti tramite la pagina di destinazione, identificati dal tag sorgente mzc_landing_page. La lista visualizza:

- Indirizzo e-mail
- Lingua al momento dell'iscrizione
- Indirizzo IP di registrazione
- Data di iscrizione

La lista è paginata a 20 voci per pagina. Usa i link di navigazione in basso per sfogliare.

Clicca su Aggiorna lista per ricaricare i dati degli iscritti.

### Esportazione CSV

Clicca su Esporta CSV per scaricare tutti gli iscritti della pagina di destinazione come file di valori separati da virgole. L'esportazione include tutti gli iscritti (non solo la pagina corrente), con colonne: e-mail, lingua, IP e data.

### Integrazione con ps_emailsubscription

Poiché il modulo usa la tabella nativa della newsletter di PrestaShop, gli iscritti raccolti sulla pagina di destinazione appaiono anche in:

- La lista iscritti del modulo ps_emailsubscription
- Qualsiasi strumento di esportazione newsletter che legge dalla tabella emailsubscription
- Le integrazioni Mailchimp, Sendinblue e altre connesse a PrestaShop

---

## 11. Configurazione SEO

La pagina di destinazione carica automaticamente i metadati SEO dalla configurazione del tuo negozio per la pagina index (pagina iniziale):

- Meta title — usato come titolo della pagina HTML
- Meta description — renderizzato come tag meta description
- Meta keywords — renderizzato come tag meta keywords (se configurato)

Per configurare questi valori, accedi a Parametri negozio > Traffico e SEO > SEO e URL, trova la pagina etichettata come index e modifica meta title, meta description e meta keywords.

Se non è configurato alcun meta title, il modulo usa il nome del negozio come predefinito.

La pagina di destinazione invia un codice di stato HTTP 503 (Service Unavailable) con un header Retry-After. Questo indica ai motori di ricerca che il sito è temporaneamente non disponibile e che devono tornare più tardi, preservando le classifiche esistenti nei risultati di ricerca.

---

## 12. Interazione con la modalità manutenzione

Importante: Disattiva la modalità manutenzione integrata di PrestaShop quando usi la modalità pagina di destinazione.

La modalità manutenzione di PrestaShop (Parametri negozio > Generale > Manutenzione) e la modalità pagina di destinazione di questo modulo sono funzionalità indipendenti. Se entrambe sono attivate contemporaneamente, la modalità manutenzione di PrestaShop ha la priorità perché si esegue prima nel ciclo di vita della richiesta, prima che l'hook di questo modulo venga eseguito.

Per accedere alle impostazioni di manutenzione, vai in Parametri negozio > Generale > Manutenzione nel tuo back-office e imposta Abilita negozio su Sì.

Flusso di lavoro consigliato:

1. Disattiva la modalità manutenzione di PrestaShop (imposta Abilita negozio su Sì)
2. Attiva la modalità pagina di destinazione di MZC Landing Newsletter
3. Lavora sul tuo negozio — puoi accedervi tramite il tuo indirizzo IP in lista bianca
4. Quando sei pronto per il lancio, disattiva la modalità pagina di destinazione
5. Il tuo negozio è immediatamente disponibile per tutti i visitatori

---

## 13. Bypass per amministratori

Quando la modalità pagina di destinazione è attivata, i seguenti utenti possono comunque accedere al negozio completo:

### Lista bianca IP

Qualsiasi indirizzo IP elencato in Parametri negozio > Generale > Manutenzione > IP di manutenzione bypassa la pagina di destinazione. Aggiungi il tuo indirizzo IP lì per lavorare sul tuo negozio mentre i visitatori vedono la pagina di destinazione. Più indirizzi IP possono essere separati da virgole. La notazione CIDR è supportata (es. 192.168.1.0/24).

### Amministratori connessi

Se l'impostazione PS_MAINTENANCE_ALLOW_ADMINS è attivata, qualsiasi utente con una sessione attiva del back-office bypassa automaticamente la pagina di destinazione. Il modulo legge il cookie amministratore di PrestaShop per rilevare gli amministratori connessi.

---

## 14. Limitazione di frequenza

Per prevenire spam e abusi, l'endpoint di iscrizione impone un limite di 3 invii per indirizzo IP per finestra di 10 minuti.

Quando il limite viene superato, il visitatore vede un messaggio che chiede di riprovare più tardi. Il contatore del limite si reimposta automaticamente dopo 10 minuti.

I dati di limitazione di frequenza (indirizzi IP e timestamp) sono memorizzati in una tabella di database dedicata e puliti automaticamente. Le voci scadute dell'IP corrente vengono eliminate ad ogni richiesta, con una pulizia globale probabilistica dell'1% per prevenire la crescita della tabella.

---

## 15. Funzionalità di sicurezza

### Protezione CSRF

Il modulo di iscrizione include un token CSRF a rotazione temporale che cambia ogni ora. Vengono accettati sia i token dell'ora corrente che dell'ora precedente durante la validazione per evitare rifiuti ai confini orari.

### Protezione XSS

Il CSS personalizzato inserito nel back-office viene sanificato prima del salvataggio. I tag HTML vengono rimossi e le sequenze di rottura del tag style vengono neutralizzate per prevenire l'iniezione di script.

### Content Security Policy

La pagina di destinazione invia un header Content-Security-Policy che limita le sorgenti di script a self e inline (richiesto per i moduli di tracciamento), e permette stili da self, inline e sorgenti HTTPS (richiesto per i font web).

### Validazione e-mail

Gli indirizzi e-mail vengono validati usando il metodo integrato Validate::isEmail() di PrestaShop prima di qualsiasi operazione sul database.

---

## 16. Traduzioni

Il modulo include traduzioni complete per 5 lingue:

- Inglese (en)
- Polacco (pl)
- Francese (fr)
- Spagnolo (es)
- Italiano (it)

Ogni file di traduzione copre tutte le 87 stringhe traducibili nella classe del modulo, nel controller di iscrizione e nel template della pagina di destinazione.

### Aggiungere o modificare traduzioni

Per tradurre il modulo in lingue aggiuntive o modificare le traduzioni esistenti:

1. Accedi a Internazionale > Traduzioni nel tuo back-office
2. Seleziona Traduzioni moduli installati dal menu a tendina Tipo
3. Seleziona la lingua di destinazione
4. Trova MZC Landing Newsletter nell'elenco dei moduli
5. Inserisci le tue traduzioni e clicca su Salva

PrestaShop salva automaticamente il file di traduzione in modules/mzc_landing_newsletter/translations/.

---

## 17. Risoluzione problemi

### La pagina di destinazione non si visualizza

- Verifica che MZC_LANDING_ENABLED sia impostato su Sì nella configurazione del modulo
- Controlla che la modalità manutenzione di PrestaShop sia disattivata (Parametri negozio > Generale > Manutenzione > Abilita negozio = Sì)
- Verifica che il tuo IP non sia nella lista bianca IP di manutenzione
- Svuota la cache di PrestaShop (Parametri avanzati > Prestazioni > Svuota cache)

### La casella GDPR non appare

- Verifica che il modulo psgdpr sia installato e attivato
- Accedi alla configurazione di psgdpr e assicurati che un messaggio di consenso sia configurato per MZC Landing Newsletter
- Svuota la cache di PrestaShop e ricarica la pagina di destinazione

### I font non si caricano correttamente

- Questo di solito si verifica quando i font del tema (es. Google Fonts) vengono caricati tramite l'hook displayHeader. Il modulo renderizza questo hook, quindi i font dovrebbero caricarsi. In caso contrario, svuota la cache del browser con Ctrl+Shift+R (Cmd+Shift+R su Mac)
- Controlla la console degli strumenti di sviluppo del browser per errori Content Security Policy

### Gli script di tracciamento non si attivano

- Verifica che il tuo modulo di tracciamento usi hook standard di PrestaShop (displayHeader, displayAfterBodyOpeningTag o displayBeforeBodyClosingTag)
- Controlla la console degli strumenti di sviluppo del browser per errori JavaScript
- Alcuni moduli di tracciamento possono richiedere un contesto di pagina specifico che non è disponibile sulla pagina di destinazione

### Il pulsante di iscrizione non funziona

- Controlla la console degli strumenti di sviluppo del browser per errori JavaScript
- Verifica che la casella di consenso psgdpr sia spuntata (se GDPR è attivato)
- Controlla se la limitazione di frequenza è stata attivata (max 3 per IP per 10 minuti)

### Gli iscritti non appaiono nella lista

- Clicca su Aggiorna lista nella pagina di configurazione
- Verifica di visualizzare il contesto di negozio corretto nelle configurazioni multi-negozio
- Controlla la tabella del database ps_emailsubscription per voci con http_referer = mzc_landing_page

---

## 18. Disinstallazione

1. Accedi a Moduli > Gestore moduli
2. Trova MZC Landing Newsletter
3. Clicca sulla freccia del menu a tendina e seleziona Disinstalla
4. Conferma la disinstallazione

Il modulo:

- Rimuoverà tutti i valori di configurazione (MZC_LANDING_ENABLED, MZC_LANDING_MESSAGE, MZC_LANDING_CSS)
- Eliminerà la tabella di limitazione di frequenza (mzc_landing_ratelimit)
- Deregistrerà tutti gli hook

Gli iscritti alla newsletter nella tabella emailsubscription NON vengono eliminati durante la disinstallazione, poiché sono condivisi con il modulo ps_emailsubscription.

---

## 19. Supporto

Per supporto, segnalazioni di bug o richieste di funzionalità, contattaci tramite il sistema di messaggistica PrestaShop Addons nella pagina del prodotto del modulo.

Quando segnali un problema, includi:

- Versione di PrestaShop
- Versione di PHP
- Nome e versione del tema
- Lista degli altri moduli installati
- Output della console degli strumenti di sviluppo del browser (se applicabile)
- Passaggi per riprodurre il problema
