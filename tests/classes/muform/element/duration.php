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
 * Behat helper for duration element.
 *
 * Table values are seconds, empty string means zero. The seconds are split
 * into the unit inputs the element renders (name[w], name[d], name[h], name[i], name[s]).
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class duration extends base {
    /** @var array seconds per unit key */
    private const array SECONDS = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'i' => 60, 's' => 1];

    #[\Override]
    public function set_value(string $value): void {
        $value = trim($value);
        if ($value === '') {
            $value = '0';
        }
        if (!preg_match('/^\d+$/D', $value)) {
            throw new ExpectationException(
                'Duration value must be a number of seconds in muform element "' . $this->get_name() . '"',
                $this->context->getSession()
            );
        }
        $seconds = (int)$value;
        foreach ($this->get_inputs() as $unit => $input) {
            $number = intdiv($seconds, self::SECONDS[$unit]);
            $seconds -= $number * self::SECONDS[$unit];
            $input->setValue($number ? (string)$number : '');
        }
        if ($seconds !== 0) {
            throw new ExpectationException(
                'Duration value ' . $value . ' cannot be entered with the units of muform element "' . $this->get_name() . '"',
                $this->context->getSession()
            );
        }
    }

    #[\Override]
    public function get_value(): string {
        $total = 0;
        foreach ($this->get_inputs() as $unit => $input) {
            $number = trim((string)$input->getValue());
            if ($number === '') {
                continue;
            }
            if (!is_numeric($number)) {
                return $number;
            }
            $total += (int)$number * self::SECONDS[$unit];
        }
        return (string)$total;
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = trim($expected);
        if ($expected === '') {
            $expected = '0';
        }
        return $expected === $this->get_value();
    }

    /**
     * Unit inputs rendered for this element, largest unit first.
     *
     * @return array inputs indexed by unit key
     */
    private function get_inputs(): array {
        $inputs = [];
        foreach ($this->find_all('css', 'input[data-muform-unit]') as $input) {
            $inputs[(string)$input->getAttribute('data-muform-unit')] = $input;
        }
        return $inputs;
    }
}
