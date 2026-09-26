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
 * Behat fixture page for the muform tags element.
 *
 * Tags of course "C1" are edited and saved. Extra query parameter "standard" sets the standard tags
 * mode of the course tag area: 0 both, 1 standard only, 2 hide standard.
 * See muform_fixture_lib.php for the other query parameters and the printed output.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\tags;
use tool_mulib\muform\form;
use tool_mulib\muform\tagarea\course;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_once(__DIR__ . '/muform_fixture_lib.php');
require_admin();

/**
 * Fixture form with a tags element.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_fixture_tags_form extends form {
    #[\Override]
    protected function definition(): void {
        $flags = $this->get_extra_data();

        $this->add(new checkbox('hide', 'Hide rule', 'Hide the element'));
        $this->add(new checkbox('lock', 'Lock rule', 'Lock the element'));

        $topics = new tags('topics', 'Topics', new course($flags['courseid']));
        $topics->set_required($flags['required']);
        $topics->set_frozen($flags['frozen']);
        $this->add($topics);

        $this->add(new buttons('buttons'));
        $this->add(new submit(), 'buttons');
        $this->add(new reload('refresh', 'Refresh'), 'buttons');
        $this->add(new cancel(), 'buttons');

        $dm = $this->get_display_manager();
        $dm->hide_if('topics', 'hide', 'checked');
        $dm->disable_if('topics', 'lock', 'checked');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        if (in_array('Forbidden', $data['topics'], true)) {
            $allerrors['topics'][] = 'Forbidden topic';
        }
    }
}

$flags = tool_mulib_muform_fixture_flags();
$standard = optional_param('standard', 0, PARAM_INT);
$pageurl = tool_mulib_muform_fixture_setup('tags', $flags);
if ($standard) {
    $pageurl->param('standard', $standard);
}

$areas = core_tag_area::get_areas();
core_tag_area::update($areas['course']['core'], ['showstandard' => $standard]);

$courseid = (int)$DB->get_field('course', 'id', ['shortname' => 'C1'], MUST_EXIST);
$flags['courseid'] = $courseid;

$current = [];
if ($flags['prefill']) {
    $current = [
        'topics' => ['Prefilled'],
    ];
}

$form = new tool_mulib_muform_fixture_tags_form($pageurl, $current, $flags);
tool_mulib_muform_fixture_finish($form, $pageurl, function (stdClass $data, form $form) use ($courseid): void {
    $form->get_element('topics')->save($courseid);
});
