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

namespace tool_mulib\phpunit\local;

use tool_mulib\local\customfield_util;
use tool_mulib\local\sql;

/**
 * Custom field helper tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\customfield_util
 */
final class customfield_util_test extends \advanced_testcase {
    public function test_change_instances_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = $generator->get_plugin_generator('core_customfield');
        $category = $cfgenerator->create_category();
        $text = $cfgenerator->create_field(['categoryid' => $category->get('id'), 'shortname' => 'code', 'type' => 'text']);
        $textarea = $cfgenerator->create_field([
            'categoryid' => $category->get('id'), 'shortname' => 'notes', 'type' => 'textarea',
        ]);
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $context1 = \context_course::instance($course1->id);
        $context2 = \context_course::instance($course2->id);
        $syscontext = \context_system::instance();

        $cfgenerator->add_instance_data($text, $course1->id, 'abc');
        $notes1 = $cfgenerator->add_instance_data($textarea, $course1->id, ['text' => '<p>x</p>', 'format' => FORMAT_HTML]);
        $cfgenerator->add_instance_data($text, $course2->id, 'def');
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context1->id, 'component' => 'customfield_textarea', 'filearea' => 'value',
            'itemid' => $notes1->get('id'), 'filepath' => '/', 'filename' => 'pic.png',
        ], 'x');

        customfield_util::change_instances_context(
            'core_course',
            'course',
            0,
            new sql(':courseid', ['courseid' => $course1->id]),
            $syscontext
        );

        $count = $DB->count_records('customfield_data', ['instanceid' => $course1->id, 'contextid' => $syscontext->id]);
        $this->assertSame(2, $count);
        $this->assertSame(1, $DB->count_records('customfield_data', ['instanceid' => $course2->id, 'contextid' => $context2->id]));
        $this->assertFalse($fs->file_exists($context1->id, 'customfield_textarea', 'value', $notes1->get('id'), '/', 'pic.png'));
        $this->assertTrue($fs->file_exists($syscontext->id, 'customfield_textarea', 'value', $notes1->get('id'), '/', 'pic.png'));

        // Different area is ignored.
        customfield_util::change_instances_context(
            'core_course',
            'other',
            0,
            new sql(':courseid', ['courseid' => $course2->id]),
            $syscontext
        );
        $this->assertSame(1, $DB->count_records('customfield_data', ['instanceid' => $course2->id, 'contextid' => $context2->id]));
    }
}
