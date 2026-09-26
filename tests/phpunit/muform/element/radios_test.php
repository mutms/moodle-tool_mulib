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

namespace tool_mulib\phpunit\muform\element;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../muform_testcase.php');

use core\exception\coding_exception;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\util\options;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Radios element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\radios
 */
final class radios_test extends muform_testcase {
    public function test_options(): void {
        $element = new radios('rr', 'R', [1 => 'One', '2' => 'Two']);
        $this->assertSame([1 => 'One', 2 => 'Two'], $element->get_options()->get_labels());
        $options = new options([1 => 'One']);
        $element = new radios('rr', 'R', $options);
        $this->assertSame($options, $element->get_options());
        $this->expectException(coding_exception::class);
        new radios('rr', 'R', ['a' => 1]);
    }

    public function test_optgroups(): void {
        $hook = function (muform_definition $hook): void {
            $options = (new options(['none' => 'No role']))
                ->add_optgroup('Teachers', ['teacher' => 'Teacher'])
                ->add_optgroup('Others', ['student' => 'Student']);
            $hook->form->add(new radios('role', 'Role', $options));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'role' => 'student', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('student', $form->get_data()->role);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('<div class="w-100 fw-bold mt-2">Teachers</div>', $html);
        $this->assertStringContainsString('<div class="w-100 fw-bold mt-2">Others</div>', $html);
        $this->assertStringContainsString('id="id_role' . $suffix . '_0" name="role" value="none"', $html);
        $this->assertStringContainsString('id="id_role' . $suffix . '_2" name="role" value="student" checked', $html);
        $this->assertSame(1, substr_count($html, 'fw-bold mt-2">Teachers'));
    }

    public function test_parsing(): void {
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_non_validated_data()->color);
        $form = new simple_form($this->get_url(), ['color' => 'green']);
        $this->assertSame('green', $form->get_non_validated_data()->color);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'color' => 'green', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('green', $form->get_data()->color);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'color' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());
        $this->assertNull($form->get_data()->color);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'color' => 'blue', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'color'));
        $this->assertNull($form->get_non_validated_data()->color);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'color' => ['green'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_data()->color);
    }

    public function test_int_keys(): void {
        $hook = function (muform_definition $hook): void {
            $level = (new radios('level', 'Level', [0 => 'Zero', 1 => 'One']))
                ->set_required(true);
            $hook->form->add($level);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'level' => '0', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());
        $this->assertSame('0', $form->get_data()->level);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'level' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'level'));
        $this->assertFalse($form->get_element('level')->has_required_value());

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['level' => 1]);
        $this->assertSame('1', $form->get_non_validated_data()->level);
    }

    public function test_render(): void {
        $hook = function (muform_definition $hook): void {
            $options = ['a' => 'A <i>x</i> & B', 'b' => 'B'];
            $level = (new radios('level', 'Level', $options, inline: true))
                ->set_required(true);
            $hook->form->add($level);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), ['level' => 'b']);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();

        $this->assertStringContainsString(
            '<span id="id_level' . $suffix . '_label" class="d-inline-block word-break">Level</span>',
            $html
        );
        $this->assertStringContainsString(
            '<div role="radiogroup" id="id_level' . $suffix . '" aria-labelledby="id_level' . $suffix . '_label"'
            . ' aria-describedby="id_error_level' . $suffix . '" class="w-100 d-flex flex-wrap column-gap-3">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="radio" class="form-check-input" id="id_level' . $suffix . '_0" name="level" value="a"'
            . ' required aria-required="true">',
            $html
        );
        $this->assertStringContainsString(
            '<label class="form-check-label" for="id_level' . $suffix . '_0">A &#60;i&#62;x&#60;/i&#62; &#38; B</label>',
            $html
        );
        $this->assertStringContainsString(
            '<input type="radio" class="form-check-input" id="id_level' . $suffix . '_1" name="level" value="b" checked'
            . ' required aria-required="true">',
            $html
        );
        $this->assertStringContainsString('<div class="form-check form-check-inline">', $html);
    }
}
