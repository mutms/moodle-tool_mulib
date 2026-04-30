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

namespace tool_mulib\local\generator;

use stdClass;

/**
 * Lesson activity generator.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_lesson_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'lesson'
     */
    public function get_modulename(): string {
        return 'lesson';
    }

    /**
     * Create a lesson activity.
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     * } $record
     * @return stdClass lesson record from DB with extra ->cmid field
     */
    public function create_activity(array $record): stdClass {
        [$record, $course] = $this->prepare_record($record);

        $moduleinfo = $this->build_moduleinfo(
            $course,
            (int)($record['section'] ?? 0),
            $record['name'],
            $record['intro'] ?? '',
            $record['introformat'] ?? null,
            !isset($record['visible']) || $record['visible'],
            $record['introfiles'] ?? null,
        );

        $moduleinfo->mediafile = 0;
        $moduleinfo->available = 0;
        $moduleinfo->deadline = 0;
        $moduleinfo->usepassword = 0;
        $moduleinfo->password = '';
        $moduleinfo->practice = 0;
        $moduleinfo->custom = 1;
        $moduleinfo->retake = 0;
        $moduleinfo->usemaxgrade = 0;
        $moduleinfo->maxattempts = 1;
        $moduleinfo->maxpages = 0;
        $moduleinfo->feedback = 1;
        $moduleinfo->activitylink = 0;
        $moduleinfo->progressbar = 0;
        $moduleinfo->ongoing = 0;
        $moduleinfo->displayleft = 0;
        $moduleinfo->displayleftif = 0;
        $moduleinfo->slideshow = 0;
        $moduleinfo->maxanswers = 5;
        $moduleinfo->dependency = 0;
        $moduleinfo->timespent = 0;
        $moduleinfo->completed = 0;
        $moduleinfo->gradebetterthan = 0;
        $moduleinfo->modattempts = 0;
        $moduleinfo->review = 0;
        $moduleinfo->nextpagedefault = 0;
        $moduleinfo->grade = 100;

        return $this->add($moduleinfo, $course);
    }
}
