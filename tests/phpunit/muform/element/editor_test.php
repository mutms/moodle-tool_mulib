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

use core\context\system;
use core\context\user as user_context;
use core\exception\coding_exception;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\element\editor;
use tool_mulib\muform\util\file_area;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Editor element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\editor
 */
final class editor_test extends muform_testcase {
    /**
     * Add editor element via hook.
     *
     * @param int $maxfiles
     * @param bool $unsafe
     * @param bool $required
     * @param file_area|null $area
     * @param bool $frozen
     */
    private function add_element(
        int $maxfiles = 0,
        bool $unsafe = false,
        bool $required = false,
        ?file_area $area = null,
        bool $frozen = false,
    ): void {
        $hook = function (muform_definition $hook) use ($maxfiles, $unsafe, $required, $area, $frozen): void {
            $element = (new editor('description', 'Description', $maxfiles, false, ['rows' => 4]))
                ->set_required($required)
                ->set_frozen($frozen);
            if ($unsafe) {
                $element->allow_unsafe_rawhtml();
            }
            if ($area) {
                $element->set_file_area($area);
            }
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Submit value and return form.
     *
     * @param mixed $value posted description value
     * @param array $current
     * @return simple_form
     */
    private function submit(mixed $value, array $current = []): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'description' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), $current);
    }

    /**
     * Seed a permanent area with one file.
     *
     * @param int $itemid
     * @return file_area
     */
    private function seeded_area(int $itemid): file_area {
        $area = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', $itemid);
        get_file_storage()->create_file_from_string([
            'contextid' => $area->get_contextid(), 'component' => 'tool_mulib', 'filearea' => 'muform_fixture',
            'itemid' => $itemid, 'filepath' => '/', 'filename' => 'pic.png',
        ], 'x');
        return $area;
    }

    public function test_current_data(): void {
        $this->add_element();
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['description' => '<p>Hi</p>', 'descriptionformat' => FORMAT_HTML]);
        $data = $form->get_non_validated_data();
        $this->assertSame('<p>Hi</p>', $data->description);
        $this->assertSame(1, $data->descriptionformat);
        $this->assertNull($data->descriptiondraftitemid);
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString('name="description[text]" rows="4"', $html);
        $this->assertStringContainsString('&lt;p&gt;Hi&lt;/p&gt;</textarea>', $html);
        $this->assertStringContainsString('<input type="hidden" name="description[format]" value="1">', $html);
        $this->assertStringNotContainsString('description[itemid]', $html);
        $this->assertStringContainsString('for="id_description' . $suffix . '"', $html);
        $this->assertStringNotContainsString('Unsafe HTML allowed', $html);

        // Missing format means the preferred one, missing text is empty.
        $form = new simple_form($this->get_url(), []);
        $this->assertSame('', $form->get_non_validated_data()->description);
        $this->assertSame(1, $form->get_non_validated_data()->descriptionformat);

        // Plain text records get the textarea editor with a format selector.
        $form = new simple_form($this->get_url(), ['description' => "a\nb", 'descriptionformat' => FORMAT_PLAIN]);
        $html = $this->render($form);
        $this->assertStringContainsString('<select class="form-select w-auto mt-1" name="description[format]"', $html);
        $this->assertStringContainsString('<option value="2" selected>', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="description[format]"', $html);
    }

    public function test_cleaning(): void {
        $dirty = '<p onclick="alert(1)">Hi<script>x()</script></p>';
        $this->add_element();
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['description' => $dirty, 'descriptionformat' => FORMAT_HTML]);
        $this->assertSame('<p>Hi</p>', $form->get_non_validated_data()->description);
        $form = new simple_form($this->get_url(), ['description' => $dirty, 'descriptionformat' => FORMAT_MOODLE]);
        $this->assertSame('<p>Hi</p>', $form->get_non_validated_data()->description);
        $form = new simple_form($this->get_url(), ['description' => $dirty, 'descriptionformat' => FORMAT_PLAIN]);
        $this->assertSame($dirty, $form->get_non_validated_data()->description);
        $form = new simple_form($this->get_url(), ['description' => $dirty, 'descriptionformat' => FORMAT_MARKDOWN]);
        $this->assertSame($dirty, $form->get_non_validated_data()->description);

        $form = $this->submit(['text' => $dirty, 'format' => (string)FORMAT_HTML]);
        $this->assertSame('<p>Hi</p>', $form->get_data()->description);
        $this->assertSame(1, $form->get_data()->descriptionformat);

        $this->add_element(0, true);
        $form = $this->submit(['text' => $dirty, 'format' => (string)FORMAT_HTML]);
        $this->assertSame($dirty, $form->get_data()->description);
        $this->assertStringContainsString('Unsafe HTML allowed', $this->render($form));
        $form = new simple_form($this->get_url(), ['description' => $dirty, 'descriptionformat' => FORMAT_HTML]);
        $this->assertSame($dirty, $form->get_non_validated_data()->description);
    }

    public function test_post(): void {
        $this->add_element();
        $form = $this->submit(['text' => 'plain', 'format' => (string)FORMAT_PLAIN]);
        $this->assertSame('plain', $form->get_data()->description);
        $this->assertSame(2, $form->get_data()->descriptionformat);
        $form = $this->submit('just text');
        $this->assertSame('just text', $form->get_data()->description);
        $this->assertSame(1, $form->get_data()->descriptionformat);

        foreach ([['text' => 'x', 'format' => '9'], ['text' => ['x']], ['text' => 'x', 'format' => 'html']] as $bad) {
            $form = $this->submit($bad);
            $this->assertTrue($form->is_invalid());
            $this->assertSame(['Error'], $this->get_rendered_errors($form, 'description'));
        }

        // Files enabled require a draft item id.
        $this->add_element(-1);
        $form = $this->submit(['text' => 'x', 'format' => '1']);
        $this->assertTrue($form->is_invalid());
        $form = $this->submit(['text' => 'x', 'format' => '1', 'itemid' => '0']);
        $this->assertTrue($form->is_invalid());
        $form = $this->submit(['text' => 'x', 'format' => '1', 'itemid' => '123']);
        $this->assertTrue($form->is_valid());
        $this->assertSame(123, $form->get_data()->descriptiondraftitemid);
    }

    public function test_required(): void {
        $this->add_element(0, false, true);
        $form = $this->submit(['text' => '<p>&nbsp;</p>', 'format' => '1']);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'description'));
        $form = $this->submit(['text' => '<p>x</p>', 'format' => '1']);
        $this->assertTrue($form->is_valid());
        $form = $this->submit(['text' => '  ', 'format' => (string)FORMAT_PLAIN]);
        $this->assertTrue($form->is_invalid());
        $form = $this->submit(['text' => ' x ', 'format' => (string)FORMAT_PLAIN]);
        $this->assertTrue($form->is_valid());
    }

    public function test_files(): void {
        global $USER;
        $area = $this->seeded_area(3);
        $text = '<p><img src="@@PLUGINFILE@@/pic.png" alt="pic"></p>';

        $this->add_element(-1);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), [
            'description' => $text, 'descriptionformat' => FORMAT_HTML, 'descriptionfilearea' => $area,
        ]);
        $element = $form->get_element('description');
        $this->assertSame($area, $element->get_file_area());
        $draftid = $form->get_non_validated_data()->descriptiondraftitemid;
        $this->assertGreaterThan(0, $draftid);
        $this->assertSame(
            '<p><img src="@@PLUGINFILE@@/pic.png" alt="pic" /></p>',
            $form->get_non_validated_data()->description
        );
        $html = $this->render($form);
        $this->assertStringContainsString('/draftfile.php/', $html);
        $this->assertStringContainsString('/' . $draftid . '/pic.png', $html);
        $usercontext = user_context::instance($USER->id);
        $this->assertCount(1, get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false));
        $this->assertStringContainsString('<input type="hidden" name="description[itemid]" value="' . $draftid . '">', $html);

        // Submitting saves files, the value is already storable.
        $draftid2 = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid2,
            'filepath' => '/', 'filename' => 'new.png',
        ], 'y');
        $newurl = \core\url::make_draftfile_url($draftid2, '/', 'new.png')->out(false);
        $posted = '<p><img src="' . $newurl . '" alt="new"></p>';
        $target = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', 4);
        $this->add_element(-1, false, false, $target);
        $form = $this->submit(['text' => $posted, 'format' => '1', 'itemid' => (string)$draftid2]);
        $this->assertTrue($form->is_valid());
        $this->assertSame('<p><img src="@@PLUGINFILE@@/new.png" alt="new" /></p>', $form->get_data()->description);
        $this->assertStringContainsString($newurl, $this->render($form));
        $form->get_element('description')->save_area();
        $files = get_file_storage()->get_area_files($target->get_contextid(), 'tool_mulib', 'muform_fixture', 4, 'id', false);
        $this->assertSame(['new.png'], array_values(array_map(fn($f) => $f->get_filename(), $files)));

        // Stale draft keeps existing files and still rewrites the text.
        $stale = file_get_unused_draft_itemid();
        $staleurl = \core\url::make_draftfile_url($stale, '/', 'new.png')->out(false);
        $staletext = '<p><img src="' . $staleurl . '" alt="s"></p>';
        $form = $this->submit(['text' => $staletext, 'format' => '1', 'itemid' => (string)$stale]);
        $this->assertStringContainsString('@@PLUGINFILE@@/new.png', $form->get_data()->description);
        $form->get_element('description')->export_to_file_area($target);
        $this->assertDebuggingCalled();
        $files = get_file_storage()->get_area_files($target->get_contextid(), 'tool_mulib', 'muform_fixture', 4, 'id', false);
        $this->assertCount(1, $files);

        // Without files export returns the text as is.
        $this->add_element();
        $form = $this->submit(['text' => '<p>t</p>', 'format' => '1']);
        $form->get_element('description')->export_to_file_area($target);
        $this->assertSame('<p>t</p>', $form->get_data()->description);
        try {
            $form->get_element('description')->save_area();
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('not provided', $e->getMessage());
        }
    }

    public function test_frozen(): void {
        $this->add_element(0, false, false, null, true);
        $current = ['description' => '<p>Hi</p>', 'descriptionformat' => FORMAT_HTML];
        $form = $this->submit(['text' => 'posted', 'format' => '1'], $current);
        $this->assertSame('<p>Hi</p>', $form->get_data()->description);
        $html = $this->render($form);
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_description' . $form->get_idsuffix() . '">',
            $html
        );
        $this->assertStringContainsString('<p>Hi</p>', $html);
        $this->assertStringNotContainsString('name="description[text]"', $html);
        $this->assertStringContainsString('<span id="id_description' . $form->get_idsuffix() . '_label"', $html);
    }

    public function test_guest_no_files(): void {
        $this->add_element(-1);
        $this->setGuestUser();
        $form = new simple_form($this->get_url(), []);
        $this->assertNull($form->get_non_validated_data()->descriptiondraftitemid);
    }
}
