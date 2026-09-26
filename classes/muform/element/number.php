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

use core\exception\coding_exception;
use core\param;
use core_renderer;

/**
 * Number HTML element.
 *
 * Rendered as a text input with numeric keyboard hints, never as type="number":
 * spinner arrows and mouse wheel scrolling must not change values unnoticed.
 *
 * The decimals attribute is the number of decimal places, 0 (default) means integers.
 * Decimal values accept comma as decimal separator, trailing zeros are ignored.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class number extends \tool_mulib\muform\element {
    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'decimals' => param::INT,
        'min' => param::FLOAT,
        'max' => param::FLOAT,
        'placeholder' => param::TEXT,
        'width' => param::ALPHA,
    ];

    /** @var string|null submitted text that is not a valid number, shown again for correction */
    private ?string $invalidtext = null;

    /**
     * Element constructor.
     *
     * @param string $name
     * @param string $label
     * @param array $attributes decimals, min, max, placeholder and width
     */
    public function __construct(string $name, string $label, array $attributes = []) {
        parent::__construct($name);
        $this->label = $label;

        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
    }

    /**
     * Parse the values.
     */
    protected function parse_value(): void {
        parent::parse_value();

        if ($this->value === '' || is_array($this->value) || is_bool($this->value)) {
            $this->value = null;
        }

        if ($this->value === null) {
            // Not entered anything, ignore other validation.
            return;
        }

        $original = is_string($this->value) ? trim($this->value) : (string)$this->value;
        $text = $original;
        $decimals = $this->get_decimals();
        if ($decimals && substr_count($text, ',') === 1 && !str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }
        if (str_contains($text, '.')) {
            // Trailing zeros are not significant, stored values often have more of them.
            $text = rtrim(rtrim($text, '0'), '.');
        }
        $pattern = $decimals ? '/^-?(\d+(\.\d{1,' . $decimals . '})?|\.\d{1,' . $decimals . '})$/D' : '/^-?\d+$/D';
        if (!preg_match($pattern, $text)) {
            $this->invalid($original);
            return;
        }
        $this->value = $decimals ? (float)$text : (int)$text;

        $min = $this->get_attribute('min');
        $max = $this->get_attribute('max');

        if ($min !== null && $this->value < $min) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        if ($max !== null && $this->value > $max) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
    }

    #[\Override]
    protected function attached(): void {
        $decimals = $this->get_decimals();
        foreach (['min', 'max'] as $name) {
            $limit = $this->get_attribute($name);
            if ($limit !== null && round((float)$limit, $decimals) != $limit) {
                throw new coding_exception("Number element $name must have at most $decimals decimal places: " . $this->get_name());
            }
        }
    }

    /**
     * Number of decimal places, 0 means integers.
     *
     * @return int
     */
    private function get_decimals(): int {
        return max(0, (int)$this->get_attribute('decimals'));
    }

    /**
     * Reject submitted text.
     *
     * @param string $text
     */
    private function invalid(string $text): void {
        $this->value = null;
        $this->invalidtext = $text;
        $this->errors[] = $this->get_invalid_hint();
    }

    #[\Override]
    protected function get_html_attributes(): array {
        // Range is not valid on text inputs, it goes to data attributes for the JavaScript, decimals to the pattern.
        return array_values(array_filter(
            parent::get_html_attributes(),
            fn($attribute) => !in_array($attribute['name'], ['min', 'max', 'decimals'], true)
        ));
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        if ($this->invalidtext !== null) {
            $context['value'] = $this->invalidtext;
        }
        $decimals = $this->get_decimals();
        $min = $this->get_attribute('min');
        $sign = ($min !== null && $min >= 0) ? '' : '-?';
        $context['inputmode'] = $decimals ? 'decimal' : 'numeric';
        if ($decimals) {
            $fraction = '[.,][0-9]{1,' . $decimals . '}0*';
            $context['pattern'] = $sign . '([0-9]+(' . $fraction . ')?|' . $fraction . ')';
        } else {
            $context['pattern'] = $sign . '[0-9]+';
        }
        // Zero is a valid limit, the list avoids falsy mustache sections.
        $context['rangeattributes'] = [];
        foreach (['min', 'max'] as $name) {
            $limit = $this->get_attribute($name);
            if ($limit !== null) {
                $context['rangeattributes'][] = ['name' => 'data-muform-' . $name, 'value' => (string)$limit];
            }
        }
        return $context;
    }
}
