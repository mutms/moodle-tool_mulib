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
 * Strings for component 'tool_mulib', language 'fr'.
 *
 * NOTE: This French translation was produced automatically by an AI language
 * model and has not yet been reviewed by a human translator. Corrections are
 * welcome.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['environment_sqlsrv_incompatible'] = 'MS SQL Server n’est pas compatible avec les plugins MuTMS.';
$string['environment_windows_unsupported'] = 'MS Windows n’est pas pris en charge comme serveur web pour les plugins MuTMS.';
$string['extdb'] = 'Bases de données externes';
$string['extdb_exception'] = 'Erreur de communication avec la base de données externe';
$string['extdb_queries'] = 'Requêtes de base de données externe';
$string['extdb_query'] = 'Requête de base de données externe';
$string['extdb_query_check'] = 'Vérifier la requête';
$string['extdb_query_create'] = 'Ajouter une requête';
$string['extdb_query_delete'] = 'Supprimer la requête';
$string['extdb_query_sqlquery'] = 'Requête SQL';
$string['extdb_query_status'] = 'Statut de la requête';
$string['extdb_query_type'] = 'Type de requête';
$string['extdb_query_update'] = 'Modifier la requête';
$string['extdb_server'] = 'Serveur de base de données externe';
$string['extdb_server_check'] = 'Vérifier la connexion';
$string['extdb_server_create'] = 'Ajouter un serveur';
$string['extdb_server_dboptions'] = 'Options PDO (JSON)';
$string['extdb_server_dboptions_help'] = 'Vous pouvez indiquer des options de connexion supplémentaires du pilote de base de données PDO au format JSON ; utilisez des nombres à la place des constantes PHP.';
$string['extdb_server_dbpass'] = 'Mot de passe de la base de données';
$string['extdb_server_dbpass_change'] = 'Utiliser un autre mot de passe';
$string['extdb_server_dbuser'] = 'Utilisateur de la base de données';
$string['extdb_server_delete'] = 'Supprimer le serveur';
$string['extdb_server_dsn'] = 'DSN PDO';
$string['extdb_server_dsn_help'] = 'Le nom de source de données (DSN) définit une connexion à un serveur de base de données. Pour plus de détails, voir la [documentation de l’extension PHP PDO](https://www.php.net/manual/en/pdo.connections.php).';
$string['extdb_server_extensions'] = 'Extensions PDO disponibles : {$a}';
$string['extdb_server_status'] = 'Statut de la connexion';
$string['extdb_server_update'] = 'Modifier le serveur';
$string['extdb_servers'] = 'Serveurs de base de données externe';
$string['filterbar_add'] = 'Ajouter un filtre';
$string['filterbar_advanced'] = 'Filtrage avancé';
$string['filterbar_any'] = 'Tous';
$string['filterbar_apply'] = 'Appliquer';
$string['filterbar_rangefrom'] = 'à partir du {$a}';
$string['filterbar_rangeto'] = 'jusqu’au {$a}';
$string['filterbar_remove'] = 'Retirer le filtre';
$string['filterbar_resetall'] = 'Réinitialiser les filtres';
$string['filterbar_resetdefault'] = 'Rétablir la valeur par défaut';
$string['filterbar_useadvanced'] = 'Utilisez le filtrage avancé pour modifier ce filtre.';
$string['import_delimiter'] = 'Séparateur CSV';
$string['import_delimiter_auto'] = 'Détecter automatiquement';
$string['import_encoding'] = 'Encodage';
$string['import_error_empty'] = 'Aucune ligne utilisable n’a été trouvée.';
$string['import_error_parse'] = 'Les données ne peuvent pas être lues : {$a}';
$string['import_error_source'] = 'Collez les données ou déposez un fichier.';
$string['import_file'] = 'Fichier source';
$string['import_file_or'] = 'Ou déposez un fichier';
$string['import_paste'] = 'Coller les données';
$string['import_paste_or'] = 'Ou collez des données CSV';
$string['import_source_locked'] = '{$a} lignes ont été lues. Annulez l’assistant pour utiliser d’autres données.';
$string['lang_settings'] = 'Traductions MuTMS';
$string['lang_string_a'] = '{$a}';
$string['muform_apply'] = 'Appliquer';
$string['muform_back'] = 'Retour';
$string['muform_clear'] = 'Effacer';
$string['muform_clearselection'] = 'Effacer la sélection';
$string['muform_clearvalue'] = 'Effacer la valeur actuelle';
$string['muform_close'] = 'Fermer la fenêtre';
$string['muform_cohortpicker_overflow'] = 'Plus de {$a} cohortes trouvées, affinez la recherche.';
$string['muform_cohortpicker_title'] = 'Sélectionner une cohorte';
$string['muform_cohortpicker_title_multiple'] = 'Sélectionner des cohortes';
$string['muform_collapsibleerrors'] = 'Contient des erreurs';
$string['muform_continue'] = 'Continuer';
$string['muform_datetimeformat'] = 'd/m/Y H:i';
$string['muform_datetimeinvalid'] = 'Date et heure non valides';
$string['muform_datetimerangeends'] = '{$a->duration}, fin : {$a->end}';
$string['muform_datetimerangeinvalid'] = 'Plage de dates et d’heures non valide';
$string['muform_datetimerangetoolong'] = 'La durée maximale est de {$a}';
$string['muform_days'] = 'jours';
$string['muform_duration'] = 'Durée';
$string['muform_editorformat'] = 'Format du texte';
$string['muform_hide'] = 'Cacher';
$string['muform_hour'] = 'Heure';
$string['muform_hours'] = 'heures';
$string['muform_loading'] = 'Chargement du formulaire';
$string['muform_minute'] = 'Minute';
$string['muform_minutes'] = 'minutes';
$string['muform_monthnext'] = 'Mois suivant';
$string['muform_monthprev'] = 'Mois précédent';
$string['muform_months'] = 'mois';
$string['muform_nofiles'] = 'Aucun fichier';
$string['muform_noresults'] = 'Aucun résultat';
$string['muform_nosubdirs'] = 'Les sous-dossiers ne sont pas autorisés';
$string['muform_notset'] = 'Non défini';
$string['muform_numhour'] = '{$a} heure';
$string['muform_numminute'] = '{$a} minute';
$string['muform_numsecond'] = '{$a} seconde';
$string['muform_pickdatetime'] = 'Choisir la date et l’heure';
$string['muform_pickdatetimerange'] = 'Choisir la plage de dates et d’heures';
$string['muform_picker_add'] = 'Ajouter';
$string['muform_picker_change'] = 'Modifier';
$string['muform_picker_remove'] = 'Retirer';
$string['muform_picker_select'] = 'Sélectionner';
$string['muform_rangeend'] = 'Fin';
$string['muform_rangestart'] = 'Début';
$string['muform_remove'] = 'Retirer : {$a}';
$string['muform_search'] = 'Rechercher…';
$string['muform_searching'] = 'Recherche en cours…';
$string['muform_seconds'] = 'secondes';
$string['muform_show'] = 'Afficher';
$string['muform_steps'] = 'Étapes';
$string['muform_stepskipped'] = 'étape sautée';
$string['muform_tagnotstandard'] = 'Seuls les tags standards peuvent être utilisés';
$string['muform_timezone'] = 'Fuseau horaire : {$a}';
$string['muform_today'] = 'Aujourd’hui';
$string['muform_toomanyfiles'] = 'Le nombre maximal de fichiers est de {$a}';
$string['muform_toomanyresults'] = 'Trop de résultats, continuez à saisir';
$string['muform_typenewvalue'] = 'Saisir une nouvelle valeur…';
$string['muform_unsafehtml'] = 'HTML non sécurisé autorisé, le contenu est enregistré sans nettoyage';
$string['muform_userpicker_addselected'] = 'Ajouter la sélection ({$a})';
$string['muform_userpicker_close'] = 'Fermer';
$string['muform_userpicker_filter_active'] = 'Actif';
$string['muform_userpicker_found'] = 'Trouvés : {$a}';
$string['muform_userpicker_name'] = 'Nom';
$string['muform_userpicker_next'] = 'Page suivante';
$string['muform_userpicker_page'] = 'Page {$a}';
$string['muform_userpicker_previous'] = 'Page précédente';
$string['muform_userpicker_selectall'] = 'Sélectionner tous les utilisateurs affichés';
$string['muform_userpicker_selectrow'] = 'Sélectionner';
$string['muform_userpicker_title'] = 'Sélectionner un utilisateur';
$string['muform_userpicker_title_multiple'] = 'Sélectionner des utilisateurs';
$string['muform_usersuspended'] = 'Utilisateur suspendu';
$string['muform_weeks'] = 'semaines';
$string['muform_wizard_expired'] = 'La session du formulaire a expiré, veuillez recommencer.';
$string['muform_wizard_unavailable'] = 'L’assistant ne peut pas être utilisé maintenant.';
$string['muform_yearnext'] = 'Année suivante';
$string['muform_yearprev'] = 'Année précédente';
$string['muform_years'] = 'ans';
$string['mulib:useextdb'] = 'Utiliser les requêtes externes';
$string['notification'] = 'Notification';
$string['notification_body'] = 'Message';
$string['notification_cc_supervisor'] = 'Envoyer une copie au supérieur';
$string['notification_create'] = 'Ajouter une notification';
$string['notification_custom'] = 'Personnalisée';
$string['notification_delete'] = 'Supprimer la notification';
$string['notification_delete_confirm'] = 'Si l’instance de notification est supprimée puis ajoutée à nouveau plus tard, les utilisateurs risquent d’être notifiés plusieurs fois.';
$string['notification_enabled'] = 'Activée';
$string['notification_extramenu'] = 'Actions de notification';
$string['notification_import'] = 'Importer des notifications';
$string['notification_import_from'] = 'Importer depuis';
$string['notification_instance'] = 'Notification pour';
$string['notification_subject'] = 'Objet';
$string['notification_type'] = 'Type de notification';
$string['notification_types'] = 'Types de notification';
$string['notification_update'] = 'Modifier la notification';
$string['notification_view'] = 'Détails de la notification';
$string['notifications'] = 'Notifications';
$string['notset'] = 'Non défini';
$string['pager_downloadall'] = 'Tout télécharger';
$string['pager_downloadfiltered'] = 'Télécharger les résultats filtrés';
$string['pager_perpage'] = '{$a} par page';
$string['pager_range'] = 'Affichage de {$a->from}–{$a->to} sur {$a->total}';
$string['pager_rangefiltered'] = 'Affichage de {$a->from}–{$a->to} sur {$a->total} correspondant aux filtres';
$string['pluginname'] = 'Bibliothèque d’outils supplémentaires';
$string['privacy:metadata:fieldid'] = 'Champ de profil';
$string['privacy:metadata:jsondata'] = 'Données temporaires saisies dans un formulaire à plusieurs pages';
$string['privacy:metadata:notificationid'] = 'Identifiant de la notification';
$string['privacy:metadata:profilefieldvalue'] = 'Valeur du champ de profil personnalisé mise en cache';
$string['privacy:metadata:timecreated'] = 'Date de création';
$string['privacy:metadata:timenotified'] = 'Date de notification de l’utilisateur';
$string['privacy:metadata:tool_mulib_muform_wizard:tableexplanation'] = 'État temporaire des formulaires à plusieurs pages, supprimé une fois le formulaire terminé ou annulé, ou après un jour';
$string['privacy:metadata:tool_mulib_notification_user:tableexplanation'] = 'Suivi des notifications des utilisateurs';
$string['privacy:metadata:tool_mulib_profilefield:tableexplanation'] = 'Cache des valeurs des champs personnalisés du profil utilisateur';
$string['privacy:metadata:userid'] = 'Utilisateur';
$string['profilefieldlifetime'] = 'Intervalle d\'actualisation complète';
$string['profilefieldlifetime_desc'] = 'Les valeurs en cache sont mises à jour automatiquement lorsque les utilisateurs ou les champs de profil changent. Toutes les valeurs sont également actualisées périodiquement afin d\'inclure les modifications faites sans événements, comme les écritures directes en base de données par certains plugins d\'authentification.';
$string['profilefields'] = 'Champs de profil en cache';
$string['profilefields_cache'] = 'Mettre en cache';
$string['profilefields_desc'] = 'Les valeurs des champs de profil personnalisés sont mises en cache pour un filtrage, un tri et une recherche rapides dans les plugins MuTMS. Tous les champs pris en charge sont mis en cache par défaut, les champs utilisés par des plugins ou affichés comme identité de l\'utilisateur ne peuvent pas être retirés du cache.';
$string['profilefields_none'] = 'Aucun champ de profil personnalisé n\'est défini.';
$string['profilefields_status_notcached'] = 'Pas en cache';
$string['profilefields_status_populating'] = 'En préparation';
$string['profilefields_status_ready'] = 'Prêt, actualisé {$a}';
$string['profilefields_status_unsupported'] = 'Type non pris en charge';
$string['profilefields_type'] = 'Type';
$string['profilefields_uncache'] = 'Ne plus mettre en cache';
$string['profilefields_usage'] = 'Utilisation';
$string['profilefields_usage_identity'] = 'Identité de l\'utilisateur';
$string['profilefields_usage_reference'] = '{$a->component} ({$a->area}) : {$a->items}';
$string['purgedownloadedlang'] = 'Utiliser les traductions fournies avec MuTMS';
$string['purgedownloadedlang_desc'] = 'Les traductions fournies avec les plugins MuTMS sont relues en même temps que le code. Si ce réglage est activé, les chaînes MuTMS sont supprimées des paquetages de langue téléchargés, car elles remplaceraient les traductions fournies. Utilisez la personnalisation de la langue pour modifier des chaînes individuelles.';
$string['task_context_map_cron'] = 'Cron de la carte des contextes';
$string['task_lang_cleanup'] = 'Purger les traductions téléchargées en conflit avec les traductions officielles de MuTMS';
$string['task_profilefield_cron'] = 'Maintenance du cache des champs de profil';
$string['task_profilefield_populate'] = 'Remplir le cache des champs de profil';
$string['toomanyrecords'] = 'Trop d’enregistrements trouvés';
