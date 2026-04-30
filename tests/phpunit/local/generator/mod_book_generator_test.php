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
 * Book generator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\local\generator\mod_book_generator
 */
final class mod_book_generator_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_create_activity(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'BGT1']);

        $book = $generator->mod_book->create_activity([
            'course' => $course,
            'name' => 'Test Book',
        ]);

        $this->assertSame('Test Book', $book->name);
        $this->assertObjectHasProperty('cmid', $book);
        $this->assertTrue($DB->record_exists('book', ['id' => $book->id]));
    }

    public function test_create_chapter(): void {
        global $DB;

        $generator = \core\di::get(generator::class);
        $category = $this->getDataGenerator()->create_category();
        $course = $generator->core_course->create_course(['category' => $category->id, 'shortname' => 'BCT1']);

        $book = $generator->mod_book->create_activity([
            'course' => $course,
            'name' => 'Book With Chapters',
        ]);

        $ch1 = $generator->mod_book->create_chapter([
            'bookid' => $book->id,
            'title' => 'Chapter One',
            'content' => '<p>First chapter</p>',
        ]);

        $this->assertInstanceOf(\stdClass::class, $ch1);
        $this->assertSame('Chapter One', $ch1->title);
        $this->assertSame('<p>First chapter</p>', $ch1->content);
        $this->assertSame((int)$book->id, (int)$ch1->bookid);
        $this->assertSame(1, (int)$ch1->pagenum);

        $ch2 = $generator->mod_book->create_chapter([
            'bookid' => $book->id,
            'title' => 'Chapter Two',
            'subchapter' => true,
        ]);

        $this->assertSame(2, (int)$ch2->pagenum);
        $this->assertSame(1, (int)$ch2->subchapter);

        $this->assertSame(2, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }

    public function test_create_chapter_requires_bookid(): void {
        $generator = \core\di::get(generator::class);

        try {
            $generator->mod_book->create_chapter([
                'title' => 'Orphan Chapter',
            ]);
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('bookid', $e->getMessage());
        }
    }
}
