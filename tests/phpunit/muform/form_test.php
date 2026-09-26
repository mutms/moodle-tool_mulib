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

use core\exception\coding_exception;
use core\exception\moodle_exception;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\text;
use tool_mulib\phpunit\muform\fixtures\dynamic_form;
use tool_mulib\phpunit\muform\fixtures\simple_form;

/**
 * Form lifecycle tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\form
 */
final class form_test extends muform_testcase {
    public function test_new(): void {
        $form = new simple_form($this->get_url(), ['name' => 'Current', 'count' => 5, 'enabled' => 1, 'color' => 'green']);

        $this->assertTrue($form->is_finalised());
        $this->assertTrue($form->is_new());
        $this->assertFalse($form->is_cancelled());
        $this->assertFalse($form->is_reloaded());
        $this->assertFalse($form->is_invalid());
        $this->assertFalse($form->is_valid());
        $this->assertNull($form->get_data());
        $this->assertNull($form->get_post_data());
        $this->assertNotEmpty($form->get_idsuffix());
        $this->assertSame(['name' => 'Current', 'count' => 5, 'enabled' => 1, 'color' => 'green'], $form->get_current_data());
        $this->assertSame([], $form->get_extra_data());

        $data = $form->get_non_validated_data();
        $this->assertSame([
            'itemid' => null,
            'name' => 'Current',
            'count' => 5,
            'notes' => '',
            'enabled' => 1,
            'color' => 'green',
        ], (array)$data);

        $this->assertSame(
            ['general', 'itemid', 'name', 'count', 'notes', 'enabled', 'color', 'buttons', 'submit', 'cancel'],
            array_keys($form->get_elements())
        );
        $this->assertSame(['itemid', 'name', 'count', 'notes', 'enabled', 'color'], $form->get_element('general')->get_children());
        $this->assertInstanceOf(text::class, $form->get_element('name'));
        $this->assertNull($form->get_element('xyz'));
    }

    public function test_additional_data(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new editor('description', 'Description'));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), ['description' => '<p>x</p>', 'descriptionformat' => FORMAT_HTML]);
        $data = $form->get_non_validated_data();
        $this->assertSame('<p>x</p>', $data->description);
        $this->assertSame(1, $data->descriptionformat);
        $this->assertNull($data->descriptiondraftitemid);
        $keys = array_keys((array)$data);
        $position = array_search('description', $keys);
        $this->assertSame(['description', 'descriptionformat', 'descriptiondraftitemid'], array_slice($keys, $position, 3));

        // Additional keys must not collide with elements.
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new editor('description', 'Description'));
            $hook->form->add(new text('descriptionformat', 'Clash'));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        new simple_form($this->get_url(), []);
    }

    public function test_object_current_data(): void {
        $form = new simple_form($this->get_url(), (object)['name' => 'Current'], ['extra' => 1]);
        $this->assertSame(['name' => 'Current'], $form->get_current_data());
        $this->assertSame(['extra' => 1], $form->get_extra_data());
    }

    public function test_post_other_form_ignored(): void {
        $this->simulate_post(dynamic_form::class, ['name' => 'Posted', 'submit' => '1']);
        $form = new simple_form($this->get_url(), ['name' => 'Current']);
        $this->assertTrue($form->is_new());
        $this->assertNull($form->get_post_data());
        $this->assertSame('Current', $form->get_non_validated_data()->name);
    }

    public function test_missing_sesskey(): void {
        $this->simulate_post(simple_form::class, ['name' => 'Posted', 'submit' => '1'], false);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_new());
        $this->assertNull($form->get_post_data());
    }

    public function test_invalid_sesskey(): void {
        $this->simulate_post(simple_form::class, ['name' => 'Posted', 'submit' => '1', '__sesskey' => 'xyz'], false);
        $this->expectException(moodle_exception::class);
        new simple_form($this->get_url(), []);
    }

    public function test_valid_submission(): void {
        $this->simulate_post(simple_form::class, [
            'itemid' => '7',
            'name' => 'Posted',
            'count' => '3',
            'notes' => "a\r\nb",
            'enabled' => '1',
            'color' => 'green',
            'submit' => '1',
            '__sesskey' => 'ignored',
            'sesskey' => 'plain data',
            'other' => 'ignored',
            '__formid' => 'ignored',
        ]);
        $form = new simple_form($this->get_url(), ['name' => 'Current', 'itemid' => 3]);

        $this->assertTrue($form->is_valid());
        $this->assertFalse($form->is_new());
        $this->assertFalse($form->is_invalid());
        $post = $form->get_post_data();
        $this->assertArrayNotHasKey('__sesskey', $post);
        $this->assertArrayNotHasKey('__formid', $post);
        $this->assertSame('plain data', $post['sesskey']);
        $this->assertSame('ignored', $post['other']);

        $data = $form->get_data();
        $this->assertSame([
            'itemid' => '3',
            'name' => 'Posted',
            'count' => 3,
            'notes' => "a\nb",
            'enabled' => 1,
            'color' => 'green',
        ], (array)$data);
        $this->assertEquals($data, $form->get_non_validated_data());
    }

    public function test_no_button_pressed(): void {
        $this->simulate_post(simple_form::class, ['name' => 'Posted']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_new());
        $this->assertNull($form->get_data());
        $this->assertSame('Posted', $form->get_non_validated_data()->name);
    }

    public function test_invalid_submission(): void {
        $this->simulate_post(simple_form::class, ['name' => '', 'count' => '11', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);

        $this->assertTrue($form->is_invalid());
        $this->assertFalse($form->is_valid());
        $this->assertNull($form->get_data());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'name'));
        $this->assertSame([get_string('error')], $this->get_rendered_errors($form, 'count'));
        $this->assertSame([], $this->get_rendered_errors($form, 'notes'));
        $this->assertSame('', $form->get_non_validated_data()->name);
    }

    public function test_form_validation(): void {
        $this->simulate_post(simple_form::class, ['name' => 'invalid', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Name is invalid'], $this->get_rendered_errors($form, 'name'));

        $this->simulate_post(simple_form::class, ['name' => 'formerror', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertDebuggingCalled('Unknown error element name: __form');
        $this->assertSame(['Whole form is invalid'], $this->get_rendered_form_errors($form));
        $this->assertSame([], $this->get_rendered_errors($form, 'name'));
    }

    public function test_cancel_wins(): void {
        $this->simulate_post(simple_form::class, ['name' => '', 'submit' => '1', 'cancel' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_cancelled());
        $this->assertFalse($form->is_invalid());
        $this->assertNull($form->get_data());
        $this->assertSame([], $this->get_rendered_errors($form, 'name'));
    }

    public function test_reload(): void {
        $this->simulate_post(dynamic_form::class, ['mode' => 'extra', 'update' => '1']);
        $form = new dynamic_form($this->get_url(), []);
        $this->assertTrue($form->is_reloaded());
        $this->assertNull($form->get_data());
        $this->assertSame('', $form->get_non_validated_data()->extra);
        $this->assertNotNull($form->get_element('extra'));
        $this->assertSame([], $this->get_rendered_errors($form, 'extra'));

        $this->simulate_post(dynamic_form::class, ['mode' => 'basic', 'update' => '1']);
        $form = new dynamic_form($this->get_url(), []);
        $this->assertTrue($form->is_reloaded());
        $this->assertNull($form->get_element('extra'));

        $this->simulate_post(dynamic_form::class, ['mode' => 'extra', 'submit' => '1']);
        $form = new dynamic_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'extra'));

        $this->simulate_post(dynamic_form::class, ['mode' => 'extra', 'extra' => 'x', 'submit' => '1']);
        $form = new dynamic_form($this->get_url(), []);
        $this->assertTrue($form->is_valid());
        $this->assertSame(['mode' => 'extra', 'extra' => 'x'], (array)$form->get_data());
    }

    public function test_add_after_finalised(): void {
        $form = new simple_form($this->get_url(), []);
        $this->expectException(coding_exception::class);
        $form->add(new text('late', 'Late'));
    }

    public function test_add_duplicate(): void {
        $hook = function (muform_definition $hook): void {
            $hook->get_form()->add(new text('name', 'Duplicate'));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Duplicate element name: name');
        new simple_form($this->get_url(), []);
    }

    public function test_add_unknown_parent(): void {
        $hook = function (muform_definition $hook): void {
            $hook->get_form()->add(new text('late', 'Late'), 'xyz');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Unknown parent element: xyz');
        new simple_form($this->get_url(), []);
    }

    public function test_add_to_non_container(): void {
        $hook = function (muform_definition $hook): void {
            $hook->get_form()->add(new text('late', 'Late'), 'name');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Element name cannot contain other elements');
        new simple_form($this->get_url(), []);
    }

    public function test_hook(): void {
        $hook = function (muform_definition $hook): void {
            $form = $hook->form;
            $this->assertFalse($form->is_finalised());
            $hooked = (new text('hooked', 'Hooked'))
                ->set_required(true);
            $form->add($hooked, 'general', 1);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $this->simulate_post(simple_form::class, ['name' => 'Posted', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->is_invalid());
        $this->assertSame([get_string('required')], $this->get_rendered_errors($form, 'hooked'));
        $this->assertSame(
            ['itemid', 'hooked', 'name', 'count', 'notes', 'enabled', 'color'],
            $form->get_element('general')->get_children()
        );
    }

    public function test_render(): void {
        $this->simulate_post(simple_form::class, ['name' => '', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $formid = 'tool_mulib-phpunit-muform-fixtures-simple_form';
        $suffix = $form->get_idsuffix();

        $this->assertStringContainsString('<form id="' . $formid . $suffix . '"', $html);
        $this->assertStringContainsString('action="' . $this->get_url()->out(false) . '"', $html);
        $this->assertStringContainsString('name="__sesskey" value="' . sesskey() . '"', $html);
        $this->assertStringContainsString('name="__formid" value="' . $formid . '"', $html);
        $this->assertStringContainsString('data-muform="' . $formid . '"', $html);
        $this->assertStringContainsString('data-muform-has-errors="1"', $html);
        $this->assertStringContainsString('data-muform-rules="', $html);
        $this->assertStringContainsString('<script type="module">', $html);
        $this->assertStringContainsString('id="id_name' . $suffix . '"', $html);
        $this->assertStringContainsString('id="id_error_name' . $suffix . '"', $html);
        $this->assertStringContainsString('<legend id="id_general' . $suffix . '"', $html);
        $this->assertStringContainsString('data-muform-role="submit"', $html);
        $this->assertStringContainsString('data-muform-role="cancel"', $html);
        $this->assertStringContainsString('>' . get_string('required') . '<', $html);
    }

    public function test_render_variant(): void {
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form, 'reversed');
        // Variant exists only for the buttons row, everything else falls back to standard templates.
        $this->assertStringContainsString('felement d-flex flex-wrap flex-row-reverse justify-content-end gap-2">', $html);
        $this->assertStringContainsString('<input type="text" class="form-control" id="id_name', $html);
        $this->assertStringContainsString('<form id="', $html);

        $html = $this->render($form, 'nosuchvariant');
        $this->assertStringContainsString('felement d-flex flex-wrap gap-2">', $html);

        $this->expectException(coding_exception::class);
        $this->render($form, 'Bad-Name');
    }

    public function test_render_template_override(): void {
        $form = new simple_form($this->get_url(), []);
        $form->set_template('tool_mulib/muform/element/hidden');
        $html = $this->render($form);
        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_template_data_elements_byname(): void {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php');
        $PAGE->set_context(\core\context\system::instance());
        $output = $PAGE->get_renderer('core');

        $form = new simple_form($this->get_url(), []);
        $method = new \ReflectionMethod($form, 'get_template_data');
        $context = $method->invoke($form, $output, '');
        $this->assertSame(['general', 'buttons'], array_keys($context['elements_byname']));
        $this->assertSame($context['elements'][0]['html'], $context['elements_byname']['general']['html']);

        $section = $form->get_element('general');
        $method = new \ReflectionMethod($section, 'get_template_data');
        $context = $method->invoke($section, $output, []);
        $this->assertSame(['itemid', 'name', 'count', 'notes', 'enabled', 'color'], array_keys($context['elements_byname']));
        $this->assertStringContainsString('id="id_name' . $form->get_idsuffix() . '"', $context['elements_byname']['name']['html']);
        $this->assertSame(array_column($context['elements'], 'html'), array_column($context['elements_byname'], 'html'));
    }
}
