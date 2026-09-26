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

namespace tool_mulib\phpunit\muform\fixtures;

use tool_mulib\muform\autocompletemany\base;

/**
 * Fake multiple values source: fixed options, one refused value.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_sources extends base {
    /** @var array fixed options */
    public const array OPTIONS = ['a' => '<b>Alpha</b>', 'b' => 'Beta', 'c' => 'Gamma'];

    /** @var string refused value */
    private string $refused;

    /**
     * Constructor.
     *
     * @param string $refused value that validate() refuses
     */
    public function __construct(string $refused = '') {
        $this->refused = $refused;
    }

    #[\Override]
    public function get_args(): array {
        return [$this->refused];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        if ($query === 'overflow') {
            return null;
        }
        $result = [];
        foreach (self::OPTIONS as $value => $label) {
            if (in_array($value, $exclude, true)) {
                continue;
            }
            if ($query === '' || stripos(strip_tags($label), $query) !== false) {
                $result[$value] = $label;
            }
        }
        return $result;
    }

    #[\Override]
    public function labels(array $values): array {
        return array_intersect_key(self::OPTIONS, array_flip($values));
    }

    #[\Override]
    public function validate(array $values): array {
        return in_array($this->refused, $values, true) ? [$this->refused => 'Not allowed'] : [];
    }
}
