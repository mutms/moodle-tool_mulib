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

namespace tool_mulib\muform\util\autocomplete;

use core\context;
use stdClass;
use tool_mulib\local\search_util;
use tool_mulib\local\context_map;
use tool_mulib\local\mulib;
use tool_mulib\local\sql;

/**
 * Course search helpers for autocomplete sources of either family.
 *
 * Courses where the user has the capability are offered, with multi-tenancy
 * courses of other tenants are not. Courses already used by the edited item
 * ("current" ids) are always accepted, whatever changed since.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait course_trait {
    /**
     * Search courses.
     *
     * @param context $context context of the edited item, tenant rules use it
     * @param string $capability required in course context
     * @param string $query
     * @param int $maxitems
     * @param string[] $exclude course ids not to return
     * @return array|null [id => label html], null when more than $maxitems match
     */
    protected function search_courses(
        context $context,
        string $capability,
        string $query,
        int $maxitems,
        array $exclude = []
    ): ?array {
        global $DB, $USER;

        $capjoin = context_map::get_contexts_by_capability_join($capability, $USER->id, 'ctx');
        $sql = (new sql(
            "SELECT c.id, c.fullname
               FROM {course} c
               JOIN {context} ctx ON ctx.contextlevel = :courselevel AND ctx.instanceid = c.id
               /* capjoin */
              WHERE c.id <> :siteid /* capwhere */
                    /* tenantwhere */ /* exclude */ /* searchsql */
           GROUP BY c.id, c.fullname
           ORDER BY c.fullname ASC, c.id ASC",
            ['courselevel' => CONTEXT_COURSE, 'siteid' => SITEID]
        ))
            ->replace_comment('capjoin', $capjoin['join'])
            ->replace_comment('capwhere', $capjoin['where']->wrap('AND ', ''))
            ->replace_comment(
                'searchsql',
                search_util::get_search_query(trim($query), ['fullname', 'shortname', 'idnumber'], 'c')->wrap('AND ', '')
            );

        $exclude = array_values(array_filter($exclude, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if ($exclude) {
            [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'cex', false);
            $sql = $sql->replace_comment('exclude', new sql("AND c.id $notin", $params));
        }
        if (mulib::is_mutenancy_active() && $context->tenantid) {
            $sql = $sql->replace_comment(
                'tenantwhere',
                new sql("AND (ctx.tenantid = ? OR ctx.tenantid IS NULL)", [$context->tenantid])
            );
        }

        $courses = $DB->get_records_sql($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($courses) > $maxitems) {
            return null;
        }
        $result = [];
        foreach ($courses as $course) {
            $result[(string)$course->id] = self::format_course_label($course);
        }
        return $result;
    }

    /**
     * Labels of courses that may be selected, unknown or not allowed ids are left out.
     *
     * @param context $context context of the edited item
     * @param string $capability required in course context
     * @param string[] $ids
     * @param int[] $current course ids already used by the edited item
     * @return array [id => label html]
     */
    protected function course_labels(context $context, string $capability, array $ids, array $current = []): array {
        $result = [];
        foreach ($this->get_courses($ids) as $id => $course) {
            if (in_array((int)$id, $current) || self::is_course_allowed($course, $context, $capability)) {
                $result[$id] = self::format_course_label($course);
            }
        }
        return $result;
    }

    /**
     * Why courses must not be selected.
     *
     * @param context $context context of the edited item
     * @param string $capability required in course context
     * @param string[] $ids
     * @param int[] $current course ids already used by the edited item
     * @return array [id => error text]
     */
    protected function validate_courses(context $context, string $capability, array $ids, array $current = []): array {
        $courses = $this->get_courses($ids);
        $result = [];
        foreach ($ids as $id) {
            if (in_array((int)$id, $current)) {
                continue;
            }
            if (!isset($courses[$id]) || !self::is_course_allowed($courses[$id], $context, $capability)) {
                $result[$id] = get_string('error');
            }
        }
        return $result;
    }

    /**
     * May the course be used for an item in the context?
     *
     * @param stdClass $course
     * @param context $context context of the edited item
     * @param string $capability required in course context
     * @return bool
     */
    private static function is_course_allowed(stdClass $course, context $context, string $capability): bool {
        $coursecontext = \core\context\course::instance($course->id, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }
        if (mulib::is_mutenancy_active()) {
            if ($coursecontext->tenantid && $context->tenantid && $coursecontext->tenantid != $context->tenantid) {
                return false;
            }
        }
        return has_capability($capability, $coursecontext);
    }

    /**
     * Format course label.
     *
     * @param stdClass $course
     * @return string html
     */
    private static function format_course_label(stdClass $course): string {
        $coursecontext = \core\context\course::instance($course->id);
        return clean_text(format_string($course->fullname, true, ['context' => $coursecontext]));
    }

    /**
     * Fetch courses, the site course is never returned.
     *
     * @param string[] $ids
     * @return stdClass[] indexed by string id
     */
    private function get_courses(array $ids): array {
        global $DB;
        $ids = array_values(array_filter($ids, fn($id) => is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id)));
        if (!$ids) {
            return [];
        }
        $result = [];
        foreach ($DB->get_records_list('course', 'id', $ids, 'id ASC', 'id, category, fullname') as $course) {
            if ((int)$course->id == SITEID) {
                continue;
            }
            $result[(string)$course->id] = $course;
        }
        return $result;
    }
}
