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

/**
 * Create a new external database query.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\local\extdb\form\query_create;
use tool_mulib\local\extdb\form\query_create_select;
use tool_mulib\local\extdb\query;
use tool_mulib\muform\handler;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var moodle_database $DB */

require(__DIR__ . '/../../../../config.php');

$component = optional_param('component', '', PARAM_ALPHANUMEXT);
$type = optional_param('type', '', PARAM_ALPHANUMEXT);

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$pageurl = new \core\url('/admin/tool/mulib/extdb/query_create.php', ['component' => $component, 'type' => $type]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading(get_string('extdb_query_create', 'tool_mulib'));
$PAGE->set_title(get_string('extdb_query_create', 'tool_mulib'));

$handler = handler::from_request();

$returnurl = new \core\url('/admin/tool/mulib/extdb/queries.php');

['component' => $component, 'type' => $type] = query_create_select::decode_type_option($component, $type);

if (!$type) {
    // First step: pick the type, the second step is the same page with the type in the URL.
    $form = new query_create_select($pageurl, []);
    if ($form->is_cancelled()) {
        $handler->cancelled($returnurl);
    }
    if ($data = $form->get_data()) {
        ['component' => $component, 'type' => $type] = query_create_select::decode_type_option('', $data->type);
        $nexturl = new \core\url($pageurl, ['component' => $component, 'type' => $type]);
        if ($handler->is_dialog()) {
            // The dialog continues with the second step, the page gets it from the next URL.
            $handler->render(function (core_renderer $output) use ($nexturl): string {
                $form = new query_create($nexturl, ['component' => $nexturl->param('component'), 'type' => $nexturl->param('type'), 'contextid' => context_system::instance()->id]);
                return $form->render($output);
            });
        }
        redirect($nexturl);
    }
} else {
    $current = ['component' => $component, 'type' => $type, 'contextid' => $context->id];
    $form = new query_create($pageurl, $current);
    if ($form->is_cancelled()) {
        $handler->cancelled($returnurl);
    }
    if ($data = $form->get_data()) {
        query::create($data);
        $handler->submitted($returnurl);
    }
}

$handler->render($form);
