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

use core\exception\moodle_exception;
use core\output\core_renderer;
use tool_mulib\muform\picker\base;

/**
 * Shared code of picker, pickermany, userpicker and userpickermany elements.
 *
 * Classes using it must define is_multiple() and call init_picker() from the constructor.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait picker_trait {
    /** @var base source of values */
    private base $source;
    /** @var string[] label html of current values indexed by value */
    private array $valuelabels = [];
    /** @var string[] why current values were refused, indexed by value */
    private array $valueerrors = [];

    /**
     * May several values be picked?
     *
     * @return bool
     */
    abstract protected function is_multiple(): bool;

    /**
     * Set up the element.
     *
     * @param string $label
     * @param base $source
     */
    private function init_picker(string $label, base $source): void {
        $this->label = $label;
        $this->source = $source;
        // Both elements look the same, only the browser module differs.
        $this->set_template('tool_mulib/muform/element/picker');
    }

    /**
     * Selected values as a list.
     *
     * @return string[]
     */
    private function get_selected(): array {
        if ($this->is_multiple()) {
            return $this->value;
        }
        return $this->value === null ? [] : [$this->value];
    }

    /**
     * Store the list of selected values as the value.
     *
     * @param string[] $values
     */
    private function set_selected(array $values): void {
        if ($this->is_multiple()) {
            $this->value = $values;
        } else {
            $this->value = $values ? reset($values) : null;
        }
    }

    /**
     * Parse submitted or current value, values without a label from the source are refused.
     */
    #[\Override]
    protected function parse_value(): void {
        if (isguestuser() || !isloggedin()) {
            throw new moodle_exception('noguest');
        }
        parent::parse_value();
        $value = $this->value;
        $this->set_selected([]);
        if ($value === null || $value === '' || $value === []) {
            return;
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        } else if (is_int($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $values = [];
        foreach ($value as $item) {
            $item = is_int($item) ? (string)$item : $item;
            if (!is_string($item) || !preg_match(base::VALUE_REGEX, trim($item))) {
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
            $values[trim($item)] = trim($item);
        }
        $values = array_map('strval', array_values($values));
        if (!$this->is_multiple() && count($values) > 1) {
            $this->errors[] = $this->get_invalid_hint();
            return;
        }

        $labels = $this->source->labels($values);
        foreach ($values as $item) {
            if (!isset($labels[$item])) {
                // Not allowed by the source, there is nothing that could be shown.
                $this->errors[] = $this->get_invalid_hint();
                return;
            }
        }
        $this->valuelabels = $labels;
        $this->set_selected($values);
    }

    /**
     * Validate the selection with the source.
     *
     * @param array $allerrors
     */
    #[\Override]
    public function validate(array &$allerrors): void {
        parent::validate($allerrors);
        $selected = $this->get_selected();
        if (!$selected || $this->errors) {
            return;
        }
        $this->valueerrors = $this->source->validate($selected);
        foreach ($selected as $item) {
            if (isset($this->valueerrors[$item])) {
                $label = html_entity_decode(strip_tags($this->valuelabels[$item]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $allerrors[$this->get_name()][] = trim(preg_replace('/\s+/u', ' ', $label)) . ': ' . $this->valueerrors[$item];
            }
        }
    }

    /**
     * Is anything selected?
     *
     * @return bool
     */
    #[\Override]
    public function has_required_value(): bool {
        parent::has_required_value();
        return (bool)$this->get_selected();
    }

    /**
     * Template data with the selection, the panel module, the source and strings.
     *
     * @param core_renderer $output
     * @param array $allerrors
     * @return array
     */
    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);

        $selected = [];
        foreach ($this->get_selected() as $item) {
            $selected[] = [
                'value' => $item,
                'label' => $this->valuelabels[$item],
                'error' => $this->valueerrors[$item] ?? null,
            ];
        }
        $panel = $this->source->export_panel($this->is_multiple());
        $strings = $panel['strings'];
        foreach (['add', 'change', 'remove', 'select'] as $key) {
            $strings['picker' . ucfirst($key)] = get_string('muform_picker_' . $key, 'tool_mulib');
        }
        // The control is a group of buttons, a label cannot point to one control.
        $context['nolabelfor'] = true;
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $context['value'] = implode(',', $this->get_selected());
        $context['is_multiple'] = $this->is_multiple();
        $context['selected'] = $selected;
        $context['has_selected'] = (bool)$selected;
        $context['selectedjson'] = json_encode($selected, $flags);
        $context['panel'] = $panel['panel'];
        $context['sourcejson'] = json_encode($panel['source'], $flags);
        $context['stringsjson'] = json_encode($strings, $flags);
        return $context;
    }
}
