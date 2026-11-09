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

namespace tool_mulib\output;

use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use tool_mulib\muform\util\calendar;

/**
 * Always visible filter bar for system reports, replaces the core filters dropdown.
 *
 * Filter values are stored in the standard report builder user filters,
 * the bar only provides different UI. Supported filter types are text, select, boolean select and date; values with
 * operators the bar does not offer (for example "starts with" saved in the core form) are kept and described
 * with the core operator name until the user changes them. Other filter types (course role, number, etc.)
 * are shown by name only when used, they can be removed in the bar and changed in the core form only.
 *
 * The core filters dropdown is moved into the bar as "Advanced filters" button, it is highlighted
 * when a value is set that only the core form can edit; page is reloaded after core form changes.
 *
 * Must be created before the report is rendered:
 *
 *     $report = system_report_factory::create(...);
 *     echo $OUTPUT->render(new report_filter_bar($report));
 *     echo $report->output();
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_filter_bar implements \core\output\named_templatable, \core\output\renderable {
    /** @var array text filter operators set elsewhere (core filter form), the bar itself uses "contains" only */
    private const TEXT_OPERATORS = [
        text::DOES_NOT_CONTAIN => 'filterdoesnotcontain',
        text::IS_EQUAL_TO => 'filterisequalto',
        text::IS_NOT_EQUAL_TO => 'filterisnotequalto',
        text::STARTS_WITH => 'filterstartswith',
        text::ENDS_WITH => 'filterendswith',
        text::IS_EMPTY => 'filterisempty',
        text::IS_NOT_EMPTY => 'filterisnotempty',
    ];

    /** @var array date filter operators in the order of the core filter form, the bar offers all of them */
    private const DATE_OPERATORS = [
        date::DATE_RANGE => 'filterrange',
        date::DATE_LAST => 'filterdatelast',
        date::DATE_CURRENT => 'filterdatecurrent',
        date::DATE_NEXT => 'filterdatenext',
        date::DATE_BEFORE => 'filterdatebefore',
        date::DATE_AFTER => 'filterdateafter',
        date::DATE_PAST => 'filterdatepast',
        date::DATE_FUTURE => 'filterdatefuture',
        date::DATE_NOT_EMPTY => 'filterisnotempty',
        date::DATE_EMPTY => 'filterisempty',
    ];

    /** @var array date filter units */
    private const DATE_UNITS = [
        date::DATE_UNIT_MINUTE => 'filterdateminutes',
        date::DATE_UNIT_HOUR => 'filterdatehours',
        date::DATE_UNIT_DAY => 'filterdatedays',
        date::DATE_UNIT_WEEK => 'filterdateweeks',
        date::DATE_UNIT_MONTH => 'filterdatemonths',
        date::DATE_UNIT_YEAR => 'filterdateyears',
    ];

    /** @var int report persistent id */
    private int $reportid;

    /** @var string json encoded report parameters */
    private string $parameters;

    /** @var string private core value of "any value" option in simplified select filters, {@see select::OPTION_ANY_VALUE} */
    private const SELECT_ANY_VALUE = '-124567';

    /** @var array filters data */
    private array $filters = [];

    /** @var array|null date and time settings of the user, null if there are no date filters */
    private ?array $datetime = null;

    /**
     * Constructor.
     *
     * @param system_report $report
     */
    public function __construct(system_report $report) {
        $this->reportid = (int)$report->get_report_persistent()->get('id');
        $this->parameters = json_encode($report->get_parameters());

        $values = $report->get_filter_values();
        foreach ($report->get_filters() as $filter) {
            $class = $filter->get_filter_class();
            $key = $filter->get_unique_identifier();
            $operator = (int)($values[$key . '_operator'] ?? 0);
            $value = $values[$key . '_value'] ?? '';
            $value = is_scalar($value) ? (string)$value : '';
            $data = [
                'key' => $key,
                'label' => $filter->get_header(),
                'operator' => $operator,
                'value' => $value,
                'options' => [],
            ];
            if (is_a($class, boolean_select::class, true)) {
                $data['type'] = 'boolean';
            } else if (is_a($class, select::class, true)) {
                $data['type'] = 'select';
                if ($value === self::SELECT_ANY_VALUE) {
                    // Core form uses this instead of operator in selects with up to two options.
                    $data['operator'] = 0;
                    $data['value'] = '';
                } else if ($operator === select::NOT_EQUAL_TO) {
                    $data['operatorlabel'] = get_string('filterisnotequalto', 'core_reportbuilder');
                }
                foreach ((array)$filter->get_options() as $optionvalue => $optionlabel) {
                    if (is_array($optionlabel)) {
                        // Option groups.
                        foreach ($optionlabel as $groupvalue => $grouplabel) {
                            $data['options'][] = ['value' => (string)$groupvalue, 'label' => (string)$grouplabel];
                        }
                    } else {
                        $data['options'][] = ['value' => (string)$optionvalue, 'label' => (string)$optionlabel];
                    }
                }
            } else if (is_a($class, date::class, true)) {
                $data = $this->export_date($filter, $data, $values);
            } else if (is_a($class, text::class, true)) {
                $data['type'] = 'text';
                if (isset(self::TEXT_OPERATORS[$operator])) {
                    $data['operatorlabel'] = get_string(self::TEXT_OPERATORS[$operator], 'core_reportbuilder');
                }
            } else {
                // Only the core form can edit this filter, the bar shows it when used and keeps its values.
                $data['type'] = 'other';
                $data['operator'] = 0;
                $data['value'] = '';
                $data['values'] = [];
                foreach ($values as $name => $filtervalue) {
                    if (str_starts_with($name, $key . '_')) {
                        $data['values'][$name] = $filtervalue;
                    }
                }
                [$sql] = $class::create($filter)->get_sql_filter($data['values']);
                $data['active'] = ($sql !== '');
            }
            $this->filters[] = $data;
        }
    }

    /**
     * Export date filter.
     *
     * The value is the number of units of relative operators, ranges are timestamps
     * with texts in the user timezone, the browser does not interpret dates itself.
     *
     * @param filter $filter
     * @param array $data common filter data
     * @param array $values stored filter values
     * @return array
     */
    private function export_date(filter $filter, array $data, array $values): array {
        $key = $data['key'];
        $data['type'] = 'date';
        $data['value'] = (string)(int)($values[$key . '_value'] ?? 1);
        $data['unit'] = (int)($values[$key . '_unit'] ?? date::DATE_UNIT_DAY);
        $data['from'] = (int)($values[$key . '_from'] ?? 0);
        $data['to'] = (int)($values[$key . '_to'] ?? 0);

        $this->datetime ??= $this->get_datetime_settings();
        $tz = new \DateTimeZone($this->datetime['timezone']);
        $data['fromtext'] = $data['from'] ? calendar::describe($data['from'], $tz, $this->datetime['lang'])['text'] : '';
        $data['totext'] = $data['to'] ? calendar::describe($data['to'], $tz, $this->datetime['lang'])['text'] : '';

        $operators = [];
        foreach (self::DATE_OPERATORS as $operator => $string) {
            $operators[$operator] = get_string($string, 'core_reportbuilder');
        }
        foreach ($filter->restrict_limited_operators($operators) as $operator => $label) {
            $data['options'][] = ['value' => (string)$operator, 'label' => $label];
        }
        return $data;
    }

    /**
     * Date and time settings of the current user for date filters.
     *
     * @return array
     */
    private function get_datetime_settings(): array {
        $timezone = \core_date::get_user_timezone();
        $lang = current_language();
        $units = [];
        foreach (self::DATE_UNITS as $unit => $string) {
            $units[] = ['value' => (string)$unit, 'label' => get_string($string, 'core_reportbuilder')];
        }
        if ($timezone === \core_date::get_server_timezone()) {
            // Users in site timezone do not need to be reminded.
            $timezonelabel = '';
        } else {
            $timezonelabel = get_string('muform_timezone', 'tool_mulib', calendar::get_timezone_name($timezone));
        }
        return [
            'timezone' => $timezone,
            'timezonelabel' => $timezonelabel,
            'lang' => $lang,
            'firstday' => \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday(),
            'hour12' => calendar::uses_12_hour_clock($lang),
            'units' => $units,
        ];
    }

    #[\Override]
    public function export_for_template(\core\output\renderer_base $output): array {
        return [
            'hasfilters' => !empty($this->filters),
            'reportid' => $this->reportid,
            'parameters' => $this->parameters,
            'filters' => json_encode($this->filters),
            'datetime' => json_encode($this->datetime),
            'uniqid' => \html_writer::random_id('tool_mulib_report_filter_bar'),
        ];
    }

    #[\Override]
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'tool_mulib/report_filter_bar';
    }
}
