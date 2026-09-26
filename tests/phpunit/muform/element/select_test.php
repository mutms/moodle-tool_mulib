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

use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\select;
use tool_mulib\muform\util\options;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Select element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\select
 */
final class select_test extends muform_testcase {
    /**
     * Add select element via hook.
     *
     * @param bool $required
     * @param bool $frozen
     */
    private function add_element(bool $required = false, bool $frozen = false): void {
        $hook = function (muform_definition $hook) use ($required, $frozen): void {
            $options = (new options(['' => 'Choose...']))
                ->add_optgroup('Teachers', ['teacher' => 'Teacher', 'editing' => 'Editing teacher'])
                ->add_optgroup('Others', ['student' => 'Student']);
            $role = (new select('role', 'Role', $options, ['size' => 3]))
                ->set_required($required)
                ->set_frozen($frozen);
            $hook->form->add($role);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit value and return form.
     *
     * @param mixed $value
     * @return simple_form
     */
    private function submit(mixed $value): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'role' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_parsing(): void {
        $this->add_element();
        $this->assertSame('student', $this->submit('student')->get_data()->role);
        $form = $this->submit('');
        $this->assertTrue($form->is_valid());
        $this->assertNull($form->get_data()->role);
        $form = $this->submit('xyz');
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'role'));
        $this->assertNull($this->submit(['student'])->get_data()->role);

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['role' => 'teacher']);
        $this->assertSame('teacher', $form->get_non_validated_data()->role);
    }

    public function test_required(): void {
        $this->add_element(true);
        $form = $this->submit('');
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'role'));
        $this->assertTrue($this->submit('teacher')->is_valid());
    }

    public function test_render(): void {
        $this->add_element(true);
        $form = new simple_form($this->get_url(), ['role' => 'editing']);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<select class="form-select" id="id_role' . $suffix . '" name="role" aria-describedby="id_error_role' . $suffix . '"'
            . ' required aria-required="true" size="3">',
            $html
        );
        $this->assertStringContainsString('<option value="">Choose...</option>', $html);
        $this->assertStringContainsString('<optgroup label="Teachers">', $html);
        $this->assertStringContainsString('<option value="editing" selected>Editing teacher</option>', $html);
        $this->assertStringContainsString('</optgroup>', $html);
        $this->assertStringContainsString(
            '<label id="id_role' . $suffix . '_label" class="d-inline word-break" for="id_role' . $suffix . '">',
            $html
        );

        $this->add_element(false, true);
        $form = new simple_form($this->get_url(), ['role' => 'editing']);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_role' . $suffix . '">Editing teacher</div>',
            $html
        );
        $this->assertStringNotContainsString('<select', $html);
    }
}
