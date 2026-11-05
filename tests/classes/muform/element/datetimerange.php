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

use Behat\Mink\Element\NodeElement;
use tool_mulib\muform\util\calendar;

/**
 * Behat helper for datetimerange element.
 *
 * Table values are two timestamps separated with a hyphen ("##tomorrow 10:00##-##tomorrow 11:00##"),
 * empty string for no value, or any range text the server can parse, such as "2026-11-06 23:30 - 01:30".
 * Timestamps are formatted with the timezone and language the page was rendered with before typing them.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class datetimerange extends base {
    /** @var string two timestamps */
    private const string TIMESTAMPS_REGEX = '/^\s*(-?\d+)\s*-\s*(-?\d+)\s*$/D';

    #[\Override]
    public function set_value(string $value): void {
        $value = trim($value);
        $input = $this->get_text_input();
        if (preg_match(self::TIMESTAMPS_REGEX, $value, $matches)) {
            $range = ['start' => (int)$matches[1], 'end' => (int)$matches[2]];
            $value = calendar::describe_range($range, $this->get_timezone($input), $this->get_lang($input))['text'];
        }
        $input->setValue($value);
        $session = $this->context->getSession();
        if (get_class($session->getDriver()) !== 'Behat\\Mink\\Driver\\BrowserKitDriver') {
            // Leaving the input triggers the normalisation request.
            $input->blur();
            \behat_base::wait_for_pending_js_in_session($session);
        }
    }

    #[\Override]
    public function get_value(): string {
        $start = $this->wrapper->find('css', 'input[type="hidden"][name$="[start]"]');
        $end = $this->wrapper->find('css', 'input[type="hidden"][name$="[end]"]');
        if ($start && $end) {
            $value = $start->getValue() . '-' . $end->getValue();
            if (preg_match(self::TIMESTAMPS_REGEX, $value)) {
                return $value;
            }
        }
        return (string)$this->get_text_input()->getValue();
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = trim($expected);
        $actual = trim($this->get_value());
        if ($expected === $actual) {
            return true;
        }
        if ($expected === '' || $actual === '') {
            return false;
        }
        $parsedexpected = $this->parse($expected);
        return $parsedexpected !== null && $parsedexpected === $this->parse($actual);
    }

    /**
     * Range from timestamps or text.
     *
     * @param string $value
     * @return array|null null when invalid
     */
    private function parse(string $value): ?array {
        if (preg_match(self::TIMESTAMPS_REGEX, $value, $matches)) {
            return ['start' => (int)$matches[1], 'end' => (int)$matches[2]];
        }
        $input = $this->get_text_input();
        $duration = (int)$input->getAttribute('data-muform-datetimerange-defaultduration');
        try {
            return calendar::parse_range(
                $value,
                $this->get_timezone($input),
                $this->get_lang($input),
                (int)$input->getAttribute('data-muform-datetime-defaulthour'),
                (int)$input->getAttribute('data-muform-datetime-defaultminute'),
                $duration > 0 ? $duration : null
            );
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * The visible text input.
     *
     * @return NodeElement
     */
    private function get_text_input(): NodeElement {
        return $this->find('css', 'input[data-muform-datetime-timezone]');
    }

    /**
     * Timezone the page was rendered with.
     *
     * @param NodeElement $input
     * @return \DateTimeZone
     */
    private function get_timezone(NodeElement $input): \DateTimeZone {
        return new \DateTimeZone((string)$input->getAttribute('data-muform-datetime-timezone'));
    }

    /**
     * Language the page was rendered with, it decides the display format.
     *
     * @param NodeElement $input
     * @return string
     */
    private function get_lang(NodeElement $input): string {
        return (string)$input->getAttribute('data-muform-datetime-lang');
    }
}
