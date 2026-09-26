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

use tool_mulib\local\extdb\query_manager;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Delete query form, current data is the query record.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class query_delete extends form {
    #[\Override]
    protected function definition(): void {
        $current = $this->get_current_data();
        $qman = \core\di::get(query_manager::class);
        $classname = $qman->get_class($current['component'], $current['type']);

        $this->add(new hidden('id'));
        $this->add(new info('querycomponent', get_string('plugin'), get_string('pluginname', $current['component'])));
        $typename = $classname ? $classname::get_name() : get_string('error');
        $this->add(new info('querytype', get_string('extdb_query_type', 'tool_mulib'), $typename));
        $this->add(new info('name', get_string('name')));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('extdb_query_delete', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
