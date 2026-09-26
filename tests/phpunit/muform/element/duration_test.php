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
use tool_mulib\muform\element\duration;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Duration element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\duration
 */
final class duration_test extends muform_testcase {
    /**
     * Add duration element via hook.
     *
     * @param bool $frozen
     * @param bool $required
     * @param mixed $default
     * @param array $units
     */
    private function add_element(
        bool $frozen = false,
        bool $required = false,
        mixed $default = null,
        array $units = ['d', 'h', 'i']
    ): void {
        $hook = function (muform_definition $hook) use ($frozen, $required, $default, $units): void {
            $element = (new duration('timelimit', 'Time limit', $units))
                ->set_frozen($frozen)
                ->set_required($required);
            if ($default !== null) {
                $element->set_default($default);
            }
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'timelimit' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_basics(): void {
        $element = new duration('timelimit', 'Time limit');
        $this->assertSame('timelimit', $element->get_name());
        $this->assertTrue($element->returns_data());

        $this->expectException(coding_exception::class);
        $element->get_value();
    }

    public function test_value_precedence(): void {
        $this->add_element();
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(0, $form->get_element('timelimit')->get_value());

        $this->add_element(false, false, 5400);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame(5400, $form->get_element('timelimit')->get_value());

        $form = new simple_form($this->get_url(), ['timelimit' => 90000]);
        $this->assertSame(90000, $form->get_element('timelimit')->get_value());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'timelimit' => ['h' => '2'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['timelimit' => 90000]);
        $this->assertSame(7200, $form->get_element('timelimit')->get_value());
        $this->assertSame(7200, $form->get_data()->timelimit);

        $this->add_element(true);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'timelimit' => ['h' => '2'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['timelimit' => 90000]);
        $this->assertSame(90000, $form->get_element('timelimit')->get_value());
        $this->assertSame(90000, $form->get_data()->timelimit);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_timelimit' . $suffix . '">1 day 1 hour</div>',
            $html
        );
        $this->assertStringNotContainsString('name="timelimit[', $html);
    }

    public function test_current_data(): void {
        $this->add_element();
        $form = new simple_form($this->get_url(), ['timelimit' => 90000]);
        $this->assertSame(90000, $form->get_element('timelimit')->get_value());
        $form = new simple_form($this->get_url(), ['timelimit' => '89.6']);
        $this->assertSame(90, $form->get_element('timelimit')->get_value());
        $form = new simple_form($this->get_url(), ['timelimit' => null]);
        $this->assertSame(0, $form->get_element('timelimit')->get_value());
        $form = new simple_form($this->get_url(), ['timelimit' => '']);
        $this->assertSame(0, $form->get_element('timelimit')->get_value());
        $form = new simple_form($this->get_url(), ['timelimit' => -60]);
        $this->assertSame(0, $form->get_element('timelimit')->get_value());
        $form = new simple_form($this->get_url(), ['timelimit' => 'abc']);
        $this->assertSame(0, $form->get_element('timelimit')->get_value());

        // Smaller units are added when the value needs them, nothing is rounded.
        $form = new simple_form($this->get_url(), ['timelimit' => 90]);
        $this->assertSame(90, $form->get_element('timelimit')->get_value());
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[i]" value="1"', $html);
        $this->assertStringContainsString('name="timelimit[s]" value="30"', $html);
        $this->assertSame(4, substr_count($html, 'data-muform-unit="'));

        $this->add_element(false, false, null, ['d', 'h']);
        $form = new simple_form($this->get_url(), ['timelimit' => 5400]);
        $this->assertSame(5400, $form->get_element('timelimit')->get_value());
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[h]" value="1"', $html);
        $this->assertStringContainsString('name="timelimit[i]" value="30"', $html);
        $this->assertStringNotContainsString('name="timelimit[s]"', $html);

        // Larger units are never needed, the largest configured one absorbs the rest.
        $form = new simple_form($this->get_url(), ['timelimit' => 8 * DAYSECS]);
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[d]" value="8"', $html);
        $this->assertStringNotContainsString('name="timelimit[w]"', $html);

        // Defaults work the same way.
        $this->add_element(false, false, 61);
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[s]" value="1"', $html);
    }

    public function test_units(): void {
        $element = new duration('timelimit', 'Time limit', ['w', 'd', 'h', 'i', 's']);
        $this->assertSame('timelimit', $element->get_name());
        $element = new duration('timelimit', 'Time limit', ['h']);
        $this->assertSame('timelimit', $element->get_name());

        try {
            new duration('timelimit', 'Time limit', []);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid duration units', $e->getMessage());
        }
        try {
            new duration('timelimit', 'Time limit', ['h', 'd']);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid duration units', $e->getMessage());
        }
        try {
            new duration('timelimit', 'Time limit', ['d', 'x']);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid duration units', $e->getMessage());
        }
        try {
            new duration('timelimit', 'Time limit', ['weeks']);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid duration units', $e->getMessage());
        }
    }

    public function test_parsing(): void {
        $this->add_element(false, false, null, ['w', 'd', 'h', 'i', 's']);

        $form = $this->submit(['w' => '', 'd' => '', 'h' => '', 'i' => '']);
        $this->assertTrue($form->is_valid());
        $this->assertSame(0, $form->get_data()->timelimit);

        $form = $this->submit(['w' => '1', 'd' => '2', 'h' => '3', 'i' => '4', 's' => '5']);
        $this->assertTrue($form->is_valid());
        $this->assertSame(604800 + 2 * 86400 + 3 * 3600 + 4 * 60 + 5, $form->get_data()->timelimit);

        // Any combination is accepted and normalised.
        $form = $this->submit(['i' => '90']);
        $this->assertSame(5400, $form->get_data()->timelimit);
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[h]" value="1"', $html);
        $this->assertStringContainsString('name="timelimit[i]" value="30"', $html);
        $this->assertStringContainsString('name="timelimit[w]" value=""', $html);

        $form = $this->submit(['h' => ' 25 ', 'unknown' => '7']);
        $this->assertSame(90000, $form->get_data()->timelimit);

        // Posted units that are not configured are accepted, they were rendered for the current data.
        $this->add_element();
        $form = $this->submit(['w' => '1', 'h' => '25', 's' => '30']);
        $this->assertSame(694830, $form->get_data()->timelimit);
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[d]" value="8"', $html);
        $this->assertStringContainsString('name="timelimit[h]" value="1"', $html);
        $this->assertStringContainsString('name="timelimit[s]" value="30"', $html);
        $this->assertStringNotContainsString('name="timelimit[w]"', $html);

        $form = $this->submit('');
        $this->assertSame(0, $form->get_data()->timelimit);

        // Numeric string without JavaScript inputs is accepted too.
        $form = $this->submit('3600');
        $this->assertSame(3600, $form->get_data()->timelimit);
    }

    public function test_invalid(): void {
        $this->add_element();

        $form = $this->submit(['h' => '1.5', 'i' => '-5', 's' => 'x', 'w' => '']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'timelimit'));
        $this->assertSame(0, $form->get_non_validated_data()->timelimit);
        $html = $this->render($form);
        $this->assertStringContainsString('name="timelimit[h]" value="1.5"', $html);
        $this->assertStringContainsString('name="timelimit[i]" value="-5"', $html);
        $this->assertStringContainsString('name="timelimit[s]" value="x"', $html);
        $this->assertStringNotContainsString('name="timelimit[w]"', $html);

        $form = $this->submit(['h' => 'abc']);
        $this->assertTrue($form->is_invalid());

        $form = $this->submit(['h' => ['1']]);
        $this->assertTrue($form->is_valid());
        $this->assertSame(0, $form->get_data()->timelimit);

        $form = $this->submit('abc');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(0, $form->get_non_validated_data()->timelimit);
    }

    public function test_required(): void {
        $this->add_element(false, true);
        $form = $this->submit([]);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'timelimit'));

        $form = $this->submit(['i' => '0']);
        $this->assertTrue($form->is_invalid());

        $form = $this->submit(['i' => '1']);
        $this->assertTrue($form->is_valid());
        $this->assertSame(60, $form->get_data()->timelimit);
    }

    public function test_has_required_value(): void {
        $this->add_element();
        $this->assertFalse($this->submit([])->get_element('timelimit')->has_required_value());
        $this->assertTrue($this->submit(['i' => '1'])->get_element('timelimit')->has_required_value());
        $this->assertFalse($this->submit(['i' => 'x'])->get_element('timelimit')->has_required_value());
        $this->expectException(coding_exception::class);
        (new duration('timelimit', 'Time limit'))->has_required_value();
    }

    public function test_render(): void {
        $this->add_element(false, true);
        $form = new simple_form($this->get_url(), ['timelimit' => 5400]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();

        $this->assertStringContainsString(
            '<span id="id_timelimit' . $suffix . '_label" class="d-inline-block word-break">Time limit</span>',
            $html
        );
        $this->assertStringContainsString(
            'role="group" id="id_timelimit' . $suffix . '" aria-labelledby="id_timelimit' . $suffix . '_label"'
            . ' aria-describedby="id_error_timelimit' . $suffix . '" data-muform-required="1">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"'
            . ' class="form-control muform-duration-input"'
            . ' id="id_timelimit' . $suffix . '_h" name="timelimit[h]" value="1" data-muform-unit="h">',
            $html
        );
        $this->assertStringContainsString(
            '<label for="id_timelimit' . $suffix . '_h" class="mb-0">' . get_string('muform_hours', 'tool_mulib') . '</label>',
            $html
        );
        $this->assertStringContainsString('title="' . get_string('required') . '"', $html);
        $this->assertSame(3, substr_count($html, 'data-muform-unit="'));
        $this->assertStringNotContainsString('name="timelimit[w]"', $html);

        $this->add_element(true);
        $form = new simple_form($this->get_url(), ['timelimit' => 0]);
        $html = $this->render($form);
        $this->assertStringContainsString('>' . get_string('numminutes', 'core', 0) . '</div>', $html);

        $this->add_element(true, false, null, ['d', 'h']);
        $form = new simple_form($this->get_url(), ['timelimit' => 0]);
        $html = $this->render($form);
        $this->assertStringContainsString('>' . get_string('numhours', 'core', 0) . '</div>', $html);
    }
}
