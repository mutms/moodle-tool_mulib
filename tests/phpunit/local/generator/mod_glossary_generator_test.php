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
 * Glossary generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_glossary_generator
 */
final class mod_glossary_generator_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_create_activity(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'GGT1']);

        $glossary = $generator->mod_glossary->create_activity([
            'course' => $course,
            'name' => 'Test Glossary',
        ]);

        $this->assertSame('Test Glossary', $glossary->name);
        $this->assertObjectHasProperty('cmid', $glossary);
        $this->assertTrue($DB->record_exists('glossary', ['id' => $glossary->id]));
    }

    public function test_create_entry(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'GGT2']);

        $glossary = $generator->mod_glossary->create_activity([
            'course' => $course,
            'name' => 'Test Glossary',
        ]);

        $e1 = $generator->mod_glossary->create_entry([
            'glossaryid' => $glossary->id,
            'concept' => 'EDF',
            'definition' => 'Earliest Deadline First — plánovací algoritmus',
        ]);
        $e2 = $generator->mod_glossary->create_entry([
            'glossaryid' => $glossary->id,
            'concept' => 'PID',
            'definition' => 'identifikátor procesu',
            'usedynalink' => 1,
        ]);

        $this->assertSame('EDF', $e1->concept);
        $this->assertSame('PID', $e2->concept);
        $this->assertSame(0, (int)$e1->usedynalink);
        $this->assertSame(1, (int)$e2->usedynalink);
        $this->assertSame(1, (int)$e1->approved);
        $this->assertSame(2, $DB->count_records('glossary_entries', ['glossaryid' => $glossary->id]));
    }

    public function test_create_entry_requires_glossaryid(): void {
        $generator = \core\di::get(generator::class);
        $this->expectException(\coding_exception::class);
        $generator->mod_glossary->create_entry([
            'concept' => 'orphan',
        ]);
    }
}
