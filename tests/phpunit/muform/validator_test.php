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

namespace tool_mulib\phpunit\muform;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/muform_testcase.php');

use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element;
use tool_mulib\muform\validator;
use tool_mulib\muform\validator\required_if_visible;
use tool_mulib\phpunit\muform\fixtures\simple_form;

/**
 * Validator tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\validator
 * @covers \tool_mulib\muform\validator\required_if_visible
 * @covers \tool_mulib\muform\element::add_validator
 * @covers \tool_mulib\muform\element::validate
 */
final class validator_test extends muform_testcase {
    public function test_validators(): void {
        $classvalidator = new class extends validator {
            #[\Override]
            public function validate(element $element, array &$allerrors): void {
                if ($element->get_value() === 'class') {
                    $allerrors[$element->get_name()][] = 'Class error';
                    $allerrors['count'][] = 'Count error';
                }
            }
        };
        $callable = function (element $element, array &$allerrors): void {
            if ($element->get_value() === 'callable') {
                $allerrors[$element->get_name()][] = 'Callable error';
            }
        };
        $hook = function (muform_definition $hook) use ($classvalidator, $callable): void {
            $name = $hook->form->get_element('name');
            $name
                ->add_validator($classvalidator)
                ->add_validator($callable);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'class', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Class error'], $this->get_rendered_errors($form, 'name'));
        $this->assertSame(['Count error'], $this->get_rendered_errors($form, 'count'));

        $this->simulate_post(simple_form::class, ['name' => 'callable', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Callable error'], $this->get_rendered_errors($form, 'name'));
        $this->assertSame([], $this->get_rendered_errors($form, 'count'));

        $this->simulate_post(simple_form::class, ['name' => 'ok', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());

        // Parse errors come first, validators still run.
        $this->simulate_post(simple_form::class, ['name' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'name'));
    }

    public function test_required_if_visible(): void {
        $hook = function (muform_definition $hook): void {
            // Notes are hidden when not enabled.
            $hook->form->get_element('notes')
                ->set_required_marker(true)
                ->add_validator(new required_if_visible());
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => '0', 'notes' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => '1', 'notes' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'notes'));
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('aria-describedby="id_error_notes' . $suffix . '" aria-required="true"', $html);
        $this->assertStringNotContainsString('aria-describedby="id_error_notes' . $suffix . '" required', $html);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'enabled' => '1', 'notes' => 'ok', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());
    }

    public function test_validators_not_run_when_not_submitted(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->get_element('name')->add_validator(function (element $element, array &$allerrors): void {
                $allerrors['name'][] = 'Always';
            });
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $form = new simple_form($this->get_url(), []);
        $this->assertSame([], $this->get_rendered_errors($form, 'name'));

        $this->simulate_post(simple_form::class, ['name' => '', 'cancel' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([], $this->get_rendered_errors($form, 'name'));
    }
}
