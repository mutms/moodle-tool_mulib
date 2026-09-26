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

namespace tool_mulib\muform\element;

use tool_mulib\muform\element;

/**
 * Form section rendered as fieldset with legend, children are rendered inside.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class section extends element {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     */
    public function __construct(string $name, string $label) {
        parent::__construct($name);
        $this->label = $label;
    }

    #[\Override]
    public function accepts_children(): bool {
        return true;
    }

    #[\Override]
    public function returns_data(): bool {
        return false;
    }

    #[\Override]
    protected function parse_value(): void {
        $this->value = null;
    }
}
