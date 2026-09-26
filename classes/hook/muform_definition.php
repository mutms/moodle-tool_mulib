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

namespace tool_mulib\hook;

use tool_mulib\muform\form;

/**
 * Hook dispatched after muform definition, before the form is finalised.
 *
 * Callbacks may add elements, validators, display rules or override templates.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Hook in muform form definition')]
#[\core\attribute\tags('tool_mulib')]
final class muform_definition {
    /**
     * Constructor.
     *
     * @param form $form form being defined
     */
    public function __construct(
        /** @var form form being defined */
        public readonly form $form,
    ) {
    }

    /**
     * Returns form being defined.
     *
     * @return form
     */
    public function get_form(): form {
        return $this->form;
    }
}
