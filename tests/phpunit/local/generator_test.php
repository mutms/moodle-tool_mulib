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

namespace tool_mulib\phpunit\local;

use tool_mulib\local\generator;
use tool_mulib\local\generator\base;
use tool_mulib\local\generator\core_course_generator;
use tool_mulib\local\generator\mod_base;
use tool_mulib\local\generator\mod_page_generator;
use tool_mulib\local\generator\mod_book_generator;

/**
 * Generator registry tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator
 */
final class generator_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    protected function tearDown(): void {
        \core\di::get(generator::class)->reset();
        parent::tearDown();
    }

    public function test_get_generator(): void {
        $generator = \core\di::get(generator::class);

        $gen = $generator->get_generator('core_course');
        $this->assertInstanceOf(core_course_generator::class, $gen);
        $this->assertSame('core_course', $gen->get_component());

        $gen = $generator->get_generator('mod_page');
        $this->assertInstanceOf(mod_page_generator::class, $gen);
        $this->assertSame('mod_page', $gen->get_component());
    }

    public function test_get_generator_singleton(): void {
        $generator = \core\di::get(generator::class);

        $gen1 = $generator->get_generator('core_course');
        $gen2 = $generator->get_generator('core_course');
        $this->assertSame($gen1, $gen2);
    }

    public function test_get_generator_unknown(): void {
        $generator = \core\di::get(generator::class);

        try {
            $generator->get_generator('mod_nonexistent');
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('mod_nonexistent', $e->getMessage());
        }
    }

    public function test_property_shortcut(): void {
        $generator = \core\di::get(generator::class);

        $gen = $generator->core_course;
        $this->assertInstanceOf(core_course_generator::class, $gen);

        $gen = $generator->mod_page;
        $this->assertInstanceOf(mod_page_generator::class, $gen);

        // Shortcut returns same singleton.
        $this->assertSame($generator->mod_page, $generator->get_generator('mod_page'));
    }

    public function test_get_registered_components(): void {
        $generator = \core\di::get(generator::class);

        $components = $generator->get_registered_components();
        $this->assertContains('core_course', $components);
        $this->assertContains('mod_page', $components);
        $this->assertContains('mod_book', $components);
        $this->assertContains('mod_forum', $components);
        $this->assertNotContains('mod_nonexistent', $components);
    }

    public function test_all_builtin_generators(): void {
        $generator = \core\di::get(generator::class);

        $expected = [
            'core_course' => core_course_generator::class,
            'mod_page' => mod_page_generator::class,
            'mod_book' => mod_book_generator::class,
        ];

        foreach ($expected as $component => $class) {
            $gen = $generator->get_generator($component);
            $this->assertInstanceOf($class, $gen);
            $this->assertSame($component, $gen->get_component());
        }

        // All module generators extend mod_base.
        $modcomponents = ['mod_page', 'mod_book', 'mod_forum', 'mod_assign', 'mod_label',
            'mod_folder', 'mod_url', 'mod_resource', 'mod_quiz', 'mod_scorm',
            'mod_lesson', 'mod_lti', 'mod_glossary', 'mod_bigbluebutton', ];
        foreach ($modcomponents as $component) {
            $gen = $generator->get_generator($component);
            $this->assertInstanceOf(mod_base::class, $gen);
        }
    }
}
