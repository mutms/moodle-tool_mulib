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

use core\output\core_renderer;
use tool_mulib\muform\element;

/**
 * Row of buttons, children are rendered inline.
 *
 * Add the submit button first, browsers use the first submit button in the form
 * for implicit submission with Enter key. To show it last use the buttons-reversed
 * template variant, either via set_template() or the render variant.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class buttons extends element {
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

    #[\Override]
    protected function get_template_data(core_renderer $output, array $allerrors): array {
        $context = parent::get_template_data($output, $allerrors);

        // A group without any submit button is fine, Enter then uses the first button.
        $form = $this->get_form();
        $first = null;
        foreach ($this->get_children() as $elname) {
            $child = $form->get_element($elname);
            if ($child->is_submitting()) {
                if ($first !== null) {
                    debugging('Submit button should be added before other buttons, browsers use the first submit button'
                        . ' for Enter key submission: ' . $first, DEBUG_DEVELOPER);
                }
                break;
            }
            if ($first === null && ($child->is_cancelling() || $child->is_reloading())) {
                $first = $elname;
            }
        }

        return $context;
    }
}
