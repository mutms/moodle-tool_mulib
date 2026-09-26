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
use tool_mulib\muform\element\text;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Text element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\text
 */
final class text_test extends muform_testcase {
    /**
     * Add text element with given attributes via hook.
     *
     * @param array $attributes
     * @param bool $required
     */
    private function add_element(array $attributes, bool $required = false): void {
        $hook = function (muform_definition $hook) use ($attributes, $required): void {
            $element = (new text('txt', 'Text', $attributes))
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'txt' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_width(): void {
        $this->add_element([]);
        $this->assertStringContainsString('class="form-control"', $this->render($this->submit('x')));
        $this->add_element(['width' => 'full']);
        $html = $this->render($this->submit('x'));
        $this->assertStringContainsString('class="form-control muform-width-full"', $html);
        $this->assertStringNotContainsString('width="full"', $html);
        $this->add_element(['type' => 'url']);
        $html = $this->render($this->submit('https://x.example'));
        $this->assertStringContainsString('class="form-control muform-width-full"', $html);
        $this->add_element(['type' => 'url', 'width' => 'small']);
        $html = $this->render($this->submit('https://x.example'));
        $this->assertStringContainsString('class="form-control muform-width-small"', $html);
        $this->expectException(coding_exception::class);
        new text('txt', 'Text', ['width' => 'huge']);
    }

    public function test_invalid_type(): void {
        $this->expectException(coding_exception::class);
        new text('txt', 'Text', ['type' => 'number']);
    }

    public function test_parsing(): void {
        $this->add_element([]);
        $this->assertSame('abc', $this->submit('abc')->get_data()->txt);
        $this->assertSame('', $this->submit('')->get_data()->txt);
        $this->assertSame('', $this->submit(['a'])->get_data()->txt);
        $this->assertSame('a b c', $this->submit("a\r\nb\nc")->get_data()->txt);
        $this->assertSame('  ', $this->submit('  ')->get_data()->txt);

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['txt' => 12]);
        $this->assertSame('12', $form->get_non_validated_data()->txt);
        $form = new simple_form($this->get_url(), ['txt' => null]);
        $this->assertSame('', $form->get_non_validated_data()->txt);
    }

    public function test_cleaning(): void {
        $this->add_element([]);
        $this->assertSame('a b', $this->submit('a <b>b</b>')->get_data()->txt);
        $multilang = '<span lang="en" class="multilang">EN</span><span lang="cs" class="multilang">CS</span>';
        $this->assertSame($multilang, $this->submit($multilang)->get_data()->txt);

        $this->add_element(['type' => 'rawtext']);
        $this->assertSame('a <b>b</b>', $this->submit('a <b>b</b>')->get_data()->txt);
        $html = $this->render($this->submit('x'));
        $this->assertStringContainsString('<input type="text" class="form-control" id="id_txt', $html);
        $this->assertStringNotContainsString('rawtext', $html);
    }

    public function test_required(): void {
        $this->add_element([], true);
        $this->assertTrue($this->submit('abc')->is_valid());
        $this->assertTrue($this->submit('0')->is_valid());
        $form = $this->submit('  ');
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'txt'));
        $this->assertFalse($form->get_element('txt')->has_required_value());
        $this->assertTrue($this->submit('0')->get_element('txt')->has_required_value());
    }

    public function test_lengths(): void {
        $this->add_element(['maxlength' => 3, 'minlength' => 2]);
        $this->assertTrue($this->submit('žšč')->is_valid());
        $this->assertTrue($this->submit('')->is_valid());
        $this->assertTrue($this->submit('ab')->is_valid());
        $this->assertTrue($this->submit('abcd')->is_invalid());
        $this->assertTrue($this->submit('a')->is_invalid());
    }

    public function test_pattern(): void {
        $this->add_element(['pattern' => '[a-z]{2}~?']);
        $this->assertTrue($this->submit('ab')->is_valid());
        $this->assertTrue($this->submit('ab~')->is_valid());
        $this->assertTrue($this->submit('abc')->is_invalid());
        $this->assertTrue($this->submit('AB')->is_invalid());
    }

    public function test_email(): void {
        $this->add_element(['type' => 'email']);
        $this->assertTrue($this->submit('a@example.com')->is_valid());
        $this->assertTrue($this->submit('')->is_valid());
        $this->assertTrue($this->submit('abc')->is_invalid());
        $form = $this->submit('');
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<input type="email" class="form-control" id="id_txt' . $suffix . '" name="txt" value=""'
            . ' aria-describedby="id_error_txt' . $suffix . '">',
            $html
        );
    }

    public function test_url(): void {
        $this->add_element(['type' => 'url']);
        $this->assertTrue($this->submit('https://example.com/a?b=c')->is_valid());
        $this->assertTrue($this->submit('abc')->is_invalid());
    }

    public function test_render(): void {
        $this->add_element(['maxlength' => 5, 'placeholder' => 'P "x"', 'autocomplete' => 'off']);
        $form = $this->submit('a"b');
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<input type="text" class="form-control" id="id_txt' . $suffix . '" name="txt" value="a&quot;b"'
            . ' aria-describedby="id_error_txt' . $suffix . '" maxlength="5" placeholder="P &quot;x&quot;" autocomplete="off">',
            $html
        );

        $this->add_element([], true);
        $form = $this->submit('');
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<input type="text" class="form-control is-invalid" id="id_txt' . $suffix . '" name="txt" value=""'
            . ' aria-describedby="id_error_txt' . $suffix . '" required aria-required="true" aria-invalid="true">',
            $html
        );
        $this->assertStringContainsString(
            '<div class="form-control-feedback invalid-feedback" id="id_error_txt' . $suffix . '" style="display: block;">',
            $html
        );
    }
}
