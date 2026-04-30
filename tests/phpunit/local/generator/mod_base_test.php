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
 * Activity module base generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_base
 */
final class mod_base_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        \core\di::get(generator::class)->reset();
        parent::tearDown();
    }

    public function test_create_activity_auto_naming(): void {
        $course = $this->getDataGenerator()->create_course();
        $gen = \core\di::get(generator::class)->mod_page;

        $page1 = $gen->create_activity(['course' => $course]);
        $this->assertStringContainsString('Sample page', $page1->name);

        $page2 = $gen->create_activity(['course' => $course]);
        $this->assertNotSame($page1->name, $page2->name);
    }

    public function test_create_activity_custom_placeholders(): void {
        $course = $this->getDataGenerator()->create_course();
        $gen = \core\di::get(generator::class)->mod_page;

        $gen->set_placeholders('create_activity', ['name' => 'Migrated page %d']);

        $page = $gen->create_activity(['course' => $course]);
        $this->assertStringStartsWith('Migrated page ', $page->name);
    }

    public function test_create_activity_with_defaults(): void {
        $course = $this->getDataGenerator()->create_course();
        $gen = \core\di::get(generator::class)->mod_page;

        $gen->set_defaults(['course' => $course, 'visible' => false]);

        $page = $gen->create_activity(['name' => 'Page With Defaults']);
        $this->assertSame('Page With Defaults', $page->name);
    }

    public function test_create_activity_requires_course(): void {
        $gen = \core\di::get(generator::class)->mod_page;

        try {
            $gen->create_activity(['name' => 'No Course']);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('course', $e->getMessage());
        }
    }

    public function test_create_activity_in_section(): void {
        $course = $this->getDataGenerator()->create_course();
        $generator = \core\di::get(generator::class);

        $section = $generator->core_course->create_section([
            'course' => $course->id,
            'name' => 'Target Section',
        ]);

        $page = $generator->mod_page->create_activity([
            'course' => $course,
            'section' => $section->section,
            'name' => 'Page In Section',
        ]);

        $cm = get_coursemodule_from_id('page', $page->cmid);
        $this->assertSame((int)$section->id, (int)$cm->section);
    }

    public function test_create_activity_hidden(): void {
        $course = $this->getDataGenerator()->create_course();

        $page = \core\di::get(generator::class)->mod_page->create_activity([
            'course' => $course,
            'name' => 'Hidden Page',
            'visible' => false,
        ]);

        $cm = get_coursemodule_from_id('page', $page->cmid);
        $this->assertSame(0, (int)$cm->visible);
    }
}
