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

namespace tool_mulib\phpunit\muform\util;

use tool_mulib\muform\util\initial_values;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Initial values tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(initial_values::class)]
#[Group('MuTMS')]
final class initial_values_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    public function test_get_ids(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertNull(initial_values::get_ids('ids', 10));

        $_POST = ['ids' => '3,1,3', 'sesskey' => sesskey()];
        $this->assertSame([3, 1], initial_values::get_ids('ids', 10));
        $this->assertNull(initial_values::get_ids('users', 10));

        $_POST = ['ids' => '', 'sesskey' => sesskey()];
        $this->assertSame([], initial_values::get_ids('ids', 10));

        $_POST = ['ids' => '1,2', 'sesskey' => sesskey(), '__formid' => 'x'];
        $this->assertNull(initial_values::get_ids('ids', 10));

        $_POST = ['ids' => '1,2,3', 'sesskey' => sesskey()];
        try {
            initial_values::get_ids('ids', 2);
            $this->fail('Exception expected');
        } catch (\core\exception\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Too many ids', $e->getMessage());
        }

        foreach (['1,a', '1,,2', '-1', ['1']] as $value) {
            $_POST = ['ids' => $value, 'sesskey' => sesskey()];
            try {
                initial_values::get_ids('ids', 10);
                $this->fail('Exception expected');
            } catch (\core\exception\invalid_parameter_exception $e) {
                $this->assertStringContainsString('Invalid ids', $e->getMessage());
            }
        }

        $_POST = ['ids' => '1,2'];
        $this->expectException(\core\exception\moodle_exception::class);
        initial_values::get_ids('ids', 10);
    }
}
