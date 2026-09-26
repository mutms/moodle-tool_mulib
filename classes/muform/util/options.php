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

namespace tool_mulib\muform\util;

use core\exception\coding_exception;

/**
 * List of options with optional groups for radios, checkboxes and selects.
 *
 * Keys are unique across all groups, groups are only a presentation detail.
 * Ungrouped options are rendered first, groups in the order they were added.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class options {
    /** @var array option labels indexed by keys, in rendering order */
    private array $labels = [];
    /** @var array list of groups, each with label and list of keys */
    private array $groups = [];

    /**
     * Constructor.
     *
     * @param array $options ungrouped option labels indexed by keys
     */
    public function __construct(array $options = []) {
        $this->groups[] = ['label' => '', 'keys' => []];
        $this->add_options($options);
    }

    /**
     * Add ungrouped options.
     *
     * @param array $options option labels indexed by keys
     * @return $this
     */
    public function add_options(array $options): static {
        foreach ($options as $key => $label) {
            $this->add_option(0, (string)$key, $label);
        }
        return $this;
    }

    /**
     * Add group of options.
     *
     * @param string $label group label
     * @param array $options option labels indexed by keys
     * @return $this
     */
    public function add_optgroup(string $label, array $options): static {
        $this->groups[] = ['label' => $label, 'keys' => []];
        $index = array_key_last($this->groups);
        foreach ($options as $key => $optionlabel) {
            $this->add_option($index, (string)$key, $optionlabel);
        }
        return $this;
    }

    /**
     * Add option to group.
     *
     * @param int $index
     * @param string $key
     * @param mixed $label
     */
    private function add_option(int $index, string $key, mixed $label): void {
        if (!is_string($label)) {
            throw new coding_exception('Option labels must be strings: ' . $key);
        }
        if (array_key_exists($key, $this->labels)) {
            throw new coding_exception('Duplicate option key: ' . $key);
        }
        $this->labels[$key] = $label;
        $this->groups[$index]['keys'][] = $key;
    }

    /**
     * Is there an option with given key?
     *
     * @param string $key
     * @return bool
     */
    public function has_key(string $key): bool {
        return array_key_exists($key, $this->labels);
    }

    /**
     * Returns all option keys in rendering order.
     *
     * @return string[]
     */
    public function get_keys(): array {
        $keys = [];
        foreach ($this->groups as $group) {
            foreach ($group['keys'] as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Returns option labels indexed by keys, without groups.
     *
     * @return array
     */
    public function get_labels(): array {
        $labels = [];
        foreach ($this->get_keys() as $key) {
            $labels[$key] = $this->labels[$key];
        }
        return $labels;
    }

    /**
     * Returns mustache context with groups of options.
     *
     * @param string $idprefix html id prefix for options
     * @param string[] $selected selected keys
     * @return array list of groups with label and options (key, label, is_checked, id)
     */
    public function get_template_data(string $idprefix, array $selected): array {
        $result = [];
        $i = 0;
        foreach ($this->groups as $group) {
            $options = [];
            foreach ($group['keys'] as $key) {
                $options[] = [
                    'key' => $key,
                    'label' => clean_string($this->labels[$key]),
                    'is_checked' => in_array($key, $selected, true),
                    'id' => $idprefix . '_' . $i,
                ];
                $i++;
            }
            if (!$options) {
                continue;
            }
            $result[] = [
                'label' => clean_string($group['label']),
                'options' => $options,
            ];
        }
        return $result;
    }
}
