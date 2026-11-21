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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_mulib\local\setting;

use tool_mulib\local\profilefield_cache;
use tool_mulib\output\muform\dialog\link;

/**
 * Admin setting showing table of cached custom profile fields with actions.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilefields extends \core_admin\setting {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('tool_mulib_profilefields', get_string('profilefields', 'tool_mulib'), get_string('profilefields_desc', 'tool_mulib'), '');
    }

    #[\Override]
    public function get_setting() {
        return true;
    }

    #[\Override]
    public function get_defaultsetting() {
        return true;
    }

    #[\Override]
    public function write_setting($data) {
        return '';
    }

    #[\Override]
    public function is_related($query) {
        global $DB;

        if (parent::is_related($query)) {
            return true;
        }
        $query = \core_text::strtolower($query);
        foreach ($DB->get_records('user_info_field', [], '', 'id, shortname, name') as $field) {
            $text = \core_text::strtolower($field->shortname . ' ' . $field->name);
            if (str_contains($text, $query)) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function output_html($data, $query = '') {
        global $DB, $OUTPUT;

        $syscontext = \context_system::instance();

        $sql = "SELECT f.*, c.name AS categoryname
                  FROM {user_info_field} f
                  JOIN {user_info_category} c ON c.id = f.categoryid
              ORDER BY c.sortorder, f.sortorder";
        $fields = $DB->get_records_sql($sql);

        $cached = profilefield_cache::get_fields();
        $required = profilefield_cache::get_required_fieldids();
        $format = get_string('strftimedatetimeshort', 'core_langconfig');

        $sql = "SELECT fieldid, component, area, COUNT('x') AS items
                  FROM {tool_mulib_profilefield_reference}
              GROUP BY fieldid, component, area
              ORDER BY fieldid, component, area";
        $usages = [];
        foreach ($DB->get_records_sql($sql) as $reference) {
            if ($reference->component === 'core' && $reference->area === 'showuseridentity') {
                $usages[$reference->fieldid][] = get_string('profilefields_usage_identity', 'tool_mulib');
                continue;
            }
            $a = (object)[
                'component' => get_string_manager()->string_exists('pluginname', $reference->component)
                    ? get_string('pluginname', $reference->component) : $reference->component,
                'area' => $reference->area,
                'items' => $reference->items,
            ];
            $usages[$reference->fieldid][] = get_string('profilefields_usage_reference', 'tool_mulib', $a);
        }

        $table = new \html_table();
        $table->head = [
            get_string('name'),
            get_string('profilecategory', 'admin'),
            get_string('profilefields_type', 'tool_mulib'),
            get_string('status'),
            get_string('profilefields_usage', 'tool_mulib'),
            get_string('actions'),
        ];
        $table->attributes['class'] = 'generaltable';
        $table->data = [];

        foreach ($fields as $field) {
            $fieldid = (int)$field->id;
            $supported = (profilefield_cache::get_handler($field) !== null);
            if (!$supported) {
                $status = get_string('profilefields_status_unsupported', 'tool_mulib');
            } else if (!array_key_exists($fieldid, $cached)) {
                $status = get_string('profilefields_status_notcached', 'tool_mulib');
            } else if (!$cached[$fieldid]) {
                $status = get_string('profilefields_status_populating', 'tool_mulib');
            } else {
                $status = get_string('profilefields_status_ready', 'tool_mulib', userdate($cached[$fieldid], $format));
            }

            $actions = '';
            if ($supported && !array_key_exists($fieldid, $cached)) {
                $url = new \core\url('/admin/tool/mulib/profilefields/cache.php', ['fieldid' => $fieldid]);
                $actions = $OUTPUT->render(new link($url, get_string('profilefields_cache', 'tool_mulib'), 'i/reload'));
            } else if (array_key_exists($fieldid, $cached) && !in_array($fieldid, $required, true)) {
                $url = new \core\url('/admin/tool/mulib/profilefields/uncache.php', ['fieldid' => $fieldid]);
                $actions = $OUTPUT->render(new link($url, get_string('profilefields_uncache', 'tool_mulib'), 'i/delete'));
            }

            $table->data[] = [
                format_string($field->name, true, ['context' => $syscontext]),
                format_string($field->categoryname, true, ['context' => $syscontext]),
                get_string('pluginname', 'profilefield_' . $field->datatype),
                $status,
                implode('<br />', array_map('s', $usages[$fieldid] ?? [])),
                $actions,
            ];
        }

        if (!$table->data) {
            $content = $OUTPUT->notification(get_string('profilefields_none', 'tool_mulib'), 'info', false);
        } else {
            $content = \html_writer::table($table);
        }

        return highlight($query, $OUTPUT->heading($this->visiblename, 3) . \html_writer::tag('p', $this->description) . $content);
    }
}
