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
 * Select custom field, the option index is stored in intvalue, 0 means nothing selected.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class select extends base {
    #[\Override]
    public function create_element(string $elname, string $label, ?stdClass $data, \context $context): element {
        $element = new element\select($elname, $label, $this->get_options());
        $element->set_required($this->is_required());
        return $element;
    }

    /**
     * Option labels indexed by option index, empty option has key ''.
     *
     * @return array
     */
    private function get_options(): array {
        $options = ['' => ''];
        foreach ($this->get_option_texts() as $i => $text) {
            $options[$i] = format_string($text, true, ['context' => \context_system::instance()]);
        }
        return $options;
    }

    /**
     * Raw option texts indexed from 1 like core field.
     *
     * @return array
     */
    private function get_option_texts(): array {
        $texts = preg_split("/\s*\n\s*/", trim((string)($this->config['options'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        if (!$texts) {
            return [];
        }
        return array_combine(range(1, count($texts)), $texts);
    }

    #[\Override]
    public function get_default_value(): mixed {
        $default = (string)($this->config['defaultvalue'] ?? '');
        if ($default === '') {
            return null;
        }
        $key = array_search($default, $this->get_option_texts(), true);
        return ($key === false) ? null : (string)$key;
    }

    #[\Override]
    public function get_stored_value(stdClass $data): mixed {
        $value = (int)$data->intvalue;
        if ($value <= 0 || !array_key_exists($value, $this->get_option_texts())) {
            return null;
        }
        return (string)$value;
    }

    #[\Override]
    public function get_datafield(): string {
        return 'intvalue';
    }

    #[\Override]
    public function get_data_columns(element $element): array {
        $value = $element->get_value();
        $value = (int)$value;
        return ['intvalue' => $value, 'value' => (string)$value];
    }
}
