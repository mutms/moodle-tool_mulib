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

/**
 * Behat fixture page for the muform customfields element with course custom fields.
 *
 * Query parameter "course" is the course shortname, submitted values are saved
 * to the course and printed.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_course\customfield\course_handler;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\customfields;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_once(__DIR__ . '/muform_fixture_lib.php');
require_admin();

/**
 * Fixture form with course custom fields.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_fixture_customfields_form extends form {
    #[\Override]
    protected function definition(): void {
        $course = $this->get_extra_data()['course'];

        $this->add(new customfields('customfields', course_handler::create(), $course->id));

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}

$shortname = required_param('course', PARAM_RAW);
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);

$pageurl = new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php', ['course' => $shortname]);
$PAGE->set_url($pageurl);
$PAGE->set_context(\core\context\system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('muform customfields');
$PAGE->set_heading('muform customfields');

$form = new tool_mulib_muform_fixture_customfields_form($pageurl, [], ['course' => $course]);
tool_mulib_muform_fixture_finish($form, $pageurl, function (stdClass $data, form $form) use ($course): void {
    $form->get_element('customfields')->save($course->id);
});
