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
use tool_mulib\muform\element\yesno;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Yes or No element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\yesno
 */
final class yesno_test extends muform_testcase {
    /**
     * Add yesno element via hook.
     *
     * @param bool $frozen
     */
    private function add_element(bool $frozen = false): void {
        $hook = function (muform_definition $hook) use ($frozen): void {
            $active = new yesno('active', 'Active');
            $active->set_frozen($frozen);
            $hook->form->add($active);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    public function test_parsing(): void {
        $this->add_element();
        foreach ([[null, 0], ['', 0], [false, 0], [0, 0], ['0', 0], [true, 1], [1, 1], ['1', 1]] as [$current, $expected]) {
            $form = new simple_form($this->get_url(), ['active' => $current]);
            $this->assertSame($expected, $form->get_non_validated_data()->active);
        }
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(0, $form->get_non_validated_data()->active);
        $this->assertTrue($form->get_element('active')->has_required_value());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'active' => '1', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(1, $form->get_data()->active);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'active' => '0', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['active' => 1]);
        $this->assertSame(0, $form->get_data()->active);

        foreach (['2', 'yes', ['1']] as $invalid) {
            $this->simulate_post(simple_form::class, ['name' => 'x', 'active' => $invalid, 'submit' => '1']);
            $form = new simple_form($this->get_url(), []);
            $this->assertNull($form->get_data());
            $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'active'));
        }
    }

    public function test_render(): void {
        $this->add_element();
        $form = new simple_form($this->get_url(), ['active' => 1]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<input type="radio" class="form-check-input" id="id_active' . $suffix . '_yes" name="active" value="1" checked>',
            $html
        );
        $this->assertStringContainsString(
            '<input type="radio" class="form-check-input" id="id_active' . $suffix . '_no" name="active" value="0">',
            $html
        );
        $this->assertStringContainsString('<label class="form-check-label" for="id_active' . $suffix . '_no">No</label>', $html);
    }

    public function test_frozen(): void {
        $this->add_element(true);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'active' => '0', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['active' => 1]);
        $this->assertSame(1, $form->get_data()->active);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringNotContainsString('name="active" value=', $html);
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_active' . $suffix . '" data-muform-yesno-value="1">Yes</div>',
            $html
        );
    }
}
