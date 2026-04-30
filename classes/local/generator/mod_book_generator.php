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
 * Book activity generator.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_book_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'book'
     */
    public function get_modulename(): string {
        return 'book';
    }

    /**
     * Placeholder patterns for create_chapter().
     *
     * @return array{title: string}
     */
    protected function create_chapter_placeholders(): array {
        return [
            'title' => 'Chapter %d',
        ];
    }

    /**
     * Create a book activity.
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     *     customtitles?: int,
     * } $record
     * @return stdClass book record from DB with extra ->cmid field
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

        $moduleinfo->customtitles = (int)($record['customtitles'] ?? 0);

        return $this->add($moduleinfo, $course);
    }

    /**
     * Create a book chapter.
     *
     * @param array{
     *     bookid: int,
     *     title?: string,
     *     content?: string,
     *     contentformat?: int,
     *     subchapter?: bool,
     *     pagenum?: int,
     * } $record
     * @return stdClass book_chapters record fetched from DB
     */
    public function create_chapter(array $record): stdClass {
        global $DB;

        $record = $this->merge_defaults($record);
        $record = $this->apply_placeholders(
            $record,
            'book_chapters',
            $this->get_placeholders('create_chapter', $this->create_chapter_placeholders())
        );

        if (empty($record['bookid'])) {
            throw new \coding_exception('create_chapter requires bookid in $record');
        }

        // Check capability via the book's course context.
        $book = $DB->get_record('book', ['id' => $record['bookid']], 'course', MUST_EXIST);
        require_capability('tool/mulib:generatecontent', \context_course::instance($book->course));

        // Auto-determine page number if not provided.
        if (!isset($record['pagenum'])) {
            $maxpagenum = $DB->get_field(
                'book_chapters',
                'MAX(pagenum)',
                ['bookid' => $record['bookid']]
            );
            $record['pagenum'] = ($maxpagenum ?? 0) + 1;
        }

        $chapter = new stdClass();
        $chapter->bookid = (int)$record['bookid'];
        $chapter->pagenum = (int)$record['pagenum'];
        $chapter->subchapter = !empty($record['subchapter']) ? 1 : 0;
        $chapter->title = $record['title'] ?? '';
        $chapter->content = $record['content'] ?? '';
        $chapter->contentformat = $record['contentformat'] ?? FORMAT_HTML;
        $chapter->hidden = 0;
        $chapter->timecreated = time();
        $chapter->timemodified = $chapter->timecreated;
        $chapter->importsrc = '';
        $chapter->id = $DB->insert_record('book_chapters', $chapter);

        return $DB->get_record('book_chapters', ['id' => $chapter->id], '*', MUST_EXIST);
    }
}
