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

use tool_mulib\local\profilefield_cache;
use stdClass;

/**
 * Custom profile fields loaded once per request.
 *
 * Usage: $fields = \core\di::get(\tool_mulib\local\profilefield\fields::class);
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fields {
    /** @var stdClass[] user_info_field records, id => record, in profile display order */
    private array $fields = [];
    /** @var base[] handlers of supported fields, fieldid => handler, in profile display order */
    private array $handlers = [];

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;
        $sql = "SELECT f.*
                  FROM {user_info_field} f
                  JOIN {user_info_category} c ON c.id = f.categoryid
              ORDER BY c.sortorder ASC, f.sortorder ASC";
        foreach ($DB->get_records_sql($sql) as $field) {
            $this->fields[$field->id] = $field;
            $handler = profilefield_cache::get_handler($field);
            if ($handler) {
                $this->handlers[$field->id] = $handler;
            }
        }
    }

    /**
     * Returns all profile fields.
     *
     * @return stdClass[] id => user_info_field record
     */
    public function get_fields(): array {
        return array_map(fn(stdClass $field) => clone($field), $this->fields);
    }

    /**
     * Returns profile field.
     *
     * @param int $fieldid
     * @return stdClass|null user_info_field record
     */
    public function get_field(int $fieldid): ?stdClass {
        return isset($this->fields[$fieldid]) ? clone($this->fields[$fieldid]) : null;
    }

    /**
     * Returns handlers of fields with types supported by the profile field cache.
     *
     * @return base[] fieldid => handler
     */
    public function get_handlers(): array {
        return $this->handlers;
    }
}
