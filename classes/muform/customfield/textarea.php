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
use tool_mulib\muform\util\file_area;

/**
 * Textarea custom field, text stored in value with valueformat, files in customfield_textarea/value/<dataid>.
 *
 * Texts are cleaned by the editor element, so valuetrust is never set.
 * Files embedded in default values are not copied to new instances.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class textarea extends base {
    #[\Override]
    public function create_element(string $elname, string $label, ?stdClass $data, \context $context): element {
        $element = new element\editor($elname, $label, -1);
        $element->set_required($this->is_required());
        if ($data) {
            $element->set_file_area(new file_area((int)$data->contextid, 'customfield_textarea', 'value', (int)$data->id));
        }
        return $element;
    }

    #[\Override]
    public function get_default_value(): mixed {
        return (string)($this->config['defaultvalue'] ?? '');
    }

    #[\Override]
    public function get_stored_value(stdClass $data): mixed {
        return (string)$data->value;
    }

    #[\Override]
    public function get_datafield(): string {
        return 'value';
    }

    #[\Override]
    public function get_data_columns(element $element): array {
        $format = $element->get_additional_data()[$element->get_name() . 'format'] ?? FORMAT_HTML;
        return ['value' => (string)$element->get_value(), 'valueformat' => (int)$format, 'valuetrust' => 0];
    }

    #[\Override]
    public function is_empty(mixed $value): bool {
        return html_is_blank((string)$value);
    }
}
