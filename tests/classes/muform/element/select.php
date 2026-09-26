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

namespace tool_mulib\tests\muform\element;

/**
 * Behat helper for select element, value is option key or exact option label.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class select extends base {
    #[\Override]
    public function set_value(string $value): void {
        $key = $this->resolve_option(trim($value), $this->get_options());
        $this->find('css', 'select')->selectOption($key);
    }

    #[\Override]
    public function get_value(): string {
        return (string)$this->find('css', 'select')->getValue();
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = trim($expected);
        if ($expected !== '') {
            $expected = $this->resolve_option($expected, $this->get_options());
        }
        return $this->get_value() === $expected;
    }
}
