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
 * Page activity generator.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_page_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'page'
     */
    public function get_modulename(): string {
        return 'page';
    }

    /**
     * Create a page activity.
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     content?: string,
     *     contentformat?: int,
     *     contentfiles?: array<string, \stored_file|string|array{content: string}>,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     * } $record
     * @return stdClass page record from DB with extra ->cmid field
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

        global $CFG, $DB;
        require_once($CFG->libdir . '/resourcelib.php');

        $moduleinfo->content = $record['content'] ?? '';
        $moduleinfo->contentformat = $record['contentformat'] ?? FORMAT_HTML;
        $moduleinfo->display = RESOURCELIB_DISPLAY_OPEN;
        $moduleinfo->printintro = 1;
        $moduleinfo->printlastmodified = 0;

        $instance = $this->add($moduleinfo, $course);

        // Save content files if provided (page_add_instance skips this without $mform).
        if (!empty($record['contentfiles'])) {
            $cmid = $instance->cmid;
            $draftitemid = $this->prepare_draft_area($record['contentfiles']);
            $context = \context_module::instance($cmid);
            require_once($CFG->dirroot . '/mod/page/locallib.php');
            $instance->content = file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'mod_page',
                'content',
                0,
                \page_get_editor_options($context),
                $instance->content,
            );
            $DB->update_record('page', $instance);
            $instance = $DB->get_record('page', ['id' => $instance->id], '*', MUST_EXIST);
            $instance->cmid = $cmid;
        }

        return $instance;
    }
}
