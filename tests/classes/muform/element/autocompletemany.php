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
 * Behat helper for autocompletemany element.
 *
 * Table values are comma separated values (usually ids) or starts of visible labels; with
 * JavaScript each value is typed into the picker and the matching result picked, an empty cell
 * removes all pills. A search that yields a single result is picked as well. Expected values
 * match the values or the starts of the pill labels.
 * Without JavaScript the values go into the fallback input.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autocompletemany extends base {
    #[\Override]
    public function set_value(string $value): void {
        $values = $this->split_values($value);
        $fallback = $this->wrapper->find('css', 'input[type="text"][name]');
        if ($fallback) {
            $fallback->setValue(implode(',', $values));
            return;
        }

        $session = $this->context->getSession();
        // Removing a pill renders the list again, stored nodes of the other pills go stale.
        while ($button = $this->wrapper->find('css', '[data-muform-autocomplete-pill] button')) {
            $button->click();
            \behat_base::wait_for_pending_js_in_session($session);
        }
        $combobox = $this->find('css', 'input[role="combobox"]');
        foreach ($values as $item) {
            $combobox->setValue($item);
            \behat_base::wait_for_pending_js_in_session($session);
            $option = $this->wait_for_option($item);
            $option->click();
            \behat_base::wait_for_pending_js_in_session($session);
        }
    }

    #[\Override]
    public function get_value(): string {
        $hidden = $this->wrapper->find('css', 'input[type="hidden"][name]');
        if ($hidden) {
            return (string)$hidden->getValue();
        }
        $fallback = $this->wrapper->find('css', 'input[type="text"][name]');
        if ($fallback) {
            return (string)$fallback->getValue();
        }
        return '';
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = $this->split_values($expected);
        $actual = $this->split_values($this->get_value());
        if ($this->same_keys($expected, $actual)) {
            return true;
        }
        $labels = $this->get_labels();
        if (count($expected) !== count($actual) || count($labels) !== count($actual)) {
            return false;
        }
        foreach ($expected as $item) {
            if (!in_array($item, $actual, true) && !$this->label_matches($item, $labels)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Wait for a result matching the value or its label.
     *
     * @param string $item
     * @return \Behat\Mink\Element\NodeElement
     */
    private function wait_for_option(string $item): \Behat\Mink\Element\NodeElement {
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            $option = $this->wrapper->find('css', '[data-muform-autocomplete-option="' . $item . '"]');
            if (!$option) {
                foreach ($this->find_all('css', '[data-muform-autocomplete-option]') as $candidate) {
                    $text = trim($candidate->getText());
                    if ($text === $item || str_starts_with($text, $item . ' ')) {
                        // Labels usually carry extra details after the name, such as the email.
                        $option = $candidate;
                        break;
                    }
                }
            }
            if (!$option) {
                // A search with exactly one result is unambiguous too.
                $options = $this->find_all('css', '[data-muform-autocomplete-option]');
                if (count($options) === 1) {
                    $option = reset($options);
                }
            }
            if ($option) {
                return $option;
            }
            usleep(200000);
        }
        throw new ExpectationException(
            'Autocomplete option "' . $item . '" not found in muform element "' . $this->get_name() . '"',
            $this->context->getSession()
        );
    }

    /**
     * Visible labels of the selected values, only available with JavaScript.
     *
     * @return string[]
     */
    private function get_labels(): array {
        $labels = [];
        foreach ($this->find_all('css', '[data-muform-autocomplete-pill]') as $node) {
            $labels[] = trim($node->getText());
        }
        return $labels;
    }

    /**
     * Does the expected text match one of the labels, exactly or as its start?
     *
     * @param string $expected
     * @param string[] $labels
     * @return bool
     */
    private function label_matches(string $expected, array $labels): bool {
        foreach ($labels as $label) {
            if ($label === $expected || str_starts_with($label, $expected . ' ')) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function type_search(string $text): void {
        $this->find('css', 'input[role="combobox"]')->setValue($text);
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
    }
}
