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
use tool_mulib\muform\element\radiocards;
use tool_mulib\muform\util\options;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Radio cards element tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\radiocards
 * @covers \tool_mulib\muform\util\cards
 */
final class radiocards_test extends muform_testcase {
    /**
     * Add the tested element via hook.
     *
     * @param bool $required
     */
    private function add_element(bool $required = false): void {
        $hook = function (muform_definition $hook) use ($required): void {
            $type = (new radiocards(
                'type',
                'Type',
                ['standard' => 'Standard', 'curated' => 'Curated', 0 => 'Zero'],
                ['curated' => 'Membership managed by <b>people</b>']
            ))->set_required($required);
            $hook->form->add($type);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    public function test_options(): void {
        $element = new radiocards('rr', 'R', [1 => 'One', '2' => 'Two'], [2 => 'Second']);
        $this->assertSame([1 => 'One', 2 => 'Two'], $element->get_options()->get_labels());
        $options = new options([1 => 'One']);
        $element = new radiocards('rr', 'R', $options);
        $this->assertSame($options, $element->get_options());

        try {
            new radiocards('rr', 'R', ['a' => 'A'], ['b' => 'B']);
            $this->fail('Exception expected');
        } catch (coding_exception $ex) {
            $this->assertStringContainsString('Description of unknown option: b', $ex->getMessage());
        }
        try {
            new radiocards('rr', 'R', ['a' => 'A'], ['a' => 1]);
            $this->fail('Exception expected');
        } catch (coding_exception $ex) {
            $this->assertStringContainsString('Option descriptions must be strings: a', $ex->getMessage());
        }
        $this->expectException(coding_exception::class);
        new radiocards('rr', 'R', ['a' => 1]);
    }

    public function test_parsing(): void {
        $this->add_element();

        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_non_validated_data()->type);
        $form = new simple_form($this->get_url(), ['type' => 'curated']);
        $this->assertSame('curated', $form->get_non_validated_data()->type);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => 'standard', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('standard', $form->get_data()->type);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => '0', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('0', $form->get_data()->type);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_data()->type);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => 'rules', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'type'));

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => ['curated'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_data()->type);
    }

    public function test_required(): void {
        $this->add_element(true);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'type'));

        $this->simulate_post(simple_form::class, ['name' => 'x', 'type' => 'curated', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('curated', $form->get_data()->type);
    }

    public function test_render(): void {
        $this->add_element(true);
        $form = new simple_form($this->get_url(), ['type' => 'curated']);
        $html = $this->render($form);
        $s = $form->get_idsuffix();

        $this->assertStringContainsString(
            '<div role="radiogroup" id="id_type' . $s . '" aria-labelledby="id_type' . $s . '_label"'
            . ' aria-describedby="id_error_type' . $s . '" class="w-100 muform-cards">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="radio" class="form-check-input" id="id_type' . $s . '_0" name="type" value="standard"'
            . ' required aria-required="true">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="radio" class="form-check-input" id="id_type' . $s . '_1" name="type" value="curated"'
            . ' aria-describedby="id_type' . $s . '_1_description" checked required aria-required="true">',
            $html
        );
        $this->assertStringContainsString('<label class="form-check-label" for="id_type' . $s . '_1">Curated</label>', $html);
        $this->assertStringContainsString(
            '<div class="form-text mt-0" id="id_type' . $s . '_1_description">'
            . 'Membership managed by &#60;b&#62;people&#60;/b&#62;</div>',
            $html
        );
        $this->assertStringNotContainsString('id="id_type' . $s . '_0_description"', $html);
        $this->assertSame(3, substr_count($html, '<div class="form-check muform-card">'));
    }
}
