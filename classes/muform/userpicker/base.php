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

namespace tool_mulib\muform\userpicker;

use core\context;
use tool_mulib\local\search_util;
use tool_mulib\local\sql;
use tool_mulib\muform\util\autocomplete\user_trait;

/**
 * Source of userpicker and userpickermany elements - where the users are picked from.
 *
 * The panel lists one page of users at a time with full names and the identity fields visible
 * in the source context (showuseridentity and moodle/site:viewuseridentity), words of the search
 * are looked up in names and those fields. Deleted, unconfirmed and guest users are never offered,
 * tenant restrictions apply. Values are user ids.
 *
 * Sources live in <component>\muform\userpicker\ namespaces, the endpoint
 * tool_mulib\route\api\muform\userpicker creates them again from get_args(),
 * so the constructor must do the access control.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \tool_mulib\muform\picker\base {
    use user_trait;

    /** @var int number of users on one page of the panel */
    public const int PERPAGE = 50;

    /**
     * Context for identity fields, full names and tenant rules.
     *
     * @return context
     */
    abstract protected function get_context(): context;

    /**
     * Who may be picked - extra condition on table alias "u", null for all users.
     *
     * @return sql|null
     */
    protected function get_candidates_sql(): ?sql {
        return null;
    }

    /**
     * Who may stay as the current value - candidates by default, override where stored values
     * must stay valid after they stopped being candidates.
     *
     * @return sql|null
     */
    protected function get_selected_sql(): ?sql {
        return $this->get_candidates_sql();
    }

    #[\Override]
    final public function labels(array $values): array {
        return $this->user_labels($this->get_context(), array_map('strval', $values), $this->get_selected_sql());
    }

    #[\Override]
    public function validate(array $values): array {
        return $this->validate_users(array_map('strval', $values));
    }

    #[\Override]
    final public function get_panel(): string {
        return 'tool_mulib/muform/userpicker/panel';
    }

    #[\Override]
    public function get_panel_strings(bool $multiple): array {
        $strings = [
            'title' => get_string($multiple ? 'muform_userpicker_title_multiple' : 'muform_userpicker_title', 'tool_mulib'),
            'noresults' => get_string('muform_noresults', 'tool_mulib'),
            'search' => get_string('muform_search', 'tool_mulib'),
            'searching' => get_string('muform_searching', 'tool_mulib'),
        ];
        foreach (['addselected', 'close', 'found', 'name', 'next', 'page', 'previous', 'selectall', 'selectrow'] as $key) {
            $strings[$key] = get_string('muform_userpicker_' . $key, 'tool_mulib');
        }
        return $strings;
    }

    /**
     * Identity columns visible in the source context.
     *
     * @return array[] list of name and label
     */
    final public function get_columns(): array {
        $columns = [];
        foreach (\core_user\fields::get_identity_fields($this->get_context(), false) as $field) {
            $columns[] = ['name' => $field, 'label' => \core_user\fields::get_display_name($field)];
        }
        return $columns;
    }

    /**
     * One page of candidates.
     *
     * @param string $query words looked up in names and visible identity fields
     * @param string[] $exclude user ids not offered, such as values already selected
     * @param int $page page number starting with 0, out of range pages show the last page
     * @return array with columns, rows (id, fullname, fields, label html), total, page and perpage
     */
    final public function search(string $query, array $exclude, int $page): array {
        global $DB;

        $context = $this->get_context();
        $query = trim($query);
        $search = ($query === '') ? new sql('') : search_util::get_user_search_query($query, 'u', $context);
        $where = $this->get_candidates_sql();
        if (!$this->allow_suspended_users()) {
            $suspended = new sql('u.suspended = 0');
            $where = $where ? sql::join(' AND ', [$where->wrap('(', ')'), $suspended]) : $suspended;
        }
        $exclude = array_map('strval', array_filter($exclude, 'is_scalar'));
        $sql = $this->users_sql($context, $search, $exclude, $where);

        $countsql = $sql->replace_comment('orderby', '');
        $total = (int)$DB->count_records_sql("SELECT COUNT('x') FROM ($countsql->sql) c", $countsql->params);
        $pages = max(1, (int)ceil($total / self::PERPAGE));
        $page = min(max(0, $page), $pages - 1);

        $orderby = search_util::get_user_search_orderby($query, 'u', $context);
        $sql = $sql->replace_comment('orderby', $orderby->wrap('ORDER BY ', ', u.id'));
        $columns = $this->get_columns();
        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
        $rows = [];
        foreach ($DB->get_records_sql($sql->sql, $sql->params, $page * self::PERPAGE, self::PERPAGE) as $user) {
            $fields = [];
            foreach ($columns as $column) {
                $fields[$column['name']] = (string)($user->{$column['name']} ?? '');
            }
            $rows[] = [
                'id' => (int)$user->id,
                'fullname' => fullname($user, $viewfullnames),
                'fields' => $fields,
                'label' => search_util::format_user_label($user, $context),
            ];
        }
        return ['columns' => $columns, 'rows' => $rows, 'total' => $total, 'page' => $page, 'perpage' => self::PERPAGE];
    }
}
