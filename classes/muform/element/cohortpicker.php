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
use tool_mulib\muform\cohortpicker\base;

/**
 * Cohort picker, the value is a cohort id string or null.
 *
 * The selected cohort is a pill, the button opens a panel over the right half of the page with cohorts
 * in the tree of course categories. The source given to the constructor decides which cohorts
 * may be picked and does the access control.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohortpicker extends element {
    use \tool_mulib\muform\util\picker_trait;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param base $source cohorts that may be picked
     */
    public function __construct(string $name, string $label, base $source) {
        parent::__construct($name);
        $this->init_picker($label, $source);
    }

    #[\Override]
    protected function is_multiple(): bool {
        return false;
    }
}
