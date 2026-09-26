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

namespace tool_mulib\local\extdb;

use core\exception\invalid_parameter_exception;
use stdClass;

/**
 * Server helper class.
 *
 * @package    tool_mulib
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class server {
    /**
     * Create external db server.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function create(stdClass $data): stdClass {
        global $DB;

        $data = (object)(array)$data;
        $data->name = trim($data->name);
        if ($data->name === '') {
            throw new invalid_parameter_exception('server name is required');
        }
        if ($DB->record_exists('tool_mulib_extdb_server', ['name' => $data->name])) {
            throw new invalid_parameter_exception('server name must be unique');
        }

        $data->id = $DB->insert_record('tool_mulib_extdb_server', $data);

        return $DB->get_record('tool_mulib_extdb_server', ['id' => $data->id], '*', MUST_EXIST);
    }

    /**
     * Update external db server.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function update(stdClass $data): stdClass {
        global $DB;

        $data = (object)(array)$data;

        $oldserver = $DB->get_record('tool_mulib_extdb_server', ['id' => $data->id], '*', MUST_EXIST);

        if (!empty($data->changedbpass)) {
            $data->dbpass = $data->newdbpass;
        }
        unset($data->changedbpass);
        unset($data->newdbpass);
        if (property_exists($data, 'dbpass') && $data->dbpass === null) {
            // The secret element returns null when the password was not changed.
            unset($data->dbpass);
        }

        if (property_exists($data, 'name')) {
            $data->name = trim($data->name);
            if ($data->name === '') {
                throw new invalid_parameter_exception('server name is required');
            }
            if ($data->name !== $oldserver->name) {
                if ($DB->record_exists('tool_mulib_extdb_server', ['name' => $data->name])) {
                    throw new invalid_parameter_exception('server name must be unique');
                }
            }
        }

        $DB->update_record('tool_mulib_extdb_server', $data);

        return $DB->get_record('tool_mulib_extdb_server', ['id' => $data->id], '*', MUST_EXIST);
    }

    /**
     * Delete external db server.
     *
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;

        if (!$DB->record_exists('tool_mulib_extdb_server', ['id' => $id])) {
            return;
        }

        if ($DB->record_exists('tool_mulib_extdb_query', ['serverid' => $id])) {
            throw new \core\exception\invalid_parameter_exception('Server is used by a query');
        }

        $DB->delete_records('tool_mulib_extdb_server', ['id' => $id]);
    }

    /**
     * Loaded PDO extensions as a form note.
     *
     * @return string html
     */
    public static function get_pdo_extensions_html(): string {
        $extensions = [];
        if (extension_loaded('pdo')) {
            foreach (get_loaded_extensions() as $extension) {
                if (str_starts_with($extension, 'pdo_')) {
                    $extensions[] = $extension;
                }
            }
        }
        $extensions = $extensions ? implode(', ', $extensions) : get_string('none');
        return '<em>' . s(get_string('extdb_server_extensions', 'tool_mulib', $extensions)) . '</em>';
    }

    /**
     * Try to connect with the values typed into the form and describe the result.
     *
     * @param array $post raw posted form values with dsn, dbuser, dbpass and dboptions
     * @return string html
     */
    public static function get_check_status_html(array $post): string {
        $dbpass = $post['dbpass'] ?? '';
        if (is_array($dbpass)) {
            $dbpass = $dbpass['value'] ?? '';
        }
        $server = (object)[
            'dsn' => is_string($post['dsn'] ?? null) ? $post['dsn'] : '',
            'dbuser' => is_string($post['dbuser'] ?? null) ? $post['dbuser'] : '',
            'dbpass' => is_string($dbpass) ? $dbpass : '',
            'dboptions' => is_string($post['dboptions'] ?? null) ? $post['dboptions'] : '',
        ];
        try {
            $pdb = new pdb($server);
            $pdb->connect();
        } catch (\Throwable $ex) {
            return '<div class="alert alert-danger">' . s($ex->getMessage()) . '</div>';
        }
        return '<span class="alert alert-success">' . s(get_string('ok')) . '</span>';
    }

    /**
     * Validate PDO options JSON.
     *
     * @param string $dboptions
     * @return string|null error text, null when valid
     */
    public static function validate_dboptions(string $dboptions): ?string {
        try {
            $options = json_decode($dboptions, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $ex) {
            return get_string('error');
        }
        if (!is_array($options) && !is_object($options)) {
            return get_string('error');
        }
        return null;
    }
}
