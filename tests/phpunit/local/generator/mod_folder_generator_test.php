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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_mulib\phpunit\local\generator;

use tool_mulib\local\generator;

/**
 * Folder generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_folder_generator
 */
final class mod_folder_generator_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        \core\di::get(generator::class)->reset();
        parent::tearDown();
    }

    public function test_create_activity_without_files(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'FOGT1']);

        $folder = $generator->mod_folder->create_activity([
            'course' => $course,
            'name' => 'Empty Folder',
        ]);

        $this->assertSame('Empty Folder', $folder->name);
        $this->assertObjectHasProperty('cmid', $folder);
        $this->assertTrue($DB->record_exists('folder', ['id' => $folder->id]));
    }

    public function test_create_activity_with_multiple_files(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'FMF1']);

        // Create a temp file on disk.
        $tempdir = make_temp_directory('generator_test');
        $tempfile = $tempdir . '/disk_file.txt';
        file_put_contents($tempfile, 'From disk');

        $folder = $generator->mod_folder->create_activity([
            'course' => $course,
            'name' => 'Multi File Folder',
            'files' => [
                'readme.txt' => ['content' => 'Inline content'],
                'data.txt' => $tempfile,
                'docs/manual.txt' => ['content' => 'Manual in subdirectory'],
            ],
        ]);

        // Verify all files exist in the mod_folder content area.
        $fs = get_file_storage();
        $context = \context_module::instance($folder->cmid);
        $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);
        $this->assertCount(3, $files);

        $filenames = [];
        foreach ($files as $file) {
            $filenames[$file->get_filepath() . $file->get_filename()] = $file->get_content();
        }
        $this->assertSame('Inline content', $filenames['/readme.txt']);
        $this->assertSame('From disk', $filenames['/data.txt']);
        $this->assertSame('Manual in subdirectory', $filenames['/docs/manual.txt']);

        @unlink($tempfile);
    }

    public function test_create_activity_with_stored_file(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'FSF1']);

        // Create a stored_file.
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $original = $fs->create_file_from_string([
            'component' => 'tool_mulib',
            'filearea' => 'draft',
            'contextid' => $syscontext->id,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'original.txt',
        ], 'Original content');

        $folder = $generator->mod_folder->create_activity([
            'course' => $course,
            'name' => 'Stored File Folder',
            'files' => [
                'copied.txt' => $original,
            ],
        ]);

        $context = \context_module::instance($folder->cmid);
        $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filename', false);
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertSame('copied.txt', $file->get_filename());
        $this->assertSame('Original content', $file->get_content());
    }
}
