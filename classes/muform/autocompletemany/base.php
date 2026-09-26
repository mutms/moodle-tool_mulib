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

namespace tool_mulib\muform\autocompletemany;

/**
 * Source of a multiple values autocomplete element.
 *
 * A source owns the whole picker: the constructor takes scalar arguments only and does
 * the access control (require_capability etc.), because the same class is instantiated
 * in the form definition and again in the tool_mulib autocompletemany endpoint from get_args().
 * Subclasses live in <component>\muform\autocompletemany namespace, the endpoint refuses
 * anything else. Labels are HTML already passed through clean_text().
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
     * @param string[] $exclude values already selected
     * @return array|null [value => label html], null when more than $maxitems match
     */
    abstract public function search(string $query, int $maxitems, array $exclude): ?array;

    /**
     * Labels of existing values the current user may select, unknown values are left out.
     *
     * @param string[] $values
     * @return array [value => label html]
     */
    abstract public function labels(array $values): array;

    /**
     * Why existing values must not be selected.
     *
     * @param string[] $values
     * @return array [value => error text], empty when all may be selected
     */
    public function validate(array $values): array {
        return [];
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
