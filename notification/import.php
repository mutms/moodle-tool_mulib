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
 * Import notification.
 *
 * @package     tool_mulib
 * @copyright   2024 Open LMS (https://www.openlms.net/)
 * @copyright   2025 Petr Skoda
 * @author      Farhan Karmali
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */

use tool_mulib\local\notification\util;
use tool_mulib\muform\handler;

require('../../../../config.php');

$component = required_param('component', PARAM_COMPONENT);
$instanceid = required_param('instanceid', PARAM_INT);
$frominstance = optional_param('frominstance', 0, PARAM_INT);

require_login();

$manager = util::get_manager_classname($component);
if (!$manager) {
    throw new invalid_parameter_exception('Invalid notification component');
}

$returnurl = $manager::get_instance_management_url($instanceid);

if (!$manager::can_manage($instanceid) || !$manager::is_import_supported()) {
    redirect($returnurl);
}

$context = $manager::get_instance_context($instanceid);
$pageurl = new \core\url('/admin/tool/mulib/notification/import.php', ['component' => $component, 'instanceid' => $instanceid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);

$handler = handler::from_request();
$title = get_string('notification_import', 'tool_mulib');

if (!$manager::validate_import_frominstance($instanceid, $frominstance)) {
    // First step: pick the source, the second step is the same page with frominstance in the URL.
    $form = new \tool_mulib\local\form\notification_import($pageurl, [], ['instanceid' => $instanceid, 'manager' => $manager]);
    if ($form->is_cancelled()) {
        $handler->cancelled($returnurl);
    }
    if ($data = $form->get_data()) {
        $nexturl = new \core\url($pageurl, ['frominstance' => $data->frominstance]);
        if ($handler->is_dialog()) {
            // The dialog continues with the second step, the page gets it from the next URL.
            $extra = [
                'instanceid' => $instanceid,
                'component' => $component,
                'frominstance' => (int)$data->frominstance,
                'manager' => $manager,
            ];
            $handler->render(function (core_renderer $output) use ($nexturl, $extra): string {
                $form = new \tool_mulib\local\form\notification_import_confirmation($nexturl, [], $extra);
                return $form->render($output);
            }, $title);
        }
        redirect($nexturl);
    }
} else {
    $pageurl->param('frominstance', $frominstance);
    $extra = ['instanceid' => $instanceid, 'component' => $component, 'frominstance' => $frominstance, 'manager' => $manager];
    $form = new \tool_mulib\local\form\notification_import_confirmation($pageurl, [], $extra);
    if ($form->is_cancelled()) {
        $handler->cancelled($returnurl);
    }
    if ($data = $form->get_data()) {
        $data->component = $component;
        $data->instanceid = $instanceid;
        $data->frominstance = $frominstance;
        util::notification_import($data, $data->notificationids);
        $handler->submitted($returnurl);
    }
}

$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$handler->render($form);
