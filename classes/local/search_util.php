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

use stdClass;

/**
 * Search helpers for autocomplete sources.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_util {
    /** @var int parameter name counter */
    private static int $paramcounter = 1;

    /**
     * Case-insensitive search for text in any of the fields.
     *
     * @param string $search
     * @param array $fields database fields
     * @param string $tablealias
     * @return sql
     */
    public static function get_search_query(string $search, array $fields, string $tablealias = ''): sql {
        global $DB;

        if ($tablealias !== '' && !str_ends_with($tablealias, '.')) {
            $tablealias .= '.';
        }

        $conditions = [];
        $params = [];

        if (trim($search) !== '') {
            $searchparam = '%' . $DB->sql_like_escape($search) . '%';

            foreach ($fields as $field) {
                $param = 'fieldsearch' . self::$paramcounter++;
                $conditions[] = $DB->sql_like($tablealias . $field, ':' . $param, false);
                $params[$param] = $searchparam;
            }
        }

        if ($conditions) {
            return new sql(' (' . implode(' OR ', $conditions) . ') ', $params);
        } else {
            return new sql(' 1=1 ');
        }
    }

    /**
     * Cohort search in name, idnumber and description.
     *
     * @param string $search
     * @param string $tablealias
     * @return sql
     */
    public static function get_cohort_search_query(string $search, string $tablealias = ''): sql {
        return self::get_search_query($search, ['name', 'idnumber', 'description'], $tablealias);
    }

    /**
     * Can the current user see the cohort.
     *
     * @param stdClass $cohort
     * @return bool
     */
    public static function is_cohort_visible(stdClass $cohort): bool {
        if ($cohort->visible) {
            return true;
        }
        $cohortcontext = \context::instance_by_id($cohort->contextid, IGNORE_MISSING);
        if (!$cohortcontext) {
            return false;
        }
        return has_capability('moodle/cohort:view', $cohortcontext);
    }

    /**
     * User search in names and identity fields visible in the context.
     *
     * @param string $search
     * @param string $tablealias
     * @param \context $context
     * @return sql
     */
    public static function get_user_search_query(string $search, string $tablealias, \context $context): sql {
        $fields = \core_user\fields::for_name()->with_identity($context, false);
        $extrafields = $fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);
        [$sql, $params] = users_search_sql($search, $tablealias, USER_SEARCH_CONTAINS, $extrafields);
        return new sql($sql, $params);
    }

    /**
     * User search ORDER BY sql.
     *
     * @param string $search
     * @param string $tablealias
     * @param \context $context
     * @return sql
     */
    public static function get_user_search_orderby(string $search, string $tablealias, \context $context): sql {
        [$sql, $params] = users_order_by_sql($tablealias, $search, $context);
        return new sql($sql, $params);
    }

    /**
     * Limit users to the tenant of the context and related users.
     *
     * @param string $useridfield for example usr.id
     * @param \context $context
     * @return sql where condition, empty if not limited
     */
    public static function get_tenant_related_users_where(string $useridfield, \context $context): sql {
        if (!mulib::is_mutenancy_active()) {
            return new sql('');
        }

        $where = \tool_mutenancy\local\tenancy::get_related_users_exists($useridfield, $context, '');
        if ($where === '1=1') {
            return new sql('');
        }

        return new sql($where);
    }

    /**
     * User label with identity fields visible in the context.
     *
     * @param stdClass $user
     * @param \context $context
     * @return string html fragment
     */
    public static function format_user_label(stdClass $user, \context $context): string {
        global $OUTPUT;

        if ($user->deleted) {
            return get_string('deleted');
        }

        $fields = \core_user\fields::for_name()->with_identity($context, false);

        $data = (object)[
            'id' => $user->id,
            'fullname' => fullname($user, has_capability('moodle/site:viewfullnames', $context)),
            'extrafields' => [],
        ];

        foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
            $data->extrafields[] = (object)[
                'name' => $extrafield,
                'value' => s($user->$extrafield),
            ];
        }

        return clean_text($OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $data));
    }
}
