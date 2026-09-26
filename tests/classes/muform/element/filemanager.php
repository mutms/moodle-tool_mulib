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

namespace tool_mulib\tests\muform\element;

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat helper for filemanager element.
 *
 * The value is the comma separated list of file names in the draft area, read from
 * the database. Files are uploaded with the "I upload ... file to ... muform filemanager"
 * step of behat_tool_mulib_files, the table step cannot set files.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filemanager extends base {
    #[\Override]
    public function set_value(string $value): void {
        throw new ExpectationException(
            'Use step "I upload ... file to ... muform filemanager" for muform element "' . $this->get_name() . '"',
            $this->context->getSession()
        );
    }

    #[\Override]
    public function get_value(): string {
        return implode(', ', $this->get_filenames());
    }

    #[\Override]
    public function matches(string $expected): bool {
        $expected = $this->split_values($expected);
        return $this->same_keys($expected, $this->get_filenames());
    }

    /**
     * File names in the draft area or in the frozen list.
     *
     * @return string[]
     */
    private function get_filenames(): array {
        global $DB;

        $hidden = $this->wrapper->find('css', 'input[type="hidden"][name]');
        if (!$hidden) {
            $names = [];
            foreach ($this->find_all('css', 'ul a') as $link) {
                $names[] = trim($link->getText());
            }
            return $names;
        }
        $draftid = (int)$hidden->getValue();
        if (!$draftid) {
            return [];
        }
        $names = $DB->get_fieldset_select(
            'files',
            'filename',
            "component = 'user' AND filearea = 'draft' AND itemid = :itemid AND filename <> '.'",
            ['itemid' => $draftid]
        );
        sort($names);
        return $names;
    }
}
