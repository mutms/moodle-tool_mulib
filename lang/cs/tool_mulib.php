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
 * Strings for component 'tool_mulib', language 'cs'.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['environment_sqlsrv_incompatible'] = 'MS SQL Server není kompatibilní s pluginy MuTMS.';
$string['environment_windows_unsupported'] = 'Systém MS Windows není podporován jako webový server pro použití s pluginy MuTMS.';
$string['extdb'] = 'Externí databáze';
$string['extdb_exception'] = 'Chyba při komunikaci s externí databází';
$string['extdb_queries'] = 'Dotazy na externí databáze';
$string['extdb_query'] = 'Dotaz na externí databázi';
$string['extdb_query_check'] = 'Zkontrolovat dotaz';
$string['extdb_query_create'] = 'Přidat dotaz';
$string['extdb_query_delete'] = 'Odstranit dotaz';
$string['extdb_query_sqlquery'] = 'SQL dotaz';
$string['extdb_query_status'] = 'Stav dotazu';
$string['extdb_query_type'] = 'Typ dotazu';
$string['extdb_query_update'] = 'Upravit dotaz';
$string['extdb_server'] = 'Server externí databáze';
$string['extdb_server_check'] = 'Zkontrolovat připojení';
$string['extdb_server_create'] = 'Přidat server';
$string['extdb_server_dboptions'] = 'Možnosti PDO (JSON)';
$string['extdb_server_dboptions_help'] = 'Volitelně můžete ve formátu JSON zadat další možnosti připojení ovladače databáze PDO. Místo konstant PHP použijte čísla.';
$string['extdb_server_dbpass'] = 'Heslo k databázi';
$string['extdb_server_dbpass_change'] = 'Použít jiné heslo';
$string['extdb_server_dbuser'] = 'Uživatel databáze';
$string['extdb_server_delete'] = 'Odstranit server';
$string['extdb_server_dsn'] = 'PDO DSN';
$string['extdb_server_dsn_help'] = 'Data Source Name (DSN) určuje připojení k databázovému serveru, podrobnosti najdete v [dokumentaci k rozšíření PHP PDO](https://www.php.net/manual/en/pdo.connections.php).';
$string['extdb_server_extensions'] = 'Dostupná rozšíření PDO: {$a}';
$string['extdb_server_status'] = 'Stav připojení';
$string['extdb_server_update'] = 'Upravit server';
$string['extdb_servers'] = 'Servery externích databází';
$string['import_delimiter'] = 'Oddělovač CSV';
$string['import_delimiter_auto'] = 'Zjistit automaticky';
$string['import_encoding'] = 'Kódování';
$string['import_error_empty'] = 'Nebyly nalezeny žádné použitelné řádky.';
$string['import_error_parse'] = 'Data nelze načíst: {$a}';
$string['import_error_source'] = 'Vložte data nebo nahrajte soubor.';
$string['import_file'] = 'Zdrojový soubor';
$string['import_file_or'] = 'Nebo nahrajte soubor';
$string['import_paste'] = 'Vložit data';
$string['import_paste_or'] = 'Nebo vložte data CSV';
$string['lang_settings'] = 'Překlady MuTMS';
$string['lang_string_a'] = '{$a}';
$string['muform_apply'] = 'Použít';
$string['muform_back'] = 'Zpět';
$string['muform_clear'] = 'Vymazat';
$string['muform_clearselection'] = 'Zrušit výběr';
$string['muform_clearvalue'] = 'Vymazat aktuální hodnotu';
$string['muform_close'] = 'Zavřít dialog';
$string['muform_datetimeformat'] = 'j. n. Y H:i';
$string['muform_datetimeinvalid'] = 'Neplatné datum a čas';
$string['muform_datetimerangeends'] = '{$a->duration}, končí {$a->end}';
$string['muform_datetimerangeinvalid'] = 'Neplatný rozsah data a času';
$string['muform_datetimerangetoolong'] = 'Maximální délka je {$a}';
$string['muform_days'] = 'dnů';
$string['muform_duration'] = 'Délka';
$string['muform_editorformat'] = 'Formát textu';
$string['muform_hide'] = 'Skrýt';
$string['muform_hour'] = 'Hodina';
$string['muform_hours'] = 'hodin';
$string['muform_loading'] = 'Načítání formuláře';
$string['muform_minute'] = 'Minuta';
$string['muform_minutes'] = 'minut';
$string['muform_monthnext'] = 'Další měsíc';
$string['muform_monthprev'] = 'Předchozí měsíc';
$string['muform_months'] = 'měsíců';
$string['muform_nofiles'] = 'Žádné soubory';
$string['muform_noresults'] = 'Žádné výsledky';
$string['muform_nosubdirs'] = 'Podsložky nejsou povoleny';
$string['muform_notset'] = 'Není nastaveno';
$string['muform_numhour'] = '{$a} hodina';
$string['muform_numminute'] = '{$a} minuta';
$string['muform_numsecond'] = '{$a} sekunda';
$string['muform_pickdatetime'] = 'Vybrat datum a čas';
$string['muform_pickdatetimerange'] = 'Vybrat datum a časový rozsah';
$string['muform_rangeend'] = 'Konec';
$string['muform_rangestart'] = 'Začátek';
$string['muform_remove'] = 'Odebrat: {$a}';
$string['muform_search'] = 'Hledat...';
$string['muform_searching'] = 'Hledání...';
$string['muform_seconds'] = 'sekund';
$string['muform_show'] = 'Zobrazit';
$string['muform_steps'] = 'Kroky';
$string['muform_stepskipped'] = 'přeskočeno';
$string['muform_tagnotstandard'] = 'Lze použít pouze standardní štítky';
$string['muform_timezone'] = 'Časové pásmo: {$a}';
$string['muform_today'] = 'Dnes';
$string['muform_toomanyfiles'] = 'Maximální počet souborů: {$a}';
$string['muform_toomanyresults'] = 'Příliš mnoho výsledků, pište dál';
$string['muform_typenewvalue'] = 'Zadejte novou hodnotu...';
$string['muform_unsafehtml'] = 'Je povoleno nebezpečné HTML, obsah se ukládá bez vyčištění';
$string['muform_usersuspended'] = 'Pozastavený uživatel';
$string['muform_weeks'] = 'týdnů';
$string['muform_wizard_expired'] = 'Platnost relace formuláře vypršela, začněte prosím znovu.';
$string['muform_yearnext'] = 'Další rok';
$string['muform_yearprev'] = 'Předchozí rok';
$string['muform_years'] = 'let';
$string['mulib:useextdb'] = 'Používat externí dotazy';
$string['notification'] = 'Oznámení';
$string['notification_body'] = 'Zpráva';
$string['notification_cc_supervisor'] = 'Poslat kopii nadřízenému';
$string['notification_create'] = 'Přidat oznámení';
$string['notification_custom'] = 'Přizpůsobeno';
$string['notification_delete'] = 'Odstranit oznámení';
$string['notification_delete_confirm'] = 'Pokud instanci oznámení odstraníte a později ji znovu přidáte, uživatelé mohou dostat oznámení opakovaně.';
$string['notification_enabled'] = 'Povoleno';
$string['notification_extramenu'] = 'Akce oznámení';
$string['notification_import'] = 'Importovat oznámení';
$string['notification_import_from'] = 'Importovat z';
$string['notification_instance'] = 'Oznámení pro';
$string['notification_subject'] = 'Předmět';
$string['notification_type'] = 'Typ oznámení';
$string['notification_types'] = 'Typy oznámení';
$string['notification_update'] = 'Upravit oznámení';
$string['notification_view'] = 'Podrobnosti oznámení';
$string['notifications'] = 'Oznámení';
$string['notset'] = 'Není nastaveno';
$string['pluginname'] = 'Knihovna pomocných nástrojů';
$string['privacy:metadata:jsondata'] = 'Dočasná data zadaná do vícestránkového formuláře';
$string['privacy:metadata:notificationid'] = 'ID oznámení';
$string['privacy:metadata:timecreated'] = 'Čas vytvoření';
$string['privacy:metadata:timenotified'] = 'Čas, kdy byl uživatel upozorněn';
$string['privacy:metadata:tool_mulib_muform_wizard:tableexplanation'] = 'Dočasný stav vícestránkových formulářů, maže se po dokončení, po zrušení nebo po jednom dni';
$string['privacy:metadata:tool_mulib_notification_user:tableexplanation'] = 'Sledovaná oznámení uživatelů';
$string['privacy:metadata:userid'] = 'Uživatel';
$string['purgedownloadedlang'] = 'Používat překlady dodávané s MuTMS';
$string['purgedownloadedlang_desc'] = 'Překlady dodávané s pluginy MuTMS jsou revidovány společně s kódem. Pokud je volba zapnuta, řetězce MuTMS se ze stažených jazykových balíčků odstraňují, protože by přepsaly dodávané překlady. Jednotlivé řetězce můžete změnit pomocí nástroje Přizpůsobení jazyka.';
$string['task_context_map_cron'] = 'Cron mapy kontextů';
$string['task_lang_cleanup'] = 'Odstranění stažených překladů v konfliktu s oficiálními překlady MuTMS';
$string['toomanyrecords'] = 'Nalezeno příliš mnoho záznamů';
