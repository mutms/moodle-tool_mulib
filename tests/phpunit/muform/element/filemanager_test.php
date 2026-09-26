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
use tool_mulib\muform\element\filemanager;
use tool_mulib\muform\util\file_area;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * File manager element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\filemanager
 */
final class filemanager_test extends muform_testcase {
    /**
     * Add filemanager element via hook.
     *
     * @param int|null $maxfiles
     * @param array|null $types
     * @param bool $subdirs
     * @param bool $required
     * @param file_area|null $area
     * @param bool $frozen
     */
    private function add_element(
        ?int $maxfiles = null,
        ?array $types = null,
        bool $subdirs = false,
        bool $required = false,
        ?file_area $area = null,
        bool $frozen = false,
    ): void {
        $hook = function (muform_definition $hook) use ($maxfiles, $types, $subdirs, $required, $area, $frozen): void {
            $element = (new filemanager('attachments', 'Attachments', $maxfiles, $types, $subdirs))
                ->set_required($required)
                ->set_frozen($frozen);
            if ($area) {
                $element->set_file_area($area);
            }
            $hook->form->add($element);
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
    }

    /**
     * Put a file into the current user's draft area.
     *
     * @param int $draftid
     * @param string $filename
     * @param string $filepath
     * @return \stored_file
     */
    private function add_draft_file(int $draftid, string $filename, string $filepath = '/'): \stored_file {
        global $USER;
        return get_file_storage()->create_file_from_string([
            'contextid' => user_context::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => $filepath,
            'filename' => $filename,
        ], 'content of ' . $filename);
    }

    /**
     * Names of files in an area.
     *
     * @param file_area $area
     * @return string[]
     */
    private function area_names(file_area $area): array {
        $files = get_file_storage()->get_area_files(
            $area->get_contextid(),
            $area->get_component(),
            $area->get_filearea(),
            $area->get_itemid(),
            'filepath, filename',
            false
        );
        return array_values(array_map(fn($f) => $f->get_filepath() . $f->get_filename(), $files));
    }

    /**
     * Submit draft id and return form.
     *
     * @param mixed $value
     * @param array $current
     * @return simple_form
     */
    private function submit(mixed $value, array $current = []): simple_form {
        $this->simulate_post(simple_form::class, ['name' => 'x', 'attachments' => $value, 'submit' => '1']);
        return new simple_form($this->get_url(), $current);
    }

    public function test_new_draft(): void {
        $this->add_element();
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $draftid = $form->get_non_validated_data()->attachments;
        $this->assertIsInt($draftid);
        $this->assertGreaterThan(0, $draftid);
        $this->assertSame([], $form->get_element('attachments')->get_files());
        $this->assertNull($form->get_element('attachments')->get_file_area());

        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<input type="hidden" id="id_attachments' . $suffix . '" name="attachments" value="' . $draftid . '">',
            $html
        );
        $this->assertStringContainsString('class="filemanager w-100 fm-loading"', $html);
        $this->assertStringContainsString('<span id="id_attachments' . $suffix . '_label"', $html);
        $this->assertStringContainsString('Maximum size for new files: Unlimited', $html);
        $this->assertStringNotContainsString('maximum number of files', $html);
        $this->assertStringNotContainsString('Accepted file types', $html);

        // A second form gets a different draft, current draft ids are reused.
        $form2 = new simple_form($this->get_url(), []);
        $this->assertNotSame($draftid, $form2->get_non_validated_data()->attachments);
        $form3 = new simple_form($this->get_url(), ['attachments' => $draftid]);
        $this->assertSame($draftid, $form3->get_non_validated_data()->attachments);
        $form4 = new simple_form($this->get_url(), ['attachments' => (string)$draftid]);
        $this->assertSame($draftid, $form4->get_non_validated_data()->attachments);
    }

    public function test_options_rendered(): void {
        $this->add_element(2, ['.txt', 'image'], true);
        $this->simulate_get();
        $form = new simple_form($this->get_url(), []);
        $html = $this->render($form);
        $this->assertStringContainsString('Maximum file size: Unlimited, maximum number of files: 2', $html);
        $this->assertStringContainsString('Accepted file types', $html);
        $this->assertStringContainsString('.txt', $html);
    }

    public function test_current_area(): void {
        $area = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', 5);
        get_file_storage()->create_file_from_string([
            'contextid' => $area->get_contextid(), 'component' => 'tool_mulib', 'filearea' => 'muform_fixture',
            'itemid' => 5, 'filepath' => '/', 'filename' => 'existing.txt',
        ], 'x');

        $this->add_element();
        $this->simulate_get();
        $form = new simple_form($this->get_url(), ['attachments' => $area]);
        $element = $form->get_element('attachments');
        $this->assertSame($area, $element->get_file_area());
        $names = array_map(fn($f) => $f->get_filename(), $element->get_files());
        $this->assertSame(['existing.txt'], array_values($names));

        // Area given in definition works the same.
        $this->add_element(null, null, false, false, $area);
        $form = new simple_form($this->get_url(), []);
        $names = array_map(fn($f) => $f->get_filename(), $form->get_element('attachments')->get_files());
        $this->assertSame(['existing.txt'], array_values($names));

        // Incomplete area means an empty draft.
        $incomplete = new file_area(null, 'tool_mulib', 'muform_fixture', null);
        $form = new simple_form($this->get_url(), ['attachments' => $incomplete]);
        $this->assertSame([], $form->get_element('attachments')->get_files());
        $this->assertSame($incomplete, $form->get_element('attachments')->get_file_area());
    }

    public function test_post(): void {
        $this->add_element();
        $draftid = file_get_unused_draft_itemid();
        $this->add_draft_file($draftid, 'a.txt');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_valid());
        $this->assertSame($draftid, $form->get_data()->attachments);
        $this->assertCount(1, $form->get_element('attachments')->get_files());

        foreach (['', 'abc', '0', '-1', ['1']] as $bad) {
            $form = $this->submit($bad);
            $this->assertTrue($form->is_invalid());
            $this->assertSame(['Error'], $this->get_rendered_errors($form, 'attachments'));
        }
    }

    public function test_required(): void {
        $this->add_element(null, null, false, true);
        $draftid = file_get_unused_draft_itemid();
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Required'], $this->get_rendered_errors($form, 'attachments'));

        $this->add_draft_file($draftid, 'a.txt');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_valid());

        // Only files that pass the type and folder rules count.
        $this->add_element(null, ['.txt'], false, true);
        $draftid = file_get_unused_draft_itemid();
        $this->add_draft_file($draftid, 'a.png');
        $this->add_draft_file($draftid, 'b.txt', '/sub/');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_invalid());
        $errors = $this->get_rendered_errors($form, 'attachments');
        $this->assertSame('Required', $errors[0]);
        $this->assertCount(3, $errors);
        $this->add_draft_file($draftid, 'c.txt');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_invalid());
        $this->assertStringNotContainsString('Required', implode('|', $this->get_rendered_errors($form, 'attachments')));
        $names = array_map(fn($f) => $f->get_filename(), $form->get_element('attachments')->get_files());
        $this->assertSame(['c.txt'], $names);
    }

    public function test_validation(): void {
        $this->add_element(1, ['.txt']);
        $draftid = file_get_unused_draft_itemid();
        $this->add_draft_file($draftid, 'a.txt');
        $this->assertTrue($this->submit((string)$draftid)->is_valid());

        $this->add_draft_file($draftid, 'b.png');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_invalid());
        $errors = $this->get_rendered_errors($form, 'attachments');
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('b.png', $errors[0]);
        $this->assertSame('Maximum number of files is 1', $errors[1]);

        $this->add_element(null, null, false);
        $draftid = file_get_unused_draft_itemid();
        $this->add_draft_file($draftid, 'a.txt', '/sub/');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_invalid());
        $this->assertSame(['Subfolders are not allowed'], $this->get_rendered_errors($form, 'attachments'));

        $this->add_element(null, null, true);
        $this->assertTrue($this->submit((string)$draftid)->is_valid());
    }

    public function test_save_area(): void {
        $area = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', null);
        $this->add_element(null, null, false, false, $area);

        $draftid = file_get_unused_draft_itemid();
        $this->add_draft_file($draftid, 'a.txt');
        $form = $this->submit((string)$draftid);
        $this->assertTrue($form->is_valid());
        $element = $form->get_element('attachments');

        try {
            $element->save_area();
            $this->fail('Exception expected for incomplete area');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('invalid', $e->getMessage());
        }

        $area->set_itemid(9);
        $element->save_area();
        $this->assertSame(['/a.txt'], $this->area_names($area));

        // Replacing the draft contents replaces the area contents.
        $other = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', 10);
        $element->export_to_file_area($other);
        $this->assertSame(['/a.txt'], $this->area_names($other));

        $draftid2 = file_get_unused_draft_itemid();
        $this->add_draft_file($draftid2, 'b.txt');
        $form = $this->submit((string)$draftid2);
        $form->get_element('attachments')->export_to_file_area($other);
        $this->assertSame(['/b.txt'], $this->area_names($other));

        // Stale draft area must not wipe existing files.
        $stale = file_get_unused_draft_itemid();
        $form = $this->submit((string)$stale);
        $form->get_element('attachments')->export_to_file_area($other);
        $this->assertDebuggingCalled();
        $this->assertSame(['/b.txt'], $this->area_names($other));

        // An empty draft for an empty area is a plain no-op.
        $empty = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', 11);
        $form->get_element('attachments')->export_to_file_area($empty);
        $this->assertSame([], $this->area_names($empty));
    }

    public function test_frozen(): void {
        $area = new file_area(system::instance(), 'tool_mulib', 'muform_fixture', 5);
        get_file_storage()->create_file_from_string([
            'contextid' => $area->get_contextid(), 'component' => 'tool_mulib', 'filearea' => 'muform_fixture',
            'itemid' => 5, 'filepath' => '/', 'filename' => 'existing.txt',
        ], 'x');
        $this->add_element(null, null, false, false, null, true);
        $form = $this->submit('12345', ['attachments' => $area]);
        $draftid = $form->get_data()->attachments;
        $this->assertNotSame(12345, $draftid);
        $html = $this->render($form);
        $this->assertStringContainsString('/draftfile.php/', $html);
        $this->assertStringContainsString('existing.txt</a></li>', $html);
        $this->assertStringNotContainsString('class="filemanager', $html);
        $this->assertStringNotContainsString(' name="attachments"', $html);

        $form = new simple_form($this->get_url(), []);
        $this->assertStringContainsString('No files', $this->render($form));
    }

    public function test_guest(): void {
        $this->add_element();
        $this->setGuestUser();
        $this->expectException(\core\exception\moodle_exception::class);
        new simple_form($this->get_url(), []);
    }
}
