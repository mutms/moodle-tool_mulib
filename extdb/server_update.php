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
 * Update external database server.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\local\extdb\server;
use tool_mulib\muform\handler;

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var moodle_database $DB */

require(__DIR__ . '/../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$pageurl = new \core\url('/admin/tool/mulib/extdb/server_update.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading(get_string('extdb_server_update', 'tool_mulib'));
$PAGE->set_title(get_string('extdb_server_update', 'tool_mulib'));

$handler = handler::from_request();

$returnurl = new \core\url('/admin/tool/mulib/extdb/servers.php');

$server = $DB->get_record('tool_mulib_extdb_server', ['id' => $id], '*', MUST_EXIST);
$current = (array)$server;
// The secret element only needs to know whether a password exists.
$current['dbpass'] = ($server->dbpass !== null && $server->dbpass !== '');

$form = new \tool_mulib\local\extdb\form\server_update($pageurl, $current, ['server' => $server]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}
if ($data = $form->get_data()) {
    server::update($data);
    $handler->submitted($returnurl);
}

$handler->render($form);
