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
 * Training credits custom field from tool_mutrain, non-negative value with two decimals stored in decvalue.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mutrain extends base {
    #[\Override]
    public function create_element(string $elname, string $label, ?stdClass $data, \context $context): element {
        $element = new element\number($elname, $label, ['decimals' => 2, 'min' => 0, 'width' => 'small']);
        $element->set_required($this->is_required());
        return $element;
    }

    #[\Override]
    public function get_default_value(): mixed {
        return null;
    }

    #[\Override]
    public function get_stored_value(stdClass $data): mixed {
        if ($data->decvalue === null || $data->decvalue === '') {
            return null;
        }
        return round((float)$data->decvalue, 2);
    }

    #[\Override]
    public function get_datafield(): string {
        return 'decvalue';
    }

    #[\Override]
    public function get_data_columns(element $element): array {
        $value = $element->get_value();
        if (empty($value)) {
            // Zero means no credits, the same as the core field.
            return ['decvalue' => null, 'value' => ''];
        }
        $value = format_float((float)$value, 2, false, false);
        return ['decvalue' => $value, 'value' => (string)$value];
    }

    #[\Override]
    public function is_empty(mixed $value): bool {
        return empty($value);
    }
}
