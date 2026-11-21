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
// phpcs:disable moodle.Commenting.ValidTags.Invalid
// phpcs:disable moodle.Commenting.InlineComment.DocBlock

namespace tool_mulib\local;

use stdClass;
use tool_mulib\local\profilefield\base;

/**
 * Cache of custom profile field values.
 *
 * Values are cached for fast filtering, sorting and searching, the cache can be always rebuilt
 * from core tables. Each non-deleted user has one row per cached field with the effective value,
 * that is stored data or the field default. Field visibility is ignored, consumers must apply
 * their own visibility rules.
 *
 * The set of cached fields is stored in plugin config as JSON object
 * fieldid => time when the field was populated, 0 means the field is being populated.
 * Admins may remove fields from the set unless they are required, that is referenced
 * by other components or used as user identity fields.
 *
 * Handlers of profile field types are in \tool_mulib\local\profilefield namespace.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilefield_cache {
    /** @var array|null handler classes, datatype => class */
    private static ?array $handlerclasses = null;

    /**
     * Returns registered handler classes.
     *
     * @return array datatype => handler class name
     */
    public static function get_handler_classes(): array {
        if (self::$handlerclasses === null || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $hook = new \tool_mulib\hook\profilefield_handlers();
            $hook->register('text', profilefield\text::class);
            $hook->register('menu', profilefield\menu::class);
            $hook->register('checkbox', profilefield\checkbox::class);
            $hook->register('datetime', profilefield\datetime::class);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            self::$handlerclasses = $hook->get_classes();
        }
        return self::$handlerclasses;
    }

    /**
     * Returns handler of profile field.
     *
     * @param stdClass $field user_info_field record
     * @return base|null null if field type is not supported
     */
    public static function get_handler(stdClass $field): ?base {
        $classname = self::get_handler_classes()[$field->datatype] ?? null;
        return $classname ? new $classname($field) : null;
    }

    /**
     * Returns profile fields of supported types.
     *
     * @return base[] fieldid => handler, in profile display order
     */
    public static function get_supported_fields(): array {
        global $DB;
        $sql = "SELECT f.*
                  FROM {user_info_field} f
                  JOIN {user_info_category} c ON c.id = f.categoryid
              ORDER BY c.sortorder ASC, f.sortorder ASC";
        $result = [];
        foreach ($DB->get_records_sql($sql) as $field) {
            $handler = self::get_handler($field);
            if ($handler) {
                $result[$field->id] = $handler;
            }
        }
        return $result;
    }

    /**
     * Returns cached fields.
     *
     * @return array fieldid => time populated, 0 means populating
     */
    public static function get_fields(): array {
        global $DB;
        // Read directly from database, the set is changed by other processes such as adhoc tasks.
        $json = $DB->get_field('config_plugins', 'value', ['plugin' => 'tool_mulib', 'name' => 'profilefields']);
        $fields = $json ? json_decode($json, true) : [];
        return is_array($fields) ? array_map('intval', $fields) : [];
    }

    /**
     * Store cached fields.
     *
     * @param array $fields fieldid => time populated
     */
    private static function set_fields(array $fields): void {
        ksort($fields);
        set_config('profilefields', mulib::json_encode((object)$fields), 'tool_mulib');
    }

    /**
     * Is the field cached and ready for use?
     *
     * @param int $fieldid
     * @return bool
     */
    public static function is_ready(int $fieldid): bool {
        return !empty(self::get_fields()[$fieldid]);
    }

    /**
     * Are cached values of the field older than allowed by site setting?
     *
     * @param int $fieldid
     * @return bool true if field should be populated again
     */
    public static function is_outdated(int $fieldid): bool {
        $populated = self::get_fields()[$fieldid] ?? 0;
        if (!$populated) {
            return true;
        }
        return $populated + self::get_lifetime() <= time();
    }

    /**
     * Returns interval of full refresh of cached values.
     *
     * @return int seconds
     */
    public static function get_lifetime(): int {
        $lifetime = get_config('tool_mulib', 'profilefieldlifetime');
        return ($lifetime === false) ? DAYSECS : (int)$lifetime;
    }

    /**
     * Add all supported fields to cache without population,
     * values are populated in populate_pending_fields() or in cron.
     */
    public static function add_supported_fields(): void {
        $fields = self::get_fields();
        foreach (array_keys(self::get_supported_fields()) as $fieldid) {
            if (!array_key_exists($fieldid, $fields)) {
                $fields[$fieldid] = 0;
            }
        }
        self::set_fields($fields);
    }

    /**
     * Populate all cached fields that were not populated yet.
     *
     * @param \progress_trace $trace
     */
    public static function populate_pending_fields(\progress_trace $trace): void {
        foreach (self::get_fields() as $fieldid => $populated) {
            if (!$populated) {
                self::populate_field($fieldid, $trace);
            }
        }
    }

    /**
     * Add field to cache, values are populated later by cron or populate_pending_fields().
     *
     * @param int $fieldid
     * @return bool true if field is cached or being populated, false if not supported
     */
    public static function add_field(int $fieldid): bool {
        global $DB;

        $fields = self::get_fields();
        if (array_key_exists($fieldid, $fields)) {
            return true;
        }
        $field = $DB->get_record('user_info_field', ['id' => $fieldid]);
        if (!$field || !self::get_handler($field)) {
            return false;
        }
        $fields[$fieldid] = 0;
        self::set_fields($fields);
        return true;
    }

    /**
     * Remove field from cache.
     *
     * @param int $fieldid
     */
    public static function remove_field(int $fieldid): void {
        if (in_array($fieldid, self::get_required_fieldids(), true)) {
            throw new \core\exception\coding_exception('Required profile field cannot be removed from cache');
        }
        self::purge_field($fieldid);
    }

    /**
     * Remove field and its cached values.
     *
     * @param int $fieldid
     */
    private static function purge_field(int $fieldid): void {
        global $DB;
        $fields = self::get_fields();
        if (array_key_exists($fieldid, $fields)) {
            unset($fields[$fieldid]);
            self::set_fields($fields);
        }
        $DB->delete_records('tool_mulib_profilefield', ['fieldid' => $fieldid]);
    }

    /**
     * Queue population of field values, meant for profile field changes in admin UI only.
     *
     * @param int $fieldid
     */
    public static function queue_populate(int $fieldid): void {
        $task = new \tool_mulib\task\profilefield_populate();
        $task->set_custom_data(['fieldid' => $fieldid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Populate cached values of field for all users.
     *
     * Nothing is done if field is not cached.
     *
     * @param int $fieldid
     * @param \progress_trace $trace
     */
    public static function populate_field(int $fieldid, \progress_trace $trace): void {
        global $DB;

        if (!array_key_exists($fieldid, self::get_fields())) {
            return;
        }
        $field = $DB->get_record('user_info_field', ['id' => $fieldid]);
        $handler = $field ? self::get_handler($field) : null;
        if (!$handler) {
            self::purge_field($fieldid);
            return;
        }

        $start = time();
        self::sync_field($handler, null);

        // The field is ready, other fields might have changed meanwhile.
        $fields = self::get_fields();
        if (array_key_exists($fieldid, $fields)) {
            $fields[$fieldid] = $start;
            self::set_fields($fields);
            $trace->output("Cached profile field $fieldid values");
        } else {
            $DB->delete_records('tool_mulib_profilefield', ['fieldid' => $fieldid]);
        }
    }

    /**
     * Synchronise cached values of field with stored profile data.
     *
     * Values are changed in place without transactions, readers always see some value
     * and nothing else is blocked. Values of users without data follow the current field default.
     *
     * @param base $handler
     * @param int|null $userid only this user, null means all users
     */
    private static function sync_field(base $handler, ?int $userid): void {
        global $DB;

        $field = $handler->get_field();
        $column = $handler->get_cache_column();
        $value = $handler->get_cache_sql('src.data');

        $userwhere = $userid ? 'AND u.id = :userid' : '';
        $source = "SELECT src.userid, $value AS cachedvalue
                     FROM (SELECT u.id AS userid, COALESCE(d.data, :defaultdata) AS data
                             FROM {user} u
                        LEFT JOIN {user_info_data} d ON d.userid = u.id AND d.fieldid = :datafieldid
                            WHERE u.deleted = 0 $userwhere) src";
        $params = [
            'fieldid' => $field->id,
            'datafieldid' => $field->id,
            'defaultdata' => (string)$field->defaultdata,
            'userid' => $userid,
        ];

        $pfwhere = $userid ? 'AND pf.userid = :pfuserid' : '';
        $updateparams = $params + ['pfuserid' => $userid];
        $sql = sql::for_db(
            pgsql: new sql(
                "UPDATE {tool_mulib_profilefield} pf
                    SET $column = v.cachedvalue
                   FROM ($source) v
                  WHERE v.userid = pf.userid AND pf.fieldid = :fieldid $pfwhere AND pf.$column <> v.cachedvalue",
                $updateparams
            ),
            mariadb: new sql(
                /** @lang=MySQL */
                "UPDATE {tool_mulib_profilefield} pf
                   JOIN ($source) v ON v.userid = pf.userid
                    SET pf.$column = v.cachedvalue
                  WHERE pf.fieldid = :fieldid $pfwhere AND pf.$column <> v.cachedvalue",
                $updateparams
            )
        );
        $DB->execute($sql->sql, $sql->params);

        $sql = "INSERT INTO {tool_mulib_profilefield} (userid, fieldid, $column)
                SELECT v.userid, :fieldid, v.cachedvalue
                  FROM ($source) v
             LEFT JOIN {tool_mulib_profilefield} pf ON pf.userid = v.userid AND pf.fieldid = :pffieldid
                 WHERE pf.id IS NULL";
        $DB->execute($sql, $params + ['pffieldid' => $field->id]);

        $deletewhere = $userid ? 'AND userid = :userid' : '';
        $sql = "DELETE FROM {tool_mulib_profilefield}
                 WHERE fieldid = :fieldid $deletewhere
                       AND userid IN (SELECT id FROM {user} WHERE deleted = 1)";
        $DB->execute($sql, ['fieldid' => $field->id, 'userid' => $userid]);
    }

    /**
     * Update cached values of one user, fields that are not ready are skipped.
     *
     * @param int $userid
     * @param int|null $fieldid null means all ready fields
     */
    public static function update_user(int $userid, ?int $fieldid = null): void {
        global $DB;

        $fieldids = array_keys(array_filter(self::get_fields()));
        if ($fieldid !== null) {
            $fieldids = array_intersect($fieldids, [$fieldid]);
        }
        foreach ($fieldids as $fid) {
            $field = $DB->get_record('user_info_field', ['id' => $fid]);
            $handler = $field ? self::get_handler($field) : null;
            if ($handler) {
                self::sync_field($handler, $userid);
            }
        }
    }

    /**
     * Replace profile fields referenced by one item, referenced fields are added to cache.
     *
     * @param string $component component using the fields, 'core' for core settings
     * @param string $area type of usage in component
     * @param int $itemid item id, 0 if not applicable
     * @param int[] $fieldids referenced fields, empty array removes all references of the item
     */
    public static function set_references(string $component, string $area, int $itemid, array $fieldids): void {
        global $DB;

        if ($component !== clean_param($component, PARAM_COMPONENT) || $component === '') {
            throw new \core\exception\coding_exception('Invalid component: ' . $component);
        }
        if ($area !== clean_param($area, PARAM_AREA) || $area === '') {
            throw new \core\exception\coding_exception('Invalid area: ' . $area);
        }

        $fieldids = array_unique(array_map('intval', $fieldids));
        $conditions = ['component' => $component, 'area' => $area, 'itemid' => $itemid];
        $existing = $DB->get_records_menu('tool_mulib_profilefield_reference', $conditions, '', 'fieldid, id');

        foreach ($existing as $fieldid => $id) {
            if (!in_array((int)$fieldid, $fieldids, true)) {
                $DB->delete_records('tool_mulib_profilefield_reference', ['id' => $id]);
            }
        }
        foreach ($fieldids as $fieldid) {
            if (isset($existing[$fieldid])) {
                continue;
            }
            if (!$DB->record_exists('user_info_field', ['id' => $fieldid])) {
                continue;
            }
            $record = (object)($conditions + ['fieldid' => $fieldid, 'timecreated' => time()]);
            $DB->insert_record('tool_mulib_profilefield_reference', $record);
            self::add_field($fieldid);
        }
    }

    /**
     * Returns references of profile field.
     *
     * @param int $fieldid
     * @return stdClass[] tool_mulib_profilefield_reference records
     */
    public static function get_references(int $fieldid): array {
        global $DB;
        return $DB->get_records(
            'tool_mulib_profilefield_reference',
            ['fieldid' => $fieldid],
            'component ASC, area ASC, itemid ASC'
        );
    }

    /**
     * Returns supported profile fields used as user identity fields.
     *
     * @return int[]
     */
    public static function get_identity_fieldids(): array {
        global $CFG;

        $shortnames = [];
        foreach (explode(',', $CFG->showuseridentity ?? '') as $name) {
            $name = trim($name);
            if (str_starts_with($name, 'profile_field_')) {
                $shortnames[] = substr($name, strlen('profile_field_'));
            }
        }
        if (!$shortnames) {
            return [];
        }
        $result = [];
        foreach (self::get_supported_fields() as $fieldid => $handler) {
            if (in_array($handler->get_field()->shortname, $shortnames, true)) {
                $result[] = (int)$fieldid;
            }
        }
        return $result;
    }

    /**
     * Returns fields that cannot be removed from cache.
     *
     * @return int[] referenced fields and identity fields
     */
    public static function get_required_fieldids(): array {
        global $DB;
        $sql = "SELECT DISTINCT fieldid
                  FROM {tool_mulib_profilefield_reference}";
        $result = array_map('intval', $DB->get_fieldset_sql($sql));
        $result = array_unique(array_merge($result, self::get_identity_fieldids()));
        sort($result);
        return $result;
    }

    /**
     * Reconcile cache with references and identity fields, populate new and outdated fields
     * and remove stale data.
     *
     * @param \progress_trace $trace
     */
    public static function cron(\progress_trace $trace): void {
        global $DB;

        $sql = "DELETE FROM {tool_mulib_profilefield_reference}
                 WHERE fieldid NOT IN (SELECT id FROM {user_info_field})";
        $DB->execute($sql);
        self::set_references('core', 'showuseridentity', 0, self::get_identity_fieldids());

        foreach (self::get_required_fieldids() as $fieldid) {
            self::add_field($fieldid);
        }

        foreach (array_keys(self::get_fields()) as $fieldid) {
            if (self::is_outdated($fieldid)) {
                self::populate_field($fieldid, $trace);
            }
        }

        $fieldids = array_keys(self::get_fields());
        if ($fieldids) {
            [$notin, $params] = $DB->get_in_or_equal($fieldids, SQL_PARAMS_NAMED, 'f', false);
            $DB->delete_records_select('tool_mulib_profilefield', "fieldid $notin", $params);
        } else {
            $DB->delete_records('tool_mulib_profilefield', []);
        }
    }

    /**
     * Observer.
     *
     * @param \core\event\user_created $event
     */
    public static function callback_user_created(\core\event\user_created $event): void {
        self::update_user((int)$event->objectid);
    }

    /**
     * Observer.
     *
     * @param \core\event\user_updated $event
     */
    public static function callback_user_updated(\core\event\user_updated $event): void {
        self::update_user((int)$event->objectid);
    }

    /**
     * Observer.
     *
     * @param \core\event\user_deleted $event
     */
    public static function callback_user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('tool_mulib_profilefield', ['userid' => $event->objectid]);
    }

    /**
     * Observer.
     *
     * @param \core\event\user_info_field_created $event
     */
    public static function callback_user_info_field_created(\core\event\user_info_field_created $event): void {
        $fieldid = (int)$event->objectid;
        if (self::add_field($fieldid)) {
            self::queue_populate($fieldid);
        }
    }

    /**
     * Observer.
     *
     * @param \core\event\user_info_field_updated $event
     */
    public static function callback_user_info_field_updated(\core\event\user_info_field_updated $event): void {
        $fieldid = (int)$event->objectid;
        if (array_key_exists($fieldid, self::get_fields())) {
            self::queue_populate($fieldid);
        }
    }

    /**
     * Observer.
     *
     * @param \core\event\user_info_field_deleted $event
     */
    public static function callback_user_info_field_deleted(\core\event\user_info_field_deleted $event): void {
        global $DB;
        $fieldid = (int)$event->objectid;
        self::purge_field($fieldid);
        $DB->delete_records('tool_mulib_profilefield_reference', ['fieldid' => $fieldid]);
    }
}
