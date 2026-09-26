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
use tool_mulib\muform\element\dateinterval;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Date interval element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\dateinterval
 */
final class dateinterval_test extends muform_testcase {
    /**
    /**
     * Add dateinterval element via hook.
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
        array $units = ['y', 'm', 'w', 'd']
    ): void {
        $hook = function (muform_definition $hook) use ($frozen, $required, $default, $units): void {
            $element = (new dateinterval('validity', 'Validity', $units))
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'validity' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_basics(): void {
        $element = new dateinterval('validity', 'Validity');
        $this->assertSame('validity', $element->get_name());
        $this->assertTrue($element->returns_data());

        $this->expectException(coding_exception::class);
        $element->get_value();
    }

    public function test_value_precedence(): void {
        $this->add_element();
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_element('validity')->get_value());

        $this->add_element(false, false, 'P1Y6M');
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('P1Y6M', $form->get_element('validity')->get_value());

        $form = new simple_form($this->get_url(), ['validity' => 'P2M']);
        $this->assertSame('P2M', $form->get_element('validity')->get_value());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'validity' => ['d' => '10'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['validity' => 'P2M']);
        $this->assertSame('P10D', $form->get_element('validity')->get_value());
        $this->assertSame('P10D', $form->get_data()->validity);

        $this->add_element(true);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'validity' => ['d' => '10'], 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['validity' => 'P1Y2M']);
        $this->assertSame('P1Y2M', $form->get_element('validity')->get_value());
        $this->assertSame('P1Y2M', $form->get_data()->validity);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_validity' . $suffix . '">1 year 2 months</div>',
            $html
        );
        $this->assertStringNotContainsString('name="validity[', $html);

        $form = new simple_form($this->get_url(), ['validity' => null]);
        $html = $this->render($form);
        $this->assertStringContainsString('>' . get_string('muform_notset', 'tool_mulib') . '</div>', $html);
    }

    public function test_current_data(): void {
        $this->add_element();
        $form = new simple_form($this->get_url(), ['validity' => 'P0Y2M0DT0S']);
        $this->assertSame('P2M', $form->get_element('validity')->get_value());
        $form = new simple_form($this->get_url(), ['validity' => 'P0D']);
        $this->assertNull($form->get_element('validity')->get_value());
        $form = new simple_form($this->get_url(), ['validity' => '']);
        $this->assertNull($form->get_element('validity')->get_value());
        $form = new simple_form($this->get_url(), ['validity' => null]);
        $this->assertNull($form->get_element('validity')->get_value());
        $form = new simple_form($this->get_url(), ['validity' => 'P1H']);
        $this->assertNull($form->get_element('validity')->get_value());
        $form = new simple_form($this->get_url(), ['validity' => 3600]);
        $this->assertNull($form->get_element('validity')->get_value());

        // Units that are not configured are added when current data or default needs them.
        $form = new simple_form($this->get_url(), ['validity' => 'P2MT4H']);
        $this->assertSame('P2MT4H', $form->get_element('validity')->get_value());
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[h]" value="4"', $html);
        $this->assertStringNotContainsString('name="validity[i]"', $html);
        $this->assertSame(5, substr_count($html, 'data-muform-unit="'));
        $form = new simple_form($this->get_url(), ['validity' => 'P2MT0H']);
        $html = $this->render($form);
        $this->assertStringNotContainsString('name="validity[h]"', $html);

        $this->add_element(false, false, null, ['m']);
        $form = new simple_form($this->get_url(), ['validity' => 'P1Y']);
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[y]" value="1"', $html);
        $this->assertStringContainsString('name="validity[m]" value=""', $html);
        $this->assertSame(2, substr_count($html, 'data-muform-unit="'));

        $this->add_element(false, false, 'PT30M');
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[i]" value="30"', $html);
    }

    public function test_parsing(): void {
        $this->add_element(false, false, null, ['y', 'm', 'w', 'd', 'h', 'i', 's']);

        $form = $this->submit(['y' => '', 'm' => '', 'w' => '', 'd' => '', 'h' => '', 'i' => '', 's' => '']);
        $this->assertTrue($form->is_valid());
        $this->assertNull($form->get_data()->validity);

        $form = $this->submit(['y' => '1', 'm' => '2', 'w' => '1', 'd' => '3', 'h' => '1', 'i' => '2', 's' => '3']);
        $this->assertTrue($form->is_valid());
        $this->assertSame('P1Y2M1W3DT1H2M3S', $form->get_data()->validity);
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[m]" value="2"', $html);
        $this->assertStringContainsString('name="validity[s]" value="3"', $html);

        $form = $this->submit(['m' => '0', 'd' => '0']);
        $this->assertNull($form->get_data()->validity);

        // Nothing is normalised, 36 hours stay 36 hours and 14 months stay 14 months.
        $form = $this->submit(['m' => ' 14 ', 'h' => '36', 'unknown' => '7']);
        $this->assertSame('P14MT36H', $form->get_data()->validity);
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[m]" value="14"', $html);
        $this->assertStringContainsString('name="validity[y]" value=""', $html);

        $form = $this->submit('');
        $this->assertNull($form->get_data()->validity);

        // ISO string without JavaScript inputs is accepted too.
        $form = $this->submit('P0Y3M');
        $this->assertSame('P3M', $form->get_data()->validity);

        // Posted units that are not configured are accepted, they were rendered for the current data.
        $this->add_element();
        $form = $this->submit(['m' => '3', 'h' => '5', 's' => '']);
        $this->assertSame('P3MT5H', $form->get_data()->validity);
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[h]" value="5"', $html);
        $this->assertStringNotContainsString('name="validity[s]"', $html);
        $this->assertSame(5, substr_count($html, 'data-muform-unit="'));
    }

    public function test_units(): void {
        $element = new dateinterval('validity', 'Validity', ['y', 'm', 'w', 'd', 'h', 'i', 's']);
        $this->assertSame('validity', $element->get_name());
        $element = new dateinterval('validity', 'Validity', ['m']);
        $this->assertSame('validity', $element->get_name());

        try {
            new dateinterval('validity', 'Validity', []);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid dateinterval units', $e->getMessage());
        }
        try {
            new dateinterval('validity', 'Validity', ['m', 'y']);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid dateinterval units', $e->getMessage());
        }
        try {
            new dateinterval('validity', 'Validity', ['y', 'x']);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Invalid dateinterval units', $e->getMessage());
        }
    }

    public function test_invalid(): void {
        $this->add_element();

        $form = $this->submit(['m' => '1.5', 'd' => '-5', 'h' => 'x', 's' => '']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'validity'));
        $this->assertNull($form->get_non_validated_data()->validity);
        $html = $this->render($form);
        $this->assertStringContainsString('name="validity[m]" value="1.5"', $html);
        $this->assertStringContainsString('name="validity[d]" value="-5"', $html);
        $this->assertStringContainsString('name="validity[h]" value="x"', $html);
        $this->assertStringNotContainsString('name="validity[s]"', $html);

        $form = $this->submit(['m' => 'abc']);
        $this->assertTrue($form->is_invalid());

        $form = $this->submit(['m' => ['1']]);
        $this->assertTrue($form->is_valid());
        $this->assertNull($form->get_data()->validity);

        $form = $this->submit('abc');
        $this->assertTrue($form->is_invalid());
        $this->assertNull($form->get_non_validated_data()->validity);
    }

    public function test_required(): void {
        $this->add_element(false, true);
        $form = $this->submit([]);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'validity'));

        $form = $this->submit(['d' => '0']);
        $this->assertTrue($form->is_invalid());

        $form = $this->submit(['d' => '1']);
        $this->assertTrue($form->is_valid());
        $this->assertSame('P1D', $form->get_data()->validity);
    }

    public function test_has_required_value(): void {
        $this->add_element();
        $this->assertFalse($this->submit([])->get_element('validity')->has_required_value());
        $this->assertTrue($this->submit(['d' => '1'])->get_element('validity')->has_required_value());
        $this->assertFalse($this->submit(['d' => 'x'])->get_element('validity')->has_required_value());
        $this->expectException(coding_exception::class);
        (new dateinterval('validity', 'Validity'))->has_required_value();
    }

    public function test_render(): void {
        $this->add_element(false, true);
        $form = new simple_form($this->get_url(), ['validity' => 'P1Y6M']);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();

        $this->assertStringContainsString(
            '<span id="id_validity' . $suffix . '_label" class="d-inline-block word-break">Validity</span>',
            $html
        );
        $this->assertStringContainsString(
            'role="group" id="id_validity' . $suffix . '" aria-labelledby="id_validity' . $suffix . '_label"'
            . ' aria-describedby="id_error_validity' . $suffix . '" data-muform-required="1">',
            $html
        );
        $this->assertStringContainsString(
            '<input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"'
            . ' class="form-control muform-dateinterval-input"'
            . ' id="id_validity' . $suffix . '_m" name="validity[m]" value="6" data-muform-unit="m">',
            $html
        );
        $this->assertStringContainsString(
            '<label for="id_validity' . $suffix . '_d" class="mb-0">' . get_string('muform_days', 'tool_mulib') . '</label>',
            $html
        );
        $this->assertSame(4, substr_count($html, 'data-muform-unit="'));
        $this->assertStringNotContainsString('name="validity[h]"', $html);
        $this->assertStringContainsString('title="' . get_string('required') . '"', $html);
    }
}
