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
use tool_mulib\muform\util\calendar;

/**
 * Behat helper for dateinterval element.
 *
 * Table values are ISO 8601 duration strings such as P1Y6M or P2DT12H,
 * empty string means no value. Expected values are compared in canonical form.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dateinterval extends base {
    #[\Override]
    public function set_value(string $value): void {
        $value = trim($value);
        $parts = ($value === '') ? [] : calendar::parse_interval($value);
        if ($parts === null) {
            throw new ExpectationException(
                'Date interval value must be an ISO 8601 duration in muform element "' . $this->get_name() . '"',
                $this->context->getSession()
            );
        }
        $inputs = $this->get_inputs();
        foreach ($parts as $unit => $number) {
            if ($number && !isset($inputs[$unit])) {
                throw new ExpectationException(
                    'Date interval value ' . $value . ' cannot be entered with the units of muform element "'
                    . $this->get_name() . '"',
                    $this->context->getSession()
                );
            }
        }
        foreach ($inputs as $unit => $input) {
            $number = $parts[$unit] ?? 0;
            $input->setValue($number ? (string)$number : '');
        }
    }

    #[\Override]
    public function get_value(): string {
        $parts = [];
        foreach ($this->get_inputs() as $unit => $input) {
            $number = trim((string)$input->getValue());
            if ($number === '') {
                continue;
            }
            if (!preg_match('/^\d+$/D', $number)) {
                return $number;
            }
            $parts[$unit] = (int)$number;
        }
        return (string)calendar::build_interval($parts);
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = trim($expected);
        if ($expected !== '') {
            $parts = calendar::parse_interval($expected);
            $expected = ($parts === null) ? $expected : (string)calendar::build_interval($parts);
        }
        return $expected === $this->get_value();
    }

    /**
     * Unit inputs rendered for this element.
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
