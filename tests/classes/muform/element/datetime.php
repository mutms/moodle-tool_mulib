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
 * Behat helper for datetime element.
 *
 * Table values are timestamps (##tomorrow noon## works), empty string for no value,
 * or any text the server can parse. Timestamps are formatted with the timezone
 * and display format the page was rendered with before typing them.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class datetime extends base {
    #[\Override]
    public function set_value(string $value): void {
        $value = trim($value);
        $input = $this->get_text_input();
        if (preg_match('/^-?\d+$/D', $value)) {
            $value = calendar::describe((int)$value, $this->get_timezone($input), $this->get_format($input))['text'];
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
        $carrier = $this->wrapper->find('css', 'input[type="hidden"]');
        if ($carrier && preg_match('/^-?\d+$/D', (string)$carrier->getValue())) {
            return (string)$carrier->getValue();
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
        $input = $this->get_text_input();
        $tz = $this->get_timezone($input);
        $format = $this->get_format($input);
        try {
            return calendar::parse($expected, $tz, $format) === calendar::parse($actual, $tz, $format);
        } catch (\InvalidArgumentException $e) {
            return false;
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
     * Display format the page was rendered with.
     *
     * @param NodeElement $input
     * @return string
     */
    private function get_format(NodeElement $input): string {
        return (string)$input->getAttribute('data-muform-datetime-format');
    }
}
