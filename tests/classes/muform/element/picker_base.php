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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../picker/panel_base.php');

use Behat\Mink\Exception\ExpectationException;
use tool_mulib\tests\muform\picker\panel_base;

/**
 * Behat helper base of picker and pickermany elements, JavaScript only.
 *
 * Table values are comma separated items understood by the helper of the panel family (panel_base):
 * the current pills are removed, the panel is opened and each item is picked there.
 * An empty cell only removes the pills. Expected values match the submitted values or pill labels.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class picker_base extends base {
    #[\Override]
    public function set_value(string $value): void {
        $session = $this->context->getSession();
        // Removing a pill renders the list again, stored nodes of the other pills go stale.
        while ($button = $this->wrapper->find('css', '[data-picker-pill] button')) {
            $button->click();
            \behat_base::wait_for_pending_js_in_session($session);
        }
        $values = $this->split_values($value);
        if (!$values) {
            return;
        }
        $multiple = (bool)$this->wrapper->find('css', '[data-picker-multiple]');
        if (!$multiple && count($values) > 1) {
            throw new ExpectationException('muform element "' . $this->get_name() . '" takes one value only', $session);
        }
        $panel = $this->open_panel();
        foreach ($values as $item) {
            $panel->pick($item);
        }
        if ($multiple) {
            $panel->confirm();
        }
        // Panels may load labels of picked values from the server.
        \behat_base::wait_for_pending_js_in_session($session);
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline && count($this->find_all('css', '[data-picker-pill]')) < count($values)) {
            usleep(100000);
        }
    }

    #[\Override]
    public function get_value(): string {
        $hidden = $this->wrapper->find('css', 'input[type="hidden"][name]');
        return $hidden ? (string)$hidden->getValue() : '';
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = $this->split_values($expected);
        $actual = $this->split_values($this->get_value());
        if ($this->same_keys($expected, $actual)) {
            return true;
        }
        $labels = [];
        foreach ($this->find_all('css', '[data-picker-pill]') as $node) {
            $labels[] = trim(preg_replace('/\s+/', ' ', $node->getText()));
        }
        if (count($expected) !== count($actual) || count($labels) !== count($actual)) {
            return false;
        }
        /** @var panel_base $class */
        $class = panel_base::get_class($this->get_panel_module());
        foreach ($expected as $item) {
            if (in_array($item, $actual, true)) {
                continue;
            }
            $found = false;
            foreach ($labels as $label) {
                $found = $found || $class::label_matches($label, $item);
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    #[\Override]
    public function type_search(string $text): void {
        $this->open_panel()->search($text);
    }

    /**
     * Panel module of the element.
     *
     * @return string
     */
    private function get_panel_module(): string {
        return (string)$this->find('css', '[data-picker-panel]')->getAttribute('data-picker-panel');
    }

    /**
     * Open the panel unless it is open already.
     *
     * @return panel_base helper of the open panel
     */
    private function open_panel(): panel_base {
        $session = $this->context->getSession();
        if (!$session->getPage()->find('css', panel_base::PANEL)) {
            $this->find('css', '[data-picker-open]')->click();
        }
        return panel_base::wait_for_panel($this->context, $this->get_panel_module(), 'muform element "' . $this->get_name() . '"');
    }
}
