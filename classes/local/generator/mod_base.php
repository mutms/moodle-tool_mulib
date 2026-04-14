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
 * Abstract base for activity module generators.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mod_base extends base {

    /** @var int|null cached module table ID */
    private ?int $moduleid = null;

    /**
     * Returns the module name (e.g. 'page', 'forum', 'book').
     *
     * @return string
     */
    abstract public function get_modulename(): string;

    public function get_component(): string {
        return 'mod_' . $this->get_modulename();
    }

    /**
     * Create an activity instance in a course.
     *
     * @param array $record must contain 'course' and 'name' (unless auto-generated)
     * @return stdClass module instance record fetched from DB, with extra ->cmid field
     */
    abstract public function create_activity(array $record): stdClass;

    /**
     * Returns placeholder patterns for auto-generating activity names.
     *
     * Override in subclasses to customise.
     *
     * @return array{name: string}
     */
    protected function create_activity_placeholders(): array {
        return [
            'name' => 'Generated ' . $this->get_modulename() . ' %d',
        ];
    }

    /**
     * Resolve and cache module table ID.
     *
     * @return int
     */
    final protected function get_module_id(): int {
        if ($this->moduleid === null) {
            global $DB;
            $this->moduleid = (int)$DB->get_field('modules', 'id',
                ['name' => $this->get_modulename()], MUST_EXIST);
        }
        return $this->moduleid;
    }

    /**
     * Build a moduleinfo object with common defaults.
     *
     * @param stdClass $course course record
     * @param int $sectionnum section number
     * @param string $name activity name
     * @param string $intro intro HTML
     * @param ?int $introformat default FORMAT_HTML, or FORMAT_PLAIN, etc.
     * @param bool $visible
     * @param array|null $introfiles files for the intro area (targetpath => source format)
     * @return stdClass moduleinfo ready for type-specific fields
     */
    final protected function build_moduleinfo(
        stdClass $course,
        int $sectionnum,
        string $name,
        string $intro = '',
        int $introformat = null,
        bool $visible = true,
        array $introfiles = null,
    ): stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        $moduleinfo = new stdClass();
        $moduleinfo->modulename = $this->get_modulename();
        $moduleinfo->module = $this->get_module_id();
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnum;
        $moduleinfo->name = $name;
        $moduleinfo->visible = $visible ? 1 : 0;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->introeditor = [
            'text' => $intro,
            'format' => $introformat ?? (int)FORMAT_HTML,
            'itemid' => $introfiles ? $this->prepare_draft_area($introfiles) : 0,
        ];

        return $moduleinfo;
    }

    /**
     * Call add_moduleinfo and return the full instance record from DB.
     *
     * @param stdClass $moduleinfo prepared moduleinfo object
     * @param stdClass $course course record
     * @return stdClass module instance record with extra ->cmid field (course_modules.id)
     */
    final protected function add(stdClass $moduleinfo, stdClass $course): stdClass {
        global $DB;

        $result = add_moduleinfo($moduleinfo, $course);
        $instance = $DB->get_record($this->get_modulename(), ['id' => $result->instance], '*', MUST_EXIST);
        $instance->cmid = $result->coursemodule;

        return $instance;
    }

    /**
     * Prepare record: merge defaults, apply placeholders, resolve course object.
     *
     * @param array $record
     * @return array{0: array, 1: stdClass} [$record, $course]
     */
    final protected function prepare_record(array $record): array {
        $record = $this->merge_defaults($record);
        $record = $this->apply_placeholders($record, $this->get_modulename(),
            $this->get_placeholders('create_activity', $this->create_activity_placeholders()));

        if (empty($record['course'])) {
            throw new \coding_exception('create_activity requires course in $record');
        }
        if (is_object($record['course'])) {
            $course = $record['course'];
            $record['course'] = $course->id;
        } else {
            $course = get_course($record['course']);
        }

        require_capability('tool/mulib:generatecontent', \context_course::instance($course->id));

        return [$record, $course];
    }
}
