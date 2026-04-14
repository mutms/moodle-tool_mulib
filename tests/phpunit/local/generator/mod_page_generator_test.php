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
 * Page generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_page_generator
 */
final class mod_page_generator_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_create_activity(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'PGT1']);

        $page = $generator->mod_page->create_activity([
            'course' => $course,
            'name' => 'Test Page',
            'content' => '<p>Hello world</p>',
        ]);

        $this->assertInstanceOf(\stdClass::class, $page);
        $this->assertSame('Test Page', $page->name);
        $this->assertSame('<p>Hello world</p>', $page->content);
        $this->assertObjectHasProperty('cmid', $page);
        $this->assertTrue($DB->record_exists('page', ['id' => $page->id]));
        $this->assertTrue($DB->record_exists('course_modules', ['id' => $page->cmid]));
    }

    public function test_create_activity_with_content_files(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'PCF1']);

        $page = $generator->mod_page->create_activity([
            'course' => $course,
            'name' => 'Page With Image',
            'content' => '<p>See image: <img src="@@PLUGINFILE@@/photo.jpg"></p>',
            'contentfiles' => [
                'photo.jpg' => ['content' => 'fake image data'],
            ],
        ]);

        // Verify file exists in mod_page/content area.
        $fs = get_file_storage();
        $context = \context_module::instance($page->cmid);
        $files = $fs->get_area_files($context->id, 'mod_page', 'content', 0, 'filename', false);
        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertSame('photo.jpg', $file->get_filename());
        $this->assertSame('fake image data', $file->get_content());

        // Verify content still references @@PLUGINFILE@@.
        $this->assertStringContainsString('@@PLUGINFILE@@/photo.jpg', $page->content);
    }

    public function test_create_activity_with_multiple_content_files(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'PMF1']);

        $page = $generator->mod_page->create_activity([
            'course' => $course,
            'name' => 'Page With Files',
            'content' => '<p><img src="@@PLUGINFILE@@/img.png"> <a href="@@PLUGINFILE@@/docs/manual.pdf">Manual</a></p>',
            'contentfiles' => [
                'img.png' => ['content' => 'PNG data'],
                'docs/manual.pdf' => ['content' => 'PDF data'],
            ],
        ]);

        $fs = get_file_storage();
        $context = \context_module::instance($page->cmid);
        $files = $fs->get_area_files($context->id, 'mod_page', 'content', 0, 'filepath, filename', false);
        $this->assertCount(2, $files);

        $filenames = [];
        foreach ($files as $file) {
            $filenames[$file->get_filepath() . $file->get_filename()] = $file->get_content();
        }
        $this->assertSame('PNG data', $filenames['/img.png']);
        $this->assertSame('PDF data', $filenames['/docs/manual.pdf']);
    }

    public function test_create_activity_without_content_files(): void {
        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'PNF1']);

        // No contentfiles — should work like before.
        $page = $generator->mod_page->create_activity([
            'course' => $course,
            'name' => 'Plain Page',
            'content' => '<p>No files</p>',
        ]);

        $fs = get_file_storage();
        $context = \context_module::instance($page->cmid);
        $files = $fs->get_area_files($context->id, 'mod_page', 'content', 0, 'filename', false);
        $this->assertCount(0, $files);
    }
}
