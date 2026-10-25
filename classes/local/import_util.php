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

/**
 * Helpers for imports of uploaded or pasted CSV data.
 *
 * Pasted data usually comes from spreadsheets, which use tabs between columns,
 * so the delimiter is detected automatically by default.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_util {
    /** @var string automatic delimiter detection */
    public const string DELIMITER_AUTO = 'auto';

    /** @var string csv_import_reader type */
    private const string CSVTYPE = 'tool_mulib_import';

    /**
     * Returns delimiter options for import forms.
     *
     * @return array name => label
     */
    public static function get_delimiter_options(): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $options = [self::DELIMITER_AUTO => get_string('import_delimiter_auto', 'tool_mulib')];
        return $options + \csv_import_reader::get_delimiter_list();
    }

    /**
     * Parse CSV content, values are trimmed.
     *
     * @param string $content
     * @param string $encoding
     * @param string $delimitername one of the delimiter names or DELIMITER_AUTO
     * @return array list of rows, each row is a list of string values, the first row is not interpreted
     * @throws import_exception when the content cannot be parsed
     */
    public static function parse(string $content, string $encoding, string $delimitername): array {
        if ($delimitername !== self::DELIMITER_AUTO) {
            return self::parse_with($content, $encoding, $delimitername);
        }

        // All rows must have the same number of columns, so the first candidate
        // that parses into more than one column is used, otherwise the data has one column.
        $single = null;
        $firsterror = null;
        foreach (self::get_auto_candidates() as $candidate) {
            try {
                $rows = self::parse_with($content, $encoding, $candidate);
            } catch (import_exception $e) {
                $firsterror = $firsterror ?? $e;
                continue;
            }
            if (count(reset($rows)) > 1) {
                return $rows;
            }
            $single = $single ?? $rows;
        }
        if ($single !== null) {
            return $single;
        }
        throw $firsterror;
    }

    /**
     * Returns delimiter names in the order of automatic detection.
     *
     * Spreadsheets copy and save columns separated by tabs, and tabs do not appear
     * inside values, so tab goes first, then the delimiter preferred on this site.
     *
     * @return string[]
     */
    private static function get_auto_candidates(): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $candidates = ['tab'];
        $delimiters = \csv_import_reader::get_delimiter_list();
        if (isset($CFG->CSV_DELIMITER) && strlen($CFG->CSV_DELIMITER) === 1) {
            $preferred = array_search($CFG->CSV_DELIMITER, $delimiters, true);
            if ($preferred !== false) {
                $candidates[] = $preferred;
            }
        } else if (get_string('listsep', 'langconfig') === ';') {
            $candidates[] = 'semicolon';
        }
        $candidates = array_merge($candidates, ['comma', 'semicolon', 'colon']);
        return array_values(array_unique($candidates));
    }

    /**
     * Parse CSV content with given delimiter.
     *
     * @param string $content
     * @param string $encoding
     * @param string $delimitername
     * @return array
     */
    private static function parse_with(string $content, string $encoding, string $delimitername): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $content = trim($content);
        if ($content === '') {
            throw new import_exception('import_error_empty');
        }

        $iid = \csv_import_reader::get_new_iid(self::CSVTYPE);
        $cir = new \csv_import_reader($iid, self::CSVTYPE);
        $readcount = $cir->load_csv_content($content, $encoding, $delimitername);
        $columns = $cir->get_columns();
        $error = $cir->get_error();
        if ($error !== null) {
            $cir->cleanup(true);
            throw new import_exception('import_error_parse', $error);
        }
        if (!$readcount || !$columns) {
            $cir->cleanup(true);
            throw new import_exception('import_error_empty');
        }

        $rows = [array_map('trim', $columns)];
        $cir->init();
        while ($line = $cir->next()) {
            $rows[] = array_map('trim', $line);
        }
        $cir->close();
        $cir->cleanup(true);

        return $rows;
    }

    /**
     * Normalise column header for guessing of column meaning.
     *
     * @param string $header
     * @return string lowercase letters and digits only
     */
    public static function normalise_header(string $header): string {
        $header = \core_text::strtolower(trim($header));
        return preg_replace('/[^a-z0-9]/', '', $header);
    }
}
