# AI Tags Optimizer for WordPress

*[README also available in English](README.md)*

Un plugin WordPress che analizza i tag dei tuoi articoli con l'API Claude (Anthropic) per suggerire l'unione di duplicati/sinonimi e segnalare i tag inutilizzati. Nulla viene modificato automaticamente: ogni suggerimento richiede approvazione manuale.

## Requisiti

- WordPress 6.0 o superiore
- PHP 7.4 o superiore
- Una API key Anthropic

## Installazione

1. Copia l'intera cartella del plugin in `wp-content/plugins/ai-tags-optimizer/` sul tuo sito WordPress.
2. Vai su **Plugin** nella bacheca di WordPress e attiva "AI Tags Optimizer for WordPress".

## Lingua

L'interfaccia del plugin è in inglese di default; se il tuo WordPress è impostato in italiano (`it_IT`), il plugin carica automaticamente la traduzione italiana inclusa (`languages/ai-tags-optimizer-it_IT.mo`). Questo è indipendente dalla lingua che Claude usa per scrivere il "motivo" di ogni suggerimento, configurabile separatamente nelle Impostazioni.

## Configurazione

Vai su **Strumenti → AI Tags Optimizer: Settings** e compila:

| Campo | Descrizione |
|---|---|
| **Anthropic API Key** | La tua API key di Claude. Usa il bottone **"Test API key"** per verificarla prima di salvare. |
| **Model** | Il modello Claude usato per l'analisi, es. `claude-haiku-4-5`. |
| **Batch size** | Numero di tag inviati per ogni chiamata API (10-500). |
| **AI response language** | Lingua che Claude deve usare per il testo "motivo" di ogni suggerimento. Lascia vuoto per farla corrispondere automaticamente alla lingua dei nomi dei tag. |
| **Full cleanup on uninstall** | Se attivo (default), l'eliminazione del plugin rimuove impostazioni e storico di suggerimenti/batch dal database. |

## Avviare un'analisi

1. Vai su **Articoli → AI Tags Optimizer**, nella tab **"AI Analysis"**.
2. Clicca **"Start analysis"**. I tag vengono elaborati a batch, con un log di elaborazione live e un indicatore di avanzamento; usa **"Stop analysis"** per interromperla.
3. Al termine dei batch, i suggerimenti compaiono raggruppati per tipo:
   - **Near-duplicates** — quasi-duplicati testuali (refusi, plurali, maiuscole/minuscole, trattini/spazi)
   - **Semantic overlaps** — formulazione diversa, significato sovrapposto
   - **Low-usage tags** — tag poco usati che potrebbero confluire in un tag più ampio già presente

## Revisionare i suggerimenti

Ogni suggerimento può essere **Approvato** (unisce il/i tag sorgente nel tag target), **Rifiutato**, oppure in seguito **Ripristinato** dalla lista "Rejected suggestions" per tornare in sospeso. Ogni tabella supporta anche la selezione multipla con checkbox "seleziona tutto" e azioni bulk **Approve selected / Reject selected / Restore selected** — selezione e azioni bulk sono indipendenti per ciascuna tabella.

## Gestire i tag senza IA

La tab **"Manage Tags"** (accanto a "AI Analysis" sulla stessa pagina) raccoglie tutta la gestione manuale, non-IA, dei tag:

- Un istogramma di distribuzione dell'utilizzo per un colpo d'occhio immediato sulla tua tassonomia.
- La tabella **"Unused tags (0 posts)"**, con un'opzione di cancellazione multipla; usa **"Recount tag counts"** se i conteggi sembrano sbagliati (es. dopo un'importazione).
- Una tabella di tutti i tag in uso, cercabile, ordinabile e paginata, con ogni tag collegato direttamente alla lista articoli filtrata. Usa il pannello opzioni schermata (in alto a destra) per cambiare quanti tag mostrare per pagina.
- **Cancella** qualsiasi tag singolarmente (azione di riga) o in blocco.
- **Unisci** 2 o più tag indipendentemente da quanto i loro nomi differiscano: selezionali con le checkbox, scegli quale deve sopravvivere da una dropdown "Merge into" e conferma. Non serve più dare ai tag un termine di ricerca comune solo per trovarli insieme, come richiede invece la schermata Tag nativa di WordPress — e qui non è coinvolta l'IA.

## Aggiornamenti

Il plugin è compatibile con [Git Updater](https://git-updater.com/), quindi può essere mantenuto aggiornato direttamente dal [repository GitHub](https://github.com/gioxx/wp-ai-tags-optimizer) senza passare da WordPress.org.
