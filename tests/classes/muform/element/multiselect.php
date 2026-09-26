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
 * Behat helper for multiselect element, value is comma separated list of option keys or exact labels.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class multiselect extends base {
    #[\Override]
    public function set_value(string $value): void {
        $options = $this->get_options();
        $keys = array_map(fn($item) => $this->resolve_option($item, $options), $this->split_values($value));
        $select = $this->find('css', 'select');
        $select->setValue([]);
        foreach ($keys as $key) {
            $select->selectOption($key, true);
        }
    }

    #[\Override]
    public function get_value(): string {
        return implode(', ', $this->get_selected_keys());
    }

    #[\Override]
    public function matches(string $expected): bool {
        $options = $this->get_options();
        $keys = array_map(fn($item) => $this->resolve_option($item, $options), $this->split_values($expected));
        return $this->same_keys($keys, $this->get_selected_keys());
    }

    /**
     * Returns keys of selected options.
     *
     * @return string[]
     */
    private function get_selected_keys(): array {
        $keys = [];
        foreach ($this->find_all('css', 'option') as $option) {
            if ($option->isSelected()) {
                $keys[] = (string)$option->getAttribute('value');
            }
        }
        return $keys;
    }
}
