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

namespace tool_mulib\local;

/**
 * Report builder helpers for MuTMS reports.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportbuilder_util {
    /**
     * Is the current request downloading a report?
     *
     * System reports should not add actions when downloading, the report builder download script
     * is missing NO_DEBUG_DISPLAY and template rendering is changing session after it is closed.
     * Column callbacks should return plain text instead of HTML markup when downloading.
     *
     * @return bool
     */
    public static function is_downloading(): bool {
        global $SCRIPT;
        return $SCRIPT === '/reportbuilder/download.php';
    }

    /**
     * Format text as a badge pill, plain text when downloading.
     *
     * @param string $text plain text
     * @param string $class Bootstrap colour classes, for example 'text-bg-success'
     * @return string html
     */
    public static function format_badge(string $text, string $class): string {
        if (self::is_downloading()) {
            return $text;
        }
        return \html_writer::span(s($text), 'badge rounded-pill ' . $class);
    }
}
