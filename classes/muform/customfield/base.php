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

use core_customfield\field_controller;
use stdClass;
use tool_mulib\muform\element;

/**
 * Maps one custom field type to a muform element and customfield_data columns.
 *
 * Only used by the customfields element, one instance per field.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var field_controller custom field */
    protected field_controller $field;
    /** @var array field configdata */
    protected array $config;

    /**
     * Constructor.
     *
     * @param field_controller $field
     */
    final public function __construct(field_controller $field) {
        $this->field = $field;
        $config = $field->get('configdata');
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Is the field editable in forms?
     *
     * @return bool
     */
    public function is_editable(): bool {
        return true;
    }

    /**
     * Is the value required?
     *
     * @return bool
     */
    final protected function is_required(): bool {
        return !empty($this->config['required']);
    }

    /**
     * Create configured element, required flag included.
     *
     * @param string $elname
     * @param string $label
     * @param stdClass|null $data customfield_data record, null if not stored yet
     * @param \context $context instance context
     * @return element
     */
    abstract public function create_element(string $elname, string $label, ?stdClass $data, \context $context): element;

    /**
     * Element value for instances without stored data.
     *
     * @return mixed
     */
    abstract public function get_default_value(): mixed;

    /**
     * Element value from a customfield_data record.
     *
     * @param stdClass $data
     * @return mixed
     */
    abstract public function get_stored_value(stdClass $data): mixed;

    /**
     * Name of the customfield_data column the type stores its value in.
     *
     * @return string
     */
    abstract public function get_datafield(): string;

    /**
     * Returns customfield_data columns for the submitted element value, "value" column included.
     *
     * @param element $element
     * @return array
     */
    abstract public function get_data_columns(element $element): array;

    /**
     * Is the element value empty? Empty values are not checked for uniqueness.
     *
     * @param mixed $value
     * @return bool
     */
    public function is_empty(mixed $value): bool {
        return $value === null || $value === '' || $value === 0;
    }

    /**
     * Type specific validation of a submitted value.
     *
     * @param element $element
     * @param array $allerrors
     */
    public function validate(element $element, array &$allerrors): void {
    }
}
