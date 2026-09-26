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
use core\output\core_renderer;
use core\param;
use tool_mulib\muform\element;

/**
 * Single line text input element, also used for email, url, tel and search inputs.
 *
 * Values are cleaned with param::TEXT which strips all tags except multilang tags,
 * use type 'rawtext' to keep the submitted value as is.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text extends element {
    /** @var string[] supported input types */
    public const array TYPES = ['text', 'rawtext', 'email', 'url', 'tel', 'search'];

    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'type' => param::ALPHA,
        'maxlength' => param::INT,
        'minlength' => param::INT,
        'pattern' => param::RAW,
        'placeholder' => param::TEXT,
        'autocomplete' => param::ALPHANUMEXT,
        'width' => param::ALPHA,
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
            throw new coding_exception('Invalid text input type: ' . $value);
        }
        return parent::set_attribute($name, $value);
    }

    #[\Override]
    protected function parse_value(): void {
        parent::parse_value();
        if ($this->value === null || is_array($this->value) || is_bool($this->value)) {
            $this->value = '';
        }
        $this->value = str_replace(["\r\n", "\r", "\n"], ' ', (string)$this->value);
        $type = $this->get_attribute('type') ?? 'text';
        if ($type !== 'rawtext') {
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
        $minlength = $this->get_attribute('minlength');
        if ($minlength && \core_text::strlen($this->value) < $minlength) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
        $pattern = $this->get_attribute('pattern');
        if ($pattern !== null && $pattern !== '') {
            if (!preg_match('~^(?:' . str_replace('~', '\~', $pattern) . ')$~u', $this->value)) {
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
        }
        if ($type === 'email' && !validate_email($this->value)) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
        if ($type === 'url') {
            if (filter_var($this->value, FILTER_VALIDATE_URL) === false || clean_param($this->value, PARAM_URL) !== $this->value) {
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return trim($this->value) !== '';
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $type = $this->get_attribute('type') ?? 'text';
        $context['inputtype'] = ($type === 'rawtext') ? 'text' : $type;
        if ($type === 'url' && $context['widthclass'] === '') {
            // There are no short URLs.
            $context['widthclass'] = 'muform-width-full';
        }
        return $context;
    }

    #[\Override]
    protected function get_html_attributes(): array {
        // The type attribute is rendered explicitly as inputtype.
        return array_values(array_filter(parent::get_html_attributes(), fn($attr) => $attr['name'] !== 'type'));
    }
}
