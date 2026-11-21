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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.ValidTags.Invalid
// phpcs:disable moodle.Commenting.InlineComment.DocBlock

namespace tool_mulib\local;

use context;
use context_system;
use context_tenant;
use context_user;
use context_coursecat;
use context_course;
use core\exception\coding_exception;

/**
 * Helper class for maintaining contents of {tool_mulib_context_parent} and {tool_mulib_context_map}
 * database tables.
 *
 * @package    tool_mulib
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context_map_builder {
    /** @var int max number of subcontexts to be updated directly when moving categories */
    public const MAX_CATEGORY_UPDATE_COUNT = 100;

    /**
     * Build context relations map.
     */
    public static function build(): void {
        global $DB;

        // NOTE: code is kept in smaller public methods to simplify testing.

        self::parent_purge_deleted();
        if (!mulib::is_mutenancy_active()) {
            self::parent_user_fix();
        } else {
            self::parent_tenant_fix();
            self::parent_tenant_user_fix();
        }
        self::parent_category_fix();
        self::parent_course_fix();

        self::map_purge_deleted();
        self::map_distance_0();
        self::map_distance_1();
        // Start with at least 4 levels in case the context depths and paths were not updated correctly.
        $max1 = (int)$DB->get_field('context', 'MAX(depth)', []);
        $max2 = (int)$DB->get_field('tool_mulib_context_map', 'MAX(distance)', []);
        $maxn = max(4, $max1, $max2);
        for ($n = 2; $n <= $maxn; $n++) {
            self::map_distance_n($n);
        }
        self::map_purge_above_system();
    }

    /**
     * Update database table statistics.
     */
    public static function analyze(): void {
        global $DB;

        $sql = sql::for_db(
            pgsql: "ANALYZE {tool_mulib_context_parent}, {tool_mulib_context_map}, {role_assignments}, {role_capabilities}",
            mariadb: "ANALYZE TABLE {tool_mulib_context_parent}, {tool_mulib_context_map}, {role_assignments}, {role_capabilities}"
        );
        $DB->execute($sql->sql, $sql->params);
    }

    /**
     * Remove references to deleted contexts.
     */
    public static function parent_purge_deleted(): void {
        $sql = "SELECT p.contextid
                  FROM {tool_mulib_context_parent} p
             LEFT JOIN {context} ctx ON ctx.id = p.contextid
                 WHERE p.contextid = :syscontextid OR ctx.id IS NULL";
        self::delete_contexts('tool_mulib_context_parent', $sql, ['syscontextid' => context_system::instance()->id]);
    }

    /**
     * Fix user context parents.
     */
    public static function parent_user_fix(): void {
        global $DB;

        $params = ['syscontextid' => context_system::instance()->id, 'userlevel' => context_user::LEVEL];

        $sql = "SELECT p.contextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :userlevel
                  JOIN {user} u ON u.id = ctx.instanceid
                 WHERE u.deleted = 1";
        self::delete_contexts('tool_mulib_context_parent', $sql, $params);

        $sql = "SELECT p.contextid, :syscontextid AS parentcontextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :userlevel
                 WHERE p.parentcontextid <> :syscontextid2";
        self::update_parents($sql, $params + ['syscontextid2' => $params['syscontextid']]);

        $sql = "INSERT INTO {tool_mulib_context_parent} (contextid, parentcontextid)
                SELECT ctx.id, :syscontextid
                  FROM {context} ctx
                  JOIN {user} u ON u.id = ctx.instanceid AND u.deleted = 0
             LEFT JOIN {tool_mulib_context_parent} p ON p.contextid = ctx.id
                 WHERE ctx.contextlevel = :userlevel AND p.contextid IS NULL";
        $DB->execute($sql, $params);
    }

    /**
     * Fix tenant context parents.
     */
    public static function parent_tenant_fix(): void {
        global $DB;

        $params = ['syscontextid' => context_system::instance()->id, 'tenantlevel' => context_tenant::LEVEL];

        $sql = "SELECT p.contextid, :syscontextid AS parentcontextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :tenantlevel
                 WHERE p.parentcontextid <> :syscontextid2";
        self::update_parents($sql, $params + ['syscontextid2' => $params['syscontextid']]);

        $sql = "INSERT INTO {tool_mulib_context_parent} (contextid, parentcontextid)
                SELECT ctx.id, :syscontextid
                  FROM {context} ctx
                  JOIN {tool_mutenancy_tenant} t ON t.id = ctx.instanceid
             LEFT JOIN {tool_mulib_context_parent} p ON p.contextid = ctx.id
                 WHERE ctx.contextlevel = :tenantlevel AND p.contextid IS NULL";
        $DB->execute($sql, $params);
    }

    /**
     * Fix user context parents with multi-tenancy on.
     */
    public static function parent_tenant_user_fix(): void {
        global $DB;

        $params = [
            'syscontextid' => context_system::instance()->id,
            'userlevel' => context_user::LEVEL,
            'tenantlevel' => context_tenant::LEVEL,
        ];
        $parent = "COALESCE(pctx.id, :syscontextid)";
        $parentjoin = "LEFT JOIN {tool_mutenancy_tenant} t ON t.id = u.tenantid
                       LEFT JOIN {context} pctx ON pctx.instanceid = t.id AND pctx.contextlevel = :tenantlevel";

        $sql = "SELECT p.contextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :userlevel
                  JOIN {user} u ON u.id = ctx.instanceid
                 WHERE u.deleted = 1";
        self::delete_contexts('tool_mulib_context_parent', $sql, $params);

        $sql = "SELECT p.contextid, $parent AS parentcontextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :userlevel
                  JOIN {user} u ON u.id = ctx.instanceid
                  $parentjoin
                 WHERE p.parentcontextid <> COALESCE(pctx.id, :syscontextid2)";
        self::update_parents($sql, $params + ['syscontextid2' => $params['syscontextid']]);

        $sql = "INSERT INTO {tool_mulib_context_parent} (contextid, parentcontextid)
                SELECT ctx.id, $parent
                  FROM {context} ctx
                  JOIN {user} u ON u.id = ctx.instanceid AND u.deleted = 0
                  $parentjoin
             LEFT JOIN {tool_mulib_context_parent} p ON p.contextid = ctx.id
                 WHERE ctx.contextlevel = :userlevel AND p.contextid IS NULL";
        $DB->execute($sql, $params);
    }

    /**
     * Fix category parents.
     */
    public static function parent_category_fix(): void {
        global $DB;

        $params = [
            'syscontextid' => context_system::instance()->id,
            'categorylevel' => context_coursecat::LEVEL,
            'parentlevel' => context_coursecat::LEVEL,
        ];
        $parent = "COALESCE(pctx.id, :syscontextid)";
        $parentjoin = "LEFT JOIN {context} pctx ON pctx.instanceid = cc.parent AND pctx.contextlevel = :parentlevel";

        $sql = "SELECT p.contextid, $parent AS parentcontextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :categorylevel
                  JOIN {course_categories} cc ON cc.id = ctx.instanceid
                  $parentjoin
                 WHERE p.parentcontextid <> COALESCE(pctx.id, :syscontextid2)";
        self::update_parents($sql, $params + ['syscontextid2' => $params['syscontextid']]);

        $sql = "INSERT INTO {tool_mulib_context_parent} (contextid, parentcontextid)
                SELECT ctx.id, $parent
                  FROM {context} ctx
                  JOIN {course_categories} cc ON cc.id = ctx.instanceid
                  $parentjoin
             LEFT JOIN {tool_mulib_context_parent} p ON p.contextid = ctx.id
                 WHERE ctx.contextlevel = :categorylevel AND p.contextid IS NULL";
        $DB->execute($sql, $params);
    }

    /**
     * Fix course context parents.
     */
    public static function parent_course_fix(): void {
        global $DB;

        $params = [
            'syscontextid' => context_system::instance()->id,
            'courselevel' => context_course::LEVEL,
            'categorylevel' => context_coursecat::LEVEL,
        ];
        $parent = "COALESCE(pctx.id, :syscontextid)";
        $parentjoin = "LEFT JOIN {context} pctx ON pctx.instanceid = cs.category AND pctx.contextlevel = :categorylevel";

        $sql = "SELECT p.contextid, $parent AS parentcontextid
                  FROM {tool_mulib_context_parent} p
                  JOIN {context} ctx ON ctx.id = p.contextid AND ctx.contextlevel = :courselevel
                  JOIN {course} cs ON cs.id = ctx.instanceid
                  $parentjoin
                 WHERE p.parentcontextid <> COALESCE(pctx.id, :syscontextid2)";
        self::update_parents($sql, $params + ['syscontextid2' => $params['syscontextid']]);

        $sql = "INSERT INTO {tool_mulib_context_parent} (contextid, parentcontextid)
                SELECT ctx.id, $parent
                  FROM {context} ctx
                  JOIN {course} cs ON cs.id = ctx.instanceid
                  $parentjoin
             LEFT JOIN {tool_mulib_context_parent} p ON p.contextid = ctx.id
                 WHERE ctx.contextlevel = :courselevel AND p.contextid IS NULL";
        $DB->execute($sql, $params);
    }

    /**
     * Remove all entries not present in context parents cache.
     */
    public static function map_purge_deleted(): void {
        $sql = "SELECT DISTINCT map.contextid
                  FROM {tool_mulib_context_map} map
             LEFT JOIN {tool_mulib_context_parent} p ON p.contextid = map.contextid
                 WHERE map.contextid <> :syscontextid AND p.contextid IS NULL";
        self::delete_contexts('tool_mulib_context_map', $sql, ['syscontextid' => context_system::instance()->id]);
    }

    /**
     * Add missing references to self.
     */
    public static function map_distance_0(): void {
        global $DB;

        $sql = "UPDATE {tool_mulib_context_map}
                   SET relatedcontextid = contextid
                 WHERE distance = 0 AND relatedcontextid <> contextid";
        $DB->execute($sql);

        $sql = "INSERT INTO {tool_mulib_context_map} (contextid, distance, relatedcontextid)
                SELECT p.contextid, 0, p.contextid
                  FROM {tool_mulib_context_parent} p
             LEFT JOIN {tool_mulib_context_map} map ON map.contextid = p.contextid AND map.distance = 0
                 WHERE map.contextid IS NULL";
        $DB->execute($sql);

        $syscontextid = context_system::instance()->id;
        if (!$DB->record_exists('tool_mulib_context_map', ['contextid' => $syscontextid, 'distance' => 0])) {
            self::upsert_context_map($syscontextid, [$syscontextid]);
        }
    }

    /**
     * Fix existing and add missing references to direct parents.
     */
    public static function map_distance_1(): void {
        global $DB;

        $sql = sql::for_db(
            pgsql: "UPDATE {tool_mulib_context_map} map
                       SET relatedcontextid = p.parentcontextid
                      FROM {tool_mulib_context_parent} p
                     WHERE p.contextid = map.contextid AND map.distance = 1
                           AND map.relatedcontextid <> p.parentcontextid",
            mariadb: /** @lang=MySQL */
                    "UPDATE {tool_mulib_context_map} map
                       JOIN {tool_mulib_context_parent} p ON p.contextid = map.contextid
                        SET map.relatedcontextid = p.parentcontextid
                      WHERE map.distance = 1 AND map.relatedcontextid <> p.parentcontextid"
        );
        $DB->execute($sql->sql, $sql->params);

        $sql = "INSERT INTO {tool_mulib_context_map} (contextid, distance, relatedcontextid)
                SELECT p.contextid, 1, p.parentcontextid
                  FROM {tool_mulib_context_parent} p
             LEFT JOIN {tool_mulib_context_map} map ON map.contextid = p.contextid AND map.distance = 1
                 WHERE map.contextid IS NULL";
        $DB->execute($sql);
    }

    /**
     * Fix existing and add missing references to parents of parents.
     *
     * @param int $n distance, at least 2
     */
    public static function map_distance_n(int $n): void {
        global $DB;

        if ($n < 2) {
            throw new coding_exception('Distance of at least 2 is expected');
        }
        $params = ['distance' => $n, 'prevdistance' => $n - 1];

        $source = "SELECT pm.contextid, p.parentcontextid
                     FROM {tool_mulib_context_map} pm
                     JOIN {tool_mulib_context_parent} p ON p.contextid = pm.relatedcontextid
                    WHERE pm.distance = :prevdistance";

        $sql = sql::for_db(
            pgsql: new sql(
                "UPDATE {tool_mulib_context_map} map
                    SET relatedcontextid = v.parentcontextid
                   FROM ($source) v
                  WHERE v.contextid = map.contextid AND map.distance = :distance
                        AND map.relatedcontextid <> v.parentcontextid",
                $params
            ),
            mariadb: new sql(
                /** @lang=MySQL */
                "UPDATE {tool_mulib_context_map} map
                   JOIN ($source) v ON v.contextid = map.contextid
                    SET map.relatedcontextid = v.parentcontextid
                  WHERE map.distance = :distance AND map.relatedcontextid <> v.parentcontextid",
                $params
            )
        );
        $DB->execute($sql->sql, $sql->params);

        $sql = "INSERT INTO {tool_mulib_context_map} (contextid, distance, relatedcontextid)
                SELECT v.contextid, :distance, v.parentcontextid
                  FROM ($source) v
             LEFT JOIN {tool_mulib_context_map} map ON map.contextid = v.contextid AND map.distance = :distance2
                 WHERE map.contextid IS NULL";
        $DB->execute($sql, $params + ['distance2' => $n]);
    }

    /**
     * Delete unused distances higher than distance to the top.
     */
    public static function map_purge_above_system(): void {
        global $DB;

        $sql = "SELECT om.contextid, om.distance
                  FROM {tool_mulib_context_map} om
                  JOIN {tool_mulib_context_map} map ON map.contextid = om.contextid AND map.relatedcontextid = :syscontextid
                 WHERE om.distance > map.distance OR om.distance < 0";
        $rs = $DB->get_recordset_sql($sql, ['syscontextid' => context_system::instance()->id]);
        foreach ($rs as $map) {
            $DB->delete_records('tool_mulib_context_map', ['contextid' => $map->contextid, 'distance' => $map->distance]);
        }
        $rs->close();
    }

    /**
     * Check if all map entries look ok.
     * @return null|string null is ok, string is error
     */
    public static function map_check(): ?string {
        global $DB;

        $sql = "SELECT map.contextid
                  FROM {tool_mulib_context_map} map
                  JOIN (SELECT contextid, MAX(distance) AS maxdistance
                          FROM {tool_mulib_context_map}
                      GROUP BY contextid
                       ) sysrel ON sysrel.contextid = map.contextid AND sysrel.maxdistance = map.distance
                 WHERE map.relatedcontextid <> :syscontextid
              ORDER BY map.contextid ASC";
        $cids = $DB->get_fieldset_sql($sql, ['syscontextid' => context_system::instance()->id]);
        if ($cids) {
            return 'Context map error - following contexts do not have system as top parent: ' . implode(', ', $cids);
        }

        return null;
    }

    /**
     * Delete rows of contexts returned by a query.
     *
     * Rows are rarely deleted, the select is fast and does not lock anything.
     *
     * @param string $table tool_mulib_context_parent or tool_mulib_context_map
     * @param string $sql query returning context ids
     * @param array $params
     */
    private static function delete_contexts(string $table, string $sql, array $params): void {
        global $DB;

        $contextids = $DB->get_fieldset_sql($sql, $params);
        foreach (array_chunk($contextids, 100) as $chunk) {
            $DB->delete_records_list($table, 'contextid', $chunk);
        }
    }

    /**
     * Update parents returned by a query.
     *
     * Parents rarely change, the select is fast and does not lock anything.
     *
     * @param string $sql query returning contextid and parentcontextid
     * @param array $params
     */
    private static function update_parents(string $sql, array $params): void {
        global $DB;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $p) {
            $DB->set_field('tool_mulib_context_parent', 'parentcontextid', $p->parentcontextid, ['contextid' => $p->contextid]);
        }
        $rs->close();
    }

    /**
     * Insert or update entries in context map table for one context.
     *
     * @param int $contextid
     * @param int $parentcontextid
     * @return void does nothing if context parameters are invalid
     */
    public static function upsert_context_parent(int $contextid, int $parentcontextid): void {
        if ($contextid < 1 || $parentcontextid < 1 || $contextid == $parentcontextid) {
            debugging('invalid context parameters', DEBUG_DEVELOPER);
            return;
        }

        $record = ['contextid' => $contextid, 'parentcontextid' => $parentcontextid];
        mudb::upsert_record('tool_mulib_context_parent', $record, ['contextid']);
    }

    /**
     * Delete relevant record from context parent table.
     * @param int $contextid
     * @return void
     */
    public static function delete_context_parent(int $contextid): void {
        global $DB;
        $DB->delete_records('tool_mulib_context_parent', ['contextid' => $contextid]);
    }

    /**
     * Insert or update entries in context map table for one context.
     *
     * @param int $contextid
     * @param array $relations array of all distance=>relatedcontextid context relations, 0 distance is added automatically
     * @return void does nothing if $relations parameter is invalid
     */
    public static function upsert_context_map(int $contextid, array $relations): void {
        global $DB;

        if (!isset($relations[0])) {
            $relations[0] = $contextid;
        }
        $maxdistance = max(array_keys($relations));
        for ($i = 0; $i <= $maxdistance; $i++) {
            if (($relations[$i] ?? 0) < 1) {
                debugging("relatedcontextid with distance $i is missing for context $contextid", DEBUG_DEVELOPER);
                return;
            }
        }
        if (count($relations) != $maxdistance + 1) {
            debugging("Unexpected number of relations detected for context $contextid", DEBUG_DEVELOPER);
            return;
        }
        if ($relations[0] != $contextid) {
            debugging("relatedcontextid with distance 0 must match own contextid $contextid", DEBUG_DEVELOPER);
            return;
        }
        if ($relations[$maxdistance] != context_system::instance()->id) {
            debugging("Top parent of context $contextid must be a system context", DEBUG_DEVELOPER);
            return;
        }

        $values = [];
        $params = [];
        foreach ($relations as $distance => $relatedcontextid) {
            $values[] = '(?, ?, ?)';
            array_push($params, $contextid, (int)$distance, (int)$relatedcontextid);
        }
        $values = implode(', ', $values);
        $sql = sql::for_db(
            pgsql: new sql(
                "INSERT INTO {tool_mulib_context_map} (contextid, distance, relatedcontextid) VALUES $values
                 ON CONFLICT (contextid, distance) DO UPDATE SET relatedcontextid = EXCLUDED.relatedcontextid",
                $params
            ),
            mariadb: new sql(
                "INSERT INTO {tool_mulib_context_map} (contextid, distance, relatedcontextid) VALUES $values
                 ON DUPLICATE KEY UPDATE relatedcontextid = VALUES(relatedcontextid)",
                $params
            )
        );
        $DB->execute($sql->sql, $sql->params);

        $DB->delete_records_select('tool_mulib_context_map', "contextid = ? AND distance > ?", [$contextid, $maxdistance]);
    }

    /**
     * Delete relevant records from context map table.
     * @param int $contextid
     * @return void
     */
    public static function delete_context_map(int $contextid): void {
        global $DB;
        $DB->delete_records('tool_mulib_context_map', ['contextid' => $contextid]);
    }

    /**
     * Observer.
     * @param \core\event\user_created $event
     */
    public static function callback_user_created(\core\event\user_created $event): void {
        $context = $event->get_context();
        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));
    }

    /**
     * Observer.
     * @param \tool_mutenancy\event\user_allocated $event
     */
    public static function callback_user_allocated(\tool_mutenancy\event\user_allocated $event): void {
        $context = $event->get_context();
        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));
    }

    /**
     * Observer.
     * @param \core\event\user_deleted $event
     */
    public static function callback_user_deleted(\core\event\user_deleted $event): void {
        $context = $event->get_context();
        self::delete_context_parent($context->id);
        self::delete_context_map($context->id);
    }

    /**
     * Observer.
     * @param \tool_mutenancy\event\tenant_created $event
     */
    public static function callback_tenant_created(\tool_mutenancy\event\tenant_created $event): void {
        $context = $event->get_context();
        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));
    }

    /**
     * Observer.
     * @param \tool_mutenancy\event\tenant_deleted $event
     */
    public static function callback_tenant_deleted(\tool_mutenancy\event\tenant_deleted $event): void {
        $context = $event->get_context();
        self::delete_context_parent($context->id);
        self::delete_context_map($context->id);
    }

    /**
     * Observer.
     * @param \core\event\course_category_created $event
     */
    public static function callback_course_category_created(\core\event\course_category_created $event): void {
        $context = $event->get_context();
        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));
    }

    /**
     * Observer.
     * @param \core\event\course_category_updated $event
     */
    public static function callback_course_category_updated(\core\event\course_category_updated $event): void {
        global $DB;
        $context = $event->get_context();

        $oldparentcontextid = $DB->get_field('tool_mulib_context_parent', 'parentcontextid', ['contextid' => $context->id]);
        if (!$oldparentcontextid || $oldparentcontextid == $context->get_parent_context()->id) {
            return;
        }

        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));

        // Guess if a direct update is more expensive than full rebuild.
        $sql = "SELECT COUNT('x')
                  FROM {context} ctx
                  JOIN {tool_mulib_context_map} map ON map.contextid = ctx.id
                 WHERE map.relatedcontextid = :contextid";
        $updatecount = $DB->count_records_sql($sql, ['contextid' => $context->id]);
        if ($updatecount > self::MAX_CATEGORY_UPDATE_COUNT) {
            self::build();
            return;
        }

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                  JOIN {tool_mulib_context_map} map ON map.contextid = ctx.id
                 WHERE map.relatedcontextid = :contextid
              ORDER BY ctx.depth ASC, ctx.id ASC";
        $rs = $DB->get_recordset_sql($sql, ['contextid' => $context->id]);
        foreach ($rs as $contextrecord) {
            \context_helper::preload_from_record($contextrecord);
            $subcontext = context::instance_by_id($contextrecord->id);
            self::upsert_context_map($subcontext->id, $subcontext->get_parent_context_ids(true));
        }
        $rs->close();
    }

    /**
     * Observer.
     * @param \core\event\course_category_deleted $event
     */
    public static function callback_course_category_deleted(\core\event\course_category_deleted $event): void {
        $context = $event->get_context();
        self::delete_context_parent($context->id);
        self::delete_context_map($context->id);
    }

    /**
     * Observer.
     * @param \core\event\course_created $event
     */
    public static function callback_course_created(\core\event\course_created $event): void {
        $context = $event->get_context();
        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));
    }

    /**
     * Observer.
     * @param \core\event\course_updated $event
     */
    public static function callback_course_updated(\core\event\course_updated $event): void {
        global $DB;
        $context = $event->get_context();

        $oldparentcontextid = $DB->get_field('tool_mulib_context_parent', 'parentcontextid', ['contextid' => $context->id]);
        if (!$oldparentcontextid || $oldparentcontextid == $context->get_parent_context()->id) {
            return;
        }

        self::upsert_context_parent($context->id, $context->get_parent_context()->id);
        self::upsert_context_map($context->id, $context->get_parent_context_ids(true));
    }

    /**
     * Observer.
     * @param \core\event\course_deleted $event
     */
    public static function callback_course_deleted(\core\event\course_deleted $event): void {
        $context = $event->get_context();
        self::delete_context_parent($context->id);
        self::delete_context_map($context->id);
    }
}
