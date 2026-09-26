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
use core_tag_area;
use core_tag_tag;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\tags;
use tool_mulib\muform\tagarea\course;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Tags element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\tags
 * @covers \tool_mulib\muform\tagarea\base
 */
final class tags_test extends muform_testcase {
    /**
     * Add tags element via hook.
     *
     * @param int $courseid
     * @param bool $required
     */
    private function add_element(int $courseid, bool $required = false): void {
        $hook = function (muform_definition $hook) use ($courseid, $required): void {
            $element = new tags('tags', 'Tags', new course($courseid));
            $element->set_required($required);
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit tags.
     *
     * @param mixed $value
     * @param array $current
     * @return simple_form
     */
    private function submit(mixed $value, array $current = []): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'tags' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), $current);
    }

    public function test_current_tags(): void {
        $course = $this->getDataGenerator()->create_course(['tags' => ['Physics', 'Maths']]);
        $this->add_element($course->id);

        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(['Physics', 'Maths'], $form->get_non_validated_data()->tags);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-tags-area="', $html);
        $this->assertStringContainsString('value="Physics, Maths"', $html);
        $this->assertStringContainsString('/tag/manage.php?tc=' . core_tag_area::get_collection('core', 'course'), $html);

        // Only tag managers get the link to standard tags.
        $teacher = $this->getDataGenerator()->create_and_enrol(get_course($course->id), 'editingteacher');
        $this->setUser($teacher);
        $form = new simple_form($this->get_url(), []);
        $this->assertStringNotContainsString('/tag/manage.php', $this->render($form));
        $this->setAdminUser();

        // Current data wins over stored tags.
        $form = new simple_form($this->get_url(), ['tags' => ['Other']]);
        $this->assertSame(['Other'], $form->get_non_validated_data()->tags);
    }

    public function test_submitted(): void {
        $course = $this->getDataGenerator()->create_course(['tags' => ['Physics']]);
        $this->add_element($course->id);

        $form = $this->submit('  Quantum   physics , maths,Maths,,');
        $this->assertTrue($form->is_valid());
        $this->assertSame(['Quantum physics', 'maths'], $form->get_data()->tags);

        $form = $this->submit(['Quantum', 'Biology']);
        $this->assertSame(['Quantum', 'Biology'], $form->get_data()->tags);

        $form = $this->submit('');
        $this->assertTrue($form->is_valid());
        $this->assertSame([], $form->get_data()->tags);

        $form->get_element('tags')->save($course->id);
        $this->assertSame([], core_tag_tag::get_item_tags_array('core', 'course', $course->id));
    }

    public function test_invalid(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->add_element($course->id);

        $form = $this->submit(['Good', 'Bad<b>']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Bad&lt;b&gt;: ' . get_string('error')], $this->get_rendered_errors($form, 'tags'));

        $form = $this->submit([str_repeat('x', TAG_MAX_LENGTH + 1)]);
        $this->assertTrue($form->is_invalid());

        $form = $this->submit(['x' => ['nested']]);
        $this->assertTrue($form->is_invalid());

        $this->add_element($course->id, true);
        $form = $this->submit('');
        $this->assertTrue($form->is_invalid());
    }

    public function test_standard_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $collid = core_tag_area::get_collection('core', 'course');
        core_tag_tag::create_if_missing($collid, ['Physics'], true);
        core_tag_area::update(core_tag_area::get_areas()['course']['core'], ['showstandard' => core_tag_tag::STANDARD_ONLY]);
        $this->add_element($course->id);

        $form = $this->submit('physics');
        $this->assertTrue($form->is_valid());

        $form = $this->submit('Physics,Chemistry');
        $this->assertTrue($form->is_invalid());
        $expected = ['Chemistry: ' . get_string('muform_tagnotstandard', 'tool_mulib')];
        $this->assertSame($expected, $this->get_rendered_errors($form, 'tags'));
        $this->assertStringContainsString('data-muform-tags-standardonly="1"', $this->render($form));
    }

    public function test_disabled(): void {
        $course = $this->getDataGenerator()->create_course(['tags' => ['Physics']]);
        core_tag_area::update(core_tag_area::get_areas()['course']['core'], ['enabled' => 0]);
        $this->add_element($course->id);

        $form = $this->submit('Other');
        $this->assertTrue($form->is_valid());
        $this->assertSame([], $form->get_data()->tags);
        $form->get_element('tags')->save($course->id);
        $html = $this->render($form);
        $this->assertStringNotContainsString('data-muform-tags-area', $html);
        $this->assertStringContainsString('data-muform-name="tags" hidden', $html);
    }

    public function test_save(): void {
        $course = $this->getDataGenerator()->create_course(['tags' => ['Physics']]);
        $this->add_element($course->id);

        $form = $this->submit('Maths,Biology');
        $form->get_element('tags')->save($course->id);
        $this->assertEqualsCanonicalizing(['Maths', 'Biology'], core_tag_tag::get_item_tags_array('core', 'course', $course->id));

        try {
            $form->get_element('tags')->save($course->id + 1);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid tags item id', $e->getMessage());
        }

        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->expectException(coding_exception::class);
        $form->get_element('tags')->save($course->id);
    }
}
