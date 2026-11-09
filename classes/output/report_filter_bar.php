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
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\system_report;

/**
 * Always visible filter bar for system reports, replaces the core filters dropdown.
 *
 * Filter values are stored in the standard report builder user filters,
 * the bar only provides different UI. Supported filter types are text, select and boolean select; values with
 * operators the bar does not offer (for example "starts with" saved in the core form) are kept and described
 * with the core operator name until the user changes them. Other filter types (dates, course role, etc.)
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

    /** @var int report persistent id */
    private int $reportid;

    /** @var string json encoded report parameters */
    private string $parameters;

    /** @var string private core value of "any value" option in simplified select filters, {@see select::OPTION_ANY_VALUE} */
    private const SELECT_ANY_VALUE = '-124567';

    /** @var array filters data */
    private array $filters = [];

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

    #[\Override]
    public function export_for_template(\core\output\renderer_base $output): array {
        return [
            'hasfilters' => !empty($this->filters),
            'reportid' => $this->reportid,
            'parameters' => $this->parameters,
            'filters' => json_encode($this->filters),
            'uniqid' => \html_writer::random_id('tool_mulib_report_filter_bar'),
        ];
    }

    #[\Override]
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'tool_mulib/report_filter_bar';
    }
}
