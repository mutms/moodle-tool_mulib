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

namespace tool_mulib\phpunit;

use tool_mulib\tests\fake_langpack;

/**
 * Fake language pack test helper tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\tests\fake_langpack
 */
final class fake_langpack_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_install(): void {
        $this->assertSame(['cs', 'de', 'en_us'], fake_langpack::available());
        $this->assertArrayNotHasKey('en_us', get_string_manager()->get_list_of_translations());

        fake_langpack::install('en_us');
        $this->assertArrayHasKey('en_us', get_string_manager()->get_list_of_translations());
        $this->assertSame('%m/%d/%y', get_string_manager()->get_string('strftimedatefullshort', 'langconfig', null, 'en_us'));
        $this->assertSame('%d/%m/%y', get_string_manager()->get_string('strftimedatefullshort', 'langconfig', null, 'en'));
        // Strings missing in child pack come from en.
        $this->assertSame('Monday', get_string_manager()->get_string('monday', 'core_calendar', null, 'en_us'));
    }
}
