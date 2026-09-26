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

use core\exception\moodle_exception;
use core\output\core_renderer;
use core\param;
use core\router\util;
use tool_mulib\muform\autocompletemany\base;
use tool_mulib\muform\element;

/**
 * Multiple values autocomplete element, the value is a list of strings.
 *
 * The source given to the constructor owns searching, labels, per-option validation
 * and access control, the same class serves the tool_mulib autocompletemany endpoint.
 * Values are restricted to letters, digits and "_-.:@", never empty, so the list is
 * posted as one comma separated string. Not available to guests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autocompletemany extends element {
    /** @var string allowed value characters */
    public const string VALUE_REGEX = '/^[a-zA-Z0-9_\-.:@]+$/D';

    /** @var array list of allowed attributes */
    protected const array ALLOWED_ATTRIBUTES = [
        'placeholder' => param::TEXT,
    ];

    /** @var base source of options */
    private base $source;
    /** @var array label html of current values */
    private array $valuelabels = [];
    /** @var array why current values were refused */
    private array $valueerrors = [];

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
    }

    #[\Override]
    protected function parse_value(): void {
        if (isguestuser() || !isloggedin()) {
            throw new moodle_exception('noguest');
        }
        parent::parse_value();
        $value = $this->value;
        if ($value === null || $value === '' || $value === []) {
            $this->value = [];
            return;
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        } else if (is_int($value)) {
            $value = [(string)$value];
        }
        if (!is_array($value)) {
            $this->value = [];
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $values = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $item = (string)$item;
            }
            if (!is_string($item)) {
                $this->value = [];
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $item = trim($item);
            if (!preg_match(self::VALUE_REGEX, $item)) {
                $this->value = [];
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $values[$item] = $item;
        }
        $values = array_values($values);

        $this->valuelabels = $this->source->labels($values);
        foreach ($values as $item) {
            if (!isset($this->valuelabels[$item])) {
                $this->value = [];
                $this->valuelabels = [];
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
        }
        $this->value = $values;
    }

    #[\Override]
    public function validate(array &$allerrors): void {
        parent::validate($allerrors);
        if (!$this->value || $this->errors) {
            return;
        }
        $this->valueerrors = $this->source->validate($this->value);
        foreach ($this->value as $item) {
            if (isset($this->valueerrors[$item])) {
                $allerrors[$this->get_name()][] = trim(strip_tags($this->valuelabels[$item])) . ': ' . $this->valueerrors[$item];
            }
        }
    }

    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return $this->value !== [];
    }

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);
        $selected = [];
        foreach ($this->value as $item) {
            $selected[] = [
                'value' => $item,
                'label' => $this->valuelabels[$item],
                'error' => $this->valueerrors[$item] ?? null,
            ];
        }
        $context['url'] = util::get_path_for_callable([\tool_mulib\route\api\muform\autocompletemany::class, 'search'])->out(false);
        $source = ['class' => get_class($this->source), 'args' => $this->source->get_args()];
        $context['sourcejson'] = json_encode($source, JSON_UNESCAPED_SLASHES);
        $context['selected'] = $selected;
        $context['has_selected'] = (bool)$selected;
        $context['selectedjson'] = json_encode($selected, JSON_UNESCAPED_SLASHES);
        $context['keys'] = implode(',', $this->value);
        return $context;
    }
}
