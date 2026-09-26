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
use tool_mulib\muform\element\multiselect;
use tool_mulib\muform\util\options;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Multiselect element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\multiselect
 */
final class multiselect_test extends muform_testcase {
    /**
     * Add multiselect element via hook.
     *
     * @param bool $required
     * @param bool $frozen
     */
    private function add_element(bool $required = false, bool $frozen = false): void {
        $hook = function (muform_definition $hook) use ($required, $frozen): void {
            $options = (new options(['manager' => 'Manager']))
                ->add_optgroup('Teachers', ['teacher' => 'Teacher', 'editing' => 'Editing teacher']);
            $roles = (new multiselect('roles', 'Roles', $options, ['size' => 5]))
                ->set_required($required)
                ->set_frozen($frozen);
            $hook->form->add($roles);
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'roles' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_parsing(): void {
        $this->add_element();
        $this->assertSame([], $this->submit([''])->get_data()->roles);
        $this->assertSame(['manager', 'editing'], $this->submit(['', 'editing', 'manager'])->get_data()->roles);
        $form = $this->submit(['', 'xyz']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'roles'));

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['roles' => 'teacher,manager']);
        $this->assertSame(['manager', 'teacher'], $form->get_non_validated_data()->roles);
    }

    public function test_required(): void {
        $this->add_element(true);
        $form = $this->submit(['']);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'roles'));
        $this->assertTrue($this->submit(['', 'teacher'])->is_valid());
    }

    public function test_render(): void {
        $this->add_element(true);
        $form = new simple_form($this->get_url(), ['roles' => ['teacher', 'manager']]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('<input type="hidden" name="roles[]" value="">', $html);
        $this->assertStringContainsString(
            '<select multiple class="form-select" id="id_roles' . $suffix . '" name="roles[]"'
            . ' aria-describedby="id_error_roles' . $suffix . '" required aria-required="true" size="5">',
            $html
        );
        $this->assertStringContainsString('<option value="manager" selected>Manager</option>', $html);
        $this->assertStringContainsString('<optgroup label="Teachers">', $html);
        $this->assertStringContainsString('<option value="teacher" selected>Teacher</option>', $html);
        $this->assertStringContainsString('<option value="editing">Editing teacher</option>', $html);

        $this->add_element(false, true);
        $form = new simple_form($this->get_url(), ['roles' => ['teacher', 'manager']]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_roles' . $suffix . '">Manager, Teacher</div>',
            $html
        );
        $this->assertStringNotContainsString('<select', $html);
        $this->assertStringNotContainsString('name="roles[]"', $html);
    }
}
