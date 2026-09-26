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
require_once(__DIR__ . '/../fixtures/fake_source.php');

use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\autocomplete;
use tool_mulib\phpunit\muform\fixtures\fake_source;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Single value autocomplete element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\autocomplete
 * @covers \tool_mulib\muform\autocomplete\base
 */
final class autocomplete_test extends muform_testcase {
    /**
     * Add element via hook.
     *
     * @param string $refused
     * @param bool $required
     * @param bool $frozen
     */
    private function add_element(string $refused = '', bool $required = false, bool $frozen = false): void {
        $hook = function (muform_definition $hook) use ($refused, $required, $frozen): void {
            $element = (new autocomplete('owner', 'Owner', new fake_source($refused), ['placeholder' => 'Pick one']))
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
        $this->simulate_post(simple_form::class, ['name' => 'x', 'owner' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), $current);
    }

    public function test_parsing(): void {
        $this->add_element();
        $this->assertSame('a', $this->submit('a')->get_data()->owner);
        $this->assertNull($this->submit('')->get_data()->owner);
        foreach (['x', 'a,b', 'a b', ['a'], 'a/b', ' a'] as $bad) {
            $form = $this->submit($bad);
            $this->assertTrue($form->is_invalid(), json_encode($bad));
            $this->assertNull($form->get_non_validated_data()->owner);
            $this->assertSame(['Error'], $this->get_rendered_errors($form, 'owner'));
        }

        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['owner' => 'b']);
        $this->assertSame('b', $form->get_non_validated_data()->owner);
        $form = new simple_form($this->get_url(), ['owner' => 7]);
        $this->assertNull($form->get_non_validated_data()->owner);
        $this->assertTrue($form->is_new());
    }

    public function test_render(): void {
        $this->add_element();
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['owner' => 'a']);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('data-muform-autocomplete-url="', $html);
        $this->assertStringContainsString('/tool_mulib/muform/autocomplete"', $html);
        $this->assertStringContainsString(
            'data-muform-autocomplete-source="{&quot;class&quot;:&quot;tool_mulib\\\\phpunit\\\\muform'
            . '\\\\fixtures\\\\fake_source&quot;,&quot;args&quot;:[&quot;&quot;]}"',
            $html
        );
        $this->assertStringContainsString(
            'data-muform-autocomplete-selected="[{&quot;value&quot;:&quot;a&quot;,'
            . '&quot;label&quot;:&quot;&lt;b&gt;Alpha&lt;/b&gt;&quot;,&quot;error&quot;:null}]"',
            $html
        );
        $this->assertStringContainsString('id="id_owner' . $suffix . '" name="owner" value="a"', $html);
        $this->assertStringContainsString('placeholder="Pick one"', $html);
        $this->assertStringContainsString('class="form-control muform-width-medium"', $html);
        $this->assertStringContainsString('<li><b>Alpha</b></li>', $html);
        $this->assertStringContainsString('for="id_owner' . $suffix . '"', $html);

        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $this->assertStringContainsString('name="owner" value=""', $html);
        $this->assertStringNotContainsString('data-muform-autocomplete-labels', $html);
    }

    public function test_required_and_refused(): void {
        $this->add_element('b', true);
        $form = $this->submit('');
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'owner'));

        $form = $this->submit('a');
        $this->assertTrue($form->is_valid());

        $form = $this->submit('b');
        $this->assertTrue($form->is_invalid());
        $this->assertSame('b', $form->get_non_validated_data()->owner);
        $this->assertSame(['Beta: Not allowed'], $this->get_rendered_errors($form, 'owner'));
        $html = $this->render($form);
        $this->assertStringContainsString('<li class="text-danger">Beta: Not allowed</li>', $html);
        $this->assertStringContainsString('&quot;error&quot;:&quot;Not allowed&quot;', $html);
    }

    public function test_frozen(): void {
        $this->add_element('', false, true);
        $form = $this->submit('b', ['owner' => 'a']);
        $this->assertSame('a', $form->get_data()->owner);
        $html = $this->render($form);
        $this->assertStringContainsString('<li><b>Alpha</b></li>', $html);
        $this->assertStringNotContainsString(' name="owner"', $html);
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
