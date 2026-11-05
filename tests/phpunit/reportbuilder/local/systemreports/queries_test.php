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

namespace tool_mulib\phpunit\reportbuilder\local\systemreports;

use tool_mulib\local\extdb\query;
use tool_mulib\reportbuilder\local\systemreports\queries;
use core_reportbuilder\system_report_factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * External database queries report tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\reportbuilder\local\systemreports\queries::class)]
#[Group('MuTMS')]
final class queries_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns rendered report.
     *
     * @return string html
     */
    private function get_report_html(): string {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mulib/extdb/queries.php');
        // Report instances are cached per request and user.
        \core_reportbuilder\manager::reset_caches();
        $report = system_report_factory::create(queries::class, \core\context\system::instance());
        return $report->output();
    }

    public function test_output(): void {
        /** @var \tool_mulib_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mulib');
        $server = $generator->create_extdb_server(['name' => 'Some server']);

        query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 1',
            'component' => 'tool_muprog',
            'type' => 'allocation',
            'sqlquery' => 'SELECT id AS userid FROM m_user',
            'note' => 'Some note',
        ]);
        query::create((object)[
            'serverid' => $server->id,
            'name' => 'Query 2',
            'component' => 'tool_mucohort',
            'type' => 'rule',
            'sqlquery' => 'SELECT username FROM m_user',
        ]);

        $this->setAdminUser();
        $html = $this->get_report_html();
        $this->assertStringContainsString('Query 1', $html);
        $this->assertStringContainsString('Program allocation', $html);
        $this->assertStringContainsString('SELECT id AS userid FROM m_user', $html);
        $this->assertStringContainsString('Some note', $html);
        $this->assertStringContainsString('Query 2', $html);
        $this->assertStringContainsString('Cohort rule', $html);
        $this->assertStringContainsString('Some server', $html);
    }
}
