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
use tool_mulib\local\context_map;
use tool_mulib\local\mulib;
use tool_mulib\local\sql;

/**
 * Helpers for sources of item contexts: the system context or a course category
 * where the user has a capability. The current context of the edited item stays
 * selectable when the capability was lost. Values are context ids.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait category_context_trait {
    /**
     * Search system and category contexts.
     *
     * @param string $capability required in the returned contexts
     * @param string $query
     * @param int $maxitems
     * @return array|null [contextid => label html], null when more than $maxitems match
     */
    protected function search_category_contexts(string $capability, string $query, int $maxitems): ?array {
        global $DB, $USER;

        $syscontext = \core\context\system::instance();
        $query = trim($query);

        $sql = new sql(
            "SELECT ctx.id, cat.name
               FROM {course_categories} cat
               JOIN {context} ctx ON ctx.instanceid = cat.id
               /* capjoin */
              WHERE ctx.contextlevel = :catlevel /* capwhere */
                    /* search */ /* tenant */
           GROUP BY ctx.id, cat.name
           ORDER BY cat.name ASC",
            ['catlevel' => CONTEXT_COURSECAT]
        );
        if ($query !== '') {
            $search = search_util::get_search_query($query, ['name', 'idnumber', 'description'], 'cat');
            $sql = $sql->replace_comment('search', $search->wrap('AND ', ''));
        }
        $joins = context_map::get_contexts_by_capability_join($capability, $USER->id, 'ctx');
        $sql = $sql->replace_comment('capjoin', $joins['join']);
        $sql = $sql->replace_comment('capwhere', $joins['where']->wrap('AND ', ''));
        if (mulib::is_mutenancy_active()) {
            $tenantid = \tool_mutenancy\local\tenancy::get_current_tenantid();
            if ($tenantid) {
                $sql = $sql->replace_comment('tenant', 'AND (ctx.tenantid IS NULL OR ctx.tenantid = ?)', [$tenantid]);
            }
        }
        $categories = $DB->get_records_sql($sql->sql, $sql->params, 0, $maxitems + 1);

        $result = [];
        if (has_capability($capability, $syscontext)) {
            $systemname = $syscontext->get_context_name(false);
            if ($query === '' || str_contains(\core_text::strtolower($systemname), \core_text::strtolower($query))) {
                $result[(string)$syscontext->id] = clean_text(get_string('coresystem'));
            }
        }
        $labels = [];
        foreach ($categories as $category) {
            $labels[$category->id] = self::get_category_context_path((int)$category->id);
        }
        \core_collator::asort($labels);
        foreach ($labels as $id => $label) {
            $result[(string)$id] = clean_text($label);
        }
        if (count($result) > $maxitems) {
            return null;
        }
        return $result;
    }

    /**
     * Label of a system or category context the user may select.
     *
     * @param string $capability required unless it is the current context
     * @param string $value context id
     * @param int|null $currentcontextid context of the edited item
     * @return string|null label html, null when unknown or not allowed
     */
    protected function category_context_label(string $capability, string $value, ?int $currentcontextid): ?string {
        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }
        $context = context::instance_by_id((int)$value, IGNORE_MISSING);
        if (!$context || ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT)) {
            return null;
        }
        if ($context->id != $currentcontextid && !has_capability($capability, $context)) {
            return null;
        }
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return clean_text(get_string('coresystem'));
        }
        return clean_text(self::get_category_context_path($context->id));
    }

    /**
     * Category path without the system context.
     *
     * @param int $contextid
     * @return string plain text
     */
    private static function get_category_context_path(int $contextid): string {
        $context = context::instance_by_id($contextid);
        $names = [];
        foreach (array_reverse($context->get_parent_contexts(true)) as $c) {
            if ($c->contextlevel == CONTEXT_SYSTEM) {
                continue;
            }
            $names[] = $c->get_context_name(false);
        }
        return implode(' / ', $names);
    }
}
