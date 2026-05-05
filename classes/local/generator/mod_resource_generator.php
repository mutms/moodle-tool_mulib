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
 * Resource (file) activity generator.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_resource_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'resource'
     */
    public function get_modulename(): string {
        return 'resource';
    }

    /**
     * Create a resource (file) activity.
     *
     * display/printintro/showsize/showtype/showdate accept overrides; if absent
     * they fall back to the defaults Moodle's manual create-resource form uses
     * (display=AUTO, everything else off).
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     *     files?: array<string, \stored_file|string|array{content: string}>,
     *     display?: int,
     *     printintro?: int,
     *     showsize?: int,
     *     showtype?: int,
     *     showdate?: int,
     * } $record
     * @return stdClass resource record from DB with extra ->cmid field
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

        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');

        $moduleinfo->display = (int)($record['display'] ?? RESOURCELIB_DISPLAY_AUTO);
        $moduleinfo->printintro = (int)($record['printintro'] ?? 0);
        $moduleinfo->showsize = (int)($record['showsize'] ?? 0);
        $moduleinfo->showtype = (int)($record['showtype'] ?? 0);
        $moduleinfo->showdate = (int)($record['showdate'] ?? 0);
        $moduleinfo->files = !empty($record['files']) ? $this->prepare_draft_area($record['files']) : 0;

        return $this->add($moduleinfo, $course);
    }
}
