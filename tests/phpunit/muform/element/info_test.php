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
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\inforawhtml;
use tool_mulib\phpunit\muform\fixtures\simple_form;
use tool_mulib\phpunit\muform\muform_testcase;

/**
 * Info and inforawhtml element tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\element\info
 * @covers \tool_mulib\muform\element\inforawhtml
 */
final class info_test extends muform_testcase {
    public function test_info(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new info('summary', 'Summary', "Default <b>text</b> & 'x'\nline 2", info::PLAIN));
            $hook->form->add(new info('title', 'Title', '<span lang="en" class="multilang">Title</span> <b>x</b>'));
            $hook->form->add(new info('details', 'Details', 'Markdown *default*', info::MARKDOWN));
            $hook->form->add(new info('html', 'Html', '<p>Some <b>html</b><script>alert(1)</script></p>', info::HTML));
            $hook->form->add(new info('stored', 'Stored', 'Plain *until* stored format', info::PLAIN));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $form = new simple_form($this->get_url(), []);
        $this->assertArrayNotHasKey('summary', (array)$form->get_non_validated_data());
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        // PLAIN is escaped, line breaks kept.
        $this->assertStringContainsString(
            '<div class="form-control-plaintext text-break" id="id_summary' . $suffix . '">'
            . 'Default &lt;b&gt;text&lt;/b&gt; &amp; &#039;x&#039;<br>' . "\n" . 'line 2</div>',
            $html
        );
        // STRING is the default, format_string() strips tags.
        $this->assertStringContainsString('id="id_title' . $suffix . '">Title x</div>', $html);
        $this->assertStringContainsString('<em>default</em>', $html);
        $this->assertStringContainsString('<p>Some <b>html</b></p>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Plain *until* stored format', $html);
        $this->assertStringContainsString('<span id="id_summary' . $suffix . '_label"', $html);

        // Text from current data wins, submitted data is ignored, only TEXTFORMAT reads the stored format.
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new info('summary', 'Summary', null, info::TEXTFORMAT));
            $hook->form->add(new info('other', 'Other', null, info::PLAIN));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->simulate_post(simple_form::class, ['name' => 'x', 'summary' => '<b>posted</b>', 'submit' => '1']);
        $form = new simple_form($this->get_url(), [
            'summary' => '<p>Current <b>html</b></p>', 'summaryformat' => FORMAT_HTML,
            'other' => '<b>other</b>', 'otherformat' => FORMAT_HTML,
        ]);
        $this->assertTrue($form->is_valid());
        $html = $this->render($form);
        $this->assertStringContainsString('<p>Current <b>html</b></p>', $html);
        $this->assertStringContainsString('&lt;b&gt;other&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('posted', $html);

        // TEXTFORMAT without a stored format uses FORMAT_HTML.
        $form = new simple_form($this->get_url(), ['summary' => '<b>no format</b>']);
        $html = $this->render($form);
        $this->assertStringContainsString('<b>no format</b>', $html);
    }

    public function test_invalid_format(): void {
        $this->expectException(coding_exception::class);
        new info('summary', 'Summary', null, 'html5');
    }

    public function test_inforawhtml(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new inforawhtml('legend', 'Legend', '<span class="badge">Active</span>'));
            $hook->form->add(new inforawhtml('banner', '', '<div class="alert">Wide</div>'));
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);

        $form = new simple_form($this->get_url(), ['legend' => 'ignored', 'banner' => 'ignored']);
        $this->assertArrayNotHasKey('legend', (array)$form->get_non_validated_data());
        $html = $this->render($form);
        $suffix = $form->get_idsuffix();
        $this->assertStringContainsString(
            '<div class="form-control-plaintext" id="id_legend' . $suffix . '"><span class="badge">Active</span></div>',
            $html
        );
        $this->assertStringContainsString(
            '<div class="col-12" id="id_banner' . $suffix . '"><div class="alert">Wide</div></div>',
            $html
        );
        $this->assertStringNotContainsString('id="id_banner' . $suffix . '_label"', $html);
        $this->assertStringNotContainsString('ignored', $html);
    }

    public function test_no_children(): void {
        $hook = function (muform_definition $hook): void {
            $hook->form->add(new info('summary', 'Summary'));
            $hook->form->add(new inforawhtml('inner', 'Inner', 'x'), 'summary');
        };
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(muform_definition::class, $hook);
        $this->expectException(coding_exception::class);
        new simple_form($this->get_url(), []);
    }
}
