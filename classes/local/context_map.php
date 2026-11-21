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

namespace tool_mulib\local;

use stdClass;

/**
 * Fast permissions lookup via context map cache.
 *
 * NOTE: course activity and block contexts are completely ignored.
 *
 * @package    tool_mulib
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context_map {
    /** @var int fake user id for default user role assignment hack */
    public const MAGIC_DEFAULT_USER_ID = -999666;

    /**
     * Returns subquery with context ids where user has permission.
     *
     * @param string $capability
     * @param int|stdClass $userorid
     * @param sql $where conditions using "ctx" table alias, usually at least context level restrictions
     * @param bool $doanything true means admins can do anything
     * @return sql full SQL query returning context ids in random order
     */
    public static function get_contexts_by_capability_query(
        string $capability,
        int|stdClass $userorid,
        sql $where,
        bool $doanything = true
    ): sql {
        $join = self::get_contexts_by_capability_join($capability, $userorid, 'ctx', $doanything, 'ctx_');

        if ($join['where']->sql === '1=2') {
            $where = $join['where'];
        } else {
            $where = sql::join(' AND ', [$join['where'], $where]);
        }

        return (new sql(
            "SELECT ctx.id
               FROM {context} ctx
               /* join */
              WHERE {$where->sql}
              /* groupby */",
            $where->params
        ))
            ->replace_comment('join', $join['join'])
            ->replace_comment('groupby', $join['join']->sql === '' ? '' : 'GROUP BY ctx.id');
    }

    /**
     * Returns joins and where SQL fragments that reference contexts where user has given
     * capability in contexts other than blocks and activity modules.
     *
     * WARNING: these join may return duplicate rows!
     *
     * @param string $capability
     * @param int|stdClass $userorid
     * @param string $contextalias for example 'ctx'
     * @param bool $doanything true means admins can do anything
     * @param string $prefix join table alias prefix to prevent conflicts
     * @return array{ join: sql, where: sql }
     */
    public static function get_contexts_by_capability_join(
        string $capability,
        int|stdClass $userorid,
        string $contextalias,
        bool $doanything = true,
        string $prefix = 'cbc_'
    ): array {
        global $CFG, $DB;
        $p = $prefix;
        $c = $contextalias;

        $userid = is_object($userorid) ? $userorid->id : $userorid;
        $nothing = ['join' => new sql(''), 'where' => new sql('1=2')];

        $capinfo = get_capability_info($capability);
        if (!$capinfo) {
            debugging('Capability "' . $capability . '" was not found! This has to be fixed in code.');
            return $nothing;
        }

        if (!$userid || isguestuser($userid)) {
            if ($capinfo->captype === 'write' || ($capinfo->riskbitmask & (RISK_XSS | RISK_CONFIG | RISK_DATALOSS))) {
                return $nothing;
            }
            $roleid = (int)($userid ? ($CFG->guestroleid ?? 0) : ($CFG->notloggedinroleid ?? 0));
            if (!$roleid) {
                return $nothing;
            }
            return self::get_contexts_by_capability_join_guest($capability, $roleid, $contextalias, $prefix);
        }

        $islocking = false;
        if (!empty($CFG->contextlocking) && $capinfo->captype === 'write' && $capinfo->name !== 'moodle/site:managecontextlocks') {
            if (!is_siteadmin($userid) || !empty($CFG->contextlockappliestoadmin)) {
                $islocking = true;
            }
        }

        if (is_siteadmin($userid) && $doanything) {
            $wheres = [new sql("$c.contextlevel <= :courselevel", ['courselevel' => CONTEXT_COURSE])];
            if ($islocking) {
                $wheres[] = "$c.locked = 0";
            }
            return ['join' => new sql(''), 'where' => sql::join(' AND ', $wheres)->wrap('(', ')')];
        }

        $usercontext = \context_user::instance($userid);
        self::add_default_role_hacks();

        $userparams = ['userid' => $userid, 'defaultuserid' => self::MAGIC_DEFAULT_USER_ID];

        $hasprohibits = false;
        if ($DB->record_exists('role_capabilities', ['capability' => $capability, 'permission' => CAP_PROHIBIT])) {
            $sql = "SELECT 'x'
                      FROM {role_assignments} ra
                      JOIN {role_capabilities} rc ON rc.contextid = ra.contextid AND rc.roleid = ra.roleid
                                                     AND rc.capability = :capability AND rc.permission = :prohibit
                     WHERE ra.contextid = :syscontextid AND (ra.userid = :userid OR ra.userid = :defaultuserid)";
            $params = $userparams + [
                'capability' => $capability,
                'prohibit' => CAP_PROHIBIT,
                'syscontextid' => \context_system::instance()->id,
            ];
            if ($DB->record_exists_sql($sql, $params)) {
                // System level role with prohibited system level capability means all contexts are prohibited.
                return $nothing;
            }
            $hasprohibits = true;
        }

        $join = new sql(
            "JOIN {tool_mulib_context_map} {$p}map ON {$p}map.contextid = $c.id
             JOIN {role_assignments} {$p}ra ON {$p}ra.contextid = {$p}map.relatedcontextid
                                               AND ({$p}ra.userid = :userid OR {$p}ra.userid = :defaultuserid)
             JOIN {tool_mulib_context_map} {$p}mc ON {$p}mc.contextid = $c.id
             JOIN {role_capabilities} {$p}rc ON {$p}rc.roleid = {$p}ra.roleid AND {$p}rc.contextid = {$p}mc.relatedcontextid
                                                AND {$p}rc.capability = :capability1 AND {$p}rc.permission = :allow
        LEFT JOIN (SELECT cm.contextid, cm.distance, rc.roleid
                     FROM {tool_mulib_context_map} cm
                     JOIN {role_capabilities} rc ON rc.contextid = cm.relatedcontextid
                                                    AND rc.capability = :capability2 AND rc.permission = :prevent
                  ) {$p}pvt ON {$p}pvt.contextid = $c.id AND {$p}pvt.roleid = {$p}ra.roleid AND {$p}pvt.distance < {$p}mc.distance
             /* prohibitjoin */",
            $userparams + ['capability1' => $capability, 'capability2' => $capability, 'allow' => CAP_ALLOW, 'prevent' => CAP_PREVENT]
        );
        $wheres = ["{$p}pvt.contextid IS NULL"];

        if ($hasprohibits) {
            $prohibitjoin = new sql(
                "LEFT JOIN (SELECT map.contextid
                              FROM {tool_mulib_context_map} map
                              JOIN {role_assignments} ra ON ra.contextid = map.relatedcontextid
                                                            AND (ra.userid = :userid OR ra.userid = :defaultuserid)
                              JOIN {tool_mulib_context_map} mc ON mc.contextid = map.contextid
                              JOIN {role_capabilities} rc ON rc.roleid = ra.roleid AND rc.contextid = mc.relatedcontextid
                                                             AND rc.capability = :capability AND rc.permission = :prohibit
                           ) {$p}pbt ON {$p}pbt.contextid = $c.id",
                $userparams + ['capability' => $capability, 'prohibit' => CAP_PROHIBIT]
            );
            $wheres[] = "{$p}pbt.contextid IS NULL";
        } else {
            $prohibitjoin = '';
        }
        $join = $join->replace_comment('prohibitjoin', $prohibitjoin);

        if ($islocking) {
            $wheres[] = "$c.locked = 0";
        }
        if (mulib::is_mutenancy_active() && $usercontext->tenantid) {
            $wheres[] = new sql("($c.tenantid IS NULL OR $c.tenantid = :tenantid)", ['tenantid' => $usercontext->tenantid]);
        }

        return ['join' => $join, 'where' => sql::join(' AND ', $wheres)->wrap('(', ')')];
    }

    /**
     * Returns joins and where SQL fragments that reference contexts where guest or not-logged-in role has given capability.
     *
     * @param string $capability
     * @param int $roleid guest or not-logged-in user role id
     * @param string $contextalias
     * @param string $prefix join table alias prefix
     * @return array{ join: sql, where: sql }
     */
    protected static function get_contexts_by_capability_join_guest(string $capability, int $roleid, string $contextalias, string $prefix): array {
        global $DB;
        $p = $prefix;
        $c = $contextalias;

        $hasprohibits = false;
        if ($DB->record_exists('role_capabilities', ['capability' => $capability, 'permission' => CAP_PROHIBIT])) {
            $conditions = ['capability' => $capability, 'permission' => CAP_PROHIBIT, 'roleid' => $roleid, 'contextid' => \context_system::instance()->id];
            if ($DB->record_exists('role_capabilities', $conditions)) {
                // System level role with prohibited system level capability means all contexts are prohibited.
                return ['join' => new sql(''), 'where' => new sql('1=2')];
            }
            $hasprohibits = true;
        }

        $join = new sql(
            "JOIN {tool_mulib_context_map} {$p}mc ON {$p}mc.contextid = $c.id
             JOIN {role_capabilities} {$p}rc ON {$p}rc.roleid = :roleid1 AND {$p}rc.contextid = {$p}mc.relatedcontextid
                                                AND {$p}rc.capability = :capability1 AND {$p}rc.permission = :allow
        LEFT JOIN (SELECT cm.contextid, cm.distance
                     FROM {tool_mulib_context_map} cm
                     JOIN {role_capabilities} rc ON rc.contextid = cm.relatedcontextid AND rc.roleid = :roleid2
                                                    AND rc.capability = :capability2 AND rc.permission = :prevent
                  ) {$p}pvt ON {$p}pvt.contextid = $c.id AND {$p}pvt.distance < {$p}mc.distance
             /* prohibitjoin */",
            [
                'roleid1' => $roleid,
                'roleid2' => $roleid,
                'capability1' => $capability,
                'capability2' => $capability,
                'allow' => CAP_ALLOW,
                'prevent' => CAP_PREVENT,
            ]
        );
        $wheres = ["{$p}pvt.contextid IS NULL"];

        if ($hasprohibits) {
            $prohibitjoin = new sql(
                "LEFT JOIN (SELECT map.contextid
                              FROM {tool_mulib_context_map} map
                              JOIN {role_capabilities} rc ON rc.roleid = :roleid AND rc.contextid = map.relatedcontextid
                                                             AND rc.capability = :capability AND rc.permission = :prohibit
                           ) {$p}pbt ON {$p}pbt.contextid = $c.id",
                ['roleid' => $roleid, 'capability' => $capability, 'prohibit' => CAP_PROHIBIT]
            );
            $wheres[] = "{$p}pbt.contextid IS NULL";
        } else {
            $prohibitjoin = '';
        }
        $join = $join->replace_comment('prohibitjoin', $prohibitjoin);

        if (mulib::is_mutenancy_active() && !get_config('tool_mutenancy', 'allowguests')) {
            $wheres[] = "$c.tenantid IS NULL";
        }

        return ['join' => $join, 'where' => sql::join(' AND ', $wheres)->wrap('(', ')')];
    }

    /**
     * Add default user roles as fake user roles into role_assignments.
     */
    protected static function add_default_role_hacks(): void {
        global $DB, $CFG;

        $expected = [];
        if (!empty($CFG->defaultuserroleid)) {
            $syscontext = \context_system::instance();
            $expected[$syscontext->id] = (int)$CFG->defaultuserroleid;
        }
        if (!empty($CFG->defaultfrontpageroleid)) {
            $frontpagecontxt = \context_course::instance(get_site()->id);
            $expected[$frontpagecontxt->id] = (int)$CFG->defaultfrontpageroleid;
        }

        $hacks = $DB->get_records('role_assignments', ['userid' => self::MAGIC_DEFAULT_USER_ID, 'component' => 'tool_mulib']);
        foreach ($hacks as $ra) {
            if (!isset($expected[$ra->contextid]) || $expected[$ra->contextid] != $ra->roleid) {
                $DB->delete_records('role_assignments', ['id' => $ra->id]);
                continue;
            }
            unset($expected[$ra->contextid]);
        }
        foreach ($expected as $contextid => $roleid) {
            $DB->insert_record('role_assignments', [
                'contextid' => $contextid,
                'userid' => self::MAGIC_DEFAULT_USER_ID,
                'roleid' => $roleid,
                'timemodified' => time(),
                'component' => 'tool_mulib',
            ]);
        }
    }
}
