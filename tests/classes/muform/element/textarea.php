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
 * Behat helper for textarea element.
 *
 * A literal backslash n in a table cell means a line break, in expected values too.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class textarea extends base {
    #[\Override]
    public function set_value(string $value): void {
        $this->find('css', 'input, textarea')->setValue(str_replace('\\n', "\n", $value));
    }

    #[\Override]
    public function get_value(): string {
        return str_replace(["\r\n", "\r"], "\n", (string)$this->find('css', 'input, textarea')->getValue());
    }

    #[\Override]
    public function matches(string $expected): bool {
        return trim(str_replace('\\n', "\n", $expected)) === trim($this->get_value());
    }
}
