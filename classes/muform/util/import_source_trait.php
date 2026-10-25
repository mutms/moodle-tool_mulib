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

namespace tool_mulib\muform\util;

use tool_mulib\local\import_exception;
use tool_mulib\local\import_util;
use tool_mulib\muform\element\filemanager;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\textarea;

/**
 * Source elements of import forms: uploaded file or pasted text, encoding and delimiter.
 *
 * Element names are sourcefile, csvtext, encoding and delimiter_name.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait import_source_trait {
    /** @var string element that shows source errors, the first one of the source elements */
    private string $importsourceerrorelement = 'sourcefile';

    /**
     * Add import source elements.
     *
     * @param bool $pastefirst true means the text area for pasting is shown before the file picker
     * @param string|null $parent parent element name
     */
    protected function add_import_source_elements(bool $pastefirst = false, ?string $parent = null): void {
        $file = new filemanager('sourcefile', get_string('import_file', 'tool_mulib'), 1, ['.csv', '.tsv', '.txt']);
        $textlabel = $pastefirst ? get_string('import_paste', 'tool_mulib') : get_string('import_paste_or', 'tool_mulib');
        $text = new textarea('csvtext', $textlabel, ['type' => 'rawtext', 'rows' => 8]);
        if ($pastefirst) {
            $this->importsourceerrorelement = 'csvtext';
            $this->add($text, $parent);
            $file = new filemanager('sourcefile', get_string('import_file_or', 'tool_mulib'), 1, ['.csv', '.tsv', '.txt']);
            $this->add($file, $parent);
        } else {
            $this->add($file, $parent);
            $this->add($text, $parent);
        }

        $encoding = (new select('encoding', get_string('import_encoding', 'tool_mulib'), \core_text::get_encodings()))
            ->set_default('UTF-8');
        $this->add($encoding, $parent);

        $delimiters = import_util::get_delimiter_options();
        $delimiter = (new select('delimiter_name', get_string('import_delimiter', 'tool_mulib'), $delimiters))
            ->set_default(import_util::DELIMITER_AUTO);
        $this->add($delimiter, $parent);
    }

    /**
     * Uploaded file content or the pasted text, the file wins.
     *
     * @return string
     */
    public function get_import_source_content(): string {
        foreach ($this->get_element('sourcefile')->get_files() as $file) {
            return trim($file->get_content());
        }
        return trim((string)$this->get_element('csvtext')->get_value());
    }

    /**
     * Parse submitted source, errors are added to the first source element.
     *
     * @param array $data submitted data
     * @param array $allerrors
     * @return array|null parsed rows, null on error
     */
    protected function validate_import_source(array $data, array &$allerrors): ?array {
        $content = $this->get_import_source_content();
        if ($content === '') {
            $allerrors[$this->importsourceerrorelement][] = get_string('import_error_source', 'tool_mulib');
            return null;
        }
        try {
            return import_util::parse($content, $data['encoding'], $data['delimiter_name']);
        } catch (import_exception $e) {
            $allerrors[$this->importsourceerrorelement][] = $e->getMessage();
            return null;
        }
    }
}
