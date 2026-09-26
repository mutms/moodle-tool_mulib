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
require_once(__DIR__ . '/../fixtures/fake_sources.php');

use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\autocompletemany;
use tool_mulib\phpunit\muform\fixtures\fake_sources;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Multiple values autocomplete element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\autocompletemany
 * @covers \tool_mulib\muform\autocompletemany\base
 */
final class autocompletemany_test extends muform_testcase {
    /**
     * Add element via hook.
     *
     * @param string $refused
     * @param bool $required
     * @param bool $frozen
     */
    private function add_element(string $refused = '', bool $required = false, bool $frozen = false): void {
        $hook = function (muform_definition $hook) use ($refused, $required, $frozen): void {
            $element = (new autocompletemany('members', 'Members', new fake_sources($refused)))
                ->set_required($required)
                ->set_frozen($frozen);
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit value and return form.
     *
     * @param mixed $value
     * @param array $current
     * @return simple_form
     */
    private function submit(mixed $value, array $current = []): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'members' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), $current);
    }

    public function test_parsing(): void {
        $this->add_element();
        $this->assertSame(['a', 'c'], $this->submit('a,c')->get_data()->members);
        $this->assertSame(['c', 'a'], $this->submit('c, a ,c')->get_data()->members);
        $this->assertSame([], $this->submit('')->get_data()->members);
        $this->assertSame(['b'], $this->submit(['b'])->get_data()->members);
        foreach (['x', 'a,,b', 'a,x', 'a b', 'a/b', ['a', ['b']], ','] as $bad) {
            $form = $this->submit($bad);
            $this->assertTrue($form->is_invalid(), json_encode($bad));
            $this->assertSame([], $form->get_non_validated_data()->members);
            $this->assertSame(['Error'], $this->get_rendered_errors($form, 'members'));
        }

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['members' => ['b', 'a']]);
        $this->assertSame(['b', 'a'], $form->get_non_validated_data()->members);
        $form = new simple_form($this->get_url(), ['members' => 'a,b']);
        $this->assertSame(['a', 'b'], $form->get_non_validated_data()->members);
        $form = new simple_form($this->get_url(), ['members' => 'a,zzz']);
        $this->assertSame([], $form->get_non_validated_data()->members);
    }

    public function test_render(): void {
        $this->add_element();
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['members' => 'b,a']);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('/tool_mulib/muform/autocompletemany"', $html);
        $this->assertStringContainsString('fake_sources&quot;,&quot;args&quot;:[&quot;&quot;]}"', $html);
        $this->assertStringContainsString('id="id_members' . $suffix . '" name="members" value="b,a"', $html);
        $this->assertStringContainsString('placeholder="Search..."', $html);
        $this->assertStringContainsString('<li>Beta</li>', $html);
        $this->assertStringContainsString('<li><b>Alpha</b></li>', $html);
        $this->assertStringContainsString('data-muform-autocomplete-selected="[{&quot;value&quot;:&quot;b&quot;', $html);
    }

    public function test_required_and_refused(): void {
        $this->add_element('b', true);
        $form = $this->submit('');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'members'));

        $this->assertTrue($this->submit('a,c')->is_valid());

        $form = $this->submit('a,b');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['a', 'b'], $form->get_non_validated_data()->members);
        $this->assertSame(['Beta: Not allowed'], $this->get_rendered_errors($form, 'members'));
        $html = $this->render($form);
        $this->assertStringContainsString('<li class="text-danger">Beta: Not allowed</li>', $html);
        $this->assertStringContainsString('<li><b>Alpha</b></li>', $html);
    }

    public function test_frozen(): void {
        $this->add_element('', false, true);
        $form = $this->submit('b', ['members' => 'a,c']);
        $this->assertSame(['a', 'c'], $form->get_data()->members);
        $html = $this->render($form);
        $this->assertStringContainsString('<li>Gamma</li>', $html);
        $this->assertStringNotContainsString(' name="members"', $html);
        $form = new simple_form($this->get_url(), []);
        $this->assertStringContainsString('Not set', $this->render($form));
    }

    public function test_guest(): void {
        $this->add_element();
        $this->setGuestUser();
        $this->expectException(\core\exception\moodle_exception::class);
        new simple_form($this->get_url(), []);
    }
}
