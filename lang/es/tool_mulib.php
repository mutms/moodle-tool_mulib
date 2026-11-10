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
 * Strings for component 'tool_mulib', language 'es'.
 *
 * NOTE: This Spanish translation was produced automatically by an AI language
 * model and has not yet been reviewed by a human translator. Corrections are
 * welcome.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['environment_sqlsrv_incompatible'] = 'MS SQL Server no es compatible con los plugins de MuTMS.';
$string['environment_windows_unsupported'] = 'MS Windows no está soportado como servidor web para su uso con los plugins de MuTMS.';
$string['extdb'] = 'Bases de datos externas';
$string['extdb_exception'] = 'Error de comunicación con la base de datos externa';
$string['extdb_queries'] = 'Consultas a bases de datos externas';
$string['extdb_query'] = 'Consulta a base de datos externa';
$string['extdb_query_check'] = 'Comprobar consulta';
$string['extdb_query_create'] = 'Añadir consulta';
$string['extdb_query_delete'] = 'Eliminar consulta';
$string['extdb_query_sqlquery'] = 'Consulta SQL';
$string['extdb_query_status'] = 'Estado de la consulta';
$string['extdb_query_type'] = 'Tipo de consulta';
$string['extdb_query_update'] = 'Editar consulta';
$string['extdb_server'] = 'Servidor de base de datos externa';
$string['extdb_server_check'] = 'Comprobar conexión';
$string['extdb_server_create'] = 'Añadir servidor';
$string['extdb_server_dboptions'] = 'Opciones de PDO (JSON)';
$string['extdb_server_dboptions_help'] = 'Opcionalmente puede indicar opciones de conexión adicionales del controlador de base de datos PDO en formato JSON; utilice números en lugar de constantes de PHP.';
$string['extdb_server_dbpass'] = 'Contraseña de la base de datos';
$string['extdb_server_dbpass_change'] = 'Usar otra contraseña';
$string['extdb_server_dbuser'] = 'Usuario de la base de datos';
$string['extdb_server_delete'] = 'Eliminar servidor';
$string['extdb_server_dsn'] = 'DSN de PDO';
$string['extdb_server_dsn_help'] = 'El nombre del origen de datos (DSN, Data Source Name) define una conexión con el servidor de base de datos; consulte la [documentación de la extensión PDO de PHP](https://www.php.net/manual/en/pdo.connections.php) para obtener información detallada.';
$string['extdb_server_extensions'] = 'Extensiones PDO disponibles: {$a}';
$string['extdb_server_status'] = 'Estado de la conexión';
$string['extdb_server_update'] = 'Editar servidor';
$string['extdb_servers'] = 'Servidores de bases de datos externas';
$string['filterbar_add'] = 'Añadir filtro';
$string['filterbar_advanced'] = 'Filtrado avanzado';
$string['filterbar_apply'] = 'Aplicar';
$string['filterbar_rangefrom'] = 'desde {$a}';
$string['filterbar_rangeto'] = 'hasta {$a}';
$string['filterbar_remove'] = 'Quitar filtro';
$string['filterbar_useadvanced'] = 'Utilice el filtrado avanzado para cambiar este filtro.';
$string['import_delimiter'] = 'Delimitador CSV';
$string['import_delimiter_auto'] = 'Detectar automáticamente';
$string['import_encoding'] = 'Codificación';
$string['import_error_empty'] = 'No se han encontrado filas utilizables.';
$string['import_error_parse'] = 'No se pueden leer los datos: {$a}';
$string['import_error_source'] = 'Pegue los datos o suba un archivo.';
$string['import_file'] = 'Archivo de origen';
$string['import_file_or'] = 'O suba un archivo';
$string['import_paste'] = 'Pegar datos';
$string['import_paste_or'] = 'O pegue datos CSV';
$string['lang_settings'] = 'Traducciones de MuTMS';
$string['lang_string_a'] = '{$a}';
$string['muform_apply'] = 'Aplicar';
$string['muform_back'] = 'Atrás';
$string['muform_clear'] = 'Limpiar';
$string['muform_clearselection'] = 'Limpiar selección';
$string['muform_clearvalue'] = 'Limpiar el valor actual';
$string['muform_close'] = 'Cerrar diálogo';
$string['muform_datetimeformat'] = 'd/m/Y H:i';
$string['muform_datetimeinvalid'] = 'Fecha y hora no válidas';
$string['muform_datetimerangeends'] = '{$a->duration}, termina el {$a->end}';
$string['muform_datetimerangeinvalid'] = 'Intervalo de fecha y hora no válido';
$string['muform_datetimerangetoolong'] = 'La duración máxima es {$a}';
$string['muform_days'] = 'días';
$string['muform_duration'] = 'Duración';
$string['muform_editorformat'] = 'Formato de texto';
$string['muform_hide'] = 'Ocultar';
$string['muform_hour'] = 'Hora';
$string['muform_hours'] = 'horas';
$string['muform_loading'] = 'Cargando formulario';
$string['muform_minute'] = 'Minuto';
$string['muform_minutes'] = 'minutos';
$string['muform_monthnext'] = 'Mes siguiente';
$string['muform_monthprev'] = 'Mes anterior';
$string['muform_months'] = 'meses';
$string['muform_nofiles'] = 'No hay archivos';
$string['muform_noresults'] = 'Sin resultados';
$string['muform_nosubdirs'] = 'No se permiten subcarpetas';
$string['muform_notset'] = 'Sin definir';
$string['muform_numhour'] = '{$a} hora';
$string['muform_numminute'] = '{$a} minuto';
$string['muform_numsecond'] = '{$a} segundo';
$string['muform_pickdatetime'] = 'Elegir fecha y hora';
$string['muform_pickdatetimerange'] = 'Elegir intervalo de fecha y hora';
$string['muform_rangeend'] = 'Fin';
$string['muform_rangestart'] = 'Inicio';
$string['muform_remove'] = 'Quitar {$a}';
$string['muform_search'] = 'Buscar...';
$string['muform_searching'] = 'Buscando...';
$string['muform_seconds'] = 'segundos';
$string['muform_show'] = 'Mostrar';
$string['muform_steps'] = 'Pasos';
$string['muform_stepskipped'] = 'omitido';
$string['muform_tagnotstandard'] = 'Solo se pueden usar etiquetas estándar';
$string['muform_timezone'] = 'Zona horaria: {$a}';
$string['muform_today'] = 'Hoy';
$string['muform_toomanyfiles'] = 'El número máximo de archivos es {$a}';
$string['muform_toomanyresults'] = 'Demasiados resultados, siga escribiendo';
$string['muform_typenewvalue'] = 'Escriba un valor nuevo...';
$string['muform_unsafehtml'] = 'Se permite HTML no seguro, el contenido se guarda sin limpiar';
$string['muform_usersuspended'] = 'Usuario suspendido';
$string['muform_weeks'] = 'semanas';
$string['muform_wizard_expired'] = 'La sesión del formulario ha expirado, empiece de nuevo.';
$string['muform_yearnext'] = 'Año siguiente';
$string['muform_yearprev'] = 'Año anterior';
$string['muform_years'] = 'años';
$string['mulib:useextdb'] = 'Usar consultas externas';
$string['notification'] = 'Notificación';
$string['notification_body'] = 'Mensaje';
$string['notification_cc_supervisor'] = 'Enviar copia al supervisor';
$string['notification_create'] = 'Añadir notificación';
$string['notification_custom'] = 'Personalizada';
$string['notification_delete'] = 'Eliminar notificación';
$string['notification_delete_confirm'] = 'Si la instancia de notificación se elimina y más adelante se vuelve a añadir, los usuarios pueden recibir la notificación repetidamente.';
$string['notification_enabled'] = 'Habilitada';
$string['notification_extramenu'] = 'Acciones de la notificación';
$string['notification_import'] = 'Importar notificaciones';
$string['notification_import_from'] = 'Importar desde';
$string['notification_instance'] = 'Notificación para';
$string['notification_subject'] = 'Asunto';
$string['notification_type'] = 'Tipo de notificación';
$string['notification_types'] = 'Tipos de notificación';
$string['notification_update'] = 'Editar notificación';
$string['notification_view'] = 'Detalles de la notificación';
$string['notifications'] = 'Notificaciones';
$string['notset'] = 'Sin definir';
$string['pluginname'] = 'Biblioteca de herramientas adicionales';
$string['privacy:metadata:jsondata'] = 'Datos temporales introducidos en un formulario de varias páginas';
$string['privacy:metadata:notificationid'] = 'ID de la notificación';
$string['privacy:metadata:timecreated'] = 'Fecha de creación';
$string['privacy:metadata:timenotified'] = 'Fecha de notificación al usuario';
$string['privacy:metadata:tool_mulib_muform_wizard:tableexplanation'] = 'Estado temporal de los formularios de varias páginas, eliminado al finalizarlos, al cancelarlos o al cabo de un día';
$string['privacy:metadata:tool_mulib_notification_user:tableexplanation'] = 'Registro de notificaciones enviadas a los usuarios';
$string['privacy:metadata:userid'] = 'Usuario';
$string['purgedownloadedlang'] = 'Usar las traducciones incluidas en MuTMS';
$string['purgedownloadedlang_desc'] = 'Las traducciones incluidas en los plugins de MuTMS se revisan junto con el código. Si se habilita, las cadenas de MuTMS se eliminan de los paquetes de idioma descargados, porque sustituirían a las traducciones incluidas. Utilice la personalización del idioma para cambiar cadenas concretas.';
$string['task_context_map_cron'] = 'Cron del mapa de contextos';
$string['task_lang_cleanup'] = 'Purgar las traducciones descargadas que entran en conflicto con las traducciones oficiales de MuTMS';
$string['toomanyrecords'] = 'Se han encontrado demasiados registros';
