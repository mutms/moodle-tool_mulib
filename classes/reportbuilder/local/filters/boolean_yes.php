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

use core_reportbuilder\local\filters\boolean_select;

/**
 * Boolean filter that filters only when set to "Yes", for options that restrict a report when switched on.
 *
 * "No" of the core boolean filter selects the opposite records, which makes no sense for filters
 * such as "Exclude sub-categories" (it would show sub-categories only). Here "No" does nothing,
 * the same as any value. Use {@see self::OPERATORS} to hide "No" in the core filters form,
 * the report filter bar offers "Yes" only:
 *
 *     $filter = (new filter(boolean_yes::class, 'currentcontextonly', $header, $entityname, $sql))
 *         ->set_limited_operators(boolean_yes::OPERATORS);
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boolean_yes extends boolean_select {
    /** @var int[] operators to pass to set_limited_operators() of the filter */
    public const OPERATORS = [self::ANY_VALUE, self::CHECKED];

    #[\Override]
    public function get_sql_filter(array $values): array {
        if ((int)($values["{$this->name}_operator"] ?? self::ANY_VALUE) !== self::CHECKED) {
            return ['', []];
        }
        return parent::get_sql_filter($values);
    }
}
