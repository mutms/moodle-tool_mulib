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

namespace tool_mulib\muform\picker;

use core\exception\coding_exception;
use tool_mulib\local\sql;

/**
 * Filters of a picker source with the values of one request, for endpoints of picker families.
 *
 * The panel sends no values until the user changes a filter, default values apply then;
 * later it sends the values of all filters, filters without a valid value do not restrict the list.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter_set {
    /** @var filter[] indexed with keys */
    private array $filters = [];

    /** @var array normalised values indexed with keys, null for filters without a value */
    private array $values = [];

    /**
     * Constructor.
     *
     * @param filter[] $filters
     * @param array|null $values submitted values indexed with filter keys, null for the default values
     */
    public function __construct(array $filters, ?array $values) {
        foreach ($filters as $filter) {
            if (!$filter instanceof filter) {
                throw new coding_exception('Picker filters must be instances of ' . filter::class);
            }
            $key = $filter->get_key();
            if (isset($this->filters[$key])) {
                throw new coding_exception('Duplicate picker filter: ' . $key);
            }
            $this->filters[$key] = $filter;
            $this->values[$key] = $filter->normalise(($values === null) ? $filter->get_default() : ($values[$key] ?? null));
        }
    }

    /**
     * Values from the body of an endpoint request.
     *
     * @param mixed $raw object or array of values indexed with filter keys
     * @return array|null null when the panel did not send any, default values apply then
     */
    public static function clean_values(mixed $raw): ?array {
        if (!is_array($raw) && !is_object($raw)) {
            return null;
        }
        $values = [];
        foreach ((array)$raw as $key => $value) {
            if (is_scalar($value)) {
                $values[(string)$key] = (string)$value;
            }
        }
        return $values;
    }

    /**
     * Localised strings of the tool_mulib/muform/pickerfilters component, to be added to the panel strings.
     *
     * @return string[]
     */
    public static function get_panel_strings(): array {
        return [
            'filteradd' => get_string('filterbar_add', 'tool_mulib'),
            'filterremove' => get_string('filterbar_remove', 'tool_mulib'),
            'filterresetall' => get_string('filterbar_resetall', 'tool_mulib'),
            'filterresetdefault' => get_string('filterbar_resetdefault', 'tool_mulib'),
            'filternoresults' => get_string('muform_noresults', 'tool_mulib'),
            'filtersearching' => get_string('muform_searching', 'tool_mulib'),
            'filtertoomanyresults' => get_string('muform_toomanyresults', 'tool_mulib'),
            'search' => get_string('muform_search', 'tool_mulib'),
        ];
    }

    /**
     * Value of a filter.
     *
     * @param string $key
     * @return string|null null when the filter has no value or does not exist
     */
    public function get_value(string $key): ?string {
        return $this->values[$key] ?? null;
    }

    /**
     * Conditions of filters with values, each in parentheses.
     *
     * @return sql[]
     */
    public function get_sqls(): array {
        $result = [];
        foreach ($this->filters as $key => $filter) {
            $sql = $filter->get_sql($this->values[$key]);
            if ($sql) {
                $result[] = $sql->wrap('(', ')');
            }
        }
        return $result;
    }

    /**
     * Filters with their values for the panel.
     *
     * @return array[]
     */
    public function export(): array {
        $result = [];
        foreach ($this->filters as $key => $filter) {
            $result[] = $filter->export($this->values[$key]);
        }
        return $result;
    }
}
