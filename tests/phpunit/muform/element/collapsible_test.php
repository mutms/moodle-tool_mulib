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

use tool_mulib\phpunit\muform\fixtures\collapsible_form;
use tool_mulib\phpunit\muform\muform_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Collapsible element and button confirmation tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\muform\element\collapsible::class)]
#[CoversClass(\tool_mulib\muform\element\button::class)]
#[Group('MuTMS')]
final class collapsible_test extends muform_testcase {
    public function test_render(): void {
        $form = new collapsible_form($this->get_url(), []);
        $this->assertFalse($form->get_element('first')->returns_data());
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<details id="fitem_id_first' . $suffix . '" class="accordion accordion-item muform-collapsible" open'
            . ' data-muform-element="collapsible" data-muform-component="tool_mulib" data-muform-name="first"'
            . ' data-muform-statekey="key-first">',
            $html
        );
        $this->assertStringContainsString(
            '<details id="fitem_id_second' . $suffix . '" class="accordion accordion-item muform-collapsible" open'
            . ' data-muform-element="collapsible" data-muform-component="tool_mulib" data-muform-name="second">',
            $html
        );
        $this->assertStringContainsString('Summary &#60;b&#62;one&#60;/b&#62;</span>', $html);
        $this->assertStringNotContainsString('data-muform-collapsible-errors', $html);
        $this->assertStringContainsString(' data-muform-confirm="Really?"', $html);
        $this->assertStringContainsString('name="remove" value="1" class="btn btn-link btn-sm"', $html);
    }

    public function test_submit_and_errors(): void {
        $this->simulate_post(collapsible_form::class, ['name' => 'Jane', 'nick' => 'JD', 'submit' => '1']);
        $form = new collapsible_form($this->get_url(), []);
        $data = $form->get_data();
        $this->assertSame('Jane', $data->name);
        $this->assertSame('JD', $data->nick);
        $this->assertObjectNotHasProperty('first', $data);

        $this->simulate_post(collapsible_form::class, ['name' => 'Jane', 'nick' => '', 'submit' => '1']);
        $form = new collapsible_form($this->get_url(), []);
        $this->assertNull($form->get_data());
        $html = $this->render($form);
        $this->assertSame(1, substr_count($html, 'data-muform-collapsible-errors="1"'));
        $this->assertStringContainsString('data-muform-name="second" data-muform-collapsible-errors="1"', $html);
    }
}
