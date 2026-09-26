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
use core\exception\moodle_exception;
use core\output\core_renderer;
use core\param;
use core\router\util;
use tool_mulib\muform\autocomplete\base;
use tool_mulib\muform\element;

/**
 * Single value autocomplete element, the value is a string or null.
 *
 * The source given to the constructor owns searching, labels, per-option validation
 * and access control, the same class serves the tool_mulib autocomplete endpoint.
 * Values are restricted to letters, digits and "_-.:@", never empty.
 * Not available to guests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autocomplete extends element {
    /** @var string allowed value characters */
    public const string VALUE_REGEX = '/^[a-zA-Z0-9_\-.:@]+$/D';

    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'placeholder' => param::TEXT,
        'width' => param::ALPHA,
    ];

    /** @var base source of options */
    private base $source;
    /** @var string|null label html of the current value */
    private ?string $valuelabel = null;
    /** @var string|null why the current value was refused */
    private ?string $valueerror = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param base $source
     * @param array $attributes
     */
    public function __construct(string $name, string $label, base $source, array $attributes = []) {
        parent::__construct($name);
        $this->label = $label;
        $this->source = $source;
        foreach ($attributes as $k => $v) {
            $this->set_attribute($k, $v);
        }
        if ($this->get_attribute('placeholder') === null) {
            $this->set_attribute('placeholder', get_string('muform_search', 'tool_mulib'));
        }
        if ($this->get_attribute('width') === null) {
            // Visibly different from the always full width autocompletemany.
            $this->set_attribute('width', 'medium');
        }
    }

    #[\Override]
    protected function parse_value(): void {
        if (isguestuser() || !isloggedin()) {
            throw new moodle_exception('noguest');
        }
        parent::parse_value();
        if ($this->value === null || $this->value === '') {
            $this->value = null;
            return;
        }
        if (is_int($this->value)) {
            $this->value = (string)$this->value;
        }
        if (!is_string($this->value) || !preg_match(self::VALUE_REGEX, $this->value)) {
            $this->value = null;
            $this->errors[] = $this->get_invalid_hint();
            return;
        }
        $this->valuelabel = $this->source->label($this->value);
        if ($this->valuelabel === null) {
            $this->value = null;
            $this->errors[] = $this->get_invalid_hint();
        }
    }

    #[\Override]
    public function validate(array &$allerrors): void {
        parent::validate($allerrors);
        if ($this->value === null || $this->errors) {
            return;
        }
        $error = $this->source->validate($this->value);
        if ($error !== null) {
            $this->valueerror = $error;
            $allerrors[$this->get_name()][] = trim(strip_tags($this->valuelabel)) . ': ' . $error;
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return $this->value !== null;
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $selected = [];
        if ($this->value !== null) {
            $selected[] = ['value' => $this->value, 'label' => $this->valuelabel, 'error' => $this->valueerror];
        }
        $context['url'] = util::get_path_for_callable([\tool_mulib\route\api\muform\autocomplete::class, 'search'])->out(false);
        $source = ['class' => get_class($this->source), 'args' => $this->source->get_args()];
        $context['sourcejson'] = json_encode($source, JSON_UNESCAPED_SLASHES);
        $context['selected'] = $selected;
        $context['has_selected'] = (bool)$selected;
        $context['selectedjson'] = json_encode($selected, JSON_UNESCAPED_SLASHES);
        $context['keys'] = $this->value ?? '';
        return $context;
    }
}
