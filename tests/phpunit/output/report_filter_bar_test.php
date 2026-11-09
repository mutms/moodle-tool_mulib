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

namespace tool_mulib\phpunit\output;

use core_admin\reportbuilder\local\systemreports\users;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use tool_mulib\output\report_filter_bar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Report filter bar tests.
 *
 * Core users system report is used because it has all kinds of filters.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\output\report_filter_bar::class)]
#[Group('MuTMS')]
final class report_filter_bar_test extends \advanced_testcase {
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

    /**
     * Export filter bar data with decoded filters keyed by filter identifier.
     *
     * @param system_report $report
     * @return array
     */
    private function export(system_report $report): array {
        global $PAGE;
        $bar = new report_filter_bar($report);
        $data = $bar->export_for_template($PAGE->get_renderer('core'));
        $filters = [];
        foreach (json_decode($data['filters'], true) as $filter) {
            $filters[$filter['key']] = $filter;
        }
        $data['filters'] = $filters;
        return $data;
    }

    public function test_export_for_template(): void {
        global $PAGE;
        $report = $this->create_report();
        $data = $this->export($report);

        $this->assertTrue($data['hasfilters']);
        $this->assertSame((int)$report->get_report_persistent()->get('id'), $data['reportid']);
        $this->assertSame(json_encode($report->get_parameters()), $data['parameters']);
        $this->assertStringStartsWith('tool_mulib_report_filter_bar', $data['uniqid']);

        $this->assertSame([
            'key' => 'user:username',
            'label' => 'Username',
            'operator' => 0,
            'value' => '',
            'options' => [],
            'type' => 'text',
        ], $data['filters']['user:username']);
        $this->assertSame('boolean', $data['filters']['user:suspended']['type']);
        $this->assertSame([], $data['filters']['user:suspended']['options']);
        $this->assertSame('select', $data['filters']['user:country']['type']);
        $this->assertContains(['value' => 'CZ', 'label' => 'Czechia'], $data['filters']['user:country']['options']);

        // Date and course role filters can be edited in the core form only.
        $this->assertSame([
            'key' => 'user:lastaccess',
            'label' => 'Last access',
            'operator' => 0,
            'value' => '',
            'options' => [],
            'type' => 'other',
            'values' => [],
            'active' => false,
        ], $data['filters']['user:lastaccess']);
        $this->assertSame('other', $data['filters']['user:courserole']['type']);
        $this->assertFalse($data['filters']['user:courserole']['active']);

        $bar = new report_filter_bar($report);
        $this->assertSame('tool_mulib/report_filter_bar', $bar->get_template_name($PAGE->get_renderer('core')));
    }

    public function test_export_for_template_values(): void {
        $report = $this->create_report();
        $reportid = (int)$report->get_report_persistent()->get('id');

        user_filter_manager::set($reportid, [
            'user:username_operator' => text::CONTAINS,
            'user:username_value' => 'alp',
            'user:country_operator' => select::EQUAL_TO,
            'user:country_value' => 'CZ',
            'user:suspended_operator' => boolean_select::CHECKED,
        ]);
        $data = $this->export($this->create_report());
        $this->assertSame(text::CONTAINS, $data['filters']['user:username']['operator']);
        $this->assertSame('alp', $data['filters']['user:username']['value']);
        $this->assertArrayNotHasKey('operatorlabel', $data['filters']['user:username']);
        $this->assertSame(select::EQUAL_TO, $data['filters']['user:country']['operator']);
        $this->assertSame('CZ', $data['filters']['user:country']['value']);
        $this->assertArrayNotHasKey('operatorlabel', $data['filters']['user:country']);
        $this->assertSame(boolean_select::CHECKED, $data['filters']['user:suspended']['operator']);

        // Operators the bar does not offer are described.
        user_filter_manager::set($reportid, [
            'user:username_operator' => text::STARTS_WITH,
            'user:username_value' => 'gam',
            'user:country_operator' => select::NOT_EQUAL_TO,
            'user:country_value' => 'DE',
        ]);
        $data = $this->export($this->create_report());
        $this->assertSame(text::STARTS_WITH, $data['filters']['user:username']['operator']);
        $this->assertSame('Starts with', $data['filters']['user:username']['operatorlabel']);
        $this->assertSame('Is not equal to', $data['filters']['user:country']['operatorlabel']);

        // Core "any value" option of simplified select filters.
        user_filter_manager::set($reportid, [
            'role:name_operator' => select::EQUAL_TO,
            'role:name_value' => '-124567',
        ]);
        $data = $this->export($this->create_report());
        $this->assertSame(0, $data['filters']['role:name']['operator']);
        $this->assertSame('', $data['filters']['role:name']['value']);

        // Values of filters that are not in the bar are exported unchanged.
        $values = [
            'user:lastaccess_operator' => date::DATE_EMPTY,
            'user:lastaccess_value' => 1,
            'user:courserole_role' => '0',
            'user:courserole_category' => '0',
            'user:courserole_course' => 'C1',
        ];
        user_filter_manager::set($reportid, $values);
        $data = $this->export($this->create_report());
        $this->assertTrue($data['filters']['user:lastaccess']['active']);
        $this->assertSame(['user:lastaccess_operator' => date::DATE_EMPTY, 'user:lastaccess_value' => 1], $data['filters']['user:lastaccess']['values']);
        $this->assertTrue($data['filters']['user:courserole']['active']);
        $this->assertSame(['user:courserole_role' => '0', 'user:courserole_category' => '0', 'user:courserole_course' => 'C1'], $data['filters']['user:courserole']['values']);
        $this->assertSame(0, $data['filters']['user:username']['operator']);

        $values['user:courserole_course'] = '';
        user_filter_manager::set($reportid, $values);
        $data = $this->export($this->create_report());
        $this->assertFalse($data['filters']['user:courserole']['active']);

        user_filter_manager::reset($reportid);
        $data = $this->export($this->create_report());
        $this->assertFalse($data['filters']['user:lastaccess']['active']);
        $this->assertSame([], $data['filters']['user:lastaccess']['values']);
    }
}
