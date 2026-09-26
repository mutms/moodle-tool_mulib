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

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat helper for info element, displayed text can be checked but not set.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class info extends base {
    #[\Override]
    public function set_value(string $value): void {
        throw new ExpectationException(
            'Display muform element "' . $this->get_name() . '" cannot be set',
            $this->context->getSession()
        );
    }

    #[\Override]
    public function get_value(): string {
        return trim((string)$this->wrapper->find('css', '.form-control-plaintext, .col-12')?->getText());
    }
}
