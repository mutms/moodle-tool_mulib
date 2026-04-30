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

namespace tool_mulib\local\generator;

use tool_mulib\local\generator;
use core\exception\coding_exception;

/**
 * Abstract base for all generators.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var int Maximum length for filepath and filename in mdl_files */
    private const FILE_FIELD_MAX_LENGTH = 255;

    /** @var generator */
    protected generator $generator;

    /** @var array default values merged into $record when not provided by caller */
    private array $defaults = [];

    /** @var array<string, array<string, string>> custom placeholder overrides keyed by method name */
    private array $placeholders = [];

    /**
     * Constructor.
     *
     * @param generator $generator
     */
    public function __construct(generator $generator) {
        $this->generator = $generator;
    }

    /**
     * Returns the component name this generator handles.
     *
     * E.g. 'core_course', 'mod_page', 'tool_muprog'.
     *
     * @return string
     */
    abstract public function get_component(): string;

    /**
     * Set default values for all subsequent create_* calls.
     *
     * Caller-provided values always win over defaults.
     *
     * @param array $defaults
     */
    public function set_defaults(array $defaults): void {
        $this->defaults = $defaults;
    }

    /**
     * Set custom placeholder patterns for a specific create method.
     *
     * Each pattern must contain exactly one %d placeholder.
     * These override the defaults returned by the create_*_placeholders() methods.
     *
     * Example:
     *   $gen->set_placeholders('create_course', ['fullname' => 'Uni Course %d', 'shortname' => 'UC%d']);
     *
     * @param string $method method name, e.g. 'create_course', 'create_activity', 'create_chapter'
     * @param array<string, string> $patterns field => sprintf pattern with %d
     * @throws coding_exception if any pattern does not contain %d
     */
    public function set_placeholders(string $method, array $patterns): void {
        foreach ($patterns as $field => $pattern) {
            if (!str_contains($pattern, '%d')) {
                throw new coding_exception("Placeholder pattern for '$field' must contain %d");
            }
        }
        $this->placeholders[$method] = $patterns;
    }

    /**
     * Clear previously set defaults and custom placeholders.
     */
    public function clear_defaults(): void {
        $this->defaults = [];
        $this->placeholders = [];
    }

    /**
     * Reset internal state (defaults, placeholders, and any subclass state).
     */
    public function reset(): void {
        $this->clear_defaults();
    }

    /**
     * Get effective placeholders for a method, merging custom overrides over class defaults.
     *
     * @param string $method method name
     * @param array $classdefaults default placeholders from the create_*_placeholders() method
     * @return array<string, string>
     */
    protected function get_placeholders(string $method, array $classdefaults): array {
        if (isset($this->placeholders[$method])) {
            return array_merge($classdefaults, $this->placeholders[$method]);
        }
        return $classdefaults;
    }

    /**
     * Merge caller record with instance defaults.
     *
     * Caller-provided values always win.
     *
     * @param array $record
     * @return array
     */
    protected function merge_defaults(array $record): array {
        return array_merge($this->defaults, $record);
    }

    /**
     * Fill missing $record fields using auto-name patterns from the database.
     *
     * Uses a single sequence number for all placeholder fields, so that
     * e.g. 'Sample course 5' and 'SC5' always get the same number.
     * The number is the highest found across all fields + 1, then
     * incremented until all generated values are unused.
     *
     * @param array $record
     * @param string $table DB table to check for existing values
     * @param array $placeholders field => sprintf pattern with %d
     * @return array $record with auto-generated fields filled in
     */
    protected function apply_placeholders(array $record, string $table, array $placeholders): array {
        // Filter to only fields that need auto-generation.
        $needed = [];
        foreach ($placeholders as $field => $pattern) {
            if (!isset($record[$field]) || $record[$field] === '') {
                $needed[$field] = $pattern;
            }
        }
        if (!$needed) {
            return $record;
        }

        // Find the highest existing number across all placeholder fields.
        $start = 1;
        foreach ($needed as $field => $pattern) {
            $candidate = $this->find_max_existing_number($table, $field, $pattern);
            if ($candidate >= $start) {
                $start = $candidate + 1;
            }
        }

        // Increment until all generated values are unused.
        $i = $start;
        while (true) {
            $collision = false;
            foreach ($needed as $field => $pattern) {
                global $DB;
                if ($DB->record_exists($table, [$field => sprintf($pattern, $i)])) {
                    $collision = true;
                    break;
                }
            }
            if (!$collision) {
                break;
            }
            $i++;
        }

        // Apply the single number to all fields.
        foreach ($needed as $field => $pattern) {
            $record[$field] = sprintf($pattern, $i);
        }

        return $record;
    }

    /**
     * Find the highest existing sequence number for a pattern in the database.
     *
     * Searches for the record with the highest id matching the LIKE pattern,
     * then extracts its sequence number via regex.
     *
     * Works safely with millions of existing records.
     *
     * @param string $table DB table name (without prefix)
     * @param string $field column name to check
     * @param string $pattern sprintf pattern with single %d placeholder
     * @return int highest existing sequence number, or 0 if none found
     */
    protected function find_max_existing_number(string $table, string $field, string $pattern): int {
        global $DB;

        // Convert sprintf pattern to SQL LIKE pattern:
        // replace %d with a placeholder first, escape the rest, then restore the wildcard.
        $likeparam = str_replace("\x00", '%', $DB->sql_like_escape(str_replace('%d', "\x00", $pattern)));

        // Find MAX(id) among rows matching the pattern.
        $likesql = $DB->sql_like($field, ':pattern');
        $maxid = $DB->get_field_select($table, 'MAX(id)', $likesql, ['pattern' => $likeparam]);

        if (!$maxid) {
            return 0;
        }

        // Fetch that record and extract the sequence number.
        $existing = $DB->get_field($table, $field, ['id' => $maxid]);
        if ($existing === false) {
            return 0;
        }

        // Build regex from pattern: preg_quote the fixed parts, replace %d with ([0-9]+).
        $regex = '/^' . str_replace('%d', '([0-9]+)', preg_quote($pattern, '/')) . '$/';
        if (preg_match($regex, $existing, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Create a draft area populated with the given files.
     *
     * Accepts an associative array where the key is the target path in the
     * draft area (e.g. 'photo.jpg', 'images/photo.jpg') and the value is one of:
     *   - stored_file object — copy from Moodle file storage
     *   - string — absolute filesystem path to read from
     *   - array with 'content' key — inline string content
     *
     * Requires a logged-in user ($USER must be set).
     *
     * @param array<string, \stored_file|string|array{content: string}> $files
     * @return int draft area itemid
     */
    protected function prepare_draft_area(array $files): int {
        global $USER;

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $draftitemid = file_get_unused_draft_itemid();

        foreach ($files as $targetpath => $source) {
            $filename = basename($targetpath);
            if ($filename === '' || $filename === '.') {
                throw new coding_exception("File target path must include filename: '$targetpath'");
            }
            $dir = dirname($targetpath);
            $filepath = ($dir === '.') ? '/' : '/' . ltrim($dir, '/') . '/';

            // Validate length limits — mdl_files columns are VARCHAR(255).
            if (strlen($filename) > self::FILE_FIELD_MAX_LENGTH) {
                throw new coding_exception(
                    "Filename exceeds " . self::FILE_FIELD_MAX_LENGTH . " characters: '$filename'"
                );
            }
            if (strlen($filepath) > self::FILE_FIELD_MAX_LENGTH) {
                throw new coding_exception(
                    "File path exceeds " . self::FILE_FIELD_MAX_LENGTH . " characters: '$filepath'"
                );
            }

            // Validate filename contains only safe characters (same as PARAM_FILE).
            $cleanfilename = clean_param($filename, PARAM_FILE);
            if ($cleanfilename !== $filename) {
                throw new coding_exception(
                    "Filename contains invalid characters: '$filename' (cleaned to '$cleanfilename')"
                );
            }

            // Validate filepath contains only safe characters (same as PARAM_PATH).
            $cleanfilepath = clean_param($filepath, PARAM_PATH);
            if ($cleanfilepath !== $filepath) {
                throw new coding_exception(
                    "File path contains invalid characters: '$filepath'"
                );
            }

            $filerecord = [
                'component' => 'user',
                'filearea' => 'draft',
                'contextid' => $usercontext->id,
                'itemid' => $draftitemid,
                'filepath' => $filepath,
                'filename' => $filename,
            ];

            if ($source instanceof \stored_file) {
                $fs->create_file_from_storedfile($filerecord, $source);
            } else if (is_string($source)) {
                if (!is_readable($source)) {
                    throw new coding_exception("File not readable: '$source'");
                }
                $fs->create_file_from_pathname($filerecord, $source);
            } else if (is_array($source) && isset($source['content'])) {
                $fs->create_file_from_string($filerecord, $source['content']);
            } else {
                throw new coding_exception("Invalid file source for '$targetpath'");
            }
        }

        return $draftitemid;
    }
}
