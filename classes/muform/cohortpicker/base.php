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

namespace tool_mulib\muform\cohortpicker;

use core\context;
use tool_mulib\local\cohort_util;
use tool_mulib\local\mulib;
use tool_mulib\local\search_util;
use tool_mulib\local\sql;

/**
 * Source of cohortpicker and cohortpickermany elements - which cohorts may be picked.
 *
 * The panel shows cohorts inside the tree of course categories they belong to, only branches
 * with cohorts are shown; words of the search are looked up in cohort names, ID numbers and descriptions.
 * Cohorts the current user may use are offered, see cohort_util::get_use_sql(); with multi-tenancy
 * cohorts of other tenants are not. Values are cohort ids.
 *
 * Sources live in <component>\muform\cohortpicker\ namespaces, the endpoint
 * tool_mulib\route\api\muform\cohortpicker creates them again from get_args(),
 * so the constructor must do the access control.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \tool_mulib\muform\picker\base {
    /** @var int maximum number of cohorts in the panel, the search must be refined when more match */
    public const int MAXCOHORTS = 200;

    /**
     * Context of the edited item, tenant rules use it.
     *
     * @return context
     */
    abstract protected function get_context(): context;

    /**
     * Cohorts already used by the edited item, they stay valid as the current value whatever changed since.
     *
     * @return int[] cohort ids
     */
    protected function get_current_cohortids(): array {
        return [];
    }

    /**
     * Cohorts that must not be picked, for example the edited cohort itself.
     *
     * @return int[] cohort ids
     */
    protected function get_excluded_cohortids(): array {
        return [];
    }

    /**
     * Extra condition cohorts must match to be picked, cohorts already used by the edited item are not affected.
     *
     * @return sql|null condition with aliases ch for the cohort and ctx for its context
     */
    protected function get_restriction_sql(): ?sql {
        return null;
    }

    #[\Override]
    final public function labels(array $values): array {
        global $DB;

        $ids = [];
        foreach ($values as $value) {
            if (is_string($value) && preg_match('/^[1-9]\d*$/D', $value)) {
                $ids[$value] = (int)$value;
            }
        }
        $ids = array_diff($ids, array_map('intval', $this->get_excluded_cohortids()));
        if (!$ids) {
            return [];
        }

        [$in, $params] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED, 'cpid');
        $sql = $this->get_cohorts_sql()
            ->replace_comment('fields', 'ch.id, ch.name')
            ->replace_comment('only', new sql("AND ch.id $in", $params));
        $names = $DB->get_records_sql_menu($sql->sql, $sql->params);

        $missing = array_diff(array_intersect($ids, array_map('intval', $this->get_current_cohortids())), array_keys($names));
        if ($missing) {
            $names += $DB->get_records_list('cohort', 'id', $missing, '', 'id, name AS cohortname');
        }

        $context = $this->get_context();
        $labels = [];
        foreach ($ids as $value => $id) {
            if (isset($names[$id])) {
                $name = is_object($names[$id]) ? $names[$id]->cohortname : $names[$id];
                $labels[(string)$value] = format_string($name, true, ['context' => $context]);
            }
        }
        return $labels;
    }

    #[\Override]
    final public function get_panel(): string {
        return 'tool_mulib/muform/cohortpicker/panel';
    }

    #[\Override]
    public function get_panel_strings(bool $multiple): array {
        $title = $multiple ? 'muform_cohortpicker_title_multiple' : 'muform_cohortpicker_title';
        return [
            'title' => get_string($title, 'tool_mulib'),
            'addselected' => get_string('muform_userpicker_addselected', 'tool_mulib'),
            'close' => get_string('muform_userpicker_close', 'tool_mulib'),
            'found' => get_string('muform_userpicker_found', 'tool_mulib'),
            'noresults' => get_string('muform_noresults', 'tool_mulib'),
            'overflow' => get_string('muform_cohortpicker_overflow', 'tool_mulib', self::MAXCOHORTS),
            'search' => get_string('muform_search', 'tool_mulib'),
            'searching' => get_string('muform_searching', 'tool_mulib'),
            'selectrow' => get_string('muform_userpicker_selectrow', 'tool_mulib'),
        ];
    }

    /**
     * Cohorts that may be picked in the tree of their contexts.
     *
     * @param string $query words looked up in cohort names, ID numbers and descriptions
     * @param string[] $exclude cohort ids not offered, such as values already selected
     * @return array with nodes (id, name, depth, parentid) in the order of the tree - the system and the
     *      categories with found cohorts and all their parents, cohorts (id, name, idnumber, nodeid, label html)
     *      ordered by name, total number of matching cohorts and overflow when not all of them are returned
     */
    final public function search(string $query, array $exclude): array {
        global $DB;

        $sql = $this->get_cohorts_sql();
        $query = trim($query);
        if ($query !== '') {
            $sql = $sql->replace_comment('search', search_util::get_cohort_search_query($query, 'ch')->wrap('AND ', ''));
        }
        $excluded = array_map('intval', $this->get_excluded_cohortids());
        foreach ($exclude as $value) {
            if (is_scalar($value) && preg_match('/^[1-9]\d*$/D', (string)$value)) {
                $excluded[] = (int)$value;
            }
        }
        if ($excluded) {
            [$notin, $params] = $DB->get_in_or_equal(array_unique($excluded), SQL_PARAMS_NAMED, 'cpex', false);
            $sql = $sql->replace_comment('only', new sql("AND ch.id $notin", $params));
        }

        $countsql = $sql->replace_comment('fields', "COUNT('x')");
        $total = (int)$DB->count_records_sql($countsql->sql, $countsql->params);

        $sql = $sql->replace_comment('fields', 'ch.id, ch.name, ch.idnumber, ch.contextid, ctx.path');
        $sql = new sql($sql->sql . ' ORDER BY ch.name ASC, ch.id ASC', $sql->params);
        $records = $DB->get_records_sql($sql->sql, $sql->params, 0, self::MAXCOHORTS);

        $context = $this->get_context();
        $cohorts = [];
        $contextids = [];
        foreach ($records as $record) {
            $name = format_string($record->name, true, ['context' => $context]);
            $cohorts[] = [
                'id' => (int)$record->id,
                'name' => html_to_text($name, 0, false),
                'idnumber' => (string)$record->idnumber,
                'nodeid' => (int)$record->contextid,
                'label' => $name,
            ];
            foreach (explode('/', trim($record->path, '/')) as $contextid) {
                $contextids[(int)$contextid] = (int)$contextid;
            }
        }

        return [
            'nodes' => $this->get_nodes($contextids),
            'cohorts' => $cohorts,
            'total' => $total,
            'overflow' => $total > count($cohorts),
        ];
    }

    /**
     * Query of cohorts that may be picked, with placeholders for fields, search and a limit to some cohorts.
     *
     * @return sql
     */
    private function get_cohorts_sql(): sql {
        global $USER;

        $sql = (new sql(
            "SELECT /* fields */
               FROM {cohort} ch
               JOIN {context} ctx ON ctx.id = ch.contextid /* tenant */
              WHERE /* use */
                    /* restriction */ /* only */ /* search */"
        ))->replace_comment('use', cohort_util::get_use_sql('ch', (int)$USER->id));

        $restriction = $this->get_restriction_sql();
        $sql = $sql->replace_comment('restriction', $restriction ? $restriction->wrap('AND (', ')') : '');

        $context = $this->get_context();
        if (mulib::is_mutenancy_active() && $context->tenantid) {
            $tenant = new sql("AND (ctx.tenantid = ? OR ctx.tenantid IS NULL)", [$context->tenantid]);
            $sql = $sql->replace_comment('tenant', $tenant);
        }
        return $sql;
    }

    /**
     * Nodes of the tree: system and course categories in the order of the course category tree.
     *
     * @param int[] $contextids contexts of found cohorts and all their parents
     * @return array[] list of id, name, depth and parentid
     */
    private function get_nodes(array $contextids): array {
        global $DB;

        if (!$contextids) {
            return [];
        }
        $syscontext = context\system::instance();
        $nodes = [];
        if (isset($contextids[$syscontext->id])) {
            $nodes[] = [
                'id' => (int)$syscontext->id,
                'name' => $syscontext->get_context_name(false),
                'depth' => 0,
                'parentid' => null,
            ];
        }
        [$in, $params] = $DB->get_in_or_equal(array_values($contextids), SQL_PARAMS_NAMED, 'cpctx');
        $params['level'] = CONTEXT_COURSECAT;
        $categories = $DB->get_records_sql(
            "SELECT ctx.id, ctx.path, ctx.depth, cc.name
               FROM {context} ctx
               JOIN {course_categories} cc ON cc.id = ctx.instanceid
              WHERE ctx.contextlevel = :level AND ctx.id $in
           ORDER BY cc.sortorder ASC, cc.id ASC",
            $params
        );
        foreach ($categories as $category) {
            $path = explode('/', trim($category->path, '/'));
            $name = format_string($category->name, true, ['context' => context::instance_by_id($category->id)]);
            $nodes[] = [
                'id' => (int)$category->id,
                'name' => html_to_text($name, 0, false),
                'depth' => (int)$category->depth - 1,
                'parentid' => (int)$path[count($path) - 2],
            ];
        }
        return $nodes;
    }
}
