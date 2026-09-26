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
use tool_mulib\muform\element\checkboxes;
use tool_mulib\muform\util\options;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Checkboxes element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\checkboxes
 */
final class checkboxes_test extends muform_testcase {
    /**
     * Add checkboxes element via hook.
     *
     * @param bool $required
     * @param bool $inline
     */
    private function add_element(bool $required = false, bool $inline = false): void {
        $hook = function (muform_definition $hook) use ($required, $inline): void {
            $roles = (new checkboxes('roles', 'Roles', [1 => 'Manager', 2 => 'Teacher', 'x' => 'Other'], $inline))
                ->set_required($required);
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

    public function test_options(): void {
        $element = new checkboxes('rr', 'R', [1 => 'One', '2' => 'Two']);
        $this->assertSame([1 => 'One', 2 => 'Two'], $element->get_options()->get_labels());
        $this->expectException(coding_exception::class);
        new checkboxes('rr', 'R', ['a' => 1]);
    }

    public function test_optgroups(): void {
        $hook = function (muform_definition $hook): void {
            $options = (new options())
                ->add_optgroup('Teachers', ['teacher' => 'Teacher', 'editing' => 'Editing teacher'])
                ->add_optgroup('Others', ['student' => 'Student']);
            $hook->form->add(new checkboxes('role', 'Role', $options));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'role' => ['', 'student', 'teacher'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(['teacher', 'student'], $form->get_data()->role);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('<div class="w-100 fw-bold mt-2">Teachers</div>', $html);
        $this->assertStringContainsString('id="id_role' . $suffix . '_0" name="role[]" value="teacher" checked', $html);
        $this->assertStringContainsString('id="id_role' . $suffix . '_1" name="role[]" value="editing"', $html);
        $this->assertStringContainsString('id="id_role' . $suffix . '_2" name="role[]" value="student" checked', $html);
    }

    public function test_parsing(): void {
        $this->add_element();

        $form = $this->submit(['']);
        $this->assertTrue($form->is_valid());
        $this->assertSame([], $form->get_data()->roles);

        $this->assertSame(['2'], $this->submit(['', '2'])->get_data()->roles);
        $this->assertSame(['1', 'x'], $this->submit(['x', '1'])->get_data()->roles);
        $this->assertSame(['1'], $this->submit(['1', '1'])->get_data()->roles);
        $this->assertSame(['x'], $this->submit('x')->get_data()->roles);

        $form = $this->submit(['1', 'y']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'roles'));
        $this->assertSame([], $form->get_non_validated_data()->roles);

        $this->assertTrue($this->submit([['1']])->is_invalid());
        $this->assertTrue($this->submit(true)->is_invalid());

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['roles' => '2, x']);
        $this->assertSame(['2', 'x'], $form->get_non_validated_data()->roles);
        $form = new simple_form($this->get_url(), ['roles' => [2]]);
        $this->assertSame(['2'], $form->get_non_validated_data()->roles);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([], $form->get_non_validated_data()->roles);
    }

    public function test_required(): void {
        $this->add_element(true);
        $form = $this->submit(['']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'roles'));
        $this->assertFalse($form->get_element('roles')->has_required_value());

        $form = $this->submit(['', '1']);
        $this->assertTrue($form->is_valid());
        $this->assertTrue($form->get_element('roles')->has_required_value());
    }

    public function test_display_rules(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new checkboxes('roles', 'Roles', [1 => 'Manager', 2 => 'Teacher']));
            $dm = $hook->form->get_display_manager();
            $dm->hide_if('notes', 'roles', 'in', [2]);
            $dm->disable_if('count', 'roles', 'empty');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $form = new simple_form($this->get_url(), ['roles' => ['2'], 'enabled' => 1]);
        $dm = $form->get_display_manager();
        $this->assertTrue($dm->is_hidden('notes'));
        $html = $this->render($form);
        $this->assertStringNotContainsString(
            'name="count" value="" aria-describedby="id_error_count' . $form->get_idsuffix() . '"'
                . ' data-muform-min="0" data-muform-max="10" disabled',
            $html
        );

        $form = new simple_form($this->get_url(), ['roles' => [], 'enabled' => 1]);
        $this->assertFalse($form->get_display_manager()->is_hidden('notes'));
        $html = $this->render($form);
        $this->assertStringContainsString(
            'name="count" value="" aria-describedby="id_error_count' . $form->get_idsuffix() . '"'
                . ' data-muform-min="0" data-muform-max="10" disabled',
            $html
        );
    }

    public function test_render(): void {
        $this->add_element(true, true);
        $form = new simple_form($this->get_url(), ['roles' => ['x']]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();

        $this->assertStringContainsString('<input type="hidden" name="roles[]" value="">', $html);
        $this->assertStringContainsString(
            '<div role="group" id="id_roles' . $suffix . '" aria-labelledby="id_roles' . $suffix . '_label"'
            . ' aria-describedby="id_error_roles' . $suffix . '" data-muform-required="1"'
            . ' class="w-100 d-flex flex-wrap column-gap-3">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="checkbox" class="form-check-input" id="id_roles' . $suffix . '_0" name="roles[]" value="1"'
            . ' aria-required="true">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="checkbox" class="form-check-input" id="id_roles' . $suffix . '_2" name="roles[]" value="x" checked'
            . ' aria-required="true">',
            $html
        );
        $this->assertStringContainsString('<label class="form-check-label" for="id_roles' . $suffix . '_2">Other</label>', $html);
        $this->assertStringNotContainsString('name="roles[]" value="1" required', $html);

        $hook = function (muform_definition $hook): void {
            $roles = (new checkboxes('roles', 'Roles', [1 => 'Manager']))
                ->set_frozen(true);
            $hook->form->add($roles);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), ['roles' => ['1']]);
        $html = $this->render($form);
        $this->assertStringNotContainsString('<input type="hidden" name="roles[]"', $html);
        $this->assertStringContainsString('name="roles[]" value="1" checked disabled>', $html);
    }
}
