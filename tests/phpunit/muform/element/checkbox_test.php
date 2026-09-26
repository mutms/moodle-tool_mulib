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
use tool_mulib\muform\element\checkbox;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Checkbox element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\checkbox
 */
final class checkbox_test extends muform_testcase {
    public function test_parsing(): void {
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(0, $form->get_non_validated_data()->enabled);
        $form = new simple_form($this->get_url(), ['enabled' => true]);
        $this->assertSame(1, $form->get_non_validated_data()->enabled);
        $form = new simple_form($this->get_url(), ['enabled' => '0']);
        $this->assertSame(0, $form->get_non_validated_data()->enabled);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => '1', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(1, $form->get_data()->enabled);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => '0', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['enabled' => 1]);
        $this->assertSame(0, $form->get_data()->enabled);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => ['1'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(0, $form->get_data()->enabled);
    }

    public function test_required(): void {
        $hook = function (muform_definition $hook): void {
            $agree = (new checkbox('agree', 'Terms', 'I agree'))
                ->set_required(true);
            $hook->form->add($agree);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'agree' => '0', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'agree'));

        $this->assertFalse($form->get_element('agree')->has_required_value());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'agree' => '1', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());
        $this->assertTrue($form->get_element('agree')->has_required_value());
    }

    public function test_render(): void {
        $form = new simple_form($this->get_url(), ['enabled' => 1]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('<input type="hidden" name="enabled" value="0">', $html);
        $this->assertStringContainsString(
            '<input type="checkbox" class="form-check-input" id="id_enabled' . $suffix . '" name="enabled" value="1" checked'
            . ' aria-describedby="id_error_enabled' . $suffix . '">',
            $html
        );
        $this->assertStringContainsString('<label class="form-check-label" for="id_enabled' . $suffix . '">Yes</label>', $html);

        $hook = function (muform_definition $hook): void {
            $agree = (new checkbox('agree', 'Terms'))
                ->set_frozen(true);
            $hook->form->add($agree);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), ['agree' => 1]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringNotContainsString('<input type="hidden" name="agree"', $html);
        $this->assertStringContainsString(
            'name="agree" value="1" checked aria-describedby="id_error_agree' . $suffix . '" disabled>',
            $html
        );
    }
}
