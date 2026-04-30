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

namespace tool_mulib\phpunit\local\generator;

use tool_mulib\local\generator;

/**
 * Course generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\core_course_generator
 */
final class core_course_generator_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        \core\di::get(generator::class)->reset();
        parent::tearDown();
    }

    public function test_create_course(): void {
        global $DB;

        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        $countbefore = $DB->count_records('course');
        $course = $gen->create_course([
            'category' => $category->id,
            'fullname' => 'Test Course',
            'shortname' => 'TC1',
            'idnumber' => 'TESTID1',
            'summary' => '<p>Course summary</p>',
            'summaryformat' => FORMAT_HTML,
            'format' => 'weeks',
            'visible' => true,
        ]);
        $this->assertSame($countbefore + 1, $DB->count_records('course'));

        $this->assertInstanceOf(\stdClass::class, $course);
        $this->assertSame('Test Course', $course->fullname);
        $this->assertSame('TC1', $course->shortname);
        $this->assertSame('TESTID1', $course->idnumber);
        $this->assertSame('<p>Course summary</p>', $course->summary);
        $this->assertSame((string)FORMAT_HTML, (string)$course->summaryformat);
        $this->assertSame('weeks', $course->format);
        $this->assertSame('1', (string)$course->visible);
        $this->assertSame((int)$category->id, (int)$course->category);

        // Verify it is a complete DB record with all standard fields.
        $dbcourse = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $this->assertSame($course->fullname, $dbcourse->fullname);
        $this->assertSame($course->shortname, $dbcourse->shortname);
        $this->assertSame($course->idnumber, $dbcourse->idnumber);

        // Verify course context was created.
        $context = \context_course::instance($course->id);
        $this->assertNotEmpty($context->id);
    }

    public function test_create_course_defaults(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        $course = $gen->create_course([
            'category' => $category->id,
            'fullname' => 'Defaults Test',
            'shortname' => 'DT1',
        ]);

        // Verify all default values.
        $this->assertSame('topics', $course->format);
        $this->assertSame('0', (string)$course->visible);
        $this->assertSame('0', (string)$course->newsitems);
        $this->assertSame('', $course->idnumber);
        $this->assertSame('', $course->summary);
    }

    public function test_create_course_auto_naming(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        // No fullname/shortname provided — should auto-generate.
        $course1 = $gen->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^Sample course \d+$/', $course1->fullname);
        $this->assertMatchesRegularExpression('/^SC\d+$/', $course1->shortname);

        // Second course gets different names.
        $course2 = $gen->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^Sample course \d+$/', $course2->fullname);
        $this->assertNotSame($course1->fullname, $course2->fullname);
        $this->assertNotSame($course1->shortname, $course2->shortname);

        // Sequence increments.
        preg_match('/(\d+)$/', $course1->shortname, $m1);
        preg_match('/(\d+)$/', $course2->shortname, $m2);
        $this->assertSame((int)$m1[1] + 1, (int)$m2[1]);
    }

    public function test_create_course_auto_naming_skips_existing(): void {
        global $DB;

        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        // Manually create a course that matches the auto-naming pattern.
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'Sample course 1',
            'shortname' => 'GC1',
        ]);

        // Auto-naming should skip past the existing 'GC1'.
        $course = $gen->create_course(['category' => $category->id]);
        $this->assertNotSame('GC1', $course->shortname);
        $this->assertFalse(str_ends_with($course->shortname, '1') && strlen($course->shortname) === 3);
    }

    public function test_create_course_custom_placeholders(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        $gen->set_placeholders('create_course', [
            'fullname' => 'UHK Course %d',
            'shortname' => 'UHK%d',
        ]);

        $course1 = $gen->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^UHK Course \d+$/', $course1->fullname);
        $this->assertMatchesRegularExpression('/^UHK\d+$/', $course1->shortname);

        $course2 = $gen->create_course(['category' => $category->id]);
        $this->assertNotSame($course1->shortname, $course2->shortname);

        $gen->clear_defaults();
    }

    public function test_create_course_with_defaults(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        $gen->set_defaults([
            'category' => $category->id,
            'visible' => true,
            'format' => 'weeks',
        ]);

        // Create two courses — both inherit defaults.
        $course1 = $gen->create_course(['fullname' => 'With Defaults 1', 'shortname' => 'WD1']);
        $course2 = $gen->create_course(['fullname' => 'With Defaults 2', 'shortname' => 'WD2']);

        $this->assertSame((int)$category->id, (int)$course1->category);
        $this->assertSame('1', (string)$course1->visible);
        $this->assertSame('weeks', $course1->format);
        $this->assertSame((int)$category->id, (int)$course2->category);
        $this->assertSame('weeks', $course2->format);

        // Caller value overrides default.
        $course3 = $gen->create_course(['fullname' => 'Override', 'shortname' => 'OV1', 'format' => 'topics']);
        $this->assertSame('topics', $course3->format);

        $gen->clear_defaults();
    }

    public function test_create_course_requires_category(): void {
        $gen = \core\di::get(generator::class)->core_course;

        try {
            $gen->create_course(['fullname' => 'No category']);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('category', $e->getMessage());
        }
    }

    public function test_create_course_multiple_in_same_category(): void {
        global $DB;

        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();

        $gen->set_defaults(['category' => $category->id]);

        $courses = [];
        for ($i = 0; $i < 5; $i++) {
            $courses[] = $gen->create_course();
        }

        // All should have unique shortnames.
        $shortnames = array_map(fn($c) => $c->shortname, $courses);
        $this->assertSame(count($shortnames), count(array_unique($shortnames)));

        // All in the same category.
        $this->assertSame(5, $DB->count_records('course', ['category' => $category->id]));

        $gen->clear_defaults();
    }

    public function test_create_section(): void {
        global $DB;

        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();
        $course = $gen->create_course(['category' => $category->id, 'shortname' => 'ST1']);

        $section = $gen->create_section([
            'course' => $course->id,
            'name' => 'Topic One',
            'summary' => '<p>Section summary</p>',
            'summaryformat' => FORMAT_HTML,
            'visible' => false,
        ]);

        $this->assertInstanceOf(\stdClass::class, $section);
        $this->assertSame('Topic One', $section->name);
        $this->assertSame('<p>Section summary</p>', $section->summary);
        $this->assertSame((int)$course->id, (int)$section->course);
        $this->assertTrue((int)$section->section > 0);
        $this->assertSame('0', (string)$section->visible);

        // Verify complete DB record.
        $dbsection = $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
        $this->assertSame('Topic One', $dbsection->name);
        $this->assertSame('<p>Section summary</p>', $dbsection->summary);
    }

    public function test_create_section_ordering(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();
        $course = $gen->create_course(['category' => $category->id, 'shortname' => 'SO1']);

        $s1 = $gen->create_section(['course' => $course->id, 'name' => 'First']);
        $s2 = $gen->create_section(['course' => $course->id, 'name' => 'Second']);
        $s3 = $gen->create_section(['course' => $course->id, 'name' => 'Third']);

        // Sections should be sequentially numbered.
        $this->assertSame((int)$s1->section + 1, (int)$s2->section);
        $this->assertSame((int)$s2->section + 1, (int)$s3->section);
    }

    public function test_create_section_auto_naming(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();
        $course = $gen->create_course(['category' => $category->id, 'shortname' => 'SA1']);

        $section = $gen->create_section(['course' => $course->id]);
        $this->assertMatchesRegularExpression('/^Sample section \d+$/', $section->name);
    }

    public function test_create_subsection(): void {
        global $DB;

        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();
        $course = $gen->create_course(['category' => $category->id, 'shortname' => 'SST1']);

        $section = $gen->create_section([
            'course' => $course->id,
            'name' => 'Parent Section',
        ]);

        // Create subsection using course object (not just id).
        $sub = $gen->create_subsection([
            'course' => $course,
            'section' => $section->section,
            'name' => 'Sub Topic',
        ]);

        $this->assertInstanceOf(\stdClass::class, $sub);
        $this->assertSame('mod_subsection', $sub->component);
        $this->assertSame((int)$course->id, (int)$sub->course);
        $this->assertTrue((int)$sub->itemid > 0);

        // Verify delegated section in DB.
        $dbsection = $DB->get_record('course_sections', ['id' => $sub->id], '*', MUST_EXIST);
        $this->assertSame('mod_subsection', $dbsection->component);

        // Verify mod_subsection activity was created.
        $this->assertTrue($DB->record_exists('subsection', ['id' => $sub->itemid]));

        // Create a second subsection in the same parent.
        $sub2 = $gen->create_subsection([
            'course' => $course,
            'section' => $section->section,
            'name' => 'Sub Topic 2',
        ]);
        $this->assertNotSame($sub->id, $sub2->id);
    }

    public function test_create_subsection_requires_course(): void {
        $gen = \core\di::get(generator::class)->core_course;

        try {
            $gen->create_subsection(['section' => 1, 'name' => 'Orphan']);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('course', $e->getMessage());
        }
    }

    public function test_create_subsection_requires_section(): void {
        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();
        $course = $gen->create_course(['category' => $category->id, 'shortname' => 'SF1']);

        try {
            $gen->create_subsection(['course' => $course]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('section', $e->getMessage());
        }
    }

    public function test_create_full_course_structure(): void {
        global $DB;

        $gen = \core\di::get(generator::class)->core_course;
        $category = $this->getDataGenerator()->create_category();
        $course = $gen->create_course([
            'category' => $category->id,
            'fullname' => 'Full Structure',
            'shortname' => 'FS1',
        ]);

        // Build a realistic course structure: 3 sections, one with a subsection.
        $s1 = $gen->create_section(['course' => $course->id, 'name' => 'Introduction']);
        $s2 = $gen->create_section(['course' => $course->id, 'name' => 'Main Content']);
        $s3 = $gen->create_section(['course' => $course->id, 'name' => 'Assessment']);

        $sub = $gen->create_subsection([
            'course' => $course,
            'section' => $s2->section,
            'name' => 'Part A',
        ]);

        // Verify structure: 3 regular sections + general section (0) + 1 delegated section = 5 section records.
        $sectioncount = $DB->count_records('course_sections', ['course' => $course->id]);
        $this->assertSame(5, $sectioncount);

        // Verify the subsection is delegated under the correct parent.
        $this->assertSame('mod_subsection', $sub->component);
    }
}
