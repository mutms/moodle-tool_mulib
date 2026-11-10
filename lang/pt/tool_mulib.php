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
 * Strings for component 'tool_mulib', language 'pt'.
 *
 * NOTE: This Portuguese translation was produced automatically by an AI
 * language model and has not yet been reviewed by a human translator. Corrections
 * are welcome.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['environment_sqlsrv_incompatible'] = 'O MS SQL Server não é compatível com os módulos MuTMS.';
$string['environment_windows_unsupported'] = 'O MS Windows não é suportado como servidor web para utilização com os módulos MuTMS.';
$string['extdb'] = 'Bases de dados externas';
$string['extdb_exception'] = 'Erro na comunicação com a base de dados externa';
$string['extdb_queries'] = 'Consultas a bases de dados externas';
$string['extdb_query'] = 'Consulta a base de dados externa';
$string['extdb_query_check'] = 'Verificar consulta';
$string['extdb_query_create'] = 'Adicionar consulta';
$string['extdb_query_delete'] = 'Apagar consulta';
$string['extdb_query_sqlquery'] = 'Consulta SQL';
$string['extdb_query_status'] = 'Estado da consulta';
$string['extdb_query_type'] = 'Tipo de consulta';
$string['extdb_query_update'] = 'Editar consulta';
$string['extdb_server'] = 'Servidor de base de dados externa';
$string['extdb_server_check'] = 'Verificar ligação';
$string['extdb_server_create'] = 'Adicionar servidor';
$string['extdb_server_dboptions'] = 'Opções PDO (JSON)';
$string['extdb_server_dboptions_help'] = 'Opcionalmente, indique opções adicionais de ligação do controlador de base de dados PDO em formato JSON, utilizando números em vez de constantes PHP.';
$string['extdb_server_dbpass'] = 'Senha da base de dados';
$string['extdb_server_dbpass_change'] = 'Utilizar uma senha diferente';
$string['extdb_server_dbuser'] = 'Utilizador da base de dados';
$string['extdb_server_delete'] = 'Apagar servidor';
$string['extdb_server_dsn'] = 'PDO DSN';
$string['extdb_server_dsn_help'] = 'O Data Source Name (DSN) define uma ligação ao servidor de base de dados. Consulte a [documentação da extensão PHP PDO](https://www.php.net/manual/en/pdo.connections.php) para obter informações detalhadas.';
$string['extdb_server_extensions'] = 'Extensões PDO disponíveis: {$a}';
$string['extdb_server_status'] = 'Estado da ligação';
$string['extdb_server_update'] = 'Editar servidor';
$string['extdb_servers'] = 'Servidores de bases de dados externas';
$string['filterbar_add'] = 'Adicionar filtro';
$string['filterbar_advanced'] = 'Filtragem avançada';
$string['filterbar_apply'] = 'Aplicar';
$string['filterbar_rangefrom'] = 'desde {$a}';
$string['filterbar_rangeto'] = 'até {$a}';
$string['filterbar_remove'] = 'Remover filtro';
$string['filterbar_useadvanced'] = 'Utilize a filtragem avançada para alterar este filtro.';
$string['import_delimiter'] = 'Delimitador CSV';
$string['import_delimiter_auto'] = 'Detetar automaticamente';
$string['import_encoding'] = 'Codificação';
$string['import_error_empty'] = 'Não foram encontradas linhas utilizáveis.';
$string['import_error_parse'] = 'Não é possível ler os dados: {$a}';
$string['import_error_source'] = 'Cole os dados ou carregue um ficheiro.';
$string['import_file'] = 'Ficheiro de origem';
$string['import_file_or'] = 'Ou carregue um ficheiro';
$string['import_paste'] = 'Colar dados';
$string['import_paste_or'] = 'Ou cole dados CSV';
$string['lang_settings'] = 'Traduções do MuTMS';
$string['lang_string_a'] = '{$a}';
$string['muform_apply'] = 'Aplicar';
$string['muform_back'] = 'Voltar';
$string['muform_clear'] = 'Limpar';
$string['muform_clearselection'] = 'Limpar seleção';
$string['muform_clearvalue'] = 'Limpar valor atual';
$string['muform_close'] = 'Fechar janela';
$string['muform_datetimeformat'] = 'd/m/Y H:i';
$string['muform_datetimeinvalid'] = 'Data e hora inválidas';
$string['muform_datetimerangeends'] = '{$a->duration}, termina em {$a->end}';
$string['muform_datetimerangeinvalid'] = 'Intervalo de data e hora inválido';
$string['muform_datetimerangetoolong'] = 'A duração máxima é {$a}';
$string['muform_days'] = 'dias';
$string['muform_duration'] = 'Duração';
$string['muform_editorformat'] = 'Formato do texto';
$string['muform_hide'] = 'Ocultar';
$string['muform_hour'] = 'Hora';
$string['muform_hours'] = 'horas';
$string['muform_loading'] = 'A carregar o formulário';
$string['muform_minute'] = 'Minuto';
$string['muform_minutes'] = 'minutos';
$string['muform_monthnext'] = 'Mês seguinte';
$string['muform_monthprev'] = 'Mês anterior';
$string['muform_months'] = 'meses';
$string['muform_nofiles'] = 'Sem ficheiros';
$string['muform_noresults'] = 'Sem resultados';
$string['muform_nosubdirs'] = 'Não são permitidas subpastas';
$string['muform_notset'] = 'Não definido';
$string['muform_numhour'] = '{$a} hora';
$string['muform_numminute'] = '{$a} minuto';
$string['muform_numsecond'] = '{$a} segundo';
$string['muform_pickdatetime'] = 'Escolher data e hora';
$string['muform_pickdatetimerange'] = 'Escolher intervalo de data e hora';
$string['muform_rangeend'] = 'Fim';
$string['muform_rangestart'] = 'Início';
$string['muform_remove'] = 'Remover: {$a}';
$string['muform_search'] = 'Pesquisar...';
$string['muform_searching'] = 'A pesquisar...';
$string['muform_seconds'] = 'segundos';
$string['muform_show'] = 'Mostrar';
$string['muform_steps'] = 'Passos';
$string['muform_stepskipped'] = 'ignorado';
$string['muform_tagnotstandard'] = 'Só podem ser utilizadas palavras-chave padrão';
$string['muform_timezone'] = 'Fuso horário: {$a}';
$string['muform_today'] = 'Hoje';
$string['muform_toomanyfiles'] = 'O número máximo de ficheiros é {$a}';
$string['muform_toomanyresults'] = 'Demasiados resultados, continue a escrever';
$string['muform_typenewvalue'] = 'Escreva um novo valor...';
$string['muform_unsafehtml'] = 'HTML não seguro permitido, o conteúdo é guardado sem limpeza';
$string['muform_usersuspended'] = 'Utilizador suspenso';
$string['muform_weeks'] = 'semanas';
$string['muform_wizard_expired'] = 'A sessão do formulário expirou, por favor comece de novo.';
$string['muform_yearnext'] = 'Ano seguinte';
$string['muform_yearprev'] = 'Ano anterior';
$string['muform_years'] = 'anos';
$string['mulib:useextdb'] = 'Utilizar consultas externas';
$string['notification'] = 'Notificação';
$string['notification_body'] = 'Mensagem';
$string['notification_cc_supervisor'] = 'Enviar cópia ao supervisor';
$string['notification_create'] = 'Adicionar notificação';
$string['notification_custom'] = 'Personalizada';
$string['notification_delete'] = 'Apagar notificação';
$string['notification_delete_confirm'] = 'Se a instância de notificação for apagada e adicionada novamente mais tarde, os utilizadores poderão ser notificados repetidamente.';
$string['notification_enabled'] = 'Ativada';
$string['notification_extramenu'] = 'Ações de notificação';
$string['notification_import'] = 'Importar notificações';
$string['notification_import_from'] = 'Importar de';
$string['notification_instance'] = 'Notificação para';
$string['notification_subject'] = 'Assunto';
$string['notification_type'] = 'Tipo de notificação';
$string['notification_types'] = 'Tipos de notificação';
$string['notification_update'] = 'Editar notificação';
$string['notification_view'] = 'Detalhes da notificação';
$string['notifications'] = 'Notificações';
$string['notset'] = 'Não definido';
$string['pluginname'] = 'Biblioteca de ferramentas adicionais';
$string['privacy:metadata:jsondata'] = 'Dados temporários introduzidos num formulário de várias páginas';
$string['privacy:metadata:notificationid'] = 'ID da notificação';
$string['privacy:metadata:timecreated'] = 'Data de criação';
$string['privacy:metadata:timenotified'] = 'Data em que o utilizador foi notificado';
$string['privacy:metadata:tool_mulib_muform_wizard:tableexplanation'] = 'Estado temporário de formulários de várias páginas, apagado quando são concluídos, cancelados ou ao fim de um dia';
$string['privacy:metadata:tool_mulib_notification_user:tableexplanation'] = 'Registo das notificações enviadas aos utilizadores';
$string['privacy:metadata:userid'] = 'Utilizador';
$string['purgedownloadedlang'] = 'Utilizar as traduções fornecidas com o MuTMS';
$string['purgedownloadedlang_desc'] = 'As traduções fornecidas com os módulos MuTMS são revistas juntamente com o código. Se esta opção estiver ativada, as strings do MuTMS são apagadas dos pacotes linguísticos descarregados, porque estas se sobreporiam às traduções fornecidas. Utilize a personalização do idioma para alterar strings individuais.';
$string['task_context_map_cron'] = 'Cron do mapa de contextos';
$string['task_lang_cleanup'] = 'Apagar as traduções descarregadas que entram em conflito com as traduções oficiais do MuTMS';
$string['toomanyrecords'] = 'Demasiados registos encontrados';
