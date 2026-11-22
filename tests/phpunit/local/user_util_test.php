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
// phpcs:disable moodle.Files.LineLength.MaxExceeded

namespace tool_mulib\phpunit\local;

use tool_mulib\local\user_util;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * User account helper tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_util::class)]
#[Group('MuTMS')]
final class user_util_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns ids of users passing PHP check.
     *
     * @param callable $callback
     * @return int[]
     */
    private function get_php_userids(callable $callback): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records('user', [], 'id ASC') as $record) {
            if ($callback($record)) {
                $result[] = (int)$record->id;
            }
        }
        return $result;
    }

    public function test_is_suspendable(): void {
        $generator = $this->getDataGenerator();
        $admin2 = $generator->create_user();
        $active = $generator->create_user();
        $suspended = $generator->create_user(['suspended' => 1]);
        $remote = $generator->create_user(['mnethostid' => 123]);
        $deleted = $generator->create_user();
        delete_user($deleted);
        $other = $generator->create_user();
        set_config('siteadmins', get_admin()->id . ',' . $admin2->id);

        $this->setAdminUser();
        $this->assertSame([(int)$active->id, (int)$other->id], $this->get_php_userids([user_util::class, 'is_suspendable']));
        $this->assertSame([(int)$suspended->id], $this->get_php_userids([user_util::class, 'is_unsuspendable']));

        $this->setUser($other);
        $this->assertSame([(int)$active->id], $this->get_php_userids([user_util::class, 'is_suspendable']));
        $this->assertSame([(int)$suspended->id], $this->get_php_userids([user_util::class, 'is_unsuspendable']));

        $fields = (object)['id' => $active->id, 'deleted' => '0', 'mnethostid' => $active->mnethostid, 'suspended' => '0'];
        $this->assertTrue(user_util::is_suspendable($fields));
    }

    public function test_suspend_unsuspend(): void {
        global $DB;
        $user1 = $this->getDataGenerator()->create_user();
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        user_util::suspend($user1);
        $events = $sink->get_events();
        $sink->close();
        $user1 = $DB->get_record('user', ['id' => $user1->id]);
        $this->assertSame('1', $user1->suspended);
        $this->assertInstanceOf(\core\event\user_updated::class, reset($events));

        try {
            user_util::suspend($user1);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }

        user_util::unsuspend($user1);
        $user1 = $DB->get_record('user', ['id' => $user1->id]);
        $this->assertSame('0', $user1->suspended);

        try {
            user_util::unsuspend($user1);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\coding_exception::class, $ex);
        }
    }
}
