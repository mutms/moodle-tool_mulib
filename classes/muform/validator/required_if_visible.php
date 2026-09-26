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

namespace tool_mulib\muform\validator;

use tool_mulib\muform\element;
use tool_mulib\muform\validator;

/**
 * Value is required only when the element is not hidden by display rules.
 *
 * Use together with $element->set_required_marker(true).
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class required_if_visible extends validator {
    #[\Override]
    public function validate(element $element, array &$allerrors): void {
        if (!empty($allerrors[$element->get_name()])) {
            return;
        }
        if ($element->get_form()->get_display_manager()->is_hidden($element->get_name())) {
            return;
        }
        if (!$element->has_required_value()) {
            $allerrors[$element->get_name()][] = $element->get_required_hint();
        }
    }
}
