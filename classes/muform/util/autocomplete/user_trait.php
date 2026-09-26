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
use tool_mulib\local\search_util;
use tool_mulib\local\sql;

/**
 * User search helpers for autocomplete sources of either family.
 *
 * Deleted, unconfirmed and guest users are never returned, tenant restrictions apply,
 * labels are the core user selector suggestion html passed through clean_text().
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait user_trait {
    /**
     * Search users.
     *
     * @param context $context context for identity fields and tenant rules
     * @param string $query
     * @param int $maxitems
     * @param string[] $exclude user ids not to return
     * @param sql|null $where extra condition on table alias "u"
     * @return array|null [id => label html], null when more than $maxitems match
     */
    protected function search_users(context $context, string $query, int $maxitems, array $exclude, ?sql $where = null): ?array {
        global $DB;

        $search = trim($query) === '' ? new sql('') : search_util::get_user_search_query(trim($query), 'u', $context);
        $orderby = search_util::get_user_search_orderby(trim($query), 'u', $context);
        $sql = $this->users_sql($context, $search, $exclude, $where);
        $sql = $sql->replace_comment('orderby', $orderby->wrap('ORDER BY ', ''));

        $users = $DB->get_records_sql($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($users) > $maxitems) {
            return null;
        }
        $result = [];
        foreach ($users as $user) {
            $result[(string)$user->id] = search_util::format_user_label($user, $context);
        }
        return $result;
    }

    /**
     * Labels of given users, unknown or not allowed ids are left out.
     *
     * @param context $context
     * @param string[] $ids
     * @param sql|null $where extra condition on table alias "u"
     * @return array [id => label html]
     */
    protected function user_labels(context $context, array $ids, ?sql $where = null): array {
        global $DB;

        $ids = array_values(array_filter($ids, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if (!$ids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ids');
        $only = new sql('u.id ' . $insql, $inparams);
        $where = $where ? sql::join(' AND ', [$only, $where]) : $only;
        $sql = $this->users_sql($context, new sql(''), [], $where);
        $sql = $sql->replace_comment('orderby', 'ORDER BY u.id');

        $result = [];
        foreach ($DB->get_records_sql($sql->sql, $sql->params) as $user) {
            $result[(string)$user->id] = search_util::format_user_label($user, $context);
        }
        return $result;
    }

    /**
     * Suspended users must not be selected.
     *
     * @param string[] $ids
     * @return array [id => error text]
     */
    protected function validate_users(array $ids): array {
        global $DB;

        $ids = array_values(array_filter($ids, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if (!$ids) {
            return [];
        }
        $result = [];
        foreach ($DB->get_records_list('user', 'id', $ids, '', 'id, suspended') as $user) {
            if ($user->suspended) {
                $result[(string)$user->id] = get_string('muform_usersuspended', 'tool_mulib');
            }
        }
        return $result;
    }

    /**
     * Base user query with placeholders for search, exclusions and ordering.
     *
     * @param context $context
     * @param sql $search
     * @param string[] $exclude
     * @param sql|null $where
     * @return sql
     */
    private function users_sql(context $context, sql $search, array $exclude, ?sql $where): sql {
        global $DB, $CFG;

        $fields = \core_user\fields::for_name()->with_identity($context, false);
        $fieldsql = $fields->get_sql('u', true);
        $tenantwhere = search_util::get_tenant_related_users_where('u.id', $context);

        $excludesql = new sql('');
        $exclude = array_values(array_filter($exclude, fn($id) => is_string($id) && preg_match('/^\d+$/D', $id)));
        if ($exclude) {
            [$notinsql, $notinparams] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);
            $excludesql = new sql('u.id ' . $notinsql, $notinparams);
        }

        $sql = new sql(
            "SELECT u.id, u.deleted, u.suspended $fieldsql->selects
               FROM {user} u
                    $fieldsql->joins
              WHERE u.deleted = 0 AND u.confirmed = 1 AND u.id <> :guestid
                    /* search */ /* tenant */ /* exclude */ /* where */
                    /* orderby */",
            array_merge($fieldsql->params, ['guestid' => (int)$CFG->siteguest])
        );
        return $sql
            ->replace_comment('search', $search->wrap('AND ', ''))
            ->replace_comment('tenant', $tenantwhere->wrap('AND ', ''))
            ->replace_comment('exclude', $excludesql->wrap('AND ', ''))
            ->replace_comment('where', ($where ?? new sql(''))->wrap('AND (', ')'));
    }
}
