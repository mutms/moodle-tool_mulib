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
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\download;
use tool_mulib\muform\element\reload;
use tool_mulib\muform\element\submit;
use tool_mulib\phpunit\muform\fixtures\dynamic_form;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Button, buttons and section element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\button
 * @covers \tool_mulib\muform\element\submit
 * @covers \tool_mulib\muform\element\cancel
 * @covers \tool_mulib\muform\element\download
 * @covers \tool_mulib\muform\element\reload
 * @covers \tool_mulib\muform\element\buttons
 * @covers \tool_mulib\muform\element\section
 */
final class button_test extends muform_testcase {
    public function test_defaults(): void {
        $submit = new submit();
        $this->assertSame('submit', $submit->get_name());
        $this->assertSame('submit', $submit->get_role());
        $this->assertTrue($submit->is_submitting());
        $this->assertFalse($submit->is_cancelling());
        $this->assertFalse($submit->is_reloading());
        $this->assertFalse($submit->returns_data());

        $cancel = new cancel();
        $this->assertSame('cancel', $cancel->get_name());
        $this->assertSame('cancel', $cancel->get_role());
        $this->assertTrue($cancel->is_cancelling());

        $reload = new reload('update', 'Update');
        $this->assertSame('reload', $reload->get_role());
        $this->assertTrue($reload->is_reloading());
    }

    public function test_pressed(): void {
        $form = new simple_form($this->get_url(), ['submit' => 1]);
        $this->assertFalse($form->get_element('submit')->get_value());
        $this->assertArrayNotHasKey('submit', (array)$form->get_non_validated_data());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'submit' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->get_element('submit')->get_value());
        $this->assertFalse($form->get_element('cancel')->get_value());
        $this->assertArrayNotHasKey('submit', (array)$form->get_data());

        $this->simulate_post(simple_form::class, ['name' => 'x', 'cancel' => '']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->get_element('cancel')->get_value());
        $this->assertTrue($form->is_cancelled());
    }

    public function test_render(): void {
        $this->simulate_post(dynamic_form::class, ['mode' => 'basic']);
        $form = new dynamic_form($this->get_url(), []);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<button type="submit" id="id_submit' . $suffix . '" name="submit" value="1" class="btn btn-primary"' . "\n"
            . '        data-muform-element="submit" data-muform-component="tool_mulib" data-muform-name="submit"'
            . ' data-muform-role="submit">'
            . get_string('savechanges') . '</button>',
            $html
        );
        $this->assertStringContainsString(
            '<button type="submit" id="id_update' . $suffix . '" name="update" value="1" class="btn btn-secondary"' . "\n"
            . '        data-muform-element="reload" data-muform-component="tool_mulib" data-muform-name="update"'
            . ' data-muform-role="reload"'
            . ' formnovalidate>Update</button>',
            $html
        );
        $this->assertStringContainsString(
            '<div id="fitem_id_buttons' . $suffix . '" class="mb-3 row fitem muform-buttons"'
            . ' data-muform-element="buttons" data-muform-component="tool_mulib" data-muform-name="buttons">',
            $html
        );

        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<fieldset id="fitem_id_general' . $suffix . '" class="muform-section mb-4"'
            . ' data-muform-element="section" data-muform-component="tool_mulib" data-muform-name="general">',
            $html
        );
        $this->assertStringContainsString('<legend id="id_general' . $suffix . '" class="h4 mb-3">General</legend>', $html);
        $this->assertStringContainsString('data-muform-role="cancel" formnovalidate>' . get_string('cancel') . '</button>', $html);
    }

    public function test_download(): void {
        $download = new download();
        $this->assertSame('download', $download->get_name());
        $this->assertSame('submit', $download->get_role());
        $this->assertTrue($download->is_submitting());
        $this->assertFalse($download->returns_data());

        $hook = function (muform_definition $hook): void {
            $form = $hook->form;
            $form->add(new buttons('export'));
            $form->add(new download('exportfile', 'Export'), 'export');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<button type="submit" id="id_exportfile' . $suffix . '" name="exportfile" value="1" class="btn btn-primary"' . "\n"
            . '        data-muform-element="download" data-muform-component="tool_mulib" data-muform-name="exportfile"'
            . ' data-muform-role="submit" formtarget="_blank" data-muform-download="1">Export</button>',
            $html
        );

        $this->simulate_post(simple_form::class, ['name' => 'x', 'exportfile' => '1']);
        $form = new simple_form($this->get_url(), []);
        $this->assertTrue($form->get_element('exportfile')->get_value());
        $this->assertSame('x', $form->get_data()->name);
    }

    public function test_reversed_and_order_check(): void {
        $hook = function (muform_definition $hook): void {
            $form = $hook->form;
            $more = (new buttons('more'))
                ->set_template('tool_mulib/muform/element/buttons-reversed');
            $form->add($more);
            $form->add(new submit('save', 'Save'), 'more');
            $form->add(new cancel('back', 'Back'), 'more');
            $form->add(new buttons('wrong'));
            $form->add(new cancel('wrongcancel', 'Back'), 'wrong');
            $form->add(new submit('wrongsave', 'Save'), 'wrong');
            // Groups without a submit button are fine.
            $form->add(new buttons('nosubmit'));
            $form->add(new reload('nosubmitrefresh', 'Refresh'), 'nosubmit');
            $form->add(new cancel('nosubmitcancel', 'Cancel'), 'nosubmit');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $this->assertDebuggingCalled(
            'Submit button should be added before other buttons, browsers use the first submit button'
            . ' for Enter key submission: wrongcancel'
        );
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<div id="fitem_id_more' . $suffix . '" class="mb-3 row fitem muform-buttons"'
            . ' data-muform-element="buttons" data-muform-component="tool_mulib" data-muform-name="more">',
            $html
        );
        $this->assertStringContainsString('felement d-flex flex-wrap flex-row-reverse justify-content-end gap-2">', $html);
        $this->assertLessThan(strpos($html, 'name="back"'), strpos($html, 'name="save"'));
    }
}
