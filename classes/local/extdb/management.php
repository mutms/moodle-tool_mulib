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

namespace tool_mulib\local\extdb;

use tool_mulib\output\muform\dialog\icon;
use core\url;

/**
 * External database management tables, servers and queries are few, plain tables are enough.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class management {
    /**
     * Returns table of external database servers.
     *
     * @return \html_table
     */
    public static function get_servers_table(): \html_table {
        global $DB, $OUTPUT;

        $table = new \html_table();
        $table->id = 'tool_mulib-extdb-servers';
        $table->attributes['class'] = 'table table-hover align-middle mb-0 tool_mulib-table';
        $table->head = [
            get_string('name'),
            get_string('extdb_server_dsn', 'tool_mulib'),
            get_string('extdb_server_dbuser', 'tool_mulib'),
            get_string('extdb_server_dboptions', 'tool_mulib'),
            get_string('note', 'core_notes'),
            get_string('actions'),
        ];
        $table->colclasses = [5 => 'tool_mulib-table-actions'];
        $canmanage = has_capability('moodle/site:config', \core\context\system::instance());
        $used = $DB->get_records_sql_menu("SELECT DISTINCT serverid, 1 FROM {tool_mulib_extdb_query}");
        foreach ($DB->get_records('tool_mulib_extdb_server', [], 'name ASC, id ASC') as $server) {
            $actions = [];
            if ($canmanage) {
                $url = new url('/admin/tool/mulib/extdb/server_update.php', ['id' => $server->id]);
                $icon = new icon($url, get_string('edit'), 't/edit');
                $icon->set_modal_title(get_string('extdb_server_update', 'tool_mulib'));
                $actions[] = $OUTPUT->render($icon);
                if (!isset($used[$server->id])) {
                    $url = new url('/admin/tool/mulib/extdb/server_delete.php', ['id' => $server->id]);
                    $icon = new icon($url, get_string('delete'), 't/delete');
                    $icon->set_modal_title(get_string('extdb_server_delete', 'tool_mulib'));
                    $icon->set_form_size('sm');
                    $actions[] = $OUTPUT->render($icon);
                }
            }
            $table->data[] = [
                s($server->name),
                s($server->dsn),
                s($server->dbuser),
                s($server->dboptions),
                s($server->note),
                implode(' ', $actions),
            ];
        }
        return $table;
    }

    /**
     * Returns table of external database queries.
     *
     * @return \html_table
     */
    public static function get_queries_table(): \html_table {
        global $DB, $OUTPUT;

        $qman = \core\di::get(query_manager::class);
        $table = new \html_table();
        $table->id = 'tool_mulib-extdb-queries';
        $table->attributes['class'] = 'table table-hover align-middle mb-0 tool_mulib-table';
        $table->head = [
            get_string('name'),
            get_string('category'),
            get_string('extdb_query_type', 'tool_mulib'),
            get_string('extdb_server', 'tool_mulib'),
            get_string('extdb_query_sqlquery', 'tool_mulib'),
            get_string('note', 'core_notes'),
            get_string('actions'),
        ];
        $table->colclasses = [6 => 'tool_mulib-table-actions'];
        $canmanage = has_capability('moodle/site:config', \core\context\system::instance());
        $sql = "SELECT q.*, s.name AS servername
                  FROM {tool_mulib_extdb_query} q
                  JOIN {tool_mulib_extdb_server} s ON s.id = q.serverid
              ORDER BY q.name ASC, q.id ASC";
        foreach ($DB->get_records_sql($sql) as $query) {
            $classname = $qman->get_class((string)$query->component, (string)$query->type);
            $context = \core\context::instance_by_id($query->contextid, IGNORE_MISSING);
            $actions = [];
            if ($canmanage) {
                $url = new url('/admin/tool/mulib/extdb/query_update.php', ['id' => $query->id]);
                $icon = new icon($url, get_string('edit'), 't/edit');
                $icon->set_modal_title(get_string('extdb_query_update', 'tool_mulib'));
                $icon->set_form_size('xl');
                $actions[] = $OUTPUT->render($icon);
                if (!$classname || !$classname::is_query_used((int)$query->id)) {
                    $url = new url('/admin/tool/mulib/extdb/query_delete.php', ['id' => $query->id]);
                    $icon = new icon($url, get_string('delete'), 't/delete');
                    $icon->set_modal_title(get_string('extdb_query_delete', 'tool_mulib'));
                    $icon->set_form_size('sm');
                    $actions[] = $OUTPUT->render($icon);
                }
            }
            $table->data[] = [
                s($query->name),
                $context ? s($context->get_context_name(false)) : '',
                $classname ? s($classname::get_name()) : s($query->type),
                s($query->servername),
                \html_writer::tag('pre', s($query->sqlquery)),
                s($query->note),
                implode(' ', $actions),
            ];
        }
        return $table;
    }

    /**
     * Render management table in a flat card, empty tables say there is nothing to display.
     *
     * @param \html_table $table
     * @return string
     */
    public static function render_table(\html_table $table): string {
        if (!$table->data) {
            $cell = new \html_table_cell(get_string('nothingtodisplay'));
            $cell->colspan = count($table->head);
            $cell->attributes['class'] = 'text-center text-body-secondary py-4';
            $table->data[] = new \html_table_row([$cell]);
        }
        return \html_writer::div(\html_writer::div(\html_writer::table($table), 'table-responsive'), 'card tool_mulib-table-card');
    }
}
