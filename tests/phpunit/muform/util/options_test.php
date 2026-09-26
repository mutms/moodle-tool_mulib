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

use core\exception\coding_exception;
use tool_mulib\muform\util\options;

/**
 * Options util tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\util\options
 */
final class options_test extends \advanced_testcase {
    public function test_options(): void {
        $options = new options(['none' => 'No role', 1 => 'One']);
        $options
            ->add_optgroup('Teachers', ['teacher' => 'Teacher', 'editing' => 'Editing teacher'])
            ->add_options([2 => 'Two'])
            ->add_optgroup('Empty', [])
            ->add_optgroup('Others', ['student' => 'Student <b>']);

        $this->assertSame(['none', '1', '2', 'teacher', 'editing', 'student'], $options->get_keys());
        $this->assertSame([
            'none' => 'No role',
            1 => 'One',
            2 => 'Two',
            'teacher' => 'Teacher',
            'editing' => 'Editing teacher',
            'student' => 'Student <b>',
        ], $options->get_labels());
        $this->assertTrue($options->has_key('1'));
        $this->assertTrue($options->has_key('student'));
        $this->assertFalse($options->has_key('x'));
        $this->assertFalse($options->has_key(''));

        $this->assertSame([
            [
                'label' => '',
                'options' => [
                    ['key' => 'none', 'label' => 'No role', 'is_checked' => false, 'id' => 'id_x_0'],
                    ['key' => '1', 'label' => 'One', 'is_checked' => true, 'id' => 'id_x_1'],
                    ['key' => '2', 'label' => 'Two', 'is_checked' => false, 'id' => 'id_x_2'],
                ],
            ],
            [
                'label' => 'Teachers',
                'options' => [
                    ['key' => 'teacher', 'label' => 'Teacher', 'is_checked' => false, 'id' => 'id_x_3'],
                    ['key' => 'editing', 'label' => 'Editing teacher', 'is_checked' => true, 'id' => 'id_x_4'],
                ],
            ],
            [
                'label' => 'Others',
                'options' => [
                    ['key' => 'student', 'label' => 'Student &#60;b&#62;', 'is_checked' => false, 'id' => 'id_x_5'],
                ],
            ],
        ], $options->get_template_data('id_x', ['1', 'editing']));

        $this->assertSame([], (new options())->get_template_data('id_x', []));
    }

    public function test_duplicate_key(): void {
        $options = new options(['a' => 'A']);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Duplicate option key: a');
        $options->add_optgroup('G', ['a' => 'B']);
    }

    public function test_invalid_label(): void {
        $this->expectException(coding_exception::class);
        new options(['a' => 1]);
    }
}
