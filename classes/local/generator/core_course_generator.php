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
 * Course, section, and subsection generator.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_course_generator extends base {
    /**
     * Component identifier.
     *
     * @return string always 'core_course'
     */
    public function get_component(): string {
        return 'core_course';
    }

    /**
     * Placeholder patterns for create_course().
     *
     * @return array{fullname: string, shortname: string}
     */
    protected function create_course_placeholders(): array {
        return [
            'fullname' => 'Sample course %d',
            'shortname' => 'SC%d',
        ];
    }

    /**
     * Placeholder patterns for create_section().
     *
     * @return array{name: string}
     */
    protected function create_section_placeholders(): array {
        return [
            'name' => 'Sample section %d',
        ];
    }

    /**
     * Placeholder patterns for create_subsection().
     *
     * @return array{name: string}
     */
    protected function create_subsection_placeholders(): array {
        return [
            'name' => 'Sample subsection %d',
        ];
    }

    /**
     * Create a new Moodle course.
     *
     * @param array{
     *     category: int,
     *     fullname?: string,
     *     shortname?: string,
     *     idnumber?: string,
     *     summary?: string,
     *     summaryformat?: int,
     *     format?: string,
     *     numsections?: int,
     *     visible?: bool,
     *     newsitems?: int,
     *     enablecompletion?: int,
     *     summaryfiles?: array<string, \stored_file|string|array{content: string}>,
     * } $record
     * @return stdClass course record fetched from DB
     */
    public function create_course(array $record = []): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $record = $this->merge_defaults($record);
        $record = $this->apply_placeholders(
            $record,
            'course',
            $this->get_placeholders('create_course', $this->create_course_placeholders())
        );

        if (empty($record['category'])) {
            throw new \coding_exception('create_course requires category in $record');
        }

        require_capability(
            'tool/mulib:generatecontent',
            \context_coursecat::instance($record['category'])
        );

        $data = new stdClass();
        $data->category = (int)$record['category'];
        $data->fullname = $record['fullname'];
        $data->shortname = $record['shortname'];
        $data->idnumber = $record['idnumber'] ?? '';
        $data->summary = $record['summary'] ?? '';
        $data->summaryformat = $record['summaryformat'] ?? FORMAT_HTML;
        $data->format = $record['format'] ?? 'topics';
        $data->numsections = (int)($record['numsections'] ?? 0);
        $data->visible = isset($record['visible']) ? ($record['visible'] ? 1 : 0) : 0;
        $data->newsitems = (int)($record['newsitems'] ?? 0);
        $data->enablecompletion = (int)($record['enablecompletion'] ?? COMPLETION_DISABLED);

        $result = create_course($data);

        // Save summary files if provided.
        if (!empty($record['summaryfiles'])) {
            $draftitemid = $this->prepare_draft_area($record['summaryfiles']);
            $coursecontext = \context_course::instance($result->id);
            $result->summary = file_save_draft_area_files(
                $draftitemid,
                $coursecontext->id,
                'course',
                'summary',
                0,
                ['maxfiles' => -1, 'maxbytes' => 0],
                $result->summary,
            );
            $DB->update_record('course', $result);
        }

        return $DB->get_record('course', ['id' => $result->id], '*', MUST_EXIST);
    }

    /**
     * Create a new course section at the end of the course.
     *
     * @param array{
     *     course: int,
     *     name?: string,
     *     summary?: string,
     *     summaryformat?: int,
     *     summaryfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     * } $record
     * @return stdClass section record fetched from DB
     */
    public function create_section(array $record = []): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $record = $this->merge_defaults($record);
        $record = $this->apply_placeholders(
            $record,
            'course_sections',
            $this->get_placeholders('create_section', $this->create_section_placeholders())
        );

        if (empty($record['course'])) {
            throw new \coding_exception('create_section requires course in $record');
        }

        $courseid = (int)$record['course'];
        require_capability('tool/mulib:generatecontent', \context_course::instance($courseid));
        $sectionrecord = course_create_section($courseid);

        $update = new stdClass();
        $update->id = $sectionrecord->id;
        $update->name = $record['name'] ?? '';
        $update->summary = $record['summary'] ?? '';
        $update->summaryformat = $record['summaryformat'] ?? FORMAT_HTML;
        $update->visible = isset($record['visible']) ? ($record['visible'] ? 1 : 0) : 1;
        $DB->update_record('course_sections', $update);

        // Save summary files if provided.
        if (!empty($record['summaryfiles'])) {
            $draftitemid = $this->prepare_draft_area($record['summaryfiles']);
            $coursecontext = \context_course::instance($courseid);
            $update->summary = file_save_draft_area_files(
                $draftitemid,
                $coursecontext->id,
                'course',
                'section',
                $sectionrecord->id,
                ['maxfiles' => -1, 'maxbytes' => 0],
                $update->summary,
            );
            $DB->update_record('course_sections', $update);
        }

        return $DB->get_record('course_sections', ['id' => $sectionrecord->id], '*', MUST_EXIST);
    }

    /**
     * Create a subsection (delegated section via mod_subsection).
     *
     * @param array{
     *     course: int|stdClass,
     *     section: int,
     *     name?: string,
     *     visible?: bool,
     * } $record
     * @return stdClass delegated section record fetched from DB
     */
    public function create_subsection(array $record = []): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $record = $this->merge_defaults($record);
        $record = $this->apply_placeholders(
            $record,
            'course_sections',
            $this->get_placeholders('create_subsection', $this->create_subsection_placeholders())
        );

        if (empty($record['course'])) {
            throw new \coding_exception('create_subsection requires course in $record');
        }
        if (!isset($record['section'])) {
            throw new \coding_exception('create_subsection requires section (parent section number) in $record');
        }

        if (is_object($record['course'])) {
            $course = $record['course'];
        } else {
            $course = get_course($record['course']);
        }

        require_capability('tool/mulib:generatecontent', \context_course::instance($course->id));

        // Moodle's backup system has fixed SUBSECTION_LEVEL / SUBACTIVITY_LEVEL
        // constants and can't represent a subsection nested inside another
        // subsection (the resulting setting hierarchy fails the dependency
        // level check in backup_setting::add_dependency, and the course can
        // no longer be backed up or even deleted via recyclebin). Refuse here
        // so callers can never silently create such a structure.
        $parentcomponent = $DB->get_field('course_sections', 'component',
            ['course' => $course->id, 'section' => (int)$record['section']]);
        if ($parentcomponent === 'mod_subsection') {
            throw new \coding_exception(
                'create_subsection: cannot nest a subsection inside another subsection '
                . '(parent section ' . (int)$record['section'] . ' is itself a delegated mod_subsection); '
                . 'Moodle backup does not support this depth');
        }

        $moduleid = (int)$DB->get_field('modules', 'id', ['name' => 'subsection'], MUST_EXIST);

        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'subsection';
        $moduleinfo->module = $moduleid;
        $moduleinfo->course = $course->id;
        $moduleinfo->section = (int)$record['section'];
        $moduleinfo->name = $record['name'] ?? '';
        $moduleinfo->visible = isset($record['visible']) ? ($record['visible'] ? 1 : 0) : 1;
        $moduleinfo->visibleoncoursepage = 1;

        $result = add_moduleinfo($moduleinfo, $course);

        return $DB->get_record('course_sections', [
            'course' => $course->id,
            'component' => 'mod_subsection',
            'itemid' => $result->instance,
        ], '*', MUST_EXIST);
    }
}
