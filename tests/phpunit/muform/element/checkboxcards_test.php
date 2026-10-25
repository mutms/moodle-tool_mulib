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
use tool_mulib\muform\element\checkboxcards;
use tool_mulib\muform\util\options;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Check box cards element tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\checkboxcards
 * @covers \tool_mulib\muform\util\cards
 */
final class checkboxcards_test extends muform_testcase {
    /**
     * Add the tested element via hook.
     *
     * @param bool $required
     */
    private function add_element(bool $required = false): void {
        $hook = function (muform_definition $hook) use ($required): void {
            $options = (new checkboxcards(
                'options',
                'Options',
                ['addonly' => 'Add only', 'forced' => 'Seed forced', 0 => 'Zero'],
                ['addonly' => 'Members are never removed']
            ))->set_required($required);
            $hook->form->add($options);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    public function test_options(): void {
        $element = new checkboxcards('cc', 'C', [1 => 'One', '2' => 'Two'], [1 => 'First']);
        $this->assertSame([1 => 'One', 2 => 'Two'], $element->get_options()->get_labels());
        $options = new options([1 => 'One']);
        $element = new checkboxcards('cc', 'C', $options);
        $this->assertSame($options, $element->get_options());
        $this->expectException(coding_exception::class);
        new checkboxcards('cc', 'C', ['a' => 'A'], ['b' => 'B']);
    }

    public function test_parsing(): void {
        $this->add_element();

        $form = new simple_form($this->get_url(), []);
        $this->assertSame([], $form->get_non_validated_data()->options);
        $form = new simple_form($this->get_url(), ['options' => 'forced,addonly']);
        $this->assertSame(['addonly', 'forced'], $form->get_non_validated_data()->options);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'options' => ['', 'forced', '0'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(['forced', '0'], $form->get_data()->options);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'options' => [''], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([], $form->get_data()->options);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'options' => ['', 'other'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'options'));
    }

    public function test_required(): void {
        $this->add_element(true);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'options' => [''], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'options'));

        $this->simulate_post(simple_form::class, ['name' => 'x', 'options' => ['', 'addonly'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(['addonly'], $form->get_data()->options);
    }

    public function test_render(): void {
        $this->add_element(true);
        $form = new simple_form($this->get_url(), ['options' => ['addonly']]);
        $html = $this->render($form);
        $s = $form->get_idsuffix();

        $this->assertStringContainsString('<input type="hidden" name="options[]" value="">', $html);
        $this->assertStringContainsString(
            '<div role="group" id="id_options' . $s . '" aria-labelledby="id_options' . $s . '_label"'
            . ' aria-describedby="id_error_options' . $s . '" data-muform-required="1" class="w-100 muform-cards">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="checkbox" class="form-check-input" id="id_options' . $s . '_0" name="options[]" value="addonly"'
            . ' aria-describedby="id_options' . $s . '_0_description" checked aria-required="true">',
            $html
        );
        $this->assertStringContainsString(
            '<div class="form-text mt-0" id="id_options' . $s . '_0_description">Members are never removed</div>',
            $html
        );
        $this->assertStringContainsString(
            '<input type="checkbox" class="form-check-input" id="id_options' . $s . '_1" name="options[]" value="forced"'
            . ' aria-required="true">',
            $html
        );
        $this->assertSame(3, substr_count($html, '<div class="form-check muform-card">'));
    }
}
