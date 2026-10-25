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

/**
 * Option descriptions of card elements.
 *
 * Inspired by Bootstrap 6 field group cards, own Bootstrap 5 compatible implementation,
 * MuTMS cannot wait for the Bootstrap 6 migration of Moodle.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cards {
    /**
     * Validate option descriptions.
     *
     * @param array $descriptions plain text descriptions indexed by option keys
     * @param options $options
     * @return array descriptions indexed by string keys
     */
    public static function validate_descriptions(array $descriptions, options $options): array {
        $result = [];
        foreach ($descriptions as $key => $description) {
            $key = (string)$key;
            if (!is_string($description)) {
                throw new coding_exception('Option descriptions must be strings: ' . $key);
            }
            if (!$options->has_key($key)) {
                throw new coding_exception('Description of unknown option: ' . $key);
            }
            $result[$key] = $description;
        }
        return $result;
    }

    /**
     * Add descriptions to option template data.
     *
     * @param array $groups data from options::get_template_data()
     * @param array $descriptions plain text descriptions indexed by option keys
     * @return array
     */
    public static function add_descriptions(array $groups, array $descriptions): array {
        foreach ($groups as $g => $group) {
            foreach ($group['options'] as $o => $option) {
                $description = $descriptions[$option['key']] ?? '';
                $groups[$g]['options'][$o]['description'] = clean_string($description);
                $groups[$g]['options'][$o]['has_description'] = ($description !== '');
            }
        }
        return $groups;
    }
}
