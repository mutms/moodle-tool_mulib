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
use core\param;
use tool_mulib\muform\element;
use tool_mulib\muform\util\options;

/**
 * Single select dropdown, value is the key of selected option.
 *
 * Add an option with empty key for "Choose..." placeholder, it is returned as null.
 * Without it the value is never null (unless frozen or without options): the first option is used
 * when no value is given, same as shown by browsers, and required marker is not shown.
 * Submitted value that is not among options is a validation error, such value in current data
 * or default triggers developer debugging; frozen value is returned unchanged.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class select extends element {
    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'size' => param::INT,
    ];

    /** @var options */
    private options $options;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array|options $options option labels indexed by value keys or options instance with groups
     * @param array $attributes
     */
    public function __construct(string $name, string $label, array|options $options, array $attributes = []) {
        parent::__construct($name);
        $this->label = $label;
        $this->options = is_array($options) ? new options($options) : $options;
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
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

        $empty = ($this->value === null || $this->value === '' || is_array($this->value) || is_bool($this->value));

        if ($this->is_frozen()) {
            // Frozen value comes from the developer and must not be changed, not even when it is not among options.
            $this->value = $empty ? null : (string)$this->value;
            return;
        }

        // Browsers always select the first option when nothing else is selected,
        // the value must match what the user sees.
        $keys = $this->options->get_keys();
        $first = (!$keys || $keys[0] === '') ? null : $keys[0];

        if ($empty) {
            $this->value = $this->options->has_key('') ? null : $first;
            return;
        }

        $this->value = (string)$this->value;
        if ($this->options->has_key($this->value)) {
            return;
        }

        $postdata = $this->get_form()->get_post_data();
        if ($postdata !== null && array_key_exists($this->get_name(), $postdata)) {
            $this->errors[] = $this->get_invalid_hint();
        } else {
            $message = 'Value of select element "' . $this->get_name() . '" is not among its options, the first option is used';
            debugging($message, DEBUG_DEVELOPER);
        }
        $this->value = $first;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $selected = ($this->value === null) ? [] : [$this->value];
        $context['groups'] = $this->options->get_template_data($context['id'], $selected);
        // Frozen value that is not among options is shown as is.
        $context['valuelabel'] = clean_string($this->options->get_labels()[$this->value] ?? (string)$this->value);
        if (!$this->options->has_key('')) {
            // Without the "Choose..." option some value is always selected, required marker would be only noise.
            $context['is_required'] = false;
            $context['attr_required'] = false;
        }
        return $context;
    }
}
