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

namespace tool_mulib\local\form;

use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Notification import, the first step selects the source instance; extra data holds
 * instanceid and the manager class name.
 *
 * @package     tool_mulib
 * @copyright   2024 Open LMS
 * @copyright   2025 Petr Skoda
 * @author      Farhan Karmali
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_import extends form {
    #[\Override]
    protected function definition(): void {
        $extra = $this->get_extra_data();
        $instanceid = $extra['instanceid'];
        /** @var class-string<\tool_mulib\local\notification\manager> $manager */
        $manager = $extra['manager'];

        $instancename = $manager::get_instance_name($instanceid);
        $this->add(new info('instance', get_string('notification_instance', 'tool_mulib'), $instancename));

        $source = $manager::get_import_frominstance_source($instanceid);
        $frominstance = (new autocomplete('frominstance', get_string('notification_import_from', 'tool_mulib'), $source))
            ->set_required(true);
        $this->add($frominstance);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('continue')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        $extra = $this->get_extra_data();
        $manager = $extra['manager'];
        if (!$manager::validate_import_frominstance($extra['instanceid'], (int)$data['frominstance'])) {
            $allerrors['frominstance'][] = get_string('error');
        }
    }
}
