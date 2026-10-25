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

use core\url;
use stdClass;

/**
 * Cohort helpers for MuTMS plugins.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_util {
    /**
     * Returns link to cohort management page if the current user may view the cohort there.
     *
     * Advanced cohort management is used when installed, otherwise the standard cohort list.
     *
     * @param stdClass $cohort cohort record
     * @return url|null
     */
    public static function get_cohort_url(stdClass $cohort): ?url {
        if (mulib::is_mucohort_available()) {
            return \tool_mucohort\local\management::get_cohort_url($cohort);
        }

        $context = \core\context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        if (!$context || !has_any_capability(['moodle/cohort:manage', 'moodle/cohort:view'], $context)) {
            return null;
        }
        return new url('/cohort/index.php', ['contextid' => $context->id, 'search' => $cohort->name]);
    }

    /**
     * Returns formatted cohort name, linked to cohort management page if possible.
     *
     * @param stdClass $cohort cohort record
     * @param bool $link false means no link, for example when downloading reports
     * @return string html
     */
    public static function format_cohort_name(stdClass $cohort, bool $link = true): string {
        $context = \core\context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        $name = format_string($cohort->name, true, ['context' => $context ?: \core\context\system::instance()]);
        if ($link) {
            $url = self::get_cohort_url($cohort);
            if ($url) {
                $name = \core\output\html_writer::link($url, $name);
            }
        }
        return $name;
    }

    /**
     * Returns formatted names of cohorts, linked to cohort management page if possible.
     *
     * @param int[] $cohortids cohort ids, the order is kept, missing cohorts are skipped
     * @param bool $link false means no links, for example when downloading reports
     * @return array cohort id => html
     */
    public static function format_cohort_names(array $cohortids, bool $link = true): array {
        global $DB;

        if (!$cohortids) {
            return [];
        }
        $records = $DB->get_records_list('cohort', 'id', $cohortids);
        $result = [];
        foreach ($cohortids as $cohortid) {
            if (isset($records[$cohortid])) {
                $result[$cohortid] = self::format_cohort_name($records[$cohortid], $link);
            }
        }
        return $result;
    }
}
