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
 * Glossary activity generator.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_glossary_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'glossary'
     */
    public function get_modulename(): string {
        return 'glossary';
    }

    /**
     * Placeholder patterns for create_entry().
     *
     * @return array{concept: string}
     */
    protected function create_entry_placeholders(): array {
        return [
            'concept' => 'Entry %d',
        ];
    }

    /**
     * Create a glossary activity.
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
     * @return stdClass glossary record from DB with extra ->cmid field
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

        $moduleinfo->mainglossary = 0;
        $moduleinfo->globalglossary = 0;
        $moduleinfo->displayformat = 'dictionary';
        $moduleinfo->approvaldisplayformat = 'default';
        $moduleinfo->allowduplicatedentries = 0;
        // defaultapproval=0 means student-added entries are held for teacher
        // moderation. Safer default for migrated content where the upstream
        // tool was teacher-curated — caller still controls whether students
        // can add entries at all via role permissions.
        $moduleinfo->defaultapproval = 0;
        $moduleinfo->editalways = 0;
        $moduleinfo->allowprintview = 1;
        $moduleinfo->allowcomments = 0;
        $moduleinfo->usedynalink = 0;
        $moduleinfo->showalphabet = 1;
        $moduleinfo->showall = 1;
        $moduleinfo->showspecial = 1;
        $moduleinfo->entbypage = 10;
        $moduleinfo->rsstype = 0;
        $moduleinfo->rssarticles = 0;
        $moduleinfo->assessed = 0;
        $moduleinfo->grade = 100;

        return $this->add($moduleinfo, $course);
    }

    /**
     * Create a glossary entry.
     *
     * @param array{
     *     glossaryid: int,
     *     concept?: string,
     *     definition?: string,
     *     definitionformat?: int,
     *     userid?: int,
     *     approved?: bool|int,
     *     usedynalink?: bool|int,
     *     casesensitive?: bool|int,
     *     fullmatch?: bool|int,
     *     teacherentry?: bool|int,
     * } $record
     * @return stdClass glossary_entries record fetched from DB
     */
    public function create_entry(array $record): stdClass {
        global $DB, $USER;

        $record = $this->merge_defaults($record);
        $record = $this->apply_placeholders(
            $record,
            'glossary_entries',
            $this->get_placeholders('create_entry', $this->create_entry_placeholders())
        );

        if (empty($record['glossaryid'])) {
            throw new \coding_exception('create_entry requires glossaryid in $record');
        }

        $glossary = $DB->get_record('glossary', ['id' => $record['glossaryid']], '*', MUST_EXIST);
        require_capability('tool/mulib:generatecontent', \context_course::instance($glossary->course));

        $now = time();
        $entry = new stdClass();
        $entry->glossaryid = (int)$record['glossaryid'];
        $entry->userid = (int)($record['userid'] ?? $USER->id);
        $entry->concept = (string)($record['concept'] ?? '');
        $entry->definition = (string)($record['definition'] ?? '');
        $entry->definitionformat = (int)($record['definitionformat'] ?? FORMAT_HTML);
        $entry->definitiontrust = 0;
        $entry->attachment = '';
        $entry->timecreated = $now;
        $entry->timemodified = $now;
        $entry->teacherentry = !empty($record['teacherentry']) ? 1 : 0;
        $entry->sourceglossaryid = 0;
        // Default usedynalink off — BB glossary content imports often carry
        // technical terms that match unrelated words inside other activities.
        // Operator can flip on individual entries via the standard UI.
        $entry->usedynalink = !empty($record['usedynalink']) ? 1 : 0;
        $entry->casesensitive = !empty($record['casesensitive']) ? 1 : 0;
        $entry->fullmatch = isset($record['fullmatch']) ? (int)(bool)$record['fullmatch'] : 1;
        $entry->approved = isset($record['approved']) ? (int)(bool)$record['approved'] : 1;
        $entry->id = $DB->insert_record('glossary_entries', $entry);

        return $DB->get_record('glossary_entries', ['id' => $entry->id], '*', MUST_EXIST);
    }
}
