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

use dml_write_exception;
use stdClass;

/**
 * Database helper class for MuTMS plugins.
 *
 * @package    tool_mulib
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mudb {
    /**
     * A fast way to insert or update record that has EXACTLY ONE unique index.
     *
     * If there is already an existing record with unique constraint then
     * record is updated, if not new record is inserted.
     *
     * This method solves problems with highly concurrent inserts into tables with unique constraints.
     *
     * @param string $table
     * @param stdClass|array $dataobject
     * @param string[] $uniqueindexcolumns list of all columns in unique index
     * @param array $insertonlyfields additional fields with values to be used only for inserts
     */
    public static function upsert_record(string $table, stdClass|array $dataobject, array $uniqueindexcolumns, array $insertonlyfields = []): void {
        global $DB;

        $dataobject = (object)(array)$dataobject;

        self::validate_upsert_record_arguments($table, $dataobject, $uniqueindexcolumns, $insertonlyfields);

        $fields = [];
        $params = [];
        $pgsqlupdates = [];
        $mariadbupdates = [];
        foreach ((array)$dataobject as $field => $value) {
            $fields[] = $field;
            $params[] = $value;
            if (!in_array($field, $uniqueindexcolumns)) {
                $pgsqlupdates[] = "$field = EXCLUDED.$field";
                $mariadbupdates[] = "$field = VALUES($field)";
            }
        }
        foreach ($insertonlyfields as $field => $value) {
            $fields[] = $field;
            $params[] = $value;
        }
        $insert = "INSERT INTO {{$table}} (" . implode(', ', $fields) . ")
                   VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";

        $sql = sql::for_db(
            pgsql: new sql(
                "$insert ON CONFLICT (" . implode(', ', $uniqueindexcolumns) . ") DO UPDATE SET " . implode(', ', $pgsqlupdates),
                $params
            ),
            mariadb: new sql("$insert ON DUPLICATE KEY UPDATE " . implode(', ', $mariadbupdates), $params)
        );
        $DB->execute($sql->sql, $sql->params);
    }

    /**
     * Insert record unless there is already a record with the same values in unique index.
     *
     * Existing record is never modified.
     *
     * This method solves problems with concurrent inserts into tables with unique constraints,
     * it is safe to use inside transactions.
     *
     * @param string $table
     * @param stdClass|array $dataobject
     * @param string[] $uniqueindexcolumns list of all columns in unique index
     * @return bool true if record was inserted, false if record already existed
     */
    public static function insert_record_if_missing(string $table, stdClass|array $dataobject, array $uniqueindexcolumns): bool {
        global $DB, $CFG;

        $dataobject = (object)(array)$dataobject;

        if (!$uniqueindexcolumns) {
            throw new \core\exception\coding_exception(
                'mudb::insert_record_if_missing() requires list of unique constraint columns'
            );
        }
        if (property_exists($dataobject, 'id')) {
            throw new \core\exception\coding_exception(
                'mudb::insert_record_if_missing() dataobject must not have id property'
            );
        }
        $columns = $DB->get_columns($table);
        foreach ((array)$dataobject as $field => $value) {
            if (!isset($columns[$field])) {
                throw new \core\exception\coding_exception(
                    'mudb::insert_record_if_missing() dataobject contains unknown column'
                );
            }
        }
        $conditions = [];
        foreach ($uniqueindexcolumns as $column) {
            if (!isset($dataobject->$column)) {
                throw new \core\exception\coding_exception(
                    'mudb::insert_record_if_missing() dataobject must have all unique columns set'
                );
            }
            $conditions[$column] = $dataobject->$column;
        }
        if ($CFG->debugdeveloper) {
            $found = false;
            $expected = $uniqueindexcolumns;
            sort($expected);
            foreach ($DB->get_indexes($table) as $index) {
                $indexcolumns = $index['columns'];
                sort($indexcolumns);
                if ($index['unique'] && $indexcolumns === $expected) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new \core\exception\coding_exception(
                    'mudb::insert_record_if_missing() unique index with given columns does not exist'
                );
            }
        }

        if ($DB->record_exists($table, $conditions)) {
            return false;
        }
        try {
            $DB->insert_record($table, $dataobject, false);
        } catch (dml_write_exception $e) {
            if (!$DB->record_exists($table, $conditions)) {
                throw $e;
            }
            return false;
        }
        return true;
    }

    /**
     * Validate all upsert parameters.
     *
     * @param string $table
     * @param stdClass $dataobject
     * @param array $uniqueindexcolumns
     * @param array $insertonlyfields
     * @return void
     */
    protected static function validate_upsert_record_arguments(
        string $table,
        stdClass $dataobject,
        array $uniqueindexcolumns,
        array $insertonlyfields
    ): void {
        global $DB;

        if (!$uniqueindexcolumns) {
            throw new \core\exception\coding_exception(
                'moodle_database::upsert_record() requires list of unique constraint columns'
            );
        }

        if (property_exists($dataobject, 'id')) {
            throw new \core\exception\coding_exception(
                'moodle_database::upsert_record() dataobject must not have id property'
            );
        }

        foreach ($uniqueindexcolumns as $column) {
            if (!isset($dataobject->$column)) {
                throw new \core\exception\coding_exception(
                    'moodle_database::upsert_record() dataobject must have all unique columns set'
                );
            }
        }

        $columns = $DB->get_columns($table);

        foreach ($insertonlyfields as $k => $v) {
            if (!isset($columns[$k])) {
                throw new \core\exception\coding_exception(
                    'moodle_database::upsert_record() insertonlyfields contains unknown column'
                );
            }
            if (in_array($k, $uniqueindexcolumns)) {
                throw new \core\exception\coding_exception(
                    'moodle_database::upsert_record() insertonlyfields cannot contain unique columns'
                );
            }
            if (property_exists($dataobject, $k)) {
                throw new \core\exception\coding_exception(
                    'moodle_database::upsert_record() insertonlyfields must not share columns with dataobject'
                );
            }
        }

        $foundnonunique = false;
        foreach ((array)$dataobject as $field => $value) {
            if (!isset($columns[$field])) {
                throw new \core\exception\coding_exception(
                    'moodle_database::upsert_record() dataobject contains unknown column'
                );
            }
            if (!$foundnonunique && !in_array($field, $uniqueindexcolumns)) {
                $foundnonunique = true;
            }
        }

        if (!$foundnonunique) {
            throw new \core\exception\coding_exception(
                'moodle_database::upsert_record() dataobject must contain at least one non-unique column'
            );
        }
    }
}
