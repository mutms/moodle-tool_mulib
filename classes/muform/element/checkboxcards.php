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

namespace tool_mulib\muform\element;

use core\output\core_renderer;
use tool_mulib\muform\element;
use tool_mulib\muform\util\cards;
use tool_mulib\muform\util\options;

/**
 * Check boxes rendered as cards with title and optional description, value is the list of checked option keys.
 *
 * Use it instead of help buttons when independent options need an explanation, for example their consequences.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checkboxcards extends element {
    /** @var options */
    private options $options;
    /** @var array plain text descriptions indexed by option keys */
    private array $descriptions;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array|options $options option titles indexed by value keys or options instance with groups
     * @param array $descriptions plain text option descriptions indexed by value keys, optional
     */
    public function __construct(string $name, string $label, array|options $options, array $descriptions = []) {
        parent::__construct($name);
        $this->label = $label;
        $this->options = is_array($options) ? new options($options) : $options;
        $this->descriptions = cards::validate_descriptions($descriptions, $this->options);
    }

    /**
     * Returns options.
     *
     * @return options
     */
    public function get_options(): options {
        return $this->options;
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        $value = $this->value;
        if ($value === null || $value === '') {
            $value = [];
        } else if (is_string($value)) {
            $value = explode(',', $value);
        } else if (!is_array($value)) {
            $this->value = [];
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $selected = [];
        foreach ($value as $key) {
            if (is_array($key) || is_bool($key)) {
                $this->value = [];
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $key = trim((string)$key);
            if ($key === '') {
                // Empty marker from the hidden input or empty CSV item.
                continue;
            }
            if (!$this->options->has_key($key)) {
                $this->value = [];
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $selected[$key] = true;
        }

        // Keep option order.
        $this->value = array_values(array_filter($this->options->get_keys(), fn($key) => isset($selected[$key])));
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return !empty($this->value);
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $context['nolabelfor'] = true;
        $groups = $this->options->get_template_data($context['id'], $this->value);
        $context['groups'] = cards::add_descriptions($groups, $this->descriptions);
        return $context;
    }
}
