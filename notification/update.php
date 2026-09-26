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
 * Update existing notification.
 *
 * @package     tool_mulib
 * @copyright   2022 Open LMS
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\local\notification\util;
use tool_mulib\muform\handler;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$notification = $DB->get_record('tool_mulib_notification', ['id' => $id], '*', MUST_EXIST);
$manager = util::get_manager_classname($notification->component);
if (!$manager) {
    throw new invalid_parameter_exception('Invalid notification component');
}

$returnurl = $manager::get_instance_management_url($notification->instanceid);

if (!$manager::can_manage($notification->instanceid)) {
    redirect($returnurl);
}

$context = $manager::get_instance_context($notification->instanceid);
$pageurl = new \core\url('/admin/tool/mulib/notification/update.php', ['id' => $notification->id]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);

$classname = $manager::get_classname($notification->notificationtype);
$current = (array)$notification;
if ($notification->custom && $notification->customjson) {
    $decoded = json_decode($notification->customjson, true);
    $current['subject'] = $decoded['subject'] ?? '';
    $current['body'] = $decoded['body'] ?? '';
} else {
    $current['subject'] = $classname::get_default_subject();
    $current['body'] = str_replace('{$a->', '{$a-&gt;', markdown_to_html($classname::get_default_body()));
}
$current['bodyformat'] = FORMAT_HTML;

$handler = handler::from_request();
$form = new \tool_mulib\local\form\notification_update($pageurl, $current, ['manager' => $manager]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}
if ($data = $form->get_data()) {
    $notification = util::notification_update((array)$data);
    $returnurl = new \core\url('/admin/tool/mulib/notification/view.php', ['id' => $notification->id]);
    $handler->submitted($returnurl);
}

$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('notification_update', 'tool_mulib'));
$PAGE->set_heading(get_string('notification_update', 'tool_mulib'));
$handler->render($form);
