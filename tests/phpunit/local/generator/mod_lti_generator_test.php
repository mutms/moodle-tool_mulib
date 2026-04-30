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
 * LTI generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_lti_generator
 */
final class mod_lti_generator_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_create_activity(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'LTGT1']);

        $lti = $generator->mod_lti->create_activity([
            'course' => $course,
            'name' => 'Test LTI',
            'toolurl' => 'https://example.com/lti',
        ]);

        $this->assertSame('Test LTI', $lti->name);
        $this->assertObjectHasProperty('cmid', $lti);
        $this->assertTrue($DB->record_exists('lti', ['id' => $lti->id]));
    }
}
