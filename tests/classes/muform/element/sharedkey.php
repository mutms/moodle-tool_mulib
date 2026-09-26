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
 * Behat helper for sharedkey element.
 *
 * Table value "[clear]" ticks the clear checkbox, anything else is typed as the new key,
 * an empty cell keeps the current key. Matching an untouched input compares the expected
 * value with the current key from the data attribute.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharedkey extends base {
    /** @var string table value that ticks the clear checkbox */
    public const string CLEAR = '[clear]';

    #[\Override]
    public function set_value(string $value): void {
        $clear = $this->wrapper->find('css', 'input[type="checkbox"]');
        if (trim($value) === self::CLEAR) {
            $this->find('css', 'input[type="checkbox"]')->check();
            return;
        }
        if ($clear && $clear->isChecked()) {
            $clear->uncheck();
        }
        $this->find('css', 'input[type="text"]')->setValue($value);
    }

    #[\Override]
    public function get_value(): string {
        $clear = $this->wrapper->find('css', 'input[type="checkbox"]');
        if ($clear && $clear->isChecked()) {
            return self::CLEAR;
        }
        $input = $this->find('css', 'input[type="text"]');
        $value = (string)$input->getValue();
        if ($value === '') {
            return (string)$input->getAttribute('data-muform-sharedkey-current');
        }
        return $value;
    }
}
