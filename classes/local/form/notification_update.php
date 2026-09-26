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
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\hidden;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;

/**
 * Update notification form, current data is the notification record with subject and body
 * added, extra data holds the manager class name.
 *
 * @package     tool_mulib
 * @copyright   2023 Open LMS
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_update extends form {
    #[\Override]
    protected function definition(): void {
        $notification = (object)$this->get_current_data();
        /** @var class-string<\tool_mulib\local\notification\manager> $manager */
        $manager = $this->get_extra_data()['manager'];
        /** @var class-string<\tool_mulib\local\notification\notificationtype> $classname */
        $classname = $manager::get_classname($notification->notificationtype);

        $this->add(new hidden('id'));
        $instancename = $manager::get_instance_name($notification->instanceid);
        $this->add(new info('instance', get_string('notification_instance', 'tool_mulib'), $instancename));
        $this->add(new info('typename', get_string('notification_type', 'tool_mulib'), $classname::get_name()));

        if (mulib::is_murelatio_active() && $classname::is_cc_supervisor_supported()) {
            $options = $manager::get_supervisor_options($notification->instanceid, $notification->supervisorframeworkid);
            $this->add(new select('supervisorframeworkid', get_string('notification_cc_supervisor', 'tool_mulib'), $options));
        }

        $this->add(new checkbox('enabled', get_string('notification_enabled', 'tool_mulib')));
        // Note: Add aux data support here.
        $this->add(new checkbox('custom', get_string('notification_custom', 'tool_mulib')));
        $this->add(new text('subject', get_string('notification_subject', 'tool_mulib'), ['type' => 'rawtext', 'width' => 'full']));
        $this->add(new editor('body', get_string('notification_body', 'tool_mulib')));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('notification_update', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');

        $dm = $this->get_display_manager();
        $dm->hide_if('subject', 'custom', 'notchecked');
        $dm->hide_if('body', 'custom', 'notchecked');
    }
}
