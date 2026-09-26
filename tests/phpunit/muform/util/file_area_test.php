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

namespace tool_mulib\phpunit\muform\util;

use core\context\system;
use core\exception\coding_exception;
use tool_mulib\muform\util\file_area;

/**
 * File area util tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\util\file_area
 */
final class file_area_test extends \advanced_testcase {
    public function test_construct(): void {
        $syscontext = system::instance();

        $area = new file_area($syscontext, 'tool_mulib', 'muform_fixture', 7);
        $this->assertSame('tool_mulib', $area->get_component());
        $this->assertSame('muform_fixture', $area->get_filearea());
        $this->assertSame($syscontext->id, $area->get_contextid());
        $this->assertSame(7, $area->get_itemid());
        $this->assertTrue($area->is_valid());

        $area = new file_area($syscontext->id, 'tool_mulib', 'muform_fixture', 0);
        $this->assertSame($syscontext->id, $area->get_contextid());
        $this->assertSame(0, $area->get_itemid());
        $this->assertTrue($area->is_valid());
    }

    public function test_incomplete(): void {
        $syscontext = system::instance();

        $area = new file_area(null, 'tool_mulib', 'muform_fixture', null);
        $this->assertNull($area->get_contextid());
        $this->assertNull($area->get_itemid());
        $this->assertFalse($area->is_valid());

        $area->set_contextid($syscontext);
        $this->assertFalse($area->is_valid());
        $area->set_itemid(3);
        $this->assertTrue($area->is_valid());
        $area->set_contextid(-1);
        $this->assertFalse($area->is_valid());
    }

    public function test_invalid_component(): void {
        $this->expectException(coding_exception::class);
        new file_area(null, 'Tool Mulib', 'x', null);
    }

    public function test_invalid_area(): void {
        $this->expectException(coding_exception::class);
        new file_area(null, 'tool_mulib', 'bad area', null);
    }
}
