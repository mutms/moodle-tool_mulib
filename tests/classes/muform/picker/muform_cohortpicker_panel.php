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
 * Behat helper of the cohorts picker panel (tool_mulib/muform/cohortpicker/panel).
 *
 * Items are cohort ids, names or ID numbers: each is typed into the search field and the matching row
 * picked, a search with exactly one result is picked as well. Pill labels are cohort names.
 *
 * Loaded by panel_base::get_class().
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class muform_cohortpicker_panel extends panel_base {
    #[\Override]
    public static function is_ready(NodeElement $panel): bool {
        return (bool)$panel->find('css', '[data-cohortpicker-search]');
    }

    #[\Override]
    public function search(string $text): void {
        $this->panel->find('css', '[data-cohortpicker-search]')->setValue($text);
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
        // Typing is debounced, wait until the search started.
        usleep(300000);
    }

    #[\Override]
    public function pick(string $item): void {
        $this->search(ctype_digit($item) ? '' : $item);
        $this->wait_for_row($item)->click();
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
    }

    #[\Override]
    public function confirm(): void {
        $this->panel->find('css', '[data-cohortpicker-add]')->click();
        \behat_base::wait_for_pending_js_in_session($this->context->getSession());
    }

    /**
     * Wait for the row of a cohort given by id, name or ID number; one row alone matches too.
     *
     * @param string $item
     * @return NodeElement
     */
    private function wait_for_row(string $item): NodeElement {
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            // Rows of the previous search are replaced when the answer arrives.
            if (!$this->panel->find('css', '[aria-busy="true"]')) {
                $rows = $this->panel->findAll('css', '[data-cohortpicker-row]');
                foreach ($rows as $row) {
                    foreach (['data-cohortpicker-row', 'data-cohortpicker-name', 'data-cohortpicker-idnumber'] as $attribute) {
                        if ($row->getAttribute($attribute) === $item) {
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
        throw new ExpectationException('Cohort "' . $item . '" not found in ' . $this->what, $this->context->getSession());
    }
}
