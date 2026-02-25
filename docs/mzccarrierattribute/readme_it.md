# MZC Carrier Attribute — Documentazione

## Versione 1.0.0

## Autore: Marcin Zbijowski Consulting

## Compatibilità: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Indice

1. Panoramica
2. Requisiti
3. Installazione
4. Come funziona
5. Configurazione
6. Esempi
7. Comportamento del carrello
8. Aggiornamenti dei corrieri
9. Configurazione Multi-Negozio
10. Prestazioni
11. Traduzioni
12. Risoluzione dei problemi
13. Disinstallazione
14. Supporto

---

## 1. Panoramica

MZC Carrier Attribute ti permette di limitare quali corrieri sono disponibili al checkout in base ai valori degli attributi del prodotto (es. taglie, materiali, pesi).

Utilizza un modello whitelist: per ogni valore di attributo, selezioni quali corrieri sono consentiti. I valori di attributi senza corrieri selezionati non impongono alcuna restrizione — tutti i corrieri restano disponibili. Solo i valori di attributi con corrieri esplicitamente assegnati limitano le opzioni di spedizione.

Quando un cliente aggiunge prodotti con valori di attributi ristretti al carrello, il modulo rimuove automaticamente i corrieri non consentiti dalle opzioni di consegna al checkout. Se nel carrello sono presenti più valori di attributi ristretti, saranno disponibili solo i corrieri consentiti da TUTTI.

I prodotti semplici senza combinazioni non vengono mai influenzati da questo modulo.

---

## 2. Requisiti

- PrestaShop 8.0.0 o successivo (compatibile fino alla 9.x)
- PHP 8.1 o successivo
- Almeno un corriere attivo configurato
- Almeno un gruppo di attributi con valori (es. Taglia: S, M, L, XL)

---

## 3. Installazione

### Da PrestaShop Addons

1. Scarica il file ZIP del modulo dal tuo account Addons
2. Vai al back-office del tuo PrestaShop
3. Naviga su Moduli > Gestore moduli
4. Clicca su Carica un modulo
5. Seleziona il file ZIP e attendi il completamento dell'installazione
6. Clicca su Configura per impostare il modulo

### Installazione manuale

1. Estrai il file ZIP
2. Carica la cartella mzccarrierattribute nella directory modules del tuo PrestaShop via FTP
3. Vai su Moduli > Gestore moduli nel back-office
4. Cerca MZC Carrier Attribute
5. Clicca su Installa, poi su Configura

Dopo l'installazione, un link al modulo appare anche nel menu sinistro del back-office sotto **Spedizione** > **Attributi corrieri**, fornendo accesso rapido alla pagina di configurazione.

---

## 4. Come funziona

### Il modello whitelist

Il modulo opera su un semplice principio whitelist:

1. Ogni valore di attributo del prodotto (es. «XXL» nel gruppo «Taglia») può avere assegnati uno o più corrieri consentiti
2. Se un valore di attributo non ha corrieri assegnati, non impone alcuna restrizione — tutti i corrieri restano disponibili
3. Se un valore di attributo ha corrieri assegnati, solo quei corrieri sono consentiti quando un prodotto con quell'attributo è nel carrello
4. Quando più valori di attributi ristretti sono nel carrello, il modulo calcola l'intersezione — solo i corrieri presenti in TUTTE le whitelist sono disponibili

### Esempio di logica

Consideriamo queste regole:

- Taglia «XXL» → corrieri consentiti: Corriere A, Corriere B
- Taglia «S» → nessun corriere assegnato (nessuna restrizione)
- Materiale «Vetro» → corrieri consentiti: Corriere B, Corriere C

Scenari:

- Il carrello contiene un prodotto in taglia S → tutti i corrieri disponibili (nessuna restrizione)
- Il carrello contiene un prodotto in taglia XXL → solo Corriere A e B disponibili
- Il carrello contiene un prodotto in taglia XXL e materiale Vetro → solo Corriere B disponibile (intersezione di {A, B} e {B, C})
- Il carrello contiene un prodotto in taglia XXL e un altro in taglia S → solo Corriere A e B disponibili (solo XXL impone restrizioni)

### Hook utilizzati

Il modulo utilizza tre hook di PrestaShop:

- **actionFilterDeliveryOptionList** — l'hook di filtraggio principale. Rimuove i corrieri non consentiti dalle opzioni di consegna presentate al checkout.
- **actionCarrierUpdate** — invalida la cache interna quando un corriere viene modificato nel back-office, garantendo che le regole facciano sempre riferimento ai dati correnti del corriere.
- **actionPresentCart** — annota l'etichetta del subtotale di spedizione nel riepilogo del carrello quando le restrizioni sono attive, in modo che il cliente veda un avviso che alcuni metodi di spedizione sono limitati dagli attributi del prodotto.

---

## 5. Configurazione

Naviga su Moduli > Gestore moduli, trova MZC Carrier Attribute e clicca su Configura. In alternativa, usa la scorciatoia **Spedizione** > **Attributi corrieri** nel menu a sinistra.

### Pannello delle regole

La pagina di configurazione mostra tutti i gruppi di attributi e i loro valori in pannelli comprimibili. Per ogni valore di attributo, una riga di caselle di controllo elenca tutti i corrieri attivi. Spunta i corrieri che vuoi consentire per quel valore di attributo.

### Filtraggio

Usa il pulsante Mostra solo attributi configurati per filtrare la vista ai soli valori di attributi che hanno almeno un corriere assegnato. Questo è utile quando hai molti attributi ma solo poche regole.

Clicca su Mostra tutti gli attributi per tornare alla vista completa.

### Salvataggio

Clicca su Salva regole in fondo alla pagina per salvare tutte le regole in una volta. Il modulo salva tutte le regole in una singola transazione per coerenza.

Un messaggio di successo conferma il salvataggio. Se non vengono trovati attributi, viene visualizzato un messaggio di errore.

---

## 6. Esempi

### Caso d'uso 1: Articoli sovradimensionati

Vendi mobili e alcuni articoli vengono spediti solo da corrieri di trasporto merci.

1. Crea un gruppo di attributi «Classe di spedizione» con valori come «Standard» e «Solo trasporto merci»
2. Assegna «Solo trasporto merci» → i tuoi corrieri di trasporto merci
3. Lascia «Standard» senza corrieri selezionati (tutti consentiti)
4. Applica «Solo trasporto merci» alle combinazioni di prodotti sovradimensionati

Risultato: I prodotti sovradimensionati mostrano solo i corrieri di trasporto merci al checkout.

### Caso d'uso 2: Restrizioni regionali

Vendi prodotti che possono essere spediti solo da determinati corrieri regionali a causa di restrizioni sui materiali.

1. Usa un attributo esistente come «Materiale» con valori come «Batteria al litio», «Infiammabile», «Standard»
2. Assegna «Batteria al litio» → corrieri certificati per materiali pericolosi
3. Assegna «Infiammabile» → corrieri certificati per merci infiammabili
4. Lascia «Standard» senza corrieri selezionati

Risultato: I prodotti con materiali ristretti mostrano solo i corrieri certificati.

### Caso d'uso 3: Spedizione basata sulla taglia

Vendi abbigliamento e le taglie XXL richiedono un corriere specifico con imballaggi più grandi.

1. Nel gruppo di attributi «Taglia», assegna «XXL» e «XXXL» → il tuo corriere per pacchi grandi
2. Lascia tutte le altre taglie senza corrieri selezionati

Risultato: Gli ordini contenenti articoli XXL o XXXL mostrano solo il corriere per pacchi grandi.

---

## 7. Comportamento del carrello

### Più prodotti

Quando il carrello contiene più prodotti con attributi ristretti, il modulo calcola l'intersezione di tutti gli insiemi di corrieri consentiti. Questo significa:

- Se il Prodotto A consente i corrieri {1, 2, 3} e il Prodotto B consente {2, 3, 4}, solo i corrieri {2, 3} sono disponibili
- Se l'intersezione è vuota (nessun corriere è consentito da tutti i prodotti ristretti), tutte le opzioni di consegna vengono rimosse e il cliente non può procedere al checkout

### Avviso nel subtotale di spedizione

Quando le restrizioni dei corrieri sono attive per il carrello corrente, il modulo aggiunge il testo «(alcuni metodi limitati dagli attributi del prodotto)» all'etichetta del subtotale di spedizione nel riepilogo del carrello. Questo fornisce trasparenza al cliente sul motivo per cui alcuni metodi di spedizione potrebbero non essere disponibili.

### Carrello misto

Se il carrello contiene sia prodotti ristretti che non ristretti, si applicano solo le regole dei prodotti ristretti. I prodotti non ristretti (prodotti semplici o quelli con attributi non configurati) non limitano la disponibilità dei corrieri.

---

## 8. Aggiornamenti dei corrieri

Quando modifichi un corriere nel back-office di PrestaShop (rinomina, cambia zone, aggiorna prezzi), il modulo invalida automaticamente la sua cache interna al successivo caricamento della pagina. Questo garantisce che le regole facciano sempre riferimento ai dati correnti del corriere.

PrestaShop crea un nuovo record del corriere con un nuovo ID quando modifichi un corriere, ma preserva l'ID di riferimento del corriere. Il modulo usa gli ID di riferimento, quindi le regole restano valide anche dopo la modifica del corriere.

Se elimini completamente un corriere, le sue regole diventano inattive ma non vengono automaticamente rimosse dalla configurazione. Il modulo gestisce correttamente i riferimenti a corrieri eliminati saltandoli durante il filtraggio.

---

## 9. Configurazione Multi-Negozio

Il modulo supporta la funzionalità multi-negozio di PrestaShop. Ogni negozio può avere le proprie regole corriere-attributo configurate indipendentemente.

Le regole sono memorizzate per negozio nella tabella Configuration, quindi passare da un contesto di negozio all'altro nel back-office mostra e salva le regole per il negozio selezionato.

---

## 10. Prestazioni

### Strategia di cache

Il modulo utilizza una strategia di cache a due livelli:

1. **Cache di configurazione** — le regole sono serializzate come JSON nella tabella ps_configuration per letture rapide. Questo evita di interrogare la tabella delle regole ad ogni caricamento di pagina.
2. **Cache in memoria per richiesta** — una volta caricate, le regole e le mappature dei riferimenti dei corrieri vengono memorizzate nella cache in proprietà statiche per la durata della richiesta.

La cache viene automaticamente invalidata quando:

- Le regole vengono salvate tramite la pagina di configurazione
- Un corriere viene aggiornato nel back-office

### Database

Il modulo crea una singola tabella (mzc_carrier_attr_rule) con colonne indicizzate per ricerche efficienti. La tabella usa una chiave unica composita per prevenire regole duplicate.

---

## 11. Traduzioni

Il modulo è fornito con traduzioni complete per 5 lingue:

- Inglese (en)
- Polacco (pl)
- Francese (fr)
- Spagnolo (es)
- Italiano (it)

Ogni file di traduzione copre tutte le 20 stringhe traducibili nella classe del modulo, nel template del modulo di regole e nei template di avviso.

### Aggiungere o modificare traduzioni

Per tradurre il modulo in lingue aggiuntive o modificare le traduzioni esistenti:

1. Vai su Internazionale > Traduzioni nel tuo back-office
2. Seleziona Traduzioni dei moduli installati dal menu a tendina Tipo
3. Seleziona la lingua di destinazione
4. Trova MZC Carrier Attribute nella lista dei moduli
5. Inserisci le tue traduzioni e clicca su Salva

PrestaShop salva automaticamente il file di traduzione in modules/mzccarrierattribute/translations/.

---

## 12. Risoluzione dei problemi

### Le restrizioni dei corrieri non si applicano

- Verifica che le regole siano salvate: vai alla configurazione del modulo e controlla che i corrieri corretti siano spuntati per i valori di attributi rilevanti
- Assicurati che il corriere sia attivo e non eliminato
- Svuota la cache di PrestaShop (Parametri avanzati > Prestazioni > Svuota cache)
- Verifica che il prodotto abbia una combinazione con il valore di attributo ristretto (i prodotti semplici non sono interessati)

### Tutti i corrieri scompaiono al checkout

- Questo accade quando l'intersezione di tutti i valori di attributi ristretti risulta in un insieme vuoto (nessun corriere è consentito da tutti i prodotti ristretti nel carrello)
- Rivedi le tue regole per assicurarti che almeno un corriere sia comune a tutti i valori di attributi ristretti che potrebbero essere simultaneamente nel carrello
- Usa il filtro «Mostra solo attributi configurati» per esaminare tutte le regole attive

### Le regole non vengono mostrate nella pagina di configurazione

- Assicurati di avere almeno un gruppo di attributi con valori
- Assicurati di avere almeno un corriere attivo
- Se uno dei due manca, il modulo mostra un avviso invece del modulo di regole

### Pagina di configurazione lenta

- Se hai centinaia di valori di attributi, la pagina di configurazione potrebbe caricarsi lentamente a causa del modulo grande
- Usa il filtro «Mostra solo attributi configurati» per ridurre i dati visualizzati
- Considera la creazione di un gruppo di attributi dedicato «Classe di spedizione» con meno valori invece di configurare regole per ogni attributo

---

## 13. Disinstallazione

1. Vai su Moduli > Gestore moduli
2. Trova MZC Carrier Attribute
3. Clicca sulla freccia a tendina e seleziona Disinstalla
4. Conferma la disinstallazione

Il modulo:

- Eliminerà la tabella delle regole (mzc_carrier_attr_rule)
- Rimuoverà il valore di configurazione (MZC_CARRIER_ATTR_RULES)
- Deregistrerà tutti gli hook

Tutte le regole corriere-attributo verranno eliminate permanentemente.

---

## 14. Supporto

Per supporto, segnalazioni di bug o richieste di funzionalità, contattaci attraverso il sistema di messaggistica PrestaShop Addons nella pagina del prodotto del modulo.

Quando segnali un problema, includi:

- Versione di PrestaShop
- Versione di PHP
- Nome e versione del tema
- Lista dei corrieri attivi e dei gruppi di attributi
- Passaggi per riprodurre il problema
