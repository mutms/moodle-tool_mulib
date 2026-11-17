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

namespace tool_mulib\muform\cohortpicker;

use core\context;

/**
 * All cohorts the current user may use for an item in a context.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context_cohorts extends base {
    /** @var context */
    private context $context;

    /**
     * Constructor.
     *
     * @param int $contextid context of the edited item
     */
    public function __construct(int $contextid) {
        if (!isloggedin() || isguestuser()) {
            throw new \core\exception\require_login_exception('Guests cannot select cohorts');
        }
        $this->context = context::instance_by_id($contextid);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->context->id];
    }

    #[\Override]
    protected function get_context(): context {
        return $this->context;
    }
}
