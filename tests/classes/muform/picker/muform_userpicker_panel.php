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

namespace tool_mulib\tests\muform\picker;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat helper of the users picker panel (tool_mulib/muform/userpicker/panel).
 *
 * Items are user ids, full names or values of visible identity fields such as emails: each is typed
 * into the search field and the matching row picked, a search with exactly one result is picked as well.
 * Filters of the panel are removed first, so that any user that may be picked is found, such as suspended users.
 * Pill labels start with the full name followed by identity fields.
 *
 * Loaded by panel_base::get_class().
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class muform_userpicker_panel extends panel_base {
    #[\Override]
    public static function is_ready(NodeElement $panel): bool {
        return (bool)$panel->find('css', '[data-userpicker-search]');
    }

    #[\Override]
    public function search(string $text): void {
        $this->panel->find('css', '[data-userpicker-search]')->setValue($text);
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
        // Typing is debounced, wait until the search started.
        usleep(300000);
    }

    #[\Override]
    public function pick(string $item): void {
        $this->remove_filters();
        $this->search(ctype_digit($item) ? '' : $item);
        $this->wait_for_row($item)->click();
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
    }

    #[\Override]
    public function confirm(): void {
        $this->panel->find('css', '[data-userpicker-add]')->click();
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
    }

    #[\Override]
    public static function label_matches(string $label, string $item): bool {
        return $label === $item || str_starts_with($label, $item . ' ');
    }

    /**
     * Remove values of all filters, default values hide users such as the suspended ones.
     */
    private function remove_filters(): void {
        $session = $this->context->getSession();
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            if ($this->panel->find('css', '[aria-busy="true"]')) {
                usleep(100000);
                continue;
            }
            $remove = $this->panel->find('css', '[data-picker-filter-remove]');
            if (!$remove) {
                return;
            }
            $remove->click();
            \behat_base::wait_for_pending_js_in_session($session);
        }
        throw new ExpectationException('Filters cannot be removed in ' . $this->what, $session);
    }

    /**
     * Wait for the row of a user given by id, full name or identity field value; one row alone matches too.
     *
     * @param string $item
     * @return NodeElement
     */
    private function wait_for_row(string $item): NodeElement {
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            // Rows of the previous search are replaced when the answer arrives.
            if (!$this->panel->find('css', '[aria-busy="true"]')) {
                $rows = $this->panel->findAll('css', '[data-userpicker-row]');
                foreach ($rows as $row) {
                    foreach (['data-userpicker-row', 'data-userpicker-name'] as $attribute) {
                        if ($row->getAttribute($attribute) === $item) {
                            return $row;
                        }
                    }
                    foreach ($row->findAll('css', '[data-userpicker-field]') as $cell) {
                        if (trim($cell->getText()) === $item) {
                            return $row;
                        }
                    }
                }
                if (count($rows) === 1 && !ctype_digit($item)) {
                    return reset($rows);
                }
            }
            usleep(200000);
        }
        throw new ExpectationException('User "' . $item . '" not found in ' . $this->what, $this->context->getSession());
    }
}
