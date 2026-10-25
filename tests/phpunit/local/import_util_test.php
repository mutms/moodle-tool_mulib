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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\local;

use tool_mulib\local\import_util;

/**
 * Import helper tests.
 *
 * @group      MuTMS
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\import_util
 */
final class import_util_test extends \advanced_testcase {
    public function test_separator_is_detected(): void {
        $expected = [['guid', 'email'], ['z-1', 'one@example.com']];

        foreach (["\t", ',', ';', ':'] as $separator) {
            $content = "guid{$separator}email\nz-1{$separator}one@example.com";
            $rows = import_util::parse($content, 'UTF-8', import_util::DELIMITER_AUTO);
            $this->assertSame($expected, $rows, 'separator ' . json_encode($separator));
        }

        // A chosen separator is used as it is, even when it reads the data as one column.
        $rows = import_util::parse("guid\temail\nz-1\tone@example.com", 'UTF-8', 'comma');
        $this->assertSame([["guid\temail"], ["z-1\tone@example.com"]], $rows);
    }

    public function test_parse(): void {
        $rows = import_util::parse("guid,email\nz-1,one@example.com\nz-2,two@example.com", 'UTF-8', 'comma');
        $this->assertSame([['guid', 'email'], ['z-1', 'one@example.com'], ['z-2', 'two@example.com']], $rows);

        $rows = import_util::parse("guid;email\nz-1;one@example.com", 'UTF-8', 'semicolon');
        $this->assertSame([['guid', 'email'], ['z-1', 'one@example.com']], $rows);

        // Values are trimmed and quoting is honoured.
        $rows = import_util::parse("guid,name\n z-1 ,\"Doe, Jane\"", 'UTF-8', 'comma');
        $this->assertSame(['z-1', 'Doe, Jane'], $rows[1]);

        try {
            import_util::parse('   ', 'UTF-8', 'comma');
            $this->fail('Exception expected');
        } catch (\tool_mulib\local\import_exception $e) {
            $this->assertSame(get_string('import_error_empty', 'tool_mulib'), $e->getMessage());
        }

        // Inconsistent column counts are reported in a form usable for users.
        try {
            import_util::parse("guid,email\nz-1", 'UTF-8', 'comma');
            $this->fail('Exception expected');
        } catch (\tool_mulib\local\import_exception $e) {
            $expected = get_string('import_error_parse', 'tool_mulib', get_string('csvweirdcolumns', 'error'));
            $this->assertSame($expected, $e->getMessage());
        }
    }

    public function test_separator_detection_order(): void {
        global $CFG;
        $this->resetAfterTest();

        // Spreadsheet data with commas inside values.
        $rows = import_util::parse("name\temail\nDoe, Jane\tjane@example.com", 'UTF-8', import_util::DELIMITER_AUTO);
        $this->assertSame([['name', 'email'], ['Doe, Jane', 'jane@example.com']], $rows);

        // Separator that does not give the same number of columns in all rows is skipped.
        $rows = import_util::parse("Last, First;email\nDoe;jane@example.com", 'UTF-8', import_util::DELIMITER_AUTO);
        $this->assertSame([['Last, First', 'email'], ['Doe', 'jane@example.com']], $rows);

        // Site delimiter is preferred over comma.
        $CFG->CSV_DELIMITER = ';';
        $rows = import_util::parse("name;email\nDoe, Jane;jane@example.com", 'UTF-8', import_util::DELIMITER_AUTO);
        $this->assertSame([['name', 'email'], ['Doe, Jane', 'jane@example.com']], $rows);

        $CFG->CSV_DELIMITER = '|';
        $rows = import_util::parse("name|email\nDoe, Jane|jane@example.com", 'UTF-8', import_util::DELIMITER_AUTO);
        $this->assertSame([['name', 'email'], ['Doe, Jane', 'jane@example.com']], $rows);

        // One column is fine.
        $rows = import_util::parse("jane@example.com\njohn@example.com", 'UTF-8', import_util::DELIMITER_AUTO);
        $this->assertSame([['jane@example.com'], ['john@example.com']], $rows);

        $this->expectException(\tool_mulib\local\import_exception::class);
        import_util::parse(" \n ", 'UTF-8', import_util::DELIMITER_AUTO);
    }

    public function test_get_delimiter_options(): void {
        $options = import_util::get_delimiter_options();
        $this->assertSame(import_util::DELIMITER_AUTO, array_key_first($options));
        $this->assertArrayHasKey('tab', $options);
        $this->assertArrayHasKey('comma', $options);
    }

    public function test_normalise_header(): void {
        $this->assertSame('emailaddress', import_util::normalise_header(' E-mail Address '));
        $this->assertSame('idnumber', import_util::normalise_header('ID number'));
    }
}
