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
use tool_mulib\local\generator\core_course_generator;

/**
 * Comparison tests between mulib generator and Moodle core testing_data_generator.
 *
 * Demonstrates that both produce equivalent results, and shows
 * mulib extras (placeholders, defaults, DB-backed naming).
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\core_course_generator
 */
final class core_course_generator_comparison_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        \core\di::get(generator::class)->reset();
        parent::tearDown();
    }

    public function test_both_create_equivalent_courses(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();

        // Core generator.
        $core = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'Core Course',
            'shortname' => 'CORE1',
            'idnumber' => 'ID1',
            'format' => 'topics',
        ]);

        // Mulib generator.
        $mulib = \core\di::get(generator::class)->core_course->create_course([
            'category' => $category->id,
            'fullname' => 'Mulib Course',
            'shortname' => 'MULIB1',
            'idnumber' => 'ID2',
            'format' => 'topics',
        ]);

        // Both are full DB records.
        $this->assertTrue($DB->record_exists('course', ['id' => $core->id]));
        $this->assertTrue($DB->record_exists('course', ['id' => $mulib->id]));

        // Both have contexts.
        $this->assertNotEmpty(\context_course::instance($core->id));
        $this->assertNotEmpty(\context_course::instance($mulib->id));

        // Same format, same category.
        $this->assertSame($core->format, $mulib->format);
        $this->assertSame((int)$core->category, (int)$mulib->category);

        // Both return complete records — check a field that only exists if fetched from DB.
        $this->assertObjectHasProperty('timecreated', $core);
        $this->assertObjectHasProperty('timecreated', $mulib);
    }

    public function test_both_create_equivalent_sections(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $course = \core\di::get(generator::class)->core_course->create_course([
            'category' => $category->id,
            'shortname' => 'SECCOMP1',
        ]);

        // Core generator.
        $coresection = $this->getDataGenerator()->create_course_section([
            'course' => $course->id,
            'section' => 1,
            'name' => 'Core Section',
        ]);

        // Mulib generator.
        $mulibsection = \core\di::get(generator::class)->core_course->create_section([
            'course' => $course->id,
            'name' => 'Mulib Section',
        ]);

        // Both exist in DB.
        $this->assertTrue($DB->record_exists('course_sections', ['id' => $coresection->id]));
        $this->assertTrue($DB->record_exists('course_sections', ['id' => $mulibsection->id]));

        // Both belong to the same course.
        $this->assertSame((int)$course->id, (int)$coresection->course);
        $this->assertSame((int)$course->id, (int)$mulibsection->course);
    }

    public function test_mulib_placeholders_vs_core_counters(): void {
        $category = $this->getDataGenerator()->create_category();

        // Core generator uses static counters — names like 'Test course 1', 'tc_1'.
        $core1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $core2 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^Test course \d+/', $core1->fullname);
        $this->assertMatchesRegularExpression('/^tc_\d+/', $core1->shortname);

        // Mulib generator uses DB-backed naming — names like 'Generated course 1', 'GC1'.
        $mulib1 = \core\di::get(generator::class)->core_course->create_course(['category' => $category->id]);
        $mulib2 = \core\di::get(generator::class)->core_course->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^Generated course \d+$/', $mulib1->fullname);
        $this->assertMatchesRegularExpression('/^GC\d+$/', $mulib1->shortname);

        // Mulib naming can be customised per-instance.
        \core\di::get(generator::class)->core_course->set_placeholders('create_course', [
            'fullname' => 'Migration batch %d',
            'shortname' => 'MIG%d',
        ]);
        $custom = \core\di::get(generator::class)->core_course->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^Migration batch \d+$/', $custom->fullname);
        $this->assertMatchesRegularExpression('/^MIG\d+$/', $custom->shortname);
    }

    public function test_mulib_defaults_eliminate_repetition(): void {
        $category = $this->getDataGenerator()->create_category();

        // With core generator, every call must repeat common fields.
        $core1 = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'format' => 'weeks',
            'fullname' => 'Core Repeated 1',
            'shortname' => 'CR1',
        ]);
        $core2 = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'format' => 'weeks',
            'fullname' => 'Core Repeated 2',
            'shortname' => 'CR2',
        ]);

        // With mulib generator, set once and reuse.
        \core\di::get(generator::class)->core_course->set_defaults([
            'category' => $category->id,
            'format' => 'weeks',
            'visible' => true,
        ]);
        $mulib1 = \core\di::get(generator::class)->core_course->create_course(['fullname' => 'Mulib Compact 1', 'shortname' => 'MC1']);
        $mulib2 = \core\di::get(generator::class)->core_course->create_course(['fullname' => 'Mulib Compact 2', 'shortname' => 'MC2']);

        // Both produce equivalent courses.
        $this->assertSame($core1->format, $mulib1->format);
        $this->assertSame($core2->format, $mulib2->format);
        $this->assertSame('weeks', $mulib1->format);
        $this->assertSame('weeks', $mulib2->format);
        $this->assertSame('1', (string)$mulib1->visible);
    }

    public function test_mulib_reset_clears_placeholders_and_defaults(): void {
        $category = $this->getDataGenerator()->create_category();
        $gen = \core\di::get(generator::class)->core_course;

        $gen->set_defaults(['category' => $category->id]);
        $gen->set_placeholders('create_course', ['shortname' => 'CUSTOM%d']);

        // Works before reset.
        $course = $gen->create_course(['fullname' => 'Before Reset']);
        $this->assertMatchesRegularExpression('/^CUSTOM\d+$/', $course->shortname);

        // Reset clears everything.
        $gen->reset();

        // After reset, category default is gone.
        try {
            $gen->create_course(['fullname' => 'After Reset', 'shortname' => 'AR1']);
            $this->fail('Exception expected — category default should be cleared');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('category', $e->getMessage());
        }

        // After reset, placeholder is gone — back to default pattern.
        $course = $gen->create_course(['category' => $category->id]);
        $this->assertMatchesRegularExpression('/^GC\d+$/', $course->shortname);
    }

    public function test_mulib_naming_production_safe(): void {
        $category = $this->getDataGenerator()->create_category();
        $gen = \core\di::get(generator::class)->core_course;

        // Simulate existing auto-named courses with mismatched numbers across fields.
        // fullname has max 5, shortname has max 3 — generator should use max(5, 3) + 1 = 6.
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'Generated course 1',
            'shortname' => 'GC2',
        ]);
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'Generated course 5',
            'shortname' => 'GC3',
        ]);

        // Uses a single number across all fields — picks max(5, 3) + 1 = 6.
        $course = $gen->create_course(['category' => $category->id]);
        $this->assertSame('Generated course 6', $course->fullname);
        $this->assertSame('GC6', $course->shortname);

        // Next one continues from 7.
        $course2 = $gen->create_course(['category' => $category->id]);
        $this->assertSame('Generated course 7', $course2->fullname);
        $this->assertSame('GC7', $course2->shortname);
    }

    public function test_mulib_naming_skips_collisions(): void {
        $category = $this->getDataGenerator()->create_category();
        $gen = \core\di::get(generator::class)->core_course;

        // Create courses that block the next candidate number.
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'Generated course 1',
            'shortname' => 'GC1',
        ]);
        // Block number 2 in shortname only.
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'Something else',
            'shortname' => 'GC2',
        ]);

        // Number 2 collides on shortname, so generator skips to 3.
        $course = $gen->create_course(['category' => $category->id]);
        $this->assertSame('Generated course 3', $course->fullname);
        $this->assertSame('GC3', $course->shortname);
    }
}
