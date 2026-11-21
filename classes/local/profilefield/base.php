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

namespace tool_mulib\local\profilefield;

use stdClass;

/**
 * Base for handlers of custom profile field types cached in tool_mulib_profilefield.
 *
 * Handlers provide SQL expressions mapping stored profile field data to cached values.
 * Every non-deleted user has one cached row per cached field with the effective value,
 * that is the stored data or the field default.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Constructor.
     *
     * @param stdClass $field user_info_field record
     */
    final public function __construct(
        /** @var stdClass user_info_field record */
        protected readonly stdClass $field
    ) {
    }

    /**
     * Returns profile field record.
     *
     * @return stdClass
     */
    final public function get_field(): stdClass {
        return clone($this->field);
    }

    /**
     * Returns formatted field name.
     *
     * @return string
     */
    final public function get_field_name(): string {
        return format_string($this->field->name, true, ['context' => \core\context\system::instance()]);
    }

    /**
     * Returns column of tool_mulib_profilefield used for cached values.
     *
     * @return string 'charvalue' or 'intvalue'
     */
    abstract public function get_cache_column(): string;

    /**
     * Returns SQL expression of cached value, never NULL.
     *
     * @param string $data expression of stored data without parameters,
     *      the field default is already used when user has no data
     * @return string
     */
    abstract public function get_cache_sql(string $data): string;
}
