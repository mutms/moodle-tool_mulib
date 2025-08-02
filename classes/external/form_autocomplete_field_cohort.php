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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_mulib\external;

use stdClass;

/**
 * Base class for cohort autocomplete fields.
 *
 * @package     tool_mulib
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form_autocomplete_field_cohort extends form_autocomplete_field {
    /** @var int max returned results */
    const MAX_RESULTS = 20;

    /**
     * Returns cohort query data.
     *
     * @param string $search
     * @param string $tablealias
     * @return array
     */
    public static function get_cohort_search_query(string $search, string $tablealias = ''): array {
        global $DB;

        if ($tablealias !== '' && !str_ends_with($tablealias, '.')) {
            $tablealias .= '.';
        }

        $conditions = [];
        $params = [];

        if (trim($search) !== '') {
            $searchparam = '%' . $DB->sql_like_escape($search) . '%';
            $fields = ['name', 'idnumber', 'description'];
            $cnt = 0;
            foreach ($fields as $field) {
                $conditions[] = $DB->sql_like($tablealias . $field, ':chsearch' . $cnt, false);
                $params['chsearch' . $cnt] = $searchparam;
                $cnt++;
            }
        }

        if ($conditions) {
            $sql = '(' . implode(' OR ', $conditions) . ') ';
            return [$sql, $params];
        } else {
            return ['1=1 ', $params];
        }
    }

    /**
     * Returns formatted cohort list to be return from WS.
     *
     * @param \moodle_recordset $rs
     * @return array
     */
    public static function prepare_cohort_list(\moodle_recordset $rs): array {
        $count = 0;
        $list = [];
        $notice = null;

        foreach ($rs as $cohort) {
            $context = \context::instance_by_id($cohort->contextid, IGNORE_MISSING);
            if (!$context || !self::is_cohort_visible($cohort, $context)) {
                continue;
            }
            $count++;
            if ($count > self::MAX_RESULTS) {
                $notice = get_string('toomanyrecords', 'tool_mulib', self::MAX_RESULTS);
                break;
            }
            $list[] = ['value' => $cohort->id, 'label' => format_string($cohort->name)];
        }
        $rs->close();

        return [
            'notice' => $notice,
            'list' => $list,
        ];
    }

    /**
     * Return function that return label for given value.
     *
     * @param array $arguments
     * @return callable
     */
    public static function get_label_callback(array $arguments): callable {
        return function ($value) use ($arguments): string {
            global $DB;

            $cohort = $DB->get_record('cohort', ['id' => $value]);
            if (!$cohort) {
                return get_string('error');
            }
            return format_string($cohort->name);
        };
    }

    /**
     * Can current user use the cohort?
     *
     * @param stdClass $cohort
     * @param \context $cohortcontext
     * @return bool
     */
    public static function is_cohort_visible(stdClass $cohort, \context $cohortcontext): bool {
        if ($cohort->visible) {
            return true;
        }
        return has_capability('moodle/cohort:view', $cohortcontext);
    }
}
