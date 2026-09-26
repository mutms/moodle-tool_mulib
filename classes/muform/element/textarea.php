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
use tool_mulib\muform\element;

/**
 * Multi line text input element.
 *
 * Values are cleaned with param::TEXT which strips all tags except multilang tags,
 * use type 'rawtext' to keep the submitted value as is.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class textarea extends element {
    /** @var string[] supported value types, this is not a html attribute */
    public const array TYPES = ['text', 'rawtext'];

    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'type' => param::ALPHA,
        'rows' => param::INT,
        'cols' => param::INT,
        'maxlength' => param::INT,
        'placeholder' => param::TEXT,
    ];

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array $attributes
     */
    public function __construct(string $name, string $label, array $attributes = []) {
        parent::__construct($name);
        $this->label = $label;
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
    }

    #[\Override]
    public function set_attribute(string $name, mixed $value): static {
        if ($name === 'type' && $value !== null && !in_array($value, self::TYPES, true)) {
            throw new coding_exception('Invalid textarea type: ' . $value);
        }
        return parent::set_attribute($name, $value);
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value === null || is_array($this->value) || is_bool($this->value)) {
            $this->value = '';
        }
        $this->value = str_replace(["\r\n", "\r"], "\n", (string)$this->value);
        if ($this->get_attribute('type') !== 'rawtext') {
            $this->value = param::TEXT->clean($this->value);
        }

        if (trim($this->value) === '') {
            return;
        }

        $maxlength = $this->get_attribute('maxlength');
        if ($maxlength && \core_text::strlen($this->value) > $maxlength) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return trim($this->value) !== '';
    }

    #[\Override]
    protected function get_html_attributes(): array {
        // The type attribute selects cleaning only.
        return array_values(array_filter(parent::get_html_attributes(), fn($attr) => $attr['name'] !== 'type'));
    }
}
