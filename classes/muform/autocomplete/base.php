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

namespace tool_mulib\muform\autocomplete;

use core\exception\coding_exception;

/**
 * Source of autocomplete and autocompletemany elements.
 *
 * A source owns the whole picker: the constructor takes scalar arguments only and does
 * the access control (require_capability etc.), because the same class is instantiated
 * in the form definition and again in the tool_mulib autocomplete endpoint from get_args().
 * Subclasses live in <component>\muform\autocomplete namespace, the endpoint refuses
 * anything else. The same source serves both elements, the element decides whether one
 * or several values are selected. Labels are HTML already passed through clean_text().
 *
 * Labels and validation work either per value (label_value(), validate_value()) or for all values
 * at once (labels(), validate()), override whichever is simpler.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Constructor arguments as a list of scalars, used to reinstantiate the source in the endpoint.
     *
     * @return array
     */
    abstract public function get_args(): array;

    /**
     * Search for options.
     *
     * @param string $query
     * @param int $maxitems
     * @param string[] $exclude values already selected, the endpoint removes them from the result anyway
     * @return array|null [value => label html], null when more than $maxitems match
     */
    abstract public function search(string $query, int $maxitems, array $exclude): ?array;

    /**
     * Labels of existing values the current user may select, unknown values are left out.
     *
     * @param string[] $values
     * @return array [value => label html]
     */
    public function labels(array $values): array {
        $result = [];
        foreach ($values as $value) {
            $label = $this->label_value((string)$value);
            if ($label !== null) {
                $result[(string)$value] = $label;
            }
        }
        return $result;
    }

    /**
     * Label of one existing value the current user may select, used by the default labels().
     *
     * @param string $value
     * @return string|null label html, null when unknown or not allowed
     */
    protected function label_value(string $value): ?string {
        throw new coding_exception(static::class . ' must override labels() or label_value()');
    }

    /**
     * Why existing values must not be selected, called only for values known to labels().
     *
     * @param string[] $values
     * @return array [value => error text], empty when all may be selected
     */
    public function validate(array $values): array {
        $result = [];
        foreach ($values as $value) {
            $error = $this->validate_value((string)$value);
            if ($error !== null) {
                $result[(string)$value] = $error;
            }
        }
        return $result;
    }

    /**
     * Why one existing value must not be selected, used by the default validate().
     *
     * @param string $value
     * @return string|null error text, null when it may be selected
     */
    protected function validate_value(string $value): ?string {
        return null;
    }

    /**
     * Maximum number of search results.
     *
     * @return int
     */
    public function get_maxitems(): int {
        return 50;
    }
}
