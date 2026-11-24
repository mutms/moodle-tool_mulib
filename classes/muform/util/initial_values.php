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

use core\exception\invalid_parameter_exception;

/**
 * Initial values posted to forms with sesskey, for example ids selected in a report.
 *
 * Dialog opened with data option posts the values in the first request, it is not a form submission,
 * scripts use the values as element defaults and validate them again when the form is submitted.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class initial_values {
    /**
     * Returns posted comma separated ids.
     *
     * @param string $name parameter name
     * @param int $max maximum number of ids
     * @return int[]|null null when not posted or when the request is a form submission
     */
    public static function get_ids(string $name, int $max): ?array {
        if (!isset($_POST[$name]) || isset($_POST['__formid'])) {
            return null;
        }
        require_sesskey();
        $value = $_POST[$name];
        if ($value === '') {
            return [];
        }
        if (!is_string($value) || !preg_match('/^\d+(,\d+)*$/D', $value)) {
            throw new invalid_parameter_exception('Invalid ids');
        }
        $ids = array_values(array_unique(array_map('intval', explode(',', $value))));
        if (count($ids) > $max) {
            throw new invalid_parameter_exception('Too many ids');
        }
        return $ids;
    }
}
