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

namespace tool_mulib\muform\autocomplete;

use core\context;
use tool_mulib\local\cohort_util;
use tool_mulib\local\mulib;
use tool_mulib\local\search_util;
use tool_mulib\local\sql;

/**
 * Cohorts whose members the current user may list, for filtering of users by cohort membership.
 *
 * See cohort_util::get_view_members_sql() for the rules; cohorts that are only visible are not offered,
 * because filtering by a cohort reveals its members. With multi-tenancy cohorts of other tenants are not offered.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class member_cohorts extends base {
    /** @var context */
    private context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the filtered list, tenant rules use it
     */
    public function __construct(int $contextid) {
        if (!isloggedin() || isguestuser()) {
            throw new \core\exception\require_login_exception('Guests cannot filter by cohorts');
        }
        $this->context = context::instance_by_id($contextid);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->context->id];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        global $DB;

        $sql = $this->get_cohorts_sql()
            ->replace_comment('search', search_util::get_cohort_search_query(trim($query), 'ch')->wrap('AND ', ''));
        $exclude = array_values(array_filter($exclude, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if ($exclude) {
            [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'mcex', false);
            $sql = $sql->replace_comment('only', new sql("AND ch.id $notin", $params));
        }
        $cohorts = $DB->get_records_sql_menu($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($cohorts) > $maxitems) {
            return null;
        }
        return $this->format($cohorts);
    }

    #[\Override]
    public function labels(array $values): array {
        global $DB;

        $ids = array_values(array_filter($values, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if (!$ids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mcid');
        $sql = $this->get_cohorts_sql()->replace_comment('only', new sql("AND ch.id $in", $params));
        return $this->format($DB->get_records_sql_menu($sql->sql, $sql->params));
    }

    /**
     * Query of cohorts with placeholders for search and for the limit to some cohorts.
     *
     * @return sql
     */
    private function get_cohorts_sql(): sql {
        global $USER;

        $sql = (new sql(
            "SELECT ch.id, ch.name
               FROM {cohort} ch
               /* tenantjoin */
              WHERE /* viewmembers */
                    /* only */ /* search */
           ORDER BY ch.name ASC, ch.id ASC"
        ))->replace_comment('viewmembers', cohort_util::get_view_members_sql('ch', (int)$USER->id));
        if (mulib::is_mutenancy_active() && $this->context->tenantid) {
            $sql = $sql->replace_comment(
                'tenantjoin',
                new sql(
                    "JOIN {context} tctx ON tctx.id = ch.contextid AND (tctx.tenantid = ? OR tctx.tenantid IS NULL)",
                    [$this->context->tenantid]
                )
            );
        }
        return $sql;
    }

    /**
     * Labels of cohorts.
     *
     * @param string[] $cohorts names indexed with ids
     * @return string[] label html indexed with ids
     */
    private function format(array $cohorts): array {
        $result = [];
        foreach ($cohorts as $id => $name) {
            $result[(string)$id] = format_string($name, true, ['context' => $this->context]);
        }
        return $result;
    }
}
