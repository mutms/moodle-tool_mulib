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

/**
 * Behat helper for editor element.
 *
 * Core's editor form field handles both TinyMCE and the plain textarea,
 * values are compared with whitespace collapsed because editors reformat HTML.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor extends base {
    #[\Override]
    public function set_value(string $value): void {
        $this->get_field()->set_value($value);
    }

    #[\Override]
    public function get_value(): string {
        return $this->get_field()->get_value();
    }

    #[\Override]
    public function matches(string $expected): bool {
        $normalise = fn(string $s) => trim(preg_replace('/\s+/', ' ', $s));
        $actual = $normalise($this->get_value());
        // Editors may silently wrap plain text in a paragraph, like core behat_form_editor::matches().
        return $actual === $normalise($expected) || $actual === $normalise('<p>' . $expected . '</p>');
    }

    /**
     * Core editor field for the textarea.
     *
     * @return \behat_form_editor
     */
    private function get_field(): \behat_form_editor {
        global $CFG;
        require_once($CFG->libdir . '/behat/form_field/behat_form_editor.php');
        return new \behat_form_editor($this->context->getSession(), $this->find('css', 'textarea'));
    }
}
