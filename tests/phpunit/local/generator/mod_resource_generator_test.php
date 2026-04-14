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
 * Resource generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_resource_generator
 */
final class mod_resource_generator_test extends \advanced_testcase {

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
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RGT1']);

        $resource = $generator->mod_resource->create_activity([
            'course' => $course,
            'name' => 'Empty Resource',
        ]);

        $this->assertSame('Empty Resource', $resource->name);
        $this->assertObjectHasProperty('cmid', $resource);
        $this->assertTrue($DB->record_exists('resource', ['id' => $resource->id]));
    }

    public function test_create_activity_with_file_from_content(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RFC1']);

        $resource = $generator->mod_resource->create_activity([
            'course' => $course,
            'name' => 'Text Resource',
            'files' => [
                'readme.txt' => ['content' => 'Hello world'],
            ],
        ]);

        // Verify file exists in the mod_resource content area.
        $fs = get_file_storage();
        $context = \context_module::instance($resource->cmid);
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false);
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertSame('readme.txt', $file->get_filename());
        $this->assertSame('Hello world', $file->get_content());
        $this->assertSame('/', $file->get_filepath());
    }

    public function test_create_activity_with_file_from_path(): void {
        global $CFG;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RFP1']);

        // Create a temporary file on disk.
        $tempdir = make_temp_directory('generator_test');
        $tempfile = $tempdir . '/testfile.pdf';
        file_put_contents($tempfile, 'PDF content here');

        $resource = $generator->mod_resource->create_activity([
            'course' => $course,
            'name' => 'PDF Resource',
            'files' => [
                'document.pdf' => $tempfile,
            ],
        ]);

        // Verify file exists in the mod_resource content area.
        $fs = get_file_storage();
        $context = \context_module::instance($resource->cmid);
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false);
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertSame('document.pdf', $file->get_filename());
        $this->assertSame('PDF content here', $file->get_content());

        @unlink($tempfile);
    }

    public function test_create_activity_with_stored_file(): void {
        global $USER;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RSF1']);

        // Create a stored_file in a temporary area.
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $original = $fs->create_file_from_string([
            'component' => 'tool_mulib',
            'filearea' => 'draft',
            'contextid' => $syscontext->id,
            'itemid' => 0,
            'filepath' => '/original/',
            'filename' => 'source.txt',
        ], 'Stored file content');

        $resource = $generator->mod_resource->create_activity([
            'course' => $course,
            'name' => 'Stored File Resource',
            'files' => [
                'renamed.txt' => $original,
            ],
        ]);

        // Verify file was copied to the resource content area with the target name.
        $context = \context_module::instance($resource->cmid);
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false);
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertSame('renamed.txt', $file->get_filename());
        $this->assertSame('/', $file->get_filepath());
        $this->assertSame('Stored file content', $file->get_content());
    }

    public function test_create_activity_with_file_in_subdirectory(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RSD1']);

        $resource = $generator->mod_resource->create_activity([
            'course' => $course,
            'name' => 'Subdir Resource',
            'files' => [
                'docs/manual.txt' => ['content' => 'Manual content'],
            ],
        ]);

        $fs = get_file_storage();
        $context = \context_module::instance($resource->cmid);
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false);
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertSame('manual.txt', $file->get_filename());
        $this->assertSame('/docs/', $file->get_filepath());
    }

    public function test_create_activity_with_too_long_filename(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RTLF1']);

        $longname = str_repeat('a', 256) . '.txt';
        try {
            $generator->mod_resource->create_activity([
                'course' => $course,
                'name' => 'Long Filename',
                'files' => [
                    $longname => ['content' => 'test'],
                ],
            ]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Filename exceeds', $e->getMessage());
        }
    }

    public function test_create_activity_with_too_long_filepath(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RTLP1']);

        $longpath = str_repeat('subdir/', 40) . 'file.txt';
        try {
            $generator->mod_resource->create_activity([
                'course' => $course,
                'name' => 'Long Path',
                'files' => [
                    $longpath => ['content' => 'test'],
                ],
            ]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('path exceeds', $e->getMessage());
        }
    }

    public function test_create_activity_with_missing_target_path(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RMTP1']);

        try {
            $generator->mod_resource->create_activity([
                'course' => $course,
                'name' => 'Bad Path',
                'files' => [
                    '' => ['content' => 'test'],
                ],
            ]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('must include filename', $e->getMessage());
        }
    }

    public function test_create_activity_with_invalid_source(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RIS1']);

        try {
            $generator->mod_resource->create_activity([
                'course' => $course,
                'name' => 'Bad Source',
                'files' => [
                    'test.txt' => 12345,
                ],
            ]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid file source', $e->getMessage());
        }
    }

    public function test_create_activity_with_nonexistent_file_path(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'RNEF1']);

        try {
            $generator->mod_resource->create_activity([
                'course' => $course,
                'name' => 'Missing File',
                'files' => [
                    'test.txt' => '/nonexistent/path/file.txt',
                ],
            ]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('not readable', $e->getMessage());
        }
    }
}
