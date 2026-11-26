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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\local\extdb;

use tool_mulib\local\extdb\management;
use tool_mulib\local\extdb\query;
use tool_mulib\tests\extdb\fake_queries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * External database management tables tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(management::class)]
#[Group('MuTMS')]
final class management_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        fake_queries::register();
    }

    public function test_tables(): void {
        global $DB;

        /** @var \tool_mulib_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mulib');
        $server1 = $generator->create_extdb_server(['name' => 'Some server']);
        $generator->create_extdb_server(['name' => 'Unused server']);
        query::create((object)[
            'serverid' => $server1->id,
            'name' => 'Query 1',
            'component' => 'tool_mulib',
            'type' => 'alpha',
            'sqlquery' => 'SELECT id AS userid FROM m_user',
            'note' => 'Some note',
        ]);
        $DB->insert_record('tool_mulib_extdb_query', (object)[
            'name' => 'Query 2',
            'contextid' => \context_system::instance()->id,
            'serverid' => $server1->id,
            'component' => 'tool_unknown',
            'type' => 'xyz<b>',
            'sqlquery' => 'SELECT * FROM m_user',
        ]);

        $this->setAdminUser();
        $servers = management::get_servers_table();
        $this->assertSame(['Some server', 'Unused server'], array_column($servers->data, 0));
        $this->assertStringContainsString('server_update.php', $servers->data[0][5]);
        $this->assertStringNotContainsString('server_delete.php', $servers->data[0][5]);
        $this->assertStringContainsString('server_delete.php', $servers->data[1][5]);

        $queries = management::get_queries_table();
        $this->assertSame(['Query 1', 'Query 2'], array_column($queries->data, 0));
        $this->assertSame(['Alpha query', 'xyz&lt;b&gt;'], array_column($queries->data, 2));
        $this->assertSame('Some server', $queries->data[0][3]);
        $this->assertSame('<pre>SELECT id AS userid FROM m_user</pre>', $queries->data[0][4]);
        $this->assertSame('Some note', $queries->data[0][5]);
        $this->assertStringContainsString('query_delete.php', $queries->data[0][6]);
        $html = \html_writer::table($queries);
        $this->assertStringContainsString('Alpha query', $html);

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mulib:useextdb', CAP_ALLOW, $roleid, \context_system::instance());
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $this->assertSame(['', ''], array_column(management::get_queries_table()->data, 6));
    }
}
