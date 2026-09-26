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
 * Cohort search helpers for autocomplete sources of either family.
 *
 * Visible cohorts and cohorts the user may view are offered, with multi-tenancy
 * cohorts of other tenants are not. Cohorts already used by the edited item
 * ("current" ids) are always accepted, whatever changed since.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait cohort_trait {
    /**
     * Search cohorts.
     *
     * @param context $context context of the edited item, tenant rules use it
     * @param string $query
     * @param int $maxitems
     * @param string[] $exclude cohort ids not to return
     * @return array|null [id => label html], null when more than $maxitems match
     */
    protected function search_cohorts(context $context, string $query, int $maxitems, array $exclude = []): ?array {
        global $DB, $USER;

        $sql = (new sql(
            "SELECT ch.id, ch.name
               FROM {cohort} ch
               /* tenantjoin */
               /* capsubquery */
              WHERE (ch.visible = 1 OR capctx.id IS NOT NULL)
                    /* exclude */ /* searchsql */
           ORDER BY ch.name ASC, ch.id ASC"
        ))
            ->replace_comment(
                'capsubquery',
                context_map::get_contexts_by_capability_query(
                    'moodle/cohort:view',
                    $USER->id,
                    new sql("(ctx.contextlevel = ? OR ctx.contextlevel = ?)", [CONTEXT_SYSTEM, CONTEXT_COURSECAT])
                )->wrap("LEFT JOIN (", ")capctx ON capctx.id = ch.contextid")
            )
            ->replace_comment('searchsql', search_util::get_cohort_search_query(trim($query), 'ch')->wrap('AND ', ''));

        $exclude = array_values(array_filter($exclude, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if ($exclude) {
            [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'chex', false);
            $sql = $sql->replace_comment('exclude', new sql("AND ch.id $notin", $params));
        }
        if (mulib::is_mutenancy_active() && $context->tenantid) {
            $sql = $sql->replace_comment(
                'tenantjoin',
                new sql(
                    "JOIN {context} tctx ON tctx.id = ch.contextid AND (tctx.tenantid = ? OR tctx.tenantid IS NULL)",
                    [$context->tenantid]
                )
            );
        }

        $cohorts = $DB->get_records_sql_menu($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($cohorts) > $maxitems) {
            return null;
        }
        $result = [];
        foreach ($cohorts as $id => $name) {
            $result[(string)$id] = format_string($name, true, ['context' => $context]);
        }
        return $result;
    }

    /**
     * Labels of cohorts that may be selected, unknown or not allowed ids are left out.
     *
     * @param context $context context of the edited item
     * @param string[] $ids
     * @param int[] $current cohort ids already used by the edited item
     * @return array [id => label html]
     */
    protected function cohort_labels(context $context, array $ids, array $current = []): array {
        $result = [];
        foreach ($this->get_cohorts($ids) as $id => $cohort) {
            if (in_array((int)$id, $current) || self::is_cohort_allowed($cohort, $context)) {
                $result[$id] = format_string($cohort->name, true, ['context' => $context]);
            }
        }
        return $result;
    }

    /**
     * Why cohorts must not be selected.
     *
     * @param context $context context of the edited item
     * @param string[] $ids
     * @param int[] $current cohort ids already used by the edited item
     * @return array [id => error text]
     */
    protected function validate_cohorts(context $context, array $ids, array $current = []): array {
        $cohorts = $this->get_cohorts($ids);
        $result = [];
        foreach ($ids as $id) {
            if (in_array((int)$id, $current)) {
                continue;
            }
            if (!isset($cohorts[$id]) || !self::is_cohort_allowed($cohorts[$id], $context)) {
                $result[$id] = get_string('error');
            }
        }
        return $result;
    }

    /**
     * May the cohort be used for an item in the context?
     *
     * @param stdClass $cohort
     * @param context $context context of the edited item
     * @return bool
     */
    private static function is_cohort_allowed(stdClass $cohort, context $context): bool {
        $cohortcontext = context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        if (!$cohortcontext) {
            return false;
        }
        if (mulib::is_mutenancy_active()) {
            if ($cohortcontext->tenantid && $context->tenantid && $cohortcontext->tenantid != $context->tenantid) {
                return false;
            }
        }
        return search_util::is_cohort_visible($cohort);
    }

    /**
     * Fetch cohorts.
     *
     * @param string[] $ids
     * @return stdClass[] indexed by string id
     */
    private function get_cohorts(array $ids): array {
        global $DB;
        $ids = array_values(array_filter($ids, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if (!$ids) {
            return [];
        }
        $result = [];
        foreach ($DB->get_records_list('cohort', 'id', $ids) as $cohort) {
            $result[(string)$cohort->id] = $cohort;
        }
        return $result;
    }
}
