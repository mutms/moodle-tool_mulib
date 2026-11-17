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
 * Advanced cohort access records are read here only, the shape of tool_mucohort (cohortid, type)
 * and tool_mucohort_access (mucohortid, userid, usecohort, viewmembers) is a contract between tool_mucohort
 * and other MuTMS plugins.
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

    /**
     * SQL condition matching cohorts the user may use in MuTMS plugins, for example select them in forms.
     *
     * The same rules as can_use() of tool_mucohort cohort types: visible cohorts and cohorts in contexts
     * with moodle/cohort:view capability, the same as in standard Moodle, and advanced cohorts
     * with an access record that allows using of the cohort.
     *
     * @param string $cohortalias alias of the cohort table
     * @param int $userid
     * @return sql condition
     */
    public static function get_use_sql(string $cohortalias, int $userid): sql {
        if (!$userid) {
            return new sql("{$cohortalias}.visible = 1");
        }
        $levels = new sql("(ctx.contextlevel = ? OR ctx.contextlevel = ?)", [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);
        $view = context_map::get_contexts_by_capability_query('moodle/cohort:view', $userid, $levels)
            ->wrap("{$cohortalias}.contextid IN (", ")");
        $sql = (new sql("({$cohortalias}.visible = 1 OR /* view */ /* access */)"))->replace_comment('view', $view);
        if (!mulib::is_mucohort_available() || isguestuser($userid)) {
            return $sql->replace_comment('access', '');
        }
        return $sql->replace_comment(
            'access',
            new sql(
                "OR ({$cohortalias}.component = 'tool_mucohort' AND EXISTS (
                        SELECT 'x'
                          FROM {tool_mucohort} cua_mc
                          JOIN {tool_mucohort_access} cua_a ON cua_a.mucohortid = cua_mc.id
                         WHERE cua_mc.cohortid = {$cohortalias}.id AND cua_mc.type IN ('curated', 'rules')
                               AND cua_a.userid = ? AND cua_a.usecohort = 1))",
                [$userid]
            )
        );
    }

    /**
     * May the current user use the cohort in MuTMS plugins?
     *
     * @param stdClass $cohort cohort record
     * @return bool
     */
    public static function can_use(stdClass $cohort): bool {
        $context = \core\context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        if (mulib::is_mucohort_available()) {
            return \tool_mucohort\local\type\base::from_record($cohort)->can_use();
        }
        return $cohort->visible || has_capability('moodle/cohort:view', $context);
    }

    /**
     * SQL condition matching cohorts whose members the user may list.
     *
     * The same rules as can_view_members() of tool_mucohort cohort types: standard cohorts and cohorts
     * of other plugins need moodle/cohort:assign or tool/mucohort:viewmembers capability in the cohort context,
     * advanced cohorts tool/mucohort:viewmembers capability or an access record that allows viewing of members.
     * Without tool_mucohort only moodle/cohort:assign allows it, the same as in standard cohort pages.
     *
     * @param string $cohortalias alias of the cohort table
     * @param int $userid
     * @return sql condition
     */
    public static function get_view_members_sql(string $cohortalias, int $userid): sql {
        if (!$userid || isguestuser($userid)) {
            return new sql('1=2');
        }
        $levels = new sql("(ctx.contextlevel = ? OR ctx.contextlevel = ?)", [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);
        $assign = context_map::get_contexts_by_capability_query('moodle/cohort:assign', $userid, $levels)
            ->wrap("{$cohortalias}.contextid IN (", ")");
        if (!mulib::is_mucohort_available()) {
            return $assign;
        }

        $sql = new sql(
            "(/* viewmembers */
              OR (/* assign */
                  AND ({$cohortalias}.component IS NULL OR {$cohortalias}.component <> 'tool_mucohort' OR NOT EXISTS (
                        SELECT 'x'
                          FROM {tool_mucohort} vms_mc
                         WHERE vms_mc.cohortid = {$cohortalias}.id AND vms_mc.type IN ('curated', 'rules'))))
              OR ({$cohortalias}.component = 'tool_mucohort' AND EXISTS (
                        SELECT 'x'
                          FROM {tool_mucohort} vma_mc
                          JOIN {tool_mucohort_access} vma_a ON vma_a.mucohortid = vma_mc.id
                         WHERE vma_mc.cohortid = {$cohortalias}.id AND vma_mc.type IN ('curated', 'rules')
                               AND vma_a.userid = ? AND vma_a.viewmembers = 1)))",
            [$userid]
        );
        $viewmembers = context_map::get_contexts_by_capability_query('tool/mucohort:viewmembers', $userid, $levels)
            ->wrap("{$cohortalias}.contextid IN (", ")");
        return $sql->replace_comment('viewmembers', $viewmembers)->replace_comment('assign', $assign);
    }

    /**
     * May the current user list members of the cohort?
     *
     * @param stdClass $cohort cohort record
     * @return bool
     */
    public static function can_view_members(stdClass $cohort): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (mulib::is_mucohort_available()) {
            return \tool_mucohort\local\type\base::from_record($cohort)->can_view_members();
        }
        $context = \core\context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        return $context && has_capability('moodle/cohort:assign', $context);
    }
}
