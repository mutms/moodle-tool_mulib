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

namespace tool_mulib\local\extdb\form;

use core\param;
use tool_mulib\local\extdb\query;
use tool_mulib\local\extdb\query_manager;
use tool_mulib\muform\autocomplete\extdb_query_context;
use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\element\textarea;
use tool_mulib\muform\form;

/**
 * Create a new query form, current data holds component, type and contextid.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class query_create extends form {
    #[\Override]
    protected function definition(): void {
        global $DB;

        $current = $this->get_current_data();
        $qman = \core\di::get(query_manager::class);
        $classname = $qman->get_class($current['component'], $current['type']);

        $this->add(new hidden('component'));
        $this->add(new hidden('type'));
        $this->add(new info('querycomponent', get_string('plugin'), get_string('pluginname', $current['component'])));
        $typename = $classname ? $classname::get_name() : get_string('error');
        $this->add(new info('querytype', get_string('extdb_query_type', 'tool_mulib'), $typename));

        $contextid = (new autocomplete('contextid', get_string('category'), new extdb_query_context((int)$current['contextid'])))
            ->set_required(true);
        $this->add($contextid);

        $servers = $DB->get_records_menu('tool_mulib_extdb_server', [], 'name ASC', 'id, name');
        $serverid = (new select('serverid', get_string('extdb_server', 'tool_mulib'), ['' => get_string('choosedots')] + $servers))
            ->set_required(true);
        $this->add($serverid);

        $name = (new text('name', get_string('name'), ['maxlength' => 255, 'width' => 'medium']))
            ->set_required(true);
        $this->add($name);

        $sqlquery = (new textarea('sqlquery', get_string('extdb_query_sqlquery', 'tool_mulib'), ['type' => 'rawtext', 'rows' => 5]))
            ->set_required(true);
        $this->add($sqlquery);

        $post = $this->get_post_data();
        if (!empty($post['check'])) {
            $status = query::get_check_status_html($post, $classname);
            $this->add(new inforawhtml('status', get_string('extdb_query_status', 'tool_mulib'), $status));
        }

        $this->add(new inforawhtml('sqlhelp', '', format_text($classname::get_query_help(), FORMAT_MARKDOWN)));
        $this->add(new textarea('note', get_string('note', 'core_notes'), ['rows' => 2]));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('extdb_query_create', 'tool_mulib')), 'buttons');
        $this->add(new reload('check', get_string('extdb_query_check', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        global $DB;

        if ($DB->record_exists_select('tool_mulib_extdb_query', "LOWER(name) = LOWER(?)", [trim($data['name'])])) {
            $allerrors['name'][] = get_string('error');
        }
    }
}
