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
 * Wiki activity generator.
 *
 * Creates an empty mod_wiki shell — the activity is structurally complete but
 * carries no pages beyond the (auto-created) first page. Wiki page content in
 * Blackboard exports lives in BB's wiki tool storage which the bbmig pipeline
 * does not read; the customer rebuilds wiki content manually after migration.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_wiki_generator extends mod_base {

    public function get_modulename(): string {
        return 'wiki';
    }

    /**
     * Create a wiki activity.
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     firstpagetitle?: string,
     *     wikimode?: string,
     *     defaultformat?: string,
     *     visible?: bool,
     * } $record
     * @return stdClass wiki record from DB with extra ->cmid field
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

        // Wiki-specific fields. Defaults match Moodle's add-instance form:
        // collaborative wiki with HTML editor, first page title falls back
        // to the activity name when not supplied.
        $moduleinfo->firstpagetitle = $record['firstpagetitle'] ?? $record['name'];
        $moduleinfo->wikimode = $record['wikimode'] ?? 'collaborative';
        $moduleinfo->defaultformat = $record['defaultformat'] ?? 'html';
        $moduleinfo->forceformat = 1;

        return $this->add($moduleinfo, $course);
    }
}
