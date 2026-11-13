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
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use tool_mulib\output\report_filter_bar;
use tool_mulib\reportbuilder\local\filters\boolean_yes;
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
#[CoversClass(\tool_mulib\reportbuilder\local\filters\boolean_yes::class)]
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
     * @param array $pinned
     * @param array $defaults
     * @return array
     */
    private function export(system_report $report, array $pinned = [], array $defaults = []): array {
        global $PAGE;
        $bar = new report_filter_bar($report, $pinned, $defaults);
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

        $lastaccess = $data['filters']['user:lastaccess'];
        $this->assertSame('date', $lastaccess['type']);
        $this->assertSame(0, $lastaccess['operator']);
        $this->assertSame('1', $lastaccess['value']);
        $this->assertSame(date::DATE_UNIT_DAY, $lastaccess['unit']);
        $this->assertSame(0, $lastaccess['from']);
        $this->assertSame(0, $lastaccess['to']);
        $this->assertSame('', $lastaccess['fromtext']);
        $this->assertSame('', $lastaccess['totext']);
        $this->assertSame(['value' => (string)date::DATE_RANGE, 'label' => 'Range'], $lastaccess['options'][0]);
        $this->assertCount(10, $lastaccess['options']);

        // Course role filter can be edited in the core form only.
        $this->assertSame([
            'key' => 'user:courserole',
            'label' => 'Course role',
            'operator' => 0,
            'value' => '',
            'options' => [],
            'type' => 'other',
            'values' => [],
            'active' => false,
        ], $data['filters']['user:courserole']);

        $datetime = json_decode($data['datetime'], true);
        $this->assertSame('Australia/Perth', $datetime['timezone']);
        $this->assertSame('', $datetime['timezonelabel']);
        $this->assertSame('en', $datetime['lang']);
        $this->assertFalse($datetime['hour12']);
        $this->assertContains(['value' => (string)date::DATE_UNIT_WEEK, 'label' => 'week(s)'], $datetime['units']);

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
            'user:courserole_role' => '0',
            'user:courserole_category' => '0',
            'user:courserole_course' => 'C1',
        ];
        user_filter_manager::set($reportid, $values);
        $data = $this->export($this->create_report());
        $this->assertTrue($data['filters']['user:courserole']['active']);
        $this->assertSame(['user:courserole_role' => '0', 'user:courserole_category' => '0', 'user:courserole_course' => 'C1'], $data['filters']['user:courserole']['values']);
        $this->assertSame(0, $data['filters']['user:username']['operator']);

        $values['user:courserole_course'] = '';
        user_filter_manager::set($reportid, $values);
        $data = $this->export($this->create_report());
        $this->assertFalse($data['filters']['user:courserole']['active']);

        user_filter_manager::reset($reportid);
        $data = $this->export($this->create_report());
        $this->assertFalse($data['filters']['user:courserole']['active']);
        $this->assertSame([], $data['filters']['user:courserole']['values']);
    }

    public function test_export_for_template_pinned(): void {
        $report = $this->create_report();
        $reportid = (int)$report->get_report_persistent()->get('id');

        $unpinned = array_keys($this->export($report)['filters']);
        $this->assertNotSame('user:suspended', $unpinned[0]);
        $this->assertArrayNotHasKey('pinned', $this->export($report)['filters']['user:country']);
        $this->assertArrayNotHasKey('anylabel', $this->export($report)['filters']['user:country']);

        // Pinned filters come first in the given order, filters that are not in the report are ignored.
        $pinned = ['user:suspended' => null, 'user:unknown' => 'Nothing', 'user:country' => 'All countries', 'user:courserole' => null];
        $data = $this->export($report, $pinned);
        $this->assertSame(
            ['user:suspended', 'user:country', 'user:courserole', ...array_values(array_diff($unpinned, array_keys($pinned)))],
            array_keys($data['filters'])
        );
        $this->assertTrue($data['filters']['user:suspended']['pinned']);
        $this->assertSame('Any', $data['filters']['user:suspended']['anylabel']);
        $this->assertTrue($data['filters']['user:country']['pinned']);
        $this->assertSame('All countries', $data['filters']['user:country']['anylabel']);
        $this->assertSame(0, $data['filters']['user:country']['operator']);
        $this->assertTrue($data['filters']['user:courserole']['pinned']);
        $this->assertSame('other', $data['filters']['user:courserole']['type']);
        $this->assertArrayNotHasKey('pinned', $data['filters']['user:username']);
        $this->assertArrayNotHasKey('anylabel', $data['filters']['user:username']);

        // Values of pinned filters are exported as usual.
        user_filter_manager::set($reportid, [
            'user:country_operator' => select::EQUAL_TO,
            'user:country_value' => 'CZ',
        ]);
        $data = $this->export($this->create_report(), $pinned);
        $this->assertSame(select::EQUAL_TO, $data['filters']['user:country']['operator']);
        $this->assertSame('CZ', $data['filters']['user:country']['value']);
        $this->assertSame('All countries', $data['filters']['user:country']['anylabel']);
    }

    public function test_export_for_template_defaults(): void {
        $report = $this->create_report();
        $reportid = (int)$report->get_report_persistent()->get('id');
        $defaults = [
            'user:suspended' => ['operator' => boolean_select::NOT_CHECKED],
            'user:country' => ['operator' => select::EQUAL_TO, 'value' => 'CZ'],
            'user:timecreated' => ['operator' => date::DATE_CURRENT, 'unit' => date::DATE_UNIT_YEAR],
            'user:unknown' => ['operator' => 1],
        ];

        // Nothing changes without defaults.
        $data = $this->export($report);
        $this->assertSame([], user_filter_manager::get($reportid));
        $this->assertArrayNotHasKey('default', $data['filters']['user:suspended']);

        // Defaults are stored for users without stored filters, filters that are not in the report are ignored.
        $data = $this->export($this->create_report(), [], $defaults);
        $this->assertEquals([
            'user:suspended_operator' => boolean_select::NOT_CHECKED,
            'user:country_operator' => select::EQUAL_TO,
            'user:country_value' => 'CZ',
            'user:timecreated_operator' => date::DATE_CURRENT,
            'user:timecreated_unit' => date::DATE_UNIT_YEAR,
        ], user_filter_manager::get($reportid));
        $this->assertSame(boolean_select::NOT_CHECKED, $data['filters']['user:suspended']['operator']);
        $this->assertSame(['operator' => boolean_select::NOT_CHECKED, 'value' => ''], $data['filters']['user:suspended']['default']);
        $this->assertSame('CZ', $data['filters']['user:country']['value']);
        $this->assertSame(['operator' => select::EQUAL_TO, 'value' => 'CZ'], $data['filters']['user:country']['default']);
        $this->assertSame(date::DATE_CURRENT, $data['filters']['user:timecreated']['operator']);
        $this->assertSame(
            ['operator' => date::DATE_CURRENT, 'value' => '1', 'unit' => date::DATE_UNIT_YEAR, 'from' => 0, 'to' => 0, 'fromtext' => '', 'totext' => ''],
            $data['filters']['user:timecreated']['default']
        );
        $this->assertArrayNotHasKey('default', $data['filters']['user:username']);
        $this->assertSame(3, $this->create_report()->get_applied_filter_count());

        // Stored values are kept, also when the user removed all filters.
        user_filter_manager::set($reportid, ['user:suspended_operator' => boolean_select::ANY_VALUE]);
        $data = $this->export($this->create_report(), [], $defaults);
        $this->assertEquals(['user:suspended_operator' => boolean_select::ANY_VALUE], user_filter_manager::get($reportid));
        $this->assertSame(boolean_select::ANY_VALUE, $data['filters']['user:suspended']['operator']);
        $this->assertSame(['operator' => boolean_select::NOT_CHECKED, 'value' => ''], $data['filters']['user:suspended']['default']);
        $this->assertSame(0, $data['filters']['user:country']['operator']);

        // Core "Reset all" deletes stored values, defaults are back.
        user_filter_manager::reset($reportid);
        $data = $this->export($this->create_report(), [], $defaults);
        $this->assertSame(boolean_select::NOT_CHECKED, $data['filters']['user:suspended']['operator']);
        $this->assertSame('CZ', $data['filters']['user:country']['value']);

        // Other users have their own values.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], user_filter_manager::get($reportid));
    }

    public function test_export_for_template_html(): void {
        $report = $this->create_report();
        $report->get_filter('user:username')->set_header(new \lang_string('lang_string_a', 'tool_mulib', 'R&amp;D <b>x</b> "y"'));
        $data = $this->export($report, ['user:username' => '<i>All</i> &amp; any', 'user:country' => null]);
        // Tags are neutralised and entities are not encoded twice, the browser decodes the result to plain text.
        $this->assertSame('R&#38;D &#60;b&#62;x&#60;/b&#62; &#34;y&#34;', $data['filters']['user:username']['label']);
        $this->assertSame('&#60;i&#62;All&#60;/i&#62; &#38; any', $data['filters']['user:username']['anylabel']);
        $this->assertSame('Any', $data['filters']['user:country']['anylabel']);
        $this->assertContains(['value' => 'CI', 'label' => 'Côte d&#39;Ivoire'], $data['filters']['user:country']['options']);
    }

    public function test_export_for_template_boolean_yes(): void {
        $report = $this->create_report();
        $reportid = (int)$report->get_report_persistent()->get('id');

        // Core boolean filters offer both values.
        $this->assertSame([], $this->export($report)['filters']['user:suspended']['options']);

        $report = $this->create_report();
        $report->get_filter('user:suspended')->set_limited_operators([boolean_select::ANY_VALUE, boolean_select::NOT_CHECKED]);
        user_filter_manager::set($reportid, ['user:suspended_operator' => boolean_select::CHECKED]);
        $suspended = $this->export($report)['filters']['user:suspended'];
        $this->assertSame([['value' => (string)boolean_select::NOT_CHECKED, 'label' => 'No']], $suspended['options']);
        $this->assertSame(0, $suspended['operator']);

        // Filter that does nothing when set to "No".
        $filter = new filter(boolean_yes::class, 'onlyyes', new \lang_string('yes'), 'user', '1');
        $instance = boolean_yes::create($filter);
        $this->assertSame(['', []], $instance->get_sql_filter([]));
        $this->assertSame(['', []], $instance->get_sql_filter(['user:onlyyes_operator' => boolean_select::ANY_VALUE]));
        $this->assertSame(['', []], $instance->get_sql_filter(['user:onlyyes_operator' => boolean_select::NOT_CHECKED]));
        $this->assertFalse($instance->applies_to_values(['user:onlyyes_operator' => boolean_select::NOT_CHECKED]));
        [$sql, $params] = $instance->get_sql_filter(['user:onlyyes_operator' => boolean_select::CHECKED]);
        $this->assertStringStartsWith('1 = :', $sql);
        $this->assertSame([1], array_values($params));
        $this->assertTrue($instance->applies_to_values(['user:onlyyes_operator' => boolean_select::CHECKED]));
    }

    public function test_export_for_template_dates(): void {
        global $USER;
        // Server timezone is Australia/Perth in tests.
        $USER->timezone = 'Europe/Prague';
        $report = $this->create_report();
        $reportid = (int)$report->get_report_persistent()->get('id');

        $from = (new \DateTimeImmutable('2026-01-01 00:00', new \DateTimeZone('Europe/Prague')))->getTimestamp();
        $to = (new \DateTimeImmutable('2026-01-31 23:59', new \DateTimeZone('Europe/Prague')))->getTimestamp();
        user_filter_manager::set($reportid, [
            'user:lastaccess_operator' => date::DATE_RANGE,
            'user:lastaccess_value' => 1,
            'user:lastaccess_unit' => date::DATE_UNIT_DAY,
            'user:lastaccess_from' => $from,
            'user:lastaccess_to' => $to,
            'user:timecreated_operator' => date::DATE_LAST,
            'user:timecreated_value' => 2,
            'user:timecreated_unit' => date::DATE_UNIT_WEEK,
            'user:timecreated_from' => 0,
            'user:timecreated_to' => 0,
        ]);
        $data = $this->export($this->create_report());
        $lastaccess = $data['filters']['user:lastaccess'];
        $this->assertSame(date::DATE_RANGE, $lastaccess['operator']);
        $this->assertSame($from, $lastaccess['from']);
        $this->assertSame($to, $lastaccess['to']);
        $this->assertSame('2026-01-01 00:00', $lastaccess['fromtext']);
        $this->assertSame('2026-01-31 23:59', $lastaccess['totext']);
        $this->assertArrayNotHasKey('operatorlabel', $lastaccess);
        $timecreated = $data['filters']['user:timecreated'];
        $this->assertSame(date::DATE_LAST, $timecreated['operator']);
        $this->assertSame('2', $timecreated['value']);
        $this->assertSame(date::DATE_UNIT_WEEK, $timecreated['unit']);
        $this->assertSame('', $timecreated['fromtext']);

        $datetime = json_decode($data['datetime'], true);
        $this->assertSame('Europe/Prague', $datetime['timezone']);
        $this->assertSame('Time zone: Europe/Prague', $datetime['timezonelabel']);
    }

    public function test_export_for_template_limited_date_operators(): void {
        $report = $this->create_report();
        $report->get_filter('user:lastaccess')->set_limited_operators([date::DATE_ANY, date::DATE_EMPTY, date::DATE_LAST]);
        $data = $this->export($report);
        $this->assertSame([
            ['value' => (string)date::DATE_LAST, 'label' => 'Last'],
            ['value' => (string)date::DATE_EMPTY, 'label' => 'Is empty'],
        ], $data['filters']['user:lastaccess']['options']);
    }
}
