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

use core\param;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\hidden;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Hidden element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\hidden
 */
final class hidden_test extends muform_testcase {
    public function test_parsing(): void {
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_non_validated_data()->itemid);
        $form = new simple_form($this->get_url(), ['itemid' => 5]);
        $this->assertSame('5', $form->get_non_validated_data()->itemid);
        $form = new simple_form($this->get_url(), ['itemid' => true]);
        $this->assertSame('1', $form->get_non_validated_data()->itemid);
        $form = new simple_form($this->get_url(), ['itemid' => ['x']]);
        $this->assertNull($form->get_non_validated_data()->itemid);

        // Untyped hidden is frozen, submitted value is ignored.
        $this->simulate_post(simple_form::class, ['name' => 'x', 'itemid' => 'abc', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['itemid' => 5]);
        $this->assertSame('5', $form->get_data()->itemid);
    }

    public function test_typed(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new hidden('token', param::ALPHANUM));
            $hook->form->add(new hidden('pages', param::INT));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'token' => 'abc123', 'pages' => '12', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['token' => 'orig']);
        $this->assertTrue($form->is_valid());
        $this->assertSame('abc123', $form->get_data()->token);
        $this->assertSame(12, $form->get_data()->pages);

        $this->simulate_post(simple_form::class, ['name' => 'x', 'token' => 'abc-123', 'pages' => '1.5', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'token'));
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'pages'));
        $this->assertSame('abc123', $form->get_non_validated_data()->token);

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['token' => 'orig']);
        $this->assertSame('orig', $form->get_non_validated_data()->token);
        $this->assertNull($form->get_non_validated_data()->pages);
    }

    public function test_render(): void {
        $form = new simple_form($this->get_url(), ['itemid' => 5]);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<input type="hidden" id="id_itemid' . $suffix . '" name="itemid" value="5"'
            . ' data-muform-element="hidden" data-muform-component="tool_mulib" data-muform-name="itemid">',
            $html
        );
    }
}
