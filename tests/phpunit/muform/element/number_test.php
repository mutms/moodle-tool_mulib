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
use tool_mulib\muform\element\number;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Number element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\number
 * @covers \tool_mulib\muform\element
 */
final class number_test extends muform_testcase {
    /**
     * Add number element with given attributes via hook.
     *
     * @param array $attributes
     * @param bool $frozen
     * @param bool $required
     */
    private function add_element(array $attributes, bool $frozen = false, bool $required = false): void {
        $hook = function (muform_definition $hook) use ($attributes, $frozen, $required): void {
            $element = (new number('num', 'Number', $attributes))
                ->set_frozen($frozen)
                ->set_required($required);
            $hook->form->add($element);
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'num' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_attributes(): void {
        $element = new number('num', 'Number');
        $this->assertSame('num', $element->get_name());
        $this->assertTrue($element->returns_data());

        $element = new number('num', 'Number', ['min' => '1', 'max' => 10.5, 'decimals' => 1]);
        $element->set_attribute('xyz', 1);
        $this->assertDebuggingCalled('Invalid element attribute specified: xyz');

        $this->add_element(['min' => '1', 'max' => 10.5, 'decimals' => 1]);
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $this->assertStringContainsString('inputmode="decimal" pattern=', $html);
        $this->assertStringContainsString('data-muform-min="1" data-muform-max="10.5"', $html);
        $this->assertStringContainsString('pattern="([0-9]+([.,][0-9]{1,1}0*)?|[.,][0-9]{1,1}0*)"', $html);
        $this->assertStringNotContainsString('decimals=', $html);
        $this->assertStringNotContainsString('type="number"', $html);
    }

    public function test_invalid_name(): void {
        $this->expectException(coding_exception::class);
        new number('Num', 'Number');
    }

    public function test_get_value_not_attached(): void {
        $element = new number('num', 'Number');
        $this->expectException(coding_exception::class);
        $element->get_value();
    }

    public function test_modify_after_attach(): void {
        $this->add_element([]);
        $form = new simple_form($this->get_url(), []);
        $element = $form->get_element('num');
        $this->expectException(coding_exception::class);
        $element->set_attribute('min', 1);
    }

    public function test_freeze_after_attach(): void {
        $this->add_element([]);
        $form = new simple_form($this->get_url(), []);
        $element = $form->get_element('num');
        $this->expectException(coding_exception::class);
        $element->set_frozen(true);
    }

    public function test_value_precedence(): void {
        $this->add_element([]);
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_element('num')->get_value());

        $form = new simple_form($this->get_url(), ['num' => 3]);
        $this->assertSame(3, $form->get_element('num')->get_value());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'num' => '5', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['num' => 3]);
        $this->assertSame(5, $form->get_element('num')->get_value());
        $this->assertSame(5, $form->get_data()->num);

        $this->add_element([], true);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'num' => '5', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['num' => 3]);
        $this->assertSame(3, $form->get_element('num')->get_value());
        $this->assertSame(3, $form->get_data()->num);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('<div class="form-control-plaintext" id="id_num' . $suffix . '">3</div>', $html);
        $this->assertStringNotContainsString('id="id_num' . $suffix . '" name="num"', $html);
    }

    public function test_parsing(): void {
        $this->add_element(['min' => -2, 'max' => 10]);

        $form = $this->submit('');
        $this->assertTrue($form->is_valid());
        $this->assertNull($form->get_data()->num);

        $this->assertSame(-2, $this->submit('-2')->get_data()->num);
        $this->assertSame(10, $this->submit('10')->get_data()->num);
        $this->assertTrue($this->submit('2.5')->is_invalid());
        $this->assertTrue($this->submit('11')->is_invalid());
        $this->assertTrue($this->submit('-3')->is_invalid());

        $form = $this->submit('abc');
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'num'));
        $this->assertNull($form->get_non_validated_data()->num);

        $this->assertNull($this->submit(['1'])->get_data()->num);
    }

    public function test_decimals(): void {
        $this->add_element(['decimals' => 2]);
        $this->assertSame(2.5, $this->submit('2.5')->get_data()->num);
        $this->assertSame(2.5, $this->submit(' 2,5 ')->get_data()->num);
        $this->assertSame(2.5, $this->submit('2.5000')->get_data()->num);
        $this->assertSame(0.25, $this->submit('.25')->get_data()->num);
        $this->assertSame(-1.25, $this->submit('-1.25')->get_data()->num);
        $this->assertSame(2.0, $this->submit('2')->get_data()->num);
        $this->assertSame(10.0, $this->submit('10.00000')->get_data()->num);
        foreach (['2.555', '1,000.5', '1.2.3', '1e3', '2,5,1', 'x'] as $invalid) {
            $this->assertNull($this->submit($invalid)->get_data(), $invalid);
        }
        // Integers do not accept decimal separators.
        $this->add_element([]);
        $this->assertNull($this->submit('2,5')->get_data());
        $this->assertNull($this->submit('2.5')->get_data());
        $this->assertSame(2, $this->submit('2.0')->get_data()->num);
    }

    public function test_limits_match_decimals(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new number('num', 'Number', ['min' => 0.005, 'decimals' => 2]));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Number element min must have at most 2 decimal places: num');
        new simple_form($this->get_url(), []);
    }

    public function test_required(): void {
        $this->add_element([], false, true);
        $form = $this->submit('');
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'num'));

        $form = $this->submit('0');
        $this->assertTrue($form->is_valid());
        $this->assertSame(0, $form->get_data()->num);
    }

    public function test_hints(): void {
        $hook = function (muform_definition $hook): void {
            $element = (new number('num', 'Number', ['max' => 5]))
                ->set_required(true)
                ->set_required_hint('Give me a number')
                ->set_invalid_hint('Bad <b>number</b> & "x"');
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $form = $this->submit('');
        $this->assertSame(['Give me a number'], $this->get_rendered_errors($form, 'num'));

        $form = $this->submit('6');
        $html = $this->render($form);
        $this->assertStringContainsString(
            'data-muform-required-hint="Give me a number"'
            . ' data-muform-invalid-hint="Bad &#60;b&#62;number&#60;/b&#62; &#38; &#34;x&#34;">',
            $html
        );
        $this->assertStringContainsString('<div>Bad &#60;b&#62;number&#60;/b&#62; &#38; &#34;x&#34;</div>', $html);
    }

    public function test_render(): void {
        $hook = function (muform_definition $hook): void {
            $attributes = ['min' => 1, 'max' => 5, 'decimals' => 1, 'placeholder' => 'Hint'];
            $element = (new number('num', 'Number <b>x</b> & <i>', $attributes))
                ->set_required(true);
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), ['num' => 3]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();

        $this->assertStringContainsString(
            '<label id="id_num' . $suffix . '_label" class="d-inline word-break" for="id_num' . $suffix . '">'
            . 'Number &#60;b&#62;x&#60;/b&#62; &#38; &#60;i&#62;</label>',
            $html
        );
        $this->assertStringContainsString(
            '<input type="text" inputmode="decimal" pattern="([0-9]+([.,][0-9]{1,1}0*)?|[.,][0-9]{1,1}0*)" autocomplete="off"'
            . ' class="form-control" id="id_num' . $suffix . '"'
            . ' name="num" value="3" aria-describedby="id_error_num' . $suffix . '" data-muform-min="1" data-muform-max="5"'
            . ' required aria-required="true" placeholder="Hint">',
            $html
        );
        $this->assertStringContainsString('title="' . get_string('required') . '"', $html);
    }

    public function test_required_marker(): void {
        $hook = function (muform_definition $hook): void {
            $element = (new number('num', 'Number'))
                ->set_required_marker(true);
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = $this->submit('');
        $this->assertTrue($form->is_valid());
        $this->assertNull($form->get_data()->num);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('title="' . get_string('required') . '"', $html);
        $this->assertStringContainsString(
            '<input type="text" inputmode="numeric" pattern="-?[0-9]+" autocomplete="off" class="form-control"'
            . ' id="id_num' . $suffix . '" name="num" value=""'
            . ' aria-describedby="id_error_num' . $suffix . '" aria-required="true">',
            $html
        );
    }

    public function test_invalid_text_is_shown_again(): void {
        $this->add_element(['min' => 0]);
        $form = $this->submit(' 1,5 ');
        $this->assertFalse($form->is_valid());
        $this->assertNull($form->get_element('num')->get_value());
        $html = $this->render($form);
        $this->assertStringContainsString('name="num" value="1,5"', $html);
        $this->assertStringContainsString('pattern="[0-9]+"', $html);
        $this->assertStringContainsString('data-muform-min="0"', $html);

        $form = $this->submit(' 7 ');
        $this->assertTrue($form->is_valid());
        $this->assertSame(7, $form->get_data()->num);
    }

    public function test_has_required_value(): void {
        $this->add_element([]);
        $this->assertFalse($this->submit('')->get_element('num')->has_required_value());
        $this->assertTrue($this->submit('0')->get_element('num')->has_required_value());
        $this->assertFalse($this->submit('abc')->get_element('num')->has_required_value());
        $this->expectException(coding_exception::class);
        (new number('num', 'Number'))->has_required_value();
    }
}
