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

/**
 * Key shared with humans such as enrolment keys or guest access keys, the current value can be shown.
 *
 * Current data: a non-empty string is the current key that "Show" reveals, true means a key
 * exists without revealing it, anything else means not set. Value: null means keep the current
 * key, empty string means cleared (only when clearing is allowed), any other string is the new
 * key as typed. This is intentionally a separate implementation from the secret element,
 * anyone who may edit the key may see it. Do not name elements "password".
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharedkey extends element {
    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'maxlength' => param::INT,
        'minlength' => param::INT,
        'width' => param::ALPHA,
    ];

    /** @var bool current data has a value */
    private bool $hasvalue = false;
    /** @var string current key that can be revealed, empty if unknown */
    private string $current = '';
    /** @var bool user may clear the current value */
    private bool $allowclear;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param array $attributes
     * @param bool $allowclear show a checkbox that clears the current value
     */
    public function __construct(string $name, string $label, array $attributes = [], bool $allowclear = false) {
        parent::__construct($name);
        $this->label = $label;
        $this->allowclear = $allowclear;
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
    }

    #[\Override]
    protected function parse_value(): void {
        $current = $this->get_form()->get_current_data()[$this->get_name()] ?? null;
        if (is_string($current) && $current !== '') {
            $this->hasvalue = true;
            $this->current = $current;
        } else if ($current === true) {
            $this->hasvalue = true;
        }

        parent::parse_value();
        $postdata = $this->get_form()->get_post_data();
        if ($this->is_frozen() || $postdata === null || !array_key_exists($this->get_name(), $postdata)) {
            // Current data or default is never a new value.
            $this->value = null;
            return;
        }

        $value = $this->value;
        if (is_array($value)) {
            if ($this->allowclear && !empty($value['clear'])) {
                $this->value = '';
                return;
            }
            $value = $value['value'] ?? '';
        }
        if ($value === null || $value === '') {
            $this->value = null;
            return;
        }
        if (!is_string($value)) {
            $this->value = null;
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
        $this->value = $value;

        $maxlength = $this->get_attribute('maxlength');
        if ($maxlength && \core_text::strlen($value) > $maxlength) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
        $minlength = $this->get_attribute('minlength');
        if ($minlength && \core_text::strlen($value) < $minlength) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        if ($this->value === null) {
            return $this->hasvalue;
        }
        return $this->value !== '';
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        // The input is always empty, the current key travels in a data attribute for the Show button.
        $context['value'] = '';
        $context['currentvalue'] = $this->current;
        $context['has_value'] = $this->hasvalue;
        $context['allow_clear'] = $this->allowclear;
        // The clear tick is not a secret, it survives reloads and validation errors.
        $context['is_cleared'] = ($this->value === '');
        $context['input_required'] = $context['attr_required'] && !$this->hasvalue;
        return $context;
    }
}
