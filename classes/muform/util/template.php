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

use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\mustache_template_finder;

/**
 * Template variant resolution, variants are optional templates with "-variant" suffix.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template {
    /** @var array cache of resolved template names */
    private static array $cache = [];

    /**
     * Returns variant template name when it exists, the original template otherwise.
     *
     * @param string $template template name such as tool_mulib/muform/element/buttons
     * @param string $variant variant name, empty means no variant
     * @return string
     */
    public static function resolve(string $template, string $variant): string {
        global $PAGE;

        if ($variant === '') {
            return $template;
        }
        if (!preg_match('/^[a-z][a-z0-9]*$/D', $variant)) {
            throw new coding_exception('Invalid template variant name: ' . $variant);
        }

        $candidate = $template . '-' . $variant;
        $themename = $PAGE->theme->name;
        $key = $themename . ':' . $candidate;
        if (!isset(self::$cache[$key])) {
            try {
                mustache_template_finder::get_template_filepath($candidate, $themename);
                self::$cache[$key] = $candidate;
            } catch (moodle_exception $ex) {
                self::$cache[$key] = $template;
            }
        }
        return self::$cache[$key];
    }
}
