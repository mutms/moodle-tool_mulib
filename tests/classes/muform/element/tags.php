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
 * Behat helper for tags element.
 *
 * Table values are comma separated tag names; with JavaScript each name is typed into the
 * tag field and confirmed with a comma, or the matching suggestion is clicked when only standard
 * tags may be used; an empty cell removes all tags. Without JavaScript the names go into the
 * fallback input. Expected values are compared as sets of names.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tags extends base {
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
        while ($button = $this->wrapper->find('css', '[data-muform-tags-pill] button')) {
            $button->click();
            \behat_base::wait_for_pending_js_in_session($session);
        }
        $combobox = $this->find('css', 'input[role="combobox"]');
        $standardonly = (bool)$this->wrapper->find('css', '[data-muform-tags-standardonly]');
        foreach ($values as $item) {
            if ($standardonly) {
                $combobox->setValue($item);
                \behat_base::wait_for_pending_js_in_session($session);
                $this->wait_for_option($item)->click();
            } else {
                // A comma ends the tag the same way as Enter.
                $combobox->setValue($item . ',');
            }
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
        return $this->same_keys($this->split_values($expected), $this->split_values($this->get_value()));
    }

    /**
     * Wait for a suggestion with the name.
     *
     * @param string $item
     * @return \Behat\Mink\Element\NodeElement
     */
    private function wait_for_option(string $item): \Behat\Mink\Element\NodeElement {
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            foreach ($this->find_all('css', '[data-muform-tags-option]') as $option) {
                if (trim($option->getText()) === $item) {
                    return $option;
                }
            }
            usleep(200000);
        }
        throw new ExpectationException(
            'Tag suggestion "' . $item . '" not found in muform element "' . $this->get_name() . '"',
            $this->context->getSession()
        );
    }

    #[\Override]
    public function type_search(string $text): void {
        $this->find('css', 'input[role="combobox"]')->setValue($text);
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
    }
}
