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

namespace tool_mulib\muform;

/**
 * Element validator base class.
 *
 * Validators run only when the form is submitted, after the form is finalised,
 * so all elements, values and display rules are available via $element->get_form().
 * Add errors as $allerrors['elementname'][] = 'text', errors for other elements are fine.
 * Validators run in the order they were added and may also replace or remove earlier
 * messages, for example to report a more specific error instead of a generic one.
 * Callables with the same signature may be used instead of validator instances.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class validator {
    /**
     * Validate element value.
     *
     * @param element $element element with parsed value, use $element->get_form() for other elements
     * @param array $allerrors all form errors indexed by element names, add errors here
     */
    abstract public function validate(element $element, array &$allerrors): void;
}
