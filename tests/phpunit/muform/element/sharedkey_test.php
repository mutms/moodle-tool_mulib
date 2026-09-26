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
use tool_mulib\muform\element\sharedkey;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Shared key element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\sharedkey
 */
final class sharedkey_test extends muform_testcase {
    /**
     * Add sharedkey element via hook.
     *
     * @param bool $allowclear
     * @param bool $required
     * @param array $attributes
     * @param bool $frozen
     */
    private function add_element(
        bool $allowclear = false,
        bool $required = false,
        array $attributes = [],
        bool $frozen = false,
    ): void {
        $hook = function (muform_definition $hook) use ($allowclear, $required, $attributes, $frozen): void {
            $element = (new sharedkey('enrolkey', 'Enrolment key', $attributes, $allowclear))
                ->set_required($required)
                ->set_frozen($frozen);
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Rendered input tag of the element.
     *
     * @param simple_form $form
     * @return string
     */
    private function get_input(simple_form $form): string {
        preg_match('~<input type="text" class="form-control muform-sharedkey-masked[^>]*>~', $this->render($form), $matches);
        return $matches[0] ?? '';
    }

    /**
     * Submit value and return form.
     *
     * @param mixed $value posted enrolkey value
     * @param array $current current data
     * @return simple_form
     */
    private function submit(mixed $value, array $current = []): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'enrolkey' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), $current);
    }

    public function test_parsing(): void {
        $this->add_element(true);
        $this->assertSame('new', $this->submit(['value' => 'new'])->get_data()->enrolkey);
        $this->assertSame(' spaced <b>raw</b> ', $this->submit(['value' => ' spaced <b>raw</b> '])->get_data()->enrolkey);
        $this->assertNull($this->submit(['value' => ''])->get_data()->enrolkey);
        $this->assertNull($this->submit([])->get_data()->enrolkey);
        $this->assertSame('', $this->submit(['value' => 'typed', 'clear' => '1'])->get_data()->enrolkey);
        $this->assertSame('plain', $this->submit('plain')->get_data()->enrolkey);
        $this->assertNull($this->submit('')->get_data()->enrolkey);

        $form = $this->submit(['value' => ['nested']]);
        $this->assertTrue($form->is_invalid());
        $this->assertNull($form->get_non_validated_data()->enrolkey);

        // Missing key means nothing was submitted for the element, current data is never returned.
        $this->simulate_post(simple_form::class, ['name' => 'x', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['enrolkey' => 'current']);
        $this->assertNull($form->get_data()->enrolkey);
    }

    public function test_clear_survives_rerender(): void {
        $this->add_element(true);
        $form = $this->submit(['clear' => '1'], ['enrolkey' => true]);
        $this->assertSame('', $form->get_data()->enrolkey);
        $this->assertStringContainsString('name="enrolkey[clear]" value="1" checked', $this->render($form));
        $form = $this->submit(['value' => 'new'], ['enrolkey' => true]);
        $this->assertStringContainsString('name="enrolkey[clear]" value="1">', $this->render($form));
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['enrolkey' => true]);
        $this->assertStringContainsString('name="enrolkey[clear]" value="1">', $this->render($form));
    }

    public function test_clear_not_allowed(): void {
        $this->add_element(false);
        $this->assertSame('typed', $this->submit(['value' => 'typed', 'clear' => '1'])->get_data()->enrolkey);
        $this->assertNull($this->submit(['clear' => '1'])->get_data()->enrolkey);
        $this->assertStringNotContainsString('[clear]', $this->render($this->submit([])));
    }

    public function test_current_value_in_data_attribute(): void {
        $this->add_element(true);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['enrolkey' => 's3cr&t<b>']);
        $this->assertNull($form->get_non_validated_data()->enrolkey);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-sharedkey-current="s3cr&amp;t&lt;b&gt;"', $html);
        $this->assertStringContainsString('placeholder="••••••••"', $html);
        $this->assertStringContainsString('name="enrolkey[value]" value=""', $html);
        $this->assertStringContainsString('name="enrolkey[clear]"', $html);
        $this->assertStringContainsString('class="form-control muform-sharedkey-masked"', $html);
        $this->assertStringContainsString('autocomplete="off" spellcheck="false" autocapitalize="off"', $html);

        // Unknown current key is still a value.
        $form = new simple_form($this->get_url(), ['enrolkey' => true]);
        $html = $this->render($form);
        $this->assertStringContainsString('data-muform-sharedkey-current=""', $html);
        $this->assertStringContainsString('placeholder="••••••••"', $html);

        // The typed value is not echoed after an invalid submission, the current key is.
        $this->simulate_post(simple_form::class, ['name' => 'invalid', 'enrolkey' => ['value' => 'typedkey'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['enrolkey' => 'current']);
        $this->assertTrue($form->is_invalid());
        $html = $this->render($form);
        $this->assertStringNotContainsString('typedkey', $html);
        $this->assertStringContainsString('data-muform-sharedkey-current="current"', $html);

        foreach ([false, null, '', 0] as $current) {
            $this->simulate_get();
            $form = new simple_form($this->get_url(), ['enrolkey' => $current]);
            $html = $this->render($form);
            $this->assertStringNotContainsString('placeholder', $html);
            $this->assertStringContainsString('data-muform-sharedkey-current=""', $html);
        }
    }

    public function test_required(): void {
        $this->add_element(true, true);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $this->assertStringContainsString(' required aria-required="true"', $this->get_input($form));
        $form = new simple_form($this->get_url(), ['enrolkey' => true]);
        $this->assertStringNotContainsString(' required', $this->get_input($form));
        $this->assertStringContainsString(' aria-required="true"', $this->get_input($form));

        $this->assertTrue($this->submit(['value' => ''])->is_invalid());
        $this->assertTrue($this->submit(['value' => ''], ['enrolkey' => true])->is_valid());
        $this->assertTrue($this->submit(['value' => 'new'])->is_valid());
        $form = $this->submit(['clear' => '1'], ['enrolkey' => true]);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'enrolkey'));
    }

    public function test_length(): void {
        $this->add_element(false, false, ['minlength' => 4, 'maxlength' => 6]);
        $this->assertTrue($this->submit(['value' => 'abcd'])->is_valid());
        $form = $this->submit(['value' => 'abc']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Error'], $this->get_rendered_errors($form, 'enrolkey'));
        $this->assertTrue($this->submit(['value' => 'abcdefg'])->is_invalid());
        $this->assertTrue($this->submit(['value' => ''])->is_valid());
        $this->assertStringContainsString('maxlength="6" minlength="4"', $this->render($this->submit(['value' => 'abcd'])));
    }

    public function test_frozen(): void {
        $this->add_element(true, false, [], true);
        $form = $this->submit(['value' => 'new'], ['enrolkey' => 'current']);
        $this->assertNull($form->get_data()->enrolkey);
        $html = $this->render($form);
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_enrolkey' . $form->get_idsuffix() . '">••••••••</div>',
            $html
        );
        $this->assertStringNotContainsString('current', $html);
        $this->assertStringNotContainsString(' name="enrolkey', $html);

        $form = $this->submit(['value' => 'new'], []);
        $this->assertStringContainsString('">Not set</div>', $this->render($form));
    }

    public function test_validator(): void {
        $hook = function (muform_definition $hook): void {
            $element = (new sharedkey('enrolkey', 'Enrolment key', [], true))
                ->add_validator(function (sharedkey $element, array &$allerrors): void {
                    $value = $element->get_value();
                    if (is_string($value) && $value !== '' && strlen($value) < 8) {
                        $allerrors['enrolkey'][] = 'Too weak';
                    }
                });
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->assertTrue($this->submit(['value' => 'short'])->is_invalid());
        $this->assertTrue($this->submit(['value' => 'longenough'])->is_valid());
        $this->assertTrue($this->submit(['value' => ''])->is_valid());
        $this->assertTrue($this->submit(['clear' => '1'])->is_valid());
    }
}
