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

namespace tool_mulib\muform\tagarea;

use core\context;
use core\context\course as context_course;

/**
 * Tags of a course.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course extends base {
    /** @var context_course course context */
    private context_course $context;

    /**
     * Constructor.
     *
     * @param int $courseid
     */
    public function __construct(
        /** @var int course id */
        private readonly int $courseid
    ) {
        $this->context = context_course::instance($courseid);
        require_capability('moodle/course:tag', $this->context);
    }

    #[\Override]
    public function get_args(): array {
        return [$this->courseid];
    }

    #[\Override]
    public function get_component(): string {
        return 'core';
    }

    #[\Override]
    public function get_itemtype(): string {
        return 'course';
    }

    #[\Override]
    public function get_context(): context {
        return $this->context;
    }

    #[\Override]
    public function get_itemid(): ?int {
        return $this->courseid;
    }
}
