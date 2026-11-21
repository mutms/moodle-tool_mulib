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
 * Add custom profile field to cache.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\local\profilefield_cache;
use tool_mulib\muform\handler;

/** @var moodle_page $PAGE */
/** @var moodle_database $DB */

require(__DIR__ . '/../../../../config.php');

$fieldid = required_param('fieldid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$pageurl = new \core\url('/admin/tool/mulib/profilefields/cache.php', ['fieldid' => $fieldid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading(get_string('profilefields_cache', 'tool_mulib'));
$PAGE->set_title(get_string('profilefields_cache', 'tool_mulib'));

$handler = handler::from_request();

$returnurl = new \core\url('/admin/settings.php', ['section' => 'tool_mulib_profilefields']);

$field = $DB->get_record('user_info_field', ['id' => $fieldid], '*', MUST_EXIST);
if (!profilefield_cache::get_handler($field) || array_key_exists((int)$field->id, profilefield_cache::get_fields())) {
    redirect($returnurl);
}

$form = new \tool_mulib\local\form\profilefield_cache($pageurl, ['fieldid' => $field->id, 'name' => $field->name]);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}
if ($form->get_data()) {
    profilefield_cache::add_field($field->id);
    profilefield_cache::queue_populate($field->id);
    $handler->submitted($returnurl);
}

$handler->render($form);
