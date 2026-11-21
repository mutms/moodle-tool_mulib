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

/**
 * Handler of datetime profile fields, timestamp or 0 when not set.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class datetime extends base {
    #[\Override]
    public function get_cache_column(): string {
        return 'intvalue';
    }

    #[\Override]
    public function get_cache_sql(string $data): string {
        global $DB;
        $timestamp = $DB->sql_cast_char2int("TRIM($data)");
        return "CASE WHEN TRIM($data) = '' THEN 0 ELSE $timestamp END";
    }
}
