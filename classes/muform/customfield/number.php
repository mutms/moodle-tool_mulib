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

namespace tool_mulib\muform\customfield;

use stdClass;
use tool_mulib\muform\element;

/**
 * Number custom field, value stored in decvalue.
 *
 * Fields with automatic value providers are not editable.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class number extends base {
    #[\Override]
    public function is_editable(): bool {
        return empty($this->config['fieldtype']);
    }

    #[\Override]
    public function create_element(string $elname, string $label, ?stdClass $data, \context $context): element {
        $decimalplaces = max(0, min(5, (int)($this->config['decimalplaces'] ?? 0)));
        $element = new element\number($elname, $label, ['decimals' => $decimalplaces]);
        $element->set_required($this->is_required());
        return $element;
    }

    #[\Override]
    public function get_default_value(): mixed {
        $default = $this->config['defaultvalue'] ?? '';
        if ($default === '' || $default === null || !is_numeric($default)) {
            return null;
        }
        return (float)$default;
    }

    #[\Override]
    public function get_stored_value(stdClass $data): mixed {
        if ($data->decvalue === null || $data->decvalue === '') {
            return null;
        }
        return (float)$data->decvalue;
    }

    #[\Override]
    public function get_datafield(): string {
        return 'decvalue';
    }

    #[\Override]
    public function get_data_columns(element $element): array {
        $value = $element->get_value();
        if ($value === null || $value === '') {
            return ['decvalue' => null, 'value' => ''];
        }
        return ['decvalue' => (float)$value, 'value' => (string)$value];
    }

    #[\Override]
    public function is_empty(mixed $value): bool {
        return $value === null || $value === '';
    }

    #[\Override]
    public function validate(element $element, array &$allerrors): void {
        $value = $element->get_value();
        if ($value === null) {
            return;
        }
        $decimalplaces = (int)($this->config['decimalplaces'] ?? 0);
        $min = $this->config['minimumvalue'] ?? '';
        $max = $this->config['maximumvalue'] ?? '';
        if (is_numeric($min) && $value < (float)$min) {
            $a = format_float((float)$min, $decimalplaces);
            $allerrors[$element->get_name()][] = get_string('minimumvalueerror', 'customfield_number', $a);
        } else if (is_numeric($max) && $value > (float)$max) {
            $a = format_float((float)$max, $decimalplaces);
            $allerrors[$element->get_name()][] = get_string('maximumvalueerror', 'customfield_number', $a);
        } else if (abs($value) >= SQL_INT_MAX) {
            $allerrors[$element->get_name()][] = get_string('maximumvalueerror', 'customfield_number', SQL_INT_MAX);
        }
    }
}
