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

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkboxes;
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

/**
 * Notification import, the second step picks the notifications; extra data holds
 * instanceid, component, frominstance and the manager class name.
 *
 * @package     tool_mulib
 * @copyright   2024 Open LMS
 * @copyright   2025 Petr Skoda
 * @author      Farhan Karmali
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_import_confirmation extends form {
    #[\Override]
    protected function definition(): void {
        global $DB;

        $extra = $this->get_extra_data();
        /** @var class-string<\tool_mulib\local\notification\manager> $manager */
        $manager = $extra['manager'];

        $fromname = $manager::get_instance_name($extra['frominstance']);
        $this->add(new info('instance', get_string('notification_import_from', 'tool_mulib'), $fromname));

        $types = $manager::get_all_types();
        $notifications = $DB->get_records(
            'tool_mulib_notification',
            ['instanceid' => $extra['frominstance'], 'component' => $extra['component'], 'enabled' => 1],
            'id ASC'
        );
        $options = [];
        foreach ($notifications as $notification) {
            if (!isset($types[$notification->notificationtype])) {
                continue;
            }
            $classname = $types[$notification->notificationtype];
            $options[(string)$notification->id] = $classname::get_name();
        }
        $notificationids = (new checkboxes('notificationids', get_string('notification_types', 'tool_mulib'), $options))
            ->set_required(true);
        $this->add($notificationids);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('notification_import', 'tool_mulib')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
