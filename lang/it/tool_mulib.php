<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

/**
 * Strings for component 'tool_mulib', language 'it'.
 *
 * NOTE: This Italian translation was produced automatically by an AI language
 * model and has not yet been reviewed by a human translator. Corrections are
 * welcome.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['environment_sqlsrv_incompatible'] = 'MS SQL Server non è compatibile con i plugin MuTMS.';
$string['environment_windows_unsupported'] = 'MS Windows non è supportato come web server per l\'uso con i plugin MuTMS.';
$string['extdb'] = 'Database esterni';
$string['extdb_exception'] = 'Errore di comunicazione con il database esterno';
$string['extdb_queries'] = 'Query su database esterni';
$string['extdb_query'] = 'Query su database esterno';
$string['extdb_query_check'] = 'Verifica query';
$string['extdb_query_create'] = 'Aggiungi query';
$string['extdb_query_delete'] = 'Elimina query';
$string['extdb_query_sqlquery'] = 'Query SQL';
$string['extdb_query_status'] = 'Stato della query';
$string['extdb_query_type'] = 'Tipo di query';
$string['extdb_query_update'] = 'Modifica query';
$string['extdb_server'] = 'Server di database esterno';
$string['extdb_server_check'] = 'Verifica connessione';
$string['extdb_server_create'] = 'Aggiungi server';
$string['extdb_server_dboptions'] = 'Opzioni PDO (JSON)';
$string['extdb_server_dboptions_help'] = 'È possibile indicare ulteriori opzioni di connessione del driver di database PDO in formato JSON, usando numeri al posto delle costanti PHP.';
$string['extdb_server_dbpass'] = 'Password del database';
$string['extdb_server_dbpass_change'] = 'Usa una password diversa';
$string['extdb_server_dbuser'] = 'Utente del database';
$string['extdb_server_delete'] = 'Elimina server';
$string['extdb_server_dsn'] = 'DSN PDO';
$string['extdb_server_dsn_help'] = 'Il Data Source Name (DSN) definisce una connessione al server di database, per informazioni dettagliate consultare la [documentazione dell\'estensione PHP PDO](https://www.php.net/manual/en/pdo.connections.php).';
$string['extdb_server_extensions'] = 'Estensioni PDO disponibili: {$a}';
$string['extdb_server_status'] = 'Stato della connessione';
$string['extdb_server_update'] = 'Modifica server';
$string['extdb_servers'] = 'Server di database esterni';
$string['filterbar_add'] = 'Aggiungi filtro';
$string['filterbar_advanced'] = 'Filtri avanzati';
$string['filterbar_any'] = 'Qualsiasi';
$string['filterbar_apply'] = 'Applica';
$string['filterbar_rangefrom'] = 'da {$a}';
$string['filterbar_rangeto'] = 'fino a {$a}';
$string['filterbar_remove'] = 'Rimuovi filtro';
$string['filterbar_resetall'] = 'Reimposta filtri';
$string['filterbar_resetdefault'] = 'Ripristina valore predefinito';
$string['filterbar_useadvanced'] = 'Per cambiare questo filtro usare i filtri avanzati.';
$string['import_delimiter'] = 'Delimitatore CSV';
$string['import_delimiter_auto'] = 'Rileva automaticamente';
$string['import_encoding'] = 'Codifica';
$string['import_error_empty'] = 'Non sono state trovate righe utilizzabili.';
$string['import_error_parse'] = 'Non è possibile leggere i dati: {$a}';
$string['import_error_source'] = 'Incollare i dati oppure caricare un file.';
$string['import_file'] = 'File di origine';
$string['import_file_or'] = 'Oppure carica un file';
$string['import_paste'] = 'Incolla i dati';
$string['import_paste_or'] = 'Oppure incolla i dati CSV';
$string['lang_settings'] = 'Traduzioni MuTMS';
$string['lang_string_a'] = '{$a}';
$string['muform_apply'] = 'Applica';
$string['muform_back'] = 'Indietro';
$string['muform_clear'] = 'Svuota';
$string['muform_clearselection'] = 'Annulla selezione';
$string['muform_clearvalue'] = 'Svuota il valore corrente';
$string['muform_close'] = 'Chiudi finestra di dialogo';
$string['muform_datetimeformat'] = 'd/m/Y H:i';
$string['muform_datetimeinvalid'] = 'Data e ora non valide';
$string['muform_datetimerangeends'] = '{$a->duration}, termina il {$a->end}';
$string['muform_datetimerangeinvalid'] = 'Intervallo di data e ora non valido';
$string['muform_datetimerangetoolong'] = 'La durata massima è {$a}';
$string['muform_days'] = 'giorni';
$string['muform_duration'] = 'Durata';
$string['muform_editorformat'] = 'Formato del testo';
$string['muform_hide'] = 'Nascondi';
$string['muform_hour'] = 'Ora';
$string['muform_hours'] = 'ore';
$string['muform_loading'] = 'Caricamento del form in corso';
$string['muform_minute'] = 'Minuto';
$string['muform_minutes'] = 'minuti';
$string['muform_monthnext'] = 'Mese successivo';
$string['muform_monthprev'] = 'Mese precedente';
$string['muform_months'] = 'mesi';
$string['muform_nofiles'] = 'Nessun file';
$string['muform_noresults'] = 'Nessun risultato';
$string['muform_nosubdirs'] = 'Le sottocartelle non sono consentite';
$string['muform_notset'] = 'Non impostato';
$string['muform_numhour'] = '{$a} ora';
$string['muform_numminute'] = '{$a} minuto';
$string['muform_numsecond'] = '{$a} secondo';
$string['muform_pickdatetime'] = 'Scegli data e ora';
$string['muform_pickdatetimerange'] = 'Scegli intervallo di data e ora';
$string['muform_rangeend'] = 'Fine';
$string['muform_rangestart'] = 'Inizio';
$string['muform_remove'] = 'Rimuovi {$a}';
$string['muform_search'] = 'Cerca...';
$string['muform_searching'] = 'Ricerca in corso...';
$string['muform_seconds'] = 'secondi';
$string['muform_show'] = 'Visualizza';
$string['muform_steps'] = 'Passaggi';
$string['muform_stepskipped'] = 'saltato';
$string['muform_tagnotstandard'] = 'È possibile usare solo tag standard';
$string['muform_timezone'] = 'Fuso orario: {$a}';
$string['muform_today'] = 'Oggi';
$string['muform_toomanyfiles'] = 'Il numero massimo di file è {$a}';
$string['muform_toomanyresults'] = 'Troppi risultati, continuare a digitare';
$string['muform_typenewvalue'] = 'Digitare un nuovo valore...';
$string['muform_unsafehtml'] = 'HTML non sicuro consentito, il contenuto viene salvato senza pulizia';
$string['muform_usersuspended'] = 'Utente sospeso';
$string['muform_weeks'] = 'settimane';
$string['muform_wizard_expired'] = 'La sessione del form è scaduta, è necessario ricominciare.';
$string['muform_yearnext'] = 'Anno successivo';
$string['muform_yearprev'] = 'Anno precedente';
$string['muform_years'] = 'anni';
$string['mulib:useextdb'] = 'Usare query esterne';
$string['notification'] = 'Notifica';
$string['notification_body'] = 'Messaggio';
$string['notification_cc_supervisor'] = 'Invia copia al supervisore';
$string['notification_create'] = 'Aggiungi notifica';
$string['notification_custom'] = 'Personalizzata';
$string['notification_delete'] = 'Elimina notifica';
$string['notification_delete_confirm'] = 'Se l\'istanza della notifica viene eliminata e in seguito aggiunta di nuovo, gli utenti potrebbero ricevere la notifica più volte.';
$string['notification_enabled'] = 'Abilitata';
$string['notification_extramenu'] = 'Azioni per la notifica';
$string['notification_import'] = 'Importa notifiche';
$string['notification_import_from'] = 'Importa da';
$string['notification_instance'] = 'Notifica per';
$string['notification_subject'] = 'Oggetto';
$string['notification_type'] = 'Tipo di notifica';
$string['notification_types'] = 'Tipi di notifica';
$string['notification_update'] = 'Modifica notifica';
$string['notification_view'] = 'Dettagli della notifica';
$string['notifications'] = 'Notifiche';
$string['notset'] = 'Non impostato';
$string['pager_downloadall'] = 'Scarica tutto';
$string['pager_downloadfiltered'] = 'Scarica filtrati';
$string['pager_perpage'] = '{$a} per pagina';
$string['pager_range'] = 'Visualizzati {$a->from}–{$a->to} di {$a->total}';
$string['pager_rangefiltered'] = 'Visualizzati {$a->from}–{$a->to} di {$a->total} corrispondenti ai filtri';
$string['pluginname'] = 'Libreria di strumenti aggiuntivi';
$string['privacy:metadata:jsondata'] = 'Dati temporanei inseriti in un form a più pagine';
$string['privacy:metadata:notificationid'] = 'Id della notifica';
$string['privacy:metadata:timecreated'] = 'Data di creazione';
$string['privacy:metadata:timenotified'] = 'Data di invio della notifica all\'utente';
$string['privacy:metadata:tool_mulib_muform_wizard:tableexplanation'] = 'Stato temporaneo dei form a più pagine, eliminato al completamento, all\'annullamento oppure dopo un giorno';
$string['privacy:metadata:tool_mulib_notification_user:tableexplanation'] = 'Notifiche agli utenti tracciate';
$string['privacy:metadata:userid'] = 'Utente';
$string['purgedownloadedlang'] = 'Usa le traduzioni fornite con MuTMS';
$string['purgedownloadedlang_desc'] = 'Le traduzioni fornite con i plugin MuTMS vengono riviste insieme al codice. Se l\'impostazione è abilitata, le stringhe MuTMS vengono eliminate dai language pack scaricati, perché avrebbero la precedenza sulle traduzioni fornite. Per cambiare singole stringhe usare la personalizzazione della lingua.';
$string['task_context_map_cron'] = 'Cron della mappa dei contesti';
$string['task_lang_cleanup'] = 'Eliminazione delle traduzioni scaricate in conflitto con le traduzioni ufficiali MuTMS';
$string['toomanyrecords'] = 'Sono stati trovati troppi record';
