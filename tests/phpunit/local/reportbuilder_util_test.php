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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\local;

use tool_mulib\local\reportbuilder_util;

/**
 * Report builder helper tests.
 *
 * @group      MuTMS
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\reportbuilder_util
 */
final class reportbuilder_util_test extends \advanced_testcase {
    public function test_is_downloading(): void {
        global $SCRIPT;
        $oldscript = $SCRIPT;

        $SCRIPT = '/admin/index.php';
        $this->assertFalse(reportbuilder_util::is_downloading());
        $SCRIPT = '/reportbuilder/download.php';
        $this->assertTrue(reportbuilder_util::is_downloading());

        $SCRIPT = $oldscript;
    }

    public function test_format_badge(): void {
        global $SCRIPT;
        $oldscript = $SCRIPT;

        $SCRIPT = '/admin/index.php';
        $this->assertSame(
            '<span class="badge rounded-pill text-bg-danger">A &amp; B</span>',
            reportbuilder_util::format_badge('A & B', 'text-bg-danger')
        );
        $SCRIPT = '/reportbuilder/download.php';
        $this->assertSame('A & B', reportbuilder_util::format_badge('A & B', 'text-bg-danger'));

        $SCRIPT = $oldscript;
    }
}
