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

namespace tool_mulib\muform;

use core\exception\coding_exception;
use core_renderer;
use tool_mulib\muform\util\template;

/**
 * A muform element base.
 *
 * Elements are configured with fluent setters and then attached with $form->add(),
 * which calls parse_value() once. After that the value is fixed: value related setters
 * throw, cosmetic setters (hints, help, template, validators) still work.
 *
 * Element classes must be in <component>\muform\element namespace, the template,
 * ES module and Behat helper are derived from the component and class name.
 *
 * To create a new element: set $this->label in the constructor,
 * declare ALLOWED_ATTRIBUTES, override parse_value() to sanitise
 * $this->value and fill $this->errors, override has_required_value() if empty value
 * is not null or empty string, and add element specific data in get_template_data().
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class element {
    /** @var string restrict element names to variable names */
    final public const string NAME_REGEX = '/^[a-z][a-z0-9_]+$/D';

    /** @var array list of allowed attributes and their parameter types, to be overridden */
    protected const array ALLOWED_ATTRIBUTES = [];

    /** @var string[] values of the "width" attribute of text-like inputs, auto is the theme default, others render muform-width-* class */
    final public const array WIDTHS = ['auto', 'small', 'medium', 'full'];

    /** @var string label of element */
    protected string $label = '';
    /** @var mixed internal element value */
    protected mixed $value = null;
    /** @var string[] internal errors produced when parsing value */
    protected array $errors = [];

    /** @var string name of element */
    private string $name;
    /** @var string component that defines the element class */
    private string $component;
    /** @var string element type, the short class name */
    private string $type;
    /** @var form|null form element is attached to */
    private ?form $form = null;
    /** @var mixed default value */
    private mixed $default = null;
    /** @var array element attributes */
    private array $attributes = [];
    /** @var bool element frozen value - current data or default returned */
    private bool $frozen = false;
    /** @var bool value is required, validated on the server and in browser */
    private bool $required = false;
    /** @var bool element is marked as required, no validation is done */
    private bool $requiredmarker = false;
    /** @var string|null custom text shown when required value is missing */
    private ?string $requiredhint = null;
    /** @var string|null custom text shown when a value is invalid */
    private ?string $invalidhint = null;
    /** @var array|null help icon info */
    private ?array $help = null;
    /** @var callable|validator[] external validators */
    private array $validators = [];
    /** @var string[] element children */
    private array $children = [];
    /** @var string element template name, defaults to component/muform/element/type */
    private string $template;
    /** @var string template variant used during rendering */
    private string $variant = '';
    /** @var bool element not visible in browser - managed via display manager */
    private bool $hidden = false;
    /** @var bool element disabled in browser - managed via display manager */
    private bool $disabled = false;

    /**
     * Element base constructor.
     *
     * @param string $name
     */
    public function __construct(string $name) {
        if (!preg_match(self::NAME_REGEX, $name)) {
            throw new coding_exception('Invalid element name: ' . $name);
        }
        $this->name = $name;

        $this->component = (string)\core_component::get_component_from_classname(static::class);
        $this->type = substr(static::class, strrpos(static::class, '\\') + 1);
        if (static::class !== $this->component . '\\muform\\element\\' . $this->type) {
            throw new coding_exception('Element classes must be in <component>\\muform\\element namespace: ' . static::class);
        }
        $this->template = $this->component . '/muform/element/' . $this->type;
    }

    /**
     * Returns element name.
     *
     * @return string
     */
    final public function get_name(): string {
        // Must not be allowed to be changed in extending classes.
        return $this->name;
    }

    /**
     * Set default element value.
     *
     * @param mixed $value
     * @return $this
     */
    public function set_default(mixed $value): static {
        if ($this->form) {
            throw new coding_exception('Default value of element cannot be changed after attaching to a form');
        }
        $this->default = $value;
        return $this;
    }

    /**
     * Returns default element value.
     *
     * @return mixed
     */
    protected function get_default(): mixed {
        return $this->default;
    }

    /**
     * Set element attribute value, attributes are element specific HTML attributes
     * listed in ALLOWED_ATTRIBUTES (such as maxlength, min, rows).
     *
     * Unknown attributes are ignored with a debugging message.
     * Use set_required() for required values, it is not an attribute.
     *
     * @param string $name
     * @param mixed $value null removes the attribute
     * @return $this
     */
    public function set_attribute(string $name, mixed $value): static {
        if ($this->form) {
            throw new coding_exception('Attributes of element cannot be changed after attaching to a form');
        }
        if (!isset(static::ALLOWED_ATTRIBUTES[$name])) {
            debugging('Invalid element attribute specified: ' . $name, DEBUG_DEVELOPER);
            return $this;
        }
        if ($value === null) {
            unset($this->attributes[$name]);
        } else {
            $value = static::ALLOWED_ATTRIBUTES[$name]->clean($value);
            if ($name === 'width' && !in_array($value, self::WIDTHS, true)) {
                throw new coding_exception('Invalid element width: ' . $value);
            }
            $this->attributes[$name] = $value;
        }
        return $this;
    }

    /**
     * Returns element attribute value.
     *
     * @param string $name
     * @return mixed
     */
    protected function get_attribute(string $name): mixed {
        if (!isset(static::ALLOWED_ATTRIBUTES[$name])) {
            debugging('Invalid element attribute specified: ' . $name, DEBUG_DEVELOPER);
        }
        return $this->attributes[$name] ?? null;
    }

    /**
     * Require value, missing value is reported as validation error
     * and required marker is shown.
     *
     * Use set_required_marker() together with a validator for conditionally required values.
     *
     * @param bool $state
     * @return $this
     */
    final public function set_required(bool $state): static {
        if ($this->form) {
            throw new coding_exception('Required flag cannot be changed after attaching to a form');
        }
        $this->required = $state;
        if ($state) {
            $this->requiredmarker = true;
        }
        return $this;
    }

    /**
     * Show required marker without any validation,
     * intended for validators that decide when the value is actually required.
     *
     * @param bool $state
     * @return $this
     */
    final public function set_required_marker(bool $state): static {
        $this->requiredmarker = $state;
        return $this;
    }

    /**
     * Does the element have a value that satisfies required check?
     *
     * Use in validators for conditionally required values instead of
     * comparing values directly, each element defines what "empty" means
     * (blank text, unchecked checkbox, no radio selected).
     * Override in elements where empty value is not null or empty string.
     *
     * @return bool
     */
    public function has_required_value(): bool {
        if (!$this->form) {
            throw new coding_exception('Value can be checked only for elements attached to a form');
        }
        return $this->value !== null && $this->value !== '' && $this->value !== [];
    }

    /**
     * Specify custom text hint shown when required value is missing.
     *
     * @param string $text
     * @return $this
     */
    public function set_required_hint(string $text): static {
        $this->requiredhint = $text;
        return $this;
    }

    /**
     * Return required value text hint.
     *
     * @return string
     */
    public function get_required_hint(): string {
        return $this->requiredhint ?? get_string('required');
    }

    /**
     * Specify custom text hint shown when value is invalid.
     *
     * @param string $text
     * @return $this
     */
    public function set_invalid_hint(string $text): static {
        $this->invalidhint = $text;
        return $this;
    }

    /**
     * Return invalid value text hint.
     *
     * @return string
     */
    protected function get_invalid_hint(): string {
        return $this->invalidhint ?? get_string('error');
    }

    /**
     * Add help button.
     *
     * @param string $identifier
     * @param string $component
     * @param string $linktext
     * @param mixed $a
     * @return $this
     */
    public function add_help_button(
        string $identifier,
        string $component = 'core',
        string $linktext = '',
        mixed $a = null,
    ): static {
        $this->help = [$identifier, $component, $linktext, $a];
        return $this;
    }

    /**
     * Freeze the element, submitted data is ignored and current data or default is used,
     * the element is rendered as static text. This is the only way to make a value unchangeable,
     * disable_if() display rules are cosmetic.
     *
     * @param bool $state
     * @return $this
     */
    final public function set_frozen(bool $state): static {
        if ($this->form) {
            throw new coding_exception('State of element affecting value cannot be changed after attaching to a form');
        }
        $this->frozen = $state;
        return $this;
    }

    /**
     * Does the element return frozen value?
     *
     * @return bool
     */
    final protected function is_frozen(): bool {
        return $this->frozen;
    }

    /**
     * Set initial hidden state in browser.
     *
     * @param bool $state
     */
    final public function set_hidden(bool $state): void {
        $this->hidden = $state;
    }

    /**
     * Set initial disabled state in browser.
     *
     * @param bool $state
     */
    final public function set_disabled(bool $state): void {
        $this->disabled = $state;
    }

    /**
     * Parse element value from submitted, current or default data,
     * called exactly once from attach_to_form().
     *
     * Override to sanitise $this->value into its final type and add
     * parse errors to $this->errors. Do not check required values here,
     * that is done in validate() via has_required_value().
     */
    protected function parse_value(): void {
        if (!$this->form) {
            throw new coding_exception('Do not call parse_value() manually');
        }

        if (!$this->is_frozen()) {
            $postdata = $this->form->get_post_data();
            if ($postdata !== null && array_key_exists($this->name, $postdata)) {
                $this->value = $postdata[$this->name];
                return;
            }
        }

        $currentdata = $this->form->get_current_data();
        if (array_key_exists($this->name, $currentdata)) {
            $this->value = $currentdata[$this->name];
            return;
        }

        $this->value = $this->get_default();
    }

    /**
     * Override to true in layout elements that may contain other elements.
     *
     * @return bool
     */
    public function accepts_children(): bool {
        return false;
    }

    /**
     * Extra keys merged into form data next to the element value, such as text format.
     *
     * Keys must match NAME_REGEX, must not be a name of any form element
     * and two elements must not return the same key.
     *
     * @return array
     */
    public function get_additional_data(): array {
        return [];
    }

    /**
     * Override to false for elements that do not add any data to $form->get_data().
     *
     * @return bool
     */
    public function returns_data(): bool {
        return true;
    }

    /**
     * Returns element value for $form->get_data() or external access.
     *
     * The value does not change after the element is attached to a form,
     * it is the same in definition(), validators, handlers and templates.
     * Override this if internal value format does not match external format.
     *
     * @return mixed
     */
    public function get_value(): mixed {
        if (!$this->form) {
            throw new coding_exception('Value can be returned only for elements already attached to a form');
        }
        return $this->value;
    }

    /**
     * Add external validator, validators run only when the form was submitted,
     * after the form is finalised, so they may inspect other elements via get_form().
     *
     * @param validator|callable $validator callable signature: function(element $element, array &$allerrors): void
     * @return static
     */
    final public function add_validator(validator|callable $validator): static {
        $this->validators[] = $validator;
        return $this;
    }

    /**
     * Validate the data, called from form only when the form was submitted.
     *
     * Order: parse errors from parse_value(), then required check
     * via has_required_value() when set_required(true), then validators.
     *
     * @param array $allerrors all form errors indexed by element names
     */
    public function validate(array &$allerrors): void {
        foreach ($this->errors as $error) {
            $allerrors[$this->name][] = $error;
        }
        if (!$this->errors && $this->required && !$this->has_required_value()) {
            $allerrors[$this->name][] = $this->get_required_hint();
        }

        foreach ($this->validators as $validator) {
            if ($validator instanceof validator) {
                $validator->validate($this, $allerrors);
            } else {
                $validator($this, $allerrors);
            }
        }
    }

    /**
     * Does a non-empty value of this element cancel form editing?
     *
     * Any element may cancel, reload or submit the form, not only buttons.
     *
     * @return bool
     */
    public static function is_cancelling(): bool {
        return false;
    }

    /**
     * Does a non-empty value of this element reload the form without validation?
     *
     * Use for elements that change the form definition, such as "add more rows" checkbox.
     *
     * @return bool
     */
    public static function is_reloading(): bool {
        return false;
    }

    /**
     * Does a non-empty value of this element submit the form for validation?
     *
     * @return bool
     */
    public static function is_submitting(): bool {
        return false;
    }

    /**
     * Called only from the form to notify element about a new child element.
     *
     * Internal API, do not call directly.
     *
     * @param string $elname
     * @param int|null $position null means at the end of list
     */
    final public function attach_child(string $elname, ?int $position = null): void {
        if (!$this->form || $this->form->is_finalised() || $this->form->get_element($elname)) {
            throw new coding_exception('Do not call attach_child() directly');
        }
        if (!$this->accepts_children()) {
            throw new coding_exception('Element ' . $this->name . ' cannot contain other elements');
        }
        if ($position === null || $position > count($this->children)) {
            $this->children[] = $elname;
            return;
        }
        if ($position < 0) {
            $position = 0;
        }
        array_splice($this->children, $position, 0, [$elname]);
    }

    /**
     * Returns all element children.
     *
     * @return string[]
     */
    final public function get_children(): array {
        return $this->children;
    }

    /**
     * Returns form the element is attached to.
     *
     * @return form|null
     */
    final public function get_form(): ?form {
        return $this->form;
    }

    /**
     * Called from the form to notify it was attached to a form.
     *
     * Internal API, do not call directly.
     *
     * @param form $form
     */
    final public function attach_to_form(form $form): void {
        if ($this->form || $form->is_finalised()) {
            throw new coding_exception('Do not call attach_to_form() directly');
        }
        $this->form = $form;
        $this->parse_value();
        $this->attached();
    }

    /**
     * Called once after the element was attached to a form and its value parsed.
     *
     * Override in layout elements that add their own children via get_form()->add().
     */
    protected function attached(): void {
    }

    /**
     * Manually override element template.
     *
     * @param string $template
     * @return static
     */
    public function set_template(string $template): static {
        $this->template = $template;
        return $this;
    }

    /**
     * CSS class of the width attribute, empty for auto or elements without the attribute.
     *
     * @return string
     */
    private function get_width_class(): string {
        if (!isset(static::ALLOWED_ATTRIBUTES['width'])) {
            return '';
        }
        $width = $this->attributes['width'] ?? 'auto';
        return ($width === 'auto') ? '' : 'muform-width-' . $width;
    }

    /**
     * Returns set attributes as list of name/value pairs for templates, in ALLOWED_ATTRIBUTES order.
     *
     * Override to exclude attributes that the template handles explicitly.
     *
     * @return array
     */
    protected function get_html_attributes(): array {
        $result = [];
        foreach (array_keys(static::ALLOWED_ATTRIBUTES) as $name) {
            if ($name === 'width') {
                // Rendered as widthclass, see the WIDTHS constant.
                continue;
            }
            $value = $this->attributes[$name] ?? null;
            if ($value === null || $value === false || $value === '') {
                continue;
            }
            if ($value === true) {
                $value = $name;
            }
            $result[] = ['name' => $name, 'value' => (string)$value];
        }
        return $result;
    }

    /**
     * Returns mustache context data with child elements rendered as html.
     *
     * @param core_renderer $output
     * @param array $allerrors
     * @return array
     */
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        if (!$this->form || !$this->form->is_finalised()) {
            throw new coding_exception('Only finalised form elements can be rendered');
        }

        $value = $this->value;
        if ($value === null || is_array($value)) {
            $value = '';
        } else if (is_bool($value)) {
            $value = (int)$value;
        }

        $context = [
            'idsuffix' => $this->form->get_idsuffix(),
            'name' => $this->name,
            'component' => $this->component,
            'type' => $this->type,
            'id' => 'id_' . $this->name . $this->form->get_idsuffix(),
            'errorid' => 'id_error_' . $this->name . $this->form->get_idsuffix(),
            'label' => clean_string($this->label),
            // Frozen elements render static text, a label must not point to it.
            'nolabelfor' => $this->frozen,
            'value' => (string)$value,
            'is_hidden' => $this->hidden,
            'is_disabled' => $this->disabled,
            'is_frozen' => $this->frozen,
            'is_required' => $this->requiredmarker,
            'attr_required' => $this->required,
            'attributes' => $this->get_html_attributes(),
            'widthclass' => $this->get_width_class(),
            'elements_byname' => [],
            'elements' => [],
            'has_errors' => !empty($allerrors[$this->name]),
            'errors' => empty($allerrors[$this->name]) ? [] : array_map('clean_string', $allerrors[$this->name]),
            'requiredhint' => clean_string($this->get_required_hint()),
            'invalidhint' => clean_string($this->get_invalid_hint()),
            'helpbuttonhtml' => '',
        ];

        if ($this->help) {
            [$identifier, $component, $linktext, $a] = $this->help;
            $context['helpbuttonhtml'] = $output->help_icon($identifier, $component, $linktext, $a);
        }

        // Custom layout templates place children by name: {{#elements_byname.x}}{{{html}}}{{/elements_byname.x}}.
        foreach ($this->get_children() as $elname) {
            $html = $this->form->get_element($elname)->render($output, $allerrors, $this->variant);
            $context['elements_byname'][$elname] = ['name' => $elname, 'html' => $html];
        }
        $context['elements'] = array_values($context['elements_byname']);

        return $context;
    }

    /**
     * Render element and all children.
     *
     * @param core_renderer $output
     * @param array $allerrors
     * @param string $variant template variant, used when "-variant" template exists
     * @return string
     */
    final public function render(core_renderer $output, array $allerrors, string $variant = ''): string {
        $this->variant = $variant;
        $context = $this->get_template_data($output, $allerrors);
        return $output->render_from_template(template::resolve($this->template, $variant), $context);
    }
}
