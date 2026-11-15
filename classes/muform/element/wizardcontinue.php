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

/**
 * Continue button of a wizard stage, a submit button with the name and label all wizards share,
 * see docs/muform.md.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wizardcontinue extends button {
    /** @var string element name */
    public const string NAME = 'submit';

    /**
     * Constructor.
     *
     * @param string|null $label null means "Continue"
     */
    public function __construct(?string $label = null) {
        parent::__construct(self::NAME, $label ?? get_string('muform_continue', 'tool_mulib'));
        $this->set_attribute('primary', true);
    }

    #[\Override]
    public function get_role(): string {
        return 'submit';
    }

    #[\Override]
    public function is_submitting(): bool {
        return true;
    }
}
