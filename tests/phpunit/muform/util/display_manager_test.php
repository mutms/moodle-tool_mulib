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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../muform_testcase.php');

use core\exception\coding_exception;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\util\display_manager;
use tool_mulib\muform\element\text;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Display manager tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\util\display_manager
 */
final class display_manager_test extends muform_testcase {
    /**
     * Operator test data.
     *
     * @return array
     */
    public static function matches_provider(): array {
        return [
            ['eq', 'a', 'a', true],
            ['eq', 'a', 'b', false],
            ['eq', 1, '1', true],
            ['eq', true, '1', true],
            ['eq', false, 0, true],
            ['eq', null, '', false],
            ['eq', ['a', 'b'], 'b', true],
            ['eq', ['a', 'b'], 'c', false],
            ['neq', 'a', 'b', true],
            ['neq', 'a', 'a', false],
            ['in', 'a', ['a', 'b'], true],
            ['in', 'c', ['a', 'b'], false],
            ['in', 2, [1, 2], true],
            ['in', ['x', 'b'], ['a', 'b'], true],
            ['in', ['x'], ['a', 'b'], false],
            ['notin', 'c', ['a', 'b'], true],
            ['notin', 'a', ['a', 'b'], false],
            ['checked', 1, null, true],
            ['checked', '1', null, true],
            ['checked', true, null, true],
            ['checked', 0, null, false],
            ['checked', 'yes', null, false],
            ['notchecked', 0, null, true],
            ['notchecked', 1, null, false],
            ['empty', null, null, true],
            ['empty', '', null, true],
            ['empty', [], null, true],
            ['empty', '0', null, false],
            ['empty', 0, null, false],
            ['notempty', 'a', null, true],
            ['notempty', '', null, false],
        ];
    }

    /**
     * Test operators.
     *
     * @dataProvider matches_provider
     * @param string $op
     * @param mixed $depvalue
     * @param mixed $value
     * @param bool $expected
     */
    public function test_matches(string $op, mixed $depvalue, mixed $value, bool $expected): void {
        $this->assertSame($expected, display_manager::matches($op, $depvalue, $value));
    }

    public function test_invalid_operator(): void {
        $this->expectException(coding_exception::class);
        display_manager::matches('xyz', 'a', 'a');
    }

    public function test_rules(): void {
        $form = new simple_form($this->get_url(), []);
        $dm = $form->get_display_manager();
        $expected = [
            ['action' => 'hide', 'target' => 'notes', 'dep' => 'enabled', 'op' => 'notchecked', 'value' => null],
            ['action' => 'disable', 'target' => 'count', 'dep' => 'color', 'op' => 'eq', 'value' => 'red'],
        ];
        $this->assertSame($expected, $dm->get_rules());
        $this->assertSame(json_encode($expected), $dm->get_rules_json());
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-rules="' . s(json_encode($expected)) . '"', $html);
    }

    public function test_rule_validation(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->get_display_manager()->hide_if('name', 'count', 'xyz');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Invalid display rule operator: xyz');
        new simple_form($this->get_url(), []);
    }

    public function test_rule_self_dependency(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->get_display_manager()->hide_if('name', 'name', 'empty');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        new simple_form($this->get_url(), []);
    }

    public function test_rule_in_requires_array(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->get_display_manager()->hide_if('name', 'color', 'in', 'red');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        new simple_form($this->get_url(), []);
    }

    public function test_rule_unknown_element(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->get_display_manager()->hide_if('xyz', 'color', 'eq', 'red');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), []);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Unknown element in display rule');
        $this->render($form);
    }

    public function test_is_hidden(): void {
        $form = new simple_form($this->get_url(), ['enabled' => 0]);
        $dm = $form->get_display_manager();
        $this->assertTrue($dm->is_hidden('notes'));
        $this->assertFalse($dm->is_hidden('name'));
        $form = new simple_form($this->get_url(), ['enabled' => 1]);
        $this->assertFalse($form->get_display_manager()->is_hidden('notes'));
        $this->expectException(coding_exception::class);
        $form->get_display_manager()->is_hidden('xyz');
    }

    public function test_rule_after_finalised(): void {
        $form = new simple_form($this->get_url(), []);
        $this->expectException(coding_exception::class);
        $form->get_display_manager()->hide_if('name', 'color', 'eq', 'red');
    }

    public function test_apply_flags_before_finalised(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->get_display_manager()->apply_flags();
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        new simple_form($this->get_url(), []);
    }

    public function test_hidden_flag(): void {
        $form = new simple_form($this->get_url(), ['enabled' => 0]);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-name="notes" hidden data-muform-hidden="1">', $html);
        $this->assertStringContainsString('data-muform-name="name">', $html);

        $form = new simple_form($this->get_url(), ['enabled' => 1]);
        $html = $this->render($form);
        $this->assertStringNotContainsString('data-muform-hidden="1"', $html);

        // Flags do not affect submitted values or validation.
        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => '0', 'notes' => 'still here', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('still here', $form->get_data()->notes);
        $html = $this->render($form);
        $this->assertStringContainsString('hidden data-muform-hidden="1"', $html);
    }

    public function test_disabled_flag(): void {
        $form = new simple_form($this->get_url(), ['color' => 'red', 'count' => 4]);
        $html = $this->render($form);
        $this->assertStringContainsString('name="count" value="4" aria-describedby="id_error_count'
            . $form->get_idsuffix() . '" data-muform-min="0" data-muform-max="10" disabled', $html);

        // Flags do not affect submitted values or validation.
        $this->simulate_post(simple_form::class, ['name' => 'x', 'color' => 'red', 'count' => '99', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['count' => 4]);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'count'));

        // Browser does not submit disabled elements, so current data is used.
        $this->simulate_post(simple_form::class, ['name' => 'x', 'color' => 'red', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['count' => 4]);
        $this->assertTrue($form->is_valid());
        $this->assertSame(4, $form->get_data()->count);
    }

    public function test_cascade(): void {
        $hook = function (muform_definition $hook): void {
            $form = $hook->form;
            $form->add(new text('other', 'Other'));
            $form->get_display_manager()->hide_if('general', 'other', 'eq', 'hide');
            $form->get_display_manager()->disable_if('general', 'other', 'eq', 'disable');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $form = new simple_form($this->get_url(), ['other' => 'hide']);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-name="general" hidden data-muform-hidden="1">', $html);
        $this->assertStringContainsString('data-muform-name="name" hidden data-muform-hidden="1">', $html);
        $this->assertStringContainsString('data-muform-name="buttons">', $html);
        $this->assertStringNotContainsString('data-muform-disabled="1"', $html);

        $form = new simple_form($this->get_url(), ['other' => 'disable']);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-name="general" data-muform-disabled="1">', $html);
        $this->assertStringContainsString('data-muform-name="name" data-muform-disabled="1">', $html);
        $this->assertStringContainsString('name="name" value="" aria-describedby="id_error_name'
            . $form->get_idsuffix() . '" required aria-required="true" disabled', $html);
        $this->assertStringNotContainsString('data-muform-name="general" hidden', $html);
    }
}
