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

namespace tool_mulib\phpunit\muform\element;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../muform_testcase.php');

use core\exception\coding_exception;
use core_course\customfield\course_handler;
use core_customfield\shared;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\customfields;
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\muform\element\section;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Custom fields element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\customfields
 * @covers \tool_mulib\muform\customfield\base
 * @covers \tool_mulib\muform\customfield\checkbox
 * @covers \tool_mulib\muform\customfield\date
 * @covers \tool_mulib\muform\customfield\mutrain
 * @covers \tool_mulib\muform\customfield\number
 * @covers \tool_mulib\muform\customfield\select
 * @covers \tool_mulib\muform\customfield\text
 * @covers \tool_mulib\muform\customfield\textarea
 */
final class customfields_test extends muform_testcase {
    /** @var array field controllers indexed by shortname */
    private array $fields = [];

    /**
     * Create course custom fields of all supported types.
     *
     * @return int category id
     */
    private function create_fields(): int {
        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category(['name' => 'Course info']);
        $categoryid = $category->get('id');
        $specs = [
            'code' => ['type' => 'text', 'configdata' => ['maxlength' => 5, 'uniquevalues' => 1, 'defaultvalue' => 'ABC']],
            'agree' => ['type' => 'checkbox', 'configdata' => ['checkbydefault' => 1]],
            'level' => ['type' => 'select', 'configdata' => ['options' => "Low\nMedium\nHigh", 'defaultvalue' => 'Medium']],
            'start' => ['type' => 'date', 'configdata' => ['includetime' => 0, 'mindate' => 946684800]],
            'size' => ['type' => 'number', 'configdata' => [
                'decimalplaces' => 1, 'minimumvalue' => '1', 'maximumvalue' => '100', 'defaultvalue' => '5',
            ]],
            'notes' => ['type' => 'textarea', 'configdata' => [
                'defaultvalue' => '<p>Default</p>', 'defaultvalueformat' => FORMAT_HTML,
            ]],
        ];
        $i = 0;
        foreach ($specs as $shortname => $spec) {
            $description = ($shortname === 'code') ? '<p>Unique code</p>' : '';
            $this->fields[$shortname] = $generator->create_custom_field([
                'categoryid' => $categoryid, 'shortname' => $shortname, 'name' => ucfirst($shortname),
                'type' => $spec['type'], 'configdata' => $spec['configdata'], 'sortorder' => $i++,
                'description' => $description,
            ]);
        }
        return $categoryid;
    }

    /**
     * Add the custom fields element via hook.
     *
     * @param int|null $instanceid
     */
    private function add_element(?int $instanceid): void {
        $hook = function (muform_definition $hook) use ($instanceid): void {
            $handler = course_handler::create();
            $hook->form->add(new customfields('customfields', $handler, $instanceid));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit custom field values.
     *
     * @param array $values
     * @return simple_form
     */
    private function submit(array $values): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'submit' => '1'] + $values);
        return new simple_form($this->get_url(), []);
    }

    /**
     * Valid values of all fields.
     *
     * @return array
     */
    private function get_valid_post(): array {
        return [
            'customfield_code' => 'XYZ',
            'customfield_agree' => '0',
            'customfield_level' => '3',
            'customfield_start' => (string)make_timestamp(2026, 9, 28, 14, 30),
            'customfield_size' => '7.5',
            'customfield_notes' => ['text' => '<p>Hello</p>', 'format' => '1', 'itemid' => (string)file_get_unused_draft_itemid()],
        ];
    }

    /**
     * Returns core values of all fields.
     *
     * @param int $courseid
     * @return array
     */
    private function get_core_values(int $courseid): array {
        $handler = course_handler::create();
        $result = [];
        foreach ($handler->get_instance_data($courseid, true) as $data) {
            $result[$data->get_field()->get('shortname')] = $data->get_value();
        }
        return $result;
    }

    public function test_definition(): void {
        $generator = $this->getDataGenerator();
        $categoryid = $this->create_fields();
        $generator->create_custom_field_category(['name' => 'Empty']);
        $other = $generator->create_custom_field_category(['name' => 'Other']);
        $generator->create_custom_field(['categoryid' => $other->get('id'), 'shortname' => 'auto', 'type' => 'number',
            'configdata' => ['fieldtype' => \customfield_number\local\numberproviders\nofactivities::class]]);

        $sharedcategory = $generator->create_custom_field_category(['component' => 'core_customfield', 'area' => 'shared']);
        $generator->create_custom_field(['categoryid' => $sharedcategory->get('id'), 'shortname' => 'color', 'type' => 'text']);
        (new shared(0, (object)[
            'categoryid' => $sharedcategory->get('id'), 'component' => 'core_course', 'area' => 'course', 'itemid' => 0,
        ]))->create();
        $this->add_element(null);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);

        $customfields = $form->get_element('customfields');
        $this->assertSame(['customfields_category_' . $categoryid], $customfields->get_children());
        $section = $form->get_element('customfields_category_' . $categoryid);
        $this->assertInstanceOf(section::class, $section);
        $this->assertSame([
            'customfield_code', 'customfields_description_' . $this->fields['code']->get('id'),
            'customfield_agree', 'customfield_level', 'customfield_start', 'customfield_size',
            'customfield_notes',
        ], $section->get_children());
        $description = $form->get_element('customfields_description_' . $this->fields['code']->get('id'));
        $this->assertInstanceOf(inforawhtml::class, $description);
        $this->assertInstanceOf(editor::class, $form->get_element('customfield_notes'));
        $this->assertNull($form->get_element('customfield_color'));
        $this->assertNull($form->get_element('customfield_auto'));

        $data = $form->get_non_validated_data();
        $this->assertObjectNotHasProperty('customfields', $data);
        $this->assertSame('ABC', $data->customfield_code);
        $this->assertSame(1, $data->customfield_agree);
        $this->assertSame('2', $data->customfield_level);
        $this->assertNull($data->customfield_start);
        $this->assertSame(5.0, $data->customfield_size);
        $this->assertSame('<p>Default</p>', $data->customfield_notes);

        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-element="customfields"', $html);
        $this->assertStringContainsString('>Course info</legend>', $html);
        $this->assertStringContainsString('<p>Unique code</p>', $html);
        $this->assertStringContainsString('>Medium</option>', $html);
    }

    public function test_stored_values(): void {
        $this->create_fields();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $start = make_timestamp(2026, 1, 2);
        $generator->add_instance_data($this->fields['code'], $course->id, 'QQ');
        $generator->add_instance_data($this->fields['agree'], $course->id, 0);
        $generator->add_instance_data($this->fields['level'], $course->id, 3);
        $generator->add_instance_data($this->fields['start'], $course->id, $start);
        $generator->add_instance_data($this->fields['size'], $course->id, 12.5);
        $generator->add_instance_data($this->fields['notes'], $course->id, ['text' => '<p>Stored</p>', 'format' => FORMAT_HTML]);

        $this->add_element($course->id);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $data = $form->get_non_validated_data();
        $this->assertSame('QQ', $data->customfield_code);
        $this->assertSame(0, $data->customfield_agree);
        $this->assertSame('3', $data->customfield_level);
        $this->assertSame($start, $data->customfield_start);
        $this->assertSame(12.5, $data->customfield_size);
        $this->assertSame('<p>Stored</p>', $data->customfield_notes);
    }

    public function test_save(): void {
        global $DB;

        $this->create_fields();
        $course = $this->getDataGenerator()->create_course();

        $this->add_element(null);
        $form = $this->submit($this->get_valid_post());
        $this->assertTrue($form->is_valid());
        $form->get_element('customfields')->save($course->id);

        $values = $this->get_core_values($course->id);
        $this->assertSame('XYZ', $values['code']);
        $this->assertEquals(0, $values['agree']);
        $this->assertEquals(3, $values['level']);
        $this->assertEquals(make_timestamp(2026, 9, 28), $values['start']);
        $this->assertEquals(7.5, $values['size']);
        $this->assertSame('<p>Hello</p>', $values['notes']);

        $context = \context_course::instance($course->id);
        $rows = $DB->get_records('customfield_data', ['instanceid' => $course->id]);
        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertSame((string)$context->id, (string)$row->contextid);
            $this->assertSame('core_course', $row->component);
            $this->assertSame('course', $row->area);
        }
        $notes = $DB->get_record('customfield_data', ['instanceid' => $course->id, 'fieldid' => $this->fields['notes']->get('id')]);
        $this->assertEquals(FORMAT_HTML, $notes->valueformat);
        $this->assertEquals(0, $notes->valuetrust);

        // Update keeps the rows.
        $this->add_element($course->id);
        $post = ['customfield_code' => 'NEW', 'customfield_agree' => '1', 'customfield_level' => '',
            'customfield_start' => '', 'customfield_size' => ''] + $this->get_valid_post();
        $form = $this->submit($post);
        $this->assertTrue($form->is_valid());
        $form->get_element('customfields')->save($course->id);
        $this->assertCount(6, $DB->get_records('customfield_data', ['instanceid' => $course->id]));
        $values = $this->get_core_values($course->id);
        $this->assertSame('NEW', $values['code']);
        $this->assertEquals(1, $values['agree']);
        $this->assertEquals(0, $values['level']);
        $this->assertEquals(0, $values['start']);
        $this->assertNull($values['size']);

        // Instance id must match.
        try {
            $form->get_element('customfields')->save($course->id + 1);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertInstanceOf(coding_exception::class, $e);
        }
    }

    public function test_save_invalid(): void {
        $this->create_fields();
        $this->add_element(null);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->expectException(coding_exception::class);
        $form->get_element('customfields')->save(10);
    }

    public function test_validation(): void {
        $this->create_fields();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $generator->add_instance_data($this->fields['code'], $othercourse->id, 'USED');
        $generator->add_instance_data($this->fields['code'], $course->id, 'MINE');

        $this->add_element($course->id);
        $form = $this->submit(['customfield_code' => 'USED'] + $this->get_valid_post());
        $this->assertSame(['This value is already used.'], $this->get_rendered_errors($form, 'customfield_code'));

        $this->add_element($course->id);
        $form = $this->submit(['customfield_code' => 'MINE'] + $this->get_valid_post());
        $this->assertTrue($form->is_valid());

        $this->add_element($course->id);
        $form = $this->submit(['customfield_code' => 'TOOLONG'] + $this->get_valid_post());
        $this->assertSame(
            ['The maximum number of characters allowed in this field is 5.'],
            $this->get_rendered_errors($form, 'customfield_code')
        );

        $this->add_element($course->id);
        $form = $this->submit(['customfield_size' => '0.5'] + $this->get_valid_post());
        $this->assertSame(['Value must be greater than or equal to 1.0'], $this->get_rendered_errors($form, 'customfield_size'));

        $this->add_element($course->id);
        $form = $this->submit(['customfield_size' => '101'] + $this->get_valid_post());
        $this->assertSame(['Value must be less than or equal to 100.0'], $this->get_rendered_errors($form, 'customfield_size'));

        $this->add_element($course->id);
        $form = $this->submit(['customfield_start' => (string)make_timestamp(1999, 12, 31)] + $this->get_valid_post());
        $errors = $this->get_rendered_errors($form, 'customfield_start');
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('Please enter a date on or after', $errors[0]);
    }

    public function test_required(): void {
        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category([]);
        foreach (['text', 'checkbox', 'select', 'date', 'number', 'textarea'] as $type) {
            $configdata = ['required' => 1];
            if ($type === 'select') {
                $configdata['options'] = "A\nB";
            }
            $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'r' . $type,
                'type' => $type, 'configdata' => $configdata]);
        }
        $this->add_element(null);
        $form = $this->submit([
            'customfield_rtext' => '', 'customfield_rselect' => '', 'customfield_rdate' => '',
            'customfield_rnumber' => '', 'customfield_rtextarea' => ['text' => '', 'format' => '1', 'itemid' => '1'],
        ]);
        $this->assertFalse($form->is_valid());
        foreach (['text', 'checkbox', 'select', 'date', 'number', 'textarea'] as $type) {
            $this->assertCount(1, $this->get_rendered_errors($form, 'customfield_r' . $type), $type);
        }
    }

    public function test_mutrain(): void {
        global $DB;

        if (!class_exists(\customfield_mutrain\field_controller::class)) {
            $this->markTestSkipped('customfield_mutrain is not installed');
        }

        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category(['name' => 'Training']);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'credits',
            'name' => 'Credits', 'type' => 'mutrain', 'configdata' => []]);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'rcredits',
            'name' => 'Required credits', 'type' => 'mutrain', 'configdata' => ['required' => 1]]);
        $course = $generator->create_course();

        $this->add_element(null);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_non_validated_data()->customfield_credits);

        $this->add_element(null);
        $form = $this->submit(['customfield_credits' => '2.5', 'customfield_rcredits' => '']);
        $this->assertFalse($form->is_valid());
        $this->assertCount(1, $this->get_rendered_errors($form, 'customfield_rcredits'));

        $this->add_element(null);
        $form = $this->submit(['customfield_credits' => '-1', 'customfield_rcredits' => '1']);
        $this->assertFalse($form->is_valid());

        $this->add_element(null);
        $form = $this->submit(['customfield_credits' => '2.5', 'customfield_rcredits' => '1']);
        $this->assertTrue($form->is_valid());
        $form->get_element('customfields')->save($course->id);
        $values = $this->get_core_values($course->id);
        $this->assertEquals(2.5, $values['credits']);
        $this->assertEquals(1, $values['rcredits']);

        $this->add_element($course->id);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(2.5, $form->get_non_validated_data()->customfield_credits);

        $this->add_element($course->id);
        $form = $this->submit(['customfield_credits' => '0', 'customfield_rcredits' => '1']);
        $this->assertTrue($form->is_valid());
        $form->get_element('customfields')->save($course->id);
        $this->assertNull($this->get_core_values($course->id)['credits']);
        $this->assertCount(2, $DB->get_records('customfield_data', ['instanceid' => $course->id]));
    }

    public function test_not_editable(): void {
        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category([]);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'open', 'type' => 'text']);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'locked', 'type' => 'text',
            'configdata' => ['locked' => 1]]);
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        $this->add_element($course->id);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->assertNotNull($form->get_element('customfield_open'));
        $this->assertNull($form->get_element('customfield_locked'));
    }

    public function test_textarea_files(): void {
        global $USER;

        $this->create_fields();
        $course = $this->getDataGenerator()->create_course();
        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftitemid, 'filepath' => '/', 'filename' => 'pic.png',
        ], 'x');
        $drafturl = \moodle_url::make_draftfile_url($draftitemid, '/', 'pic.png')->out(false);

        $this->add_element(null);
        $post = $this->get_valid_post();
        $post['customfield_notes'] = ['text' => '<p><img src="' . $drafturl . '" alt="x"></p>', 'format' => '1',
            'itemid' => (string)$draftitemid];
        $form = $this->submit($post);
        $this->assertTrue($form->is_valid());
        $form->get_element('customfields')->save($course->id);

        $values = $this->get_core_values($course->id);
        $this->assertStringContainsString('@@PLUGINFILE@@/pic.png', $values['notes']);
        $handler = course_handler::create();
        $dataid = null;
        foreach ($handler->get_instance_data($course->id, true) as $data) {
            if ($data->get_field()->get('shortname') === 'notes') {
                $dataid = $data->get('id');
            }
        }
        $context = \context_course::instance($course->id);
        $files = get_file_storage()->get_area_files($context->id, 'customfield_textarea', 'value', $dataid, 'filename', false);
        $this->assertSame(['pic.png'], array_values(array_map(fn($f) => $f->get_filename(), $files)));

        // Stored files are loaded to a new draft area.
        $this->add_element($course->id);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $data = $form->get_non_validated_data();
        $this->assertStringContainsString('@@PLUGINFILE@@/pic.png', $data->customfield_notes);
        $this->assertNotEmpty($data->customfield_notesdraftitemid);
    }
}
