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

namespace tool_mulib\phpunit\output;

use core_admin\reportbuilder\local\systemreports\users;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use tool_mulib\output\report_pager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Report pager tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\output\report_pager::class)]
#[Group('MuTMS')]
final class report_pager_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create users report.
     *
     * @return system_report
     */
    private function create_report(): system_report {
        return system_report_factory::create(users::class, \core\context\system::instance());
    }

    public function test_export_for_template(): void {
        global $PAGE;
        $report = $this->create_report();
        $reportid = (int)$report->get_report_persistent()->get('id');
        $this->assertSame(30, $report->get_default_per_page());

        $pager = new report_pager($report);
        $data = $pager->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame($reportid, $data['reportid']);
        // The default page size of the report is added.
        $this->assertSame('[10,25,30,50,100]', $data['sizes']);
        $this->assertFalse($data['filtered']);
        $this->assertTrue($data['download']);
        $this->assertStringStartsWith('tool_mulib_report_pager', $data['uniqid']);
        $this->assertSame('tool_mulib/report_pager', $pager->get_template_name($PAGE->get_renderer('core')));

        $report->set_default_per_page(50);
        $data = (new report_pager($report, download: false))->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame('[10,25,50,100]', $data['sizes']);
        $this->assertFalse($data['download']);

        user_filter_manager::set($reportid, [
            'user:username_operator' => text::CONTAINS,
            'user:username_value' => 'alp',
        ]);
        $data = (new report_pager($this->create_report()))->export_for_template($PAGE->get_renderer('core'));
        $this->assertTrue($data['filtered']);

        // Stored values that do not filter are not counted.
        user_filter_manager::set($reportid, [
            'user:username_operator' => text::CONTAINS,
            'user:username_value' => '',
        ]);
        $data = (new report_pager($this->create_report()))->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($data['filtered']);
    }
}
