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
use tool_mulib\muform\element\textarea;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Textarea element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\textarea
 */
final class textarea_test extends muform_testcase {
    /**
     * Submit value and return form.
     *
     * @param mixed $value
     * @return simple_form
     */
    private function submit(mixed $value): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'notes' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), []);
    }

    public function test_parsing(): void {
        $this->assertSame("a\nb\nc", $this->submit("a\r\nb\rc")->get_data()->notes);
        $this->assertSame('', $this->submit('')->get_data()->notes);
        $this->assertSame('', $this->submit(['a'])->get_data()->notes);
        $this->assertTrue($this->submit('')->is_valid());
    }

    public function test_cleaning(): void {
        $this->assertSame("a b\nc", $this->submit("a <b>b</b>\nc")->get_data()->notes);

        $hook = function (muform_definition $hook): void {
            $hook->form->add(new textarea('raw', 'Raw', ['type' => 'rawtext']));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'raw' => '{"a": "<b>"}', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('{"a": "<b>"}', $form->get_data()->raw);
        $html = $this->render($form);
        $this->assertStringNotContainsString('rawtext', $html);

        $this->expectException(coding_exception::class);
        new textarea('raw', 'Raw', ['type' => 'html']);
    }

    public function test_attributes(): void {
        $hook = function (muform_definition $hook): void {
            $big = (new textarea('big', 'Big', ['maxlength' => 3, 'rows' => 4, 'cols' => 20]))
                ->set_required(true);
            $hook->form->add($big);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'big' => ' ', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'big'));

        $this->simulate_post(simple_form::class, ['name' => 'x', 'big' => 'abcd', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'big'));

        $this->simulate_post(simple_form::class, ['name' => 'x', 'big' => "a&b", 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<textarea class="form-control" id="id_big' . $suffix . '" name="big" aria-describedby="id_error_big' . $suffix . '"'
            . ' required aria-required="true" rows="4" cols="20" maxlength="3">a&amp;b</textarea>',
            $html
        );
    }
}
