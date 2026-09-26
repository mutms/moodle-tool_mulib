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

namespace tool_mulib\local\form;

use tool_mulib\local\mulib;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\checkboxes;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Add notifications form, extra data holds instanceid and the manager class name.
 *
 * @package     tool_mulib
 * @copyright   2023 Open LMS
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_create extends form {
    #[\Override]
    protected function definition(): void {
        $extra = $this->get_extra_data();
        $instanceid = $extra['instanceid'];
        /** @var class-string<\tool_mulib\local\notification\manager> $manager */
        $manager = $extra['manager'];

        $this->add(new info('instance', get_string('notification_instance', 'tool_mulib'), $manager::get_instance_name($instanceid)));

        $showcc = false;
        $types = $manager::get_candidate_types($instanceid);
        foreach ($types as $type => $typename) {
            $classname = $manager::get_classname($type);
            if ($classname && $classname::is_cc_supervisor_supported()) {
                $showcc = true;
            }
        }
        $typeselement = (new checkboxes('types', get_string('notification_types', 'tool_mulib'), $types))
            ->set_required(true);
        $this->add($typeselement);

        if ($showcc && mulib::is_murelatio_active()) {
            $options = $manager::get_supervisor_options($instanceid, null);
            $this->add(new select('supervisorframeworkid', get_string('notification_cc_supervisor', 'tool_mulib'), $options));
        }

        $enabled = (new checkbox('enabled', get_string('notification_enabled', 'tool_mulib')))
            ->set_default(1);
        $this->add($enabled);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('notification_create', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
