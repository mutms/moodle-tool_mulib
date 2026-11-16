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

namespace tool_mulib\reportbuilder\local\filters;

use core_reportbuilder\local\filters\autocomplete;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;

/**
 * Filter of checkboxes - records matching any of the checked options, nothing checked does not filter.
 *
 * Stored values are the same as of the core autocomplete filter (`<name>_values` list), the core filters form
 * shows it as a multiple autocomplete, the report filter bar as checkboxes. Option values must not be
 * '' or '0' (the empty choice of the core autocomplete form).
 *
 * The filter may replace a core select filter with the same unique identifier without losing values stored
 * by users: select values (`<name>_operator`, `<name>_value`) are read when there are no checkbox values -
 * "is equal to" checks the option, "is not equal to" checks all other options. The next save stores
 * checkbox values only, core replaces all stored values of the report.
 *
 * Options are plain values of the filter field by default, override get_option_sql() for options that are
 * conditions of their own, for example "past", "current" and "future" of a period:
 *
 *     $filters[] = (new filter(my_period::class, 'period', $header, $entityname, "{$alias}.timefrom"))
 *         ->set_options(['past' => ..., 'current' => ..., 'future' => ...]);
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkboxes extends autocomplete {
    /** @var string private core value of "any value" option in simplified select filters, {@see select::OPTION_ANY_VALUE} */
    private const SELECT_ANY_VALUE = '-124567';

    /**
     * Options of the filter.
     *
     * @return array value => label
     */
    final public function get_options(): array {
        return $this->get_select_options();
    }

    /**
     * Checked options from stored filter values, including values stored by a core select filter.
     *
     * @param array $values stored filter values of the report
     * @return string[] checked option values in the order of options
     */
    final public function get_checked(array $values): array {
        $options = array_map('strval', array_keys($this->get_options()));
        if (array_key_exists("{$this->name}_values", $values)) {
            $checked = array_map('strval', (array)$values["{$this->name}_values"]);
        } else {
            $operator = (int)($values["{$this->name}_operator"] ?? select::ANY_VALUE);
            $value = (string)($values["{$this->name}_value"] ?? '');
            if ($value === '' || $value === self::SELECT_ANY_VALUE) {
                $checked = [];
            } else if ($operator === select::EQUAL_TO) {
                $checked = [$value];
            } else if ($operator === select::NOT_EQUAL_TO) {
                $checked = array_diff($options, [$value]);
            } else {
                $checked = [];
            }
        }
        return array_values(array_intersect($options, $checked));
    }

    /**
     * SQL condition of one checked option that is not a plain value of the filter field.
     *
     * Conditions must not use the filter field SQL (its parameters could be used only once), plain values
     * are compared with one IN.
     *
     * @param string $value option value
     * @return array|null [sql, params], null means the option is a plain value of the filter field
     */
    protected function get_option_sql(string $value): ?array {
        return null;
    }

    #[\Override]
    public function get_sql_filter(array $values): array {
        global $DB;

        $checked = $this->get_checked($values);
        if (!$checked) {
            return ['', []];
        }
        $conditions = [];
        $params = [];
        $plain = [];
        foreach ($checked as $value) {
            $option = $this->get_option_sql($value);
            if ($option === null) {
                $plain[] = $value;
                continue;
            }
            $conditions[] = '(' . $option[0] . ')';
            $params = array_merge($params, $option[1]);
        }
        if ($plain) {
            [$insql, $inparams] = $DB->get_in_or_equal($plain, SQL_PARAMS_NAMED, database::generate_param_name('_'));
            $conditions[] = "{$this->filter->get_field_sql()} $insql";
            $params = array_merge($params, $this->filter->get_field_params(), $inparams);
        }
        return ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    #[\Override]
    public function get_sample_values(): array {
        $options = array_keys($this->get_options());
        return [
            "{$this->name}_values" => $options ? [(string)reset($options)] : [],
        ];
    }
}
