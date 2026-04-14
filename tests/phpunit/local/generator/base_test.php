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
 * Generator base class tests (defaults, placeholders, reset).
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\base
 */
final class base_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        \core\di::get(generator::class)->reset();
        parent::tearDown();
    }

    public function test_set_defaults_and_clear(): void {
        $generator = \core\di::get(generator::class);
        $gen = $generator->core_course;

        $category = $this->getDataGenerator()->create_category();
        $gen->set_defaults(['category' => $category->id, 'format' => 'weeks']);

        $course = $gen->create_course(['fullname' => 'Defaults Test', 'shortname' => 'DFT1']);
        $this->assertSame('weeks', $course->format);

        $gen->clear_defaults();

        // After clear, must provide category again.
        try {
            $gen->create_course(['fullname' => 'No Cat', 'shortname' => 'NC1']);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('category', $e->getMessage());
        }
    }

    public function test_caller_overrides_defaults(): void {
        $generator = \core\di::get(generator::class);
        $gen = $generator->core_course;

        $category = $this->getDataGenerator()->create_category();
        $gen->set_defaults(['category' => $category->id, 'format' => 'weeks']);

        // Caller provides format — should override default.
        $course = $gen->create_course([
            'fullname' => 'Override Test',
            'shortname' => 'OT1',
            'format' => 'topics',
        ]);
        $this->assertSame('topics', $course->format);

        $gen->clear_defaults();
    }

    public function test_set_placeholders_validation(): void {
        $generator = \core\di::get(generator::class);
        $gen = $generator->core_course;

        try {
            $gen->set_placeholders('create_course', ['fullname' => 'No placeholder here']);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('%d', $e->getMessage());
        }
    }

    public function test_set_placeholders_partial_override(): void {
        $generator = \core\di::get(generator::class);
        $gen = $generator->core_course;

        $category = $this->getDataGenerator()->create_category();

        // Override only shortname pattern, fullname keeps default.
        $gen->set_placeholders('create_course', ['shortname' => 'CUSTOM_%d']);

        $course = $gen->create_course(['category' => $category->id]);
        $this->assertStringContainsString('Generated course', $course->fullname);
        $this->assertStringStartsWith('CUSTOM_', $course->shortname);

        $gen->clear_defaults();
    }

    public function test_reset_clears_everything(): void {
        $generator = \core\di::get(generator::class);
        $gen = $generator->core_course;

        $category = $this->getDataGenerator()->create_category();
        $gen->set_defaults(['category' => $category->id]);
        $gen->set_placeholders('create_course', ['shortname' => 'RST%d']);

        $gen->reset();

        // Defaults cleared — category missing.
        try {
            $gen->create_course(['fullname' => 'After Reset', 'shortname' => 'AR1']);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('category', $e->getMessage());
        }
    }

    public function test_find_next_name_sequential(): void {
        $generator = \core\di::get(generator::class);
        $gen = $generator->core_course;

        $category = $this->getDataGenerator()->create_category();
        $gen->set_placeholders('create_course', [
            'fullname' => 'Seq %d',
            'shortname' => 'SEQ%d',
        ]);

        $course1 = $gen->create_course(['category' => $category->id]);
        $course2 = $gen->create_course(['category' => $category->id]);
        $course3 = $gen->create_course(['category' => $category->id]);

        // Extract numbers — should be sequential.
        preg_match('/Seq (\d+)/', $course1->fullname, $m1);
        preg_match('/Seq (\d+)/', $course2->fullname, $m2);
        preg_match('/Seq (\d+)/', $course3->fullname, $m3);

        $this->assertSame((int)$m1[1] + 1, (int)$m2[1]);
        $this->assertSame((int)$m2[1] + 1, (int)$m3[1]);

        $gen->clear_defaults();
    }
}
