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
use tool_mulib\muform\form;

/**
 * Hide and disable rules for form elements.
 *
 * There must be exactly the same logic in display manager ESM module,
 * this class is only helping with pre-rendering of form on the server-side.
 * It does not affect values or validation in any way.
 *
 * Rules are evaluated in a single pass, there is no fixpoint iteration
 * for rules depending on other hidden or disabled elements.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class display_manager {
    /** @var string hide target element */
    public const string ACTION_HIDE = 'hide';
    /** @var string disable target element */
    public const string ACTION_DISABLE = 'disable';

    /** @var string[] supported operators */
    public const array OPS = ['eq', 'neq', 'in', 'notin', 'checked', 'notchecked', 'empty', 'notempty'];

    /** @var form */
    private form $form;
    /** @var array list of rules */
    private array $rules = [];

    /**
     * Constructor.
     *
     * @param form $form
     */
    public function __construct(form $form) {
        $this->form = $form;
    }

    /**
     * Hide target element when dependency element value matches.
     *
     * @param string $target element name
     * @param string $dependency element name
     * @param string $op one of self::OPS
     * @param mixed $value compared value for eq, neq, in and notin operators
     */
    public function hide_if(string $target, string $dependency, string $op, mixed $value = null): void {
        $this->add_rule(self::ACTION_HIDE, $target, $dependency, $op, $value);
    }

    /**
     * Disable target element when dependency element value matches.
     *
     * @param string $target element name
     * @param string $dependency element name
     * @param string $op one of self::OPS
     * @param mixed $value compared value for eq, neq, in and notin operators
     */
    public function disable_if(string $target, string $dependency, string $op, mixed $value = null): void {
        $this->add_rule(self::ACTION_DISABLE, $target, $dependency, $op, $value);
    }

    /**
     * Add rule.
     *
     * @param string $action
     * @param string $target
     * @param string $dependency
     * @param string $op
     * @param mixed $value
     */
    private function add_rule(string $action, string $target, string $dependency, string $op, mixed $value): void {
        if ($this->form->is_finalised()) {
            throw new coding_exception('Finalised form cannot be modified');
        }
        if (!in_array($op, self::OPS, true)) {
            throw new coding_exception('Invalid display rule operator: ' . $op);
        }
        if ($target === $dependency) {
            throw new coding_exception('Display rule cannot depend on itself: ' . $target);
        }
        if ($op === 'in' || $op === 'notin') {
            if (!is_array($value)) {
                throw new coding_exception('Display rule operator ' . $op . ' requires array value');
            }
            $value = array_values(array_map('strval', $value));
        } else if ($op === 'eq' || $op === 'neq') {
            $value = self::normalise_scalar($value);
        } else {
            $value = null;
        }
        $this->rules[] = [
            'action' => $action,
            'target' => $target,
            'dep' => $dependency,
            'op' => $op,
            'value' => $value,
        ];
    }

    /**
     * Returns all rules.
     *
     * @return array
     */
    public function get_rules(): array {
        return $this->rules;
    }

    /**
     * Returns rules encoded for the browser display manager.
     *
     * @return string
     */
    public function get_rules_json(): string {
        return json_encode($this->rules, JSON_THROW_ON_ERROR);
    }

    /**
     * Apply hidden and disabled flags to all elements before rendering.
     */
    public function apply_flags(): void {
        [$hidden, $disabled] = $this->evaluate();
        foreach ($this->form->get_elements() as $elname => $element) {
            $element->set_hidden($hidden[$elname]);
            $element->set_disabled($disabled[$elname]);
        }
    }

    /**
     * Would the element be hidden in browser when the form is rendered with current values?
     *
     * Intended for validators of conditionally required elements, this is a purely cosmetic hint.
     *
     * @param string $elname
     * @return bool
     */
    public function is_hidden(string $elname): bool {
        [$hidden] = $this->evaluate();
        if (!isset($hidden[$elname])) {
            throw new coding_exception('Unknown element: ' . $elname);
        }
        return $hidden[$elname];
    }

    /**
     * Evaluate rules for all elements including cascading from parents.
     *
     * @return array [hidden flags, disabled flags] indexed by element name
     */
    private function evaluate(): array {
        if (!$this->form->is_finalised()) {
            throw new coding_exception('Display rules can be evaluated only in finalised forms');
        }

        $hidden = [];
        $disabled = [];
        foreach ($this->rules as $rule) {
            $target = $this->form->get_element($rule['target']);
            $dependency = $this->form->get_element($rule['dep']);
            if (!$target || !$dependency) {
                throw new coding_exception('Unknown element in display rule: ' . $rule['target'] . ' / ' . $rule['dep']);
            }
            if (self::matches($rule['op'], $dependency->get_value(), $rule['value'])) {
                if ($rule['action'] === self::ACTION_HIDE) {
                    $hidden[$rule['target']] = true;
                } else {
                    $disabled[$rule['target']] = true;
                }
            }
        }

        $parents = [];
        foreach ($this->form->get_elements() as $elname => $element) {
            foreach ($element->get_children() as $child) {
                $parents[$child] = $elname;
            }
        }

        $hiddenflags = [];
        $disabledflags = [];
        foreach ($this->form->get_elements() as $elname => $element) {
            $ishidden = false;
            $isdisabled = false;
            $name = $elname;
            while ($name !== null) {
                $ishidden = $ishidden || isset($hidden[$name]);
                $isdisabled = $isdisabled || isset($disabled[$name]);
                $name = $parents[$name] ?? null;
            }
            $hiddenflags[$elname] = $ishidden;
            $disabledflags[$elname] = $isdisabled;
        }
        return [$hiddenflags, $disabledflags];
    }


    /**
     * Convert scalar to string used in comparisons.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function normalise_scalar(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }

    /**
     * Normalise element value for comparison.
     *
     * @param mixed $value
     * @return string|string[]|null
     */
    private static function normalise_value(mixed $value): string|array|null {
        if (is_array($value)) {
            return array_values(array_map(fn($v) => (string)self::normalise_scalar($v), $value));
        }
        return self::normalise_scalar($value);
    }

    /**
     * Does the dependency value match the rule?
     *
     * @param string $op
     * @param mixed $depvalue current value of dependency element
     * @param mixed $value rule value
     * @return bool
     */
    public static function matches(string $op, mixed $depvalue, mixed $value): bool {
        $depvalue = self::normalise_value($depvalue);
        switch ($op) {
            case 'eq':
                $value = self::normalise_scalar($value);
                if (is_array($depvalue)) {
                    return in_array($value, $depvalue, true);
                }
                return $depvalue === $value;
            case 'neq':
                return !self::matches('eq', $depvalue, $value);
            case 'in':
                $value = array_map('strval', (array)$value);
                if (is_array($depvalue)) {
                    return (bool)array_intersect($depvalue, $value);
                }
                return in_array($depvalue, $value, true);
            case 'notin':
                return !self::matches('in', $depvalue, $value);
            case 'checked':
                return $depvalue === '1';
            case 'notchecked':
                return $depvalue !== '1';
            case 'empty':
                return $depvalue === null || $depvalue === '' || $depvalue === [];
            case 'notempty':
                return !self::matches('empty', $depvalue, null);
            default:
                throw new coding_exception('Invalid display rule operator: ' . $op);
        }
    }
}
