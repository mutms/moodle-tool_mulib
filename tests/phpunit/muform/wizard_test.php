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

use core\exception\coding_exception;
use core\url;
use tool_mulib\muform\wizard;

/**
 * Wizard helper tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\wizard
 */
final class wizard_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_start_and_load(): void {
        global $DB, $USER;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $wizard = wizard::start('tool_mulib_test');
        $this->assertGreaterThan(0, $wizard->get_id());
        $this->assertSame([], $wizard->get_data());
        $record = $DB->get_record('tool_mulib_muform_wizard', ['id' => $wizard->get_id()], '*', MUST_EXIST);
        $this->assertSame((string)$user1->id, $record->userid);
        $this->assertSame('{}', $record->jsondata);
        $expected = sha1('tool_mulib_test/' . $user1->id . '/' . sesskey() . '/' . $record->timecreated);
        $this->assertSame($expected, $record->sessionhash);
        $this->assertEqualsWithDelta(time(), (int)$record->timecreated, 5);

        $loaded = wizard::load('tool_mulib_test', $wizard->get_id());
        $this->assertInstanceOf(wizard::class, $loaded);
        $this->assertSame($wizard->get_id(), $loaded->get_id());

        $this->assertNull(wizard::load('tool_mulib_test', 0));
        $this->assertNull(wizard::load('tool_mulib_test', -1));
        $this->assertNull(wizard::load('tool_mulib_test', $wizard->get_id() + 1000));

        // Other user cannot load it.
        $this->setUser($user2);
        $this->assertNull(wizard::load('tool_mulib_test', $wizard->get_id()));

        // Another wizard cannot load it.
        $this->assertNull(wizard::load('tool_mulib_other', $wizard->get_id()));
        $this->assertNull(wizard::load('', $wizard->get_id()));

        // New login session of the owner cannot load it either.
        $this->setUser($user1);
        $USER->sesskey = 'anothersession';
        $this->assertNull(wizard::load('tool_mulib_test', $wizard->get_id()));
        $this->assertTrue($DB->record_exists('tool_mulib_muform_wizard', ['id' => $wizard->get_id()]));
    }

    public function test_start_requires_user(): void {
        $this->setUser(0);
        $this->expectException(coding_exception::class);
        wizard::start('tool_mulib_test');
    }

    public function test_start_requires_name(): void {
        $this->setAdminUser();
        $this->expectException(coding_exception::class);
        wizard::start(' ');
    }

    public function test_start_refuses_guest(): void {
        $this->setGuestUser();
        $this->expectException(coding_exception::class);
        wizard::start('tool_mulib_test');
    }

    public function test_load_refuses_guest(): void {
        $this->setGuestUser();
        $this->assertNull(wizard::load('tool_mulib_test', 1));
    }

    public function test_purge_on_start(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $fresh = wizard::start('tool_mulib_test');
        $this->setUser($user2);
        $old = wizard::start('tool_mulib_test');
        $DB->set_field('tool_mulib_muform_wizard', 'timecreated', time() - wizard::TTL - 10, ['id' => $old->get_id()]);
        $recent = wizard::start('tool_mulib_test');
        $DB->set_field('tool_mulib_muform_wizard', 'timecreated', time() - wizard::TTL + 60, ['id' => $recent->get_id()]);

        $this->setUser($user1);
        wizard::start('tool_mulib_test');
        $this->assertTrue($DB->record_exists('tool_mulib_muform_wizard', ['id' => $fresh->get_id()]));
        $this->assertTrue($DB->record_exists('tool_mulib_muform_wizard', ['id' => $recent->get_id()]));
        $this->assertFalse($DB->record_exists('tool_mulib_muform_wizard', ['id' => $old->get_id()]));
    }

    public function test_data_and_delete(): void {
        global $DB;

        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test');
        $wizard->set_data(['rows' => [['a', 'ž']], 'options' => ['skip' => 1], 'url' => 'https://x/y']);
        $this->assertSame(
            '{"rows":[["a","ž"]],"options":{"skip":1},"url":"https://x/y"}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );
        $this->assertSame(['rows' => [['a', 'ž']], 'options' => ['skip' => 1], 'url' => 'https://x/y'], $wizard->get_data());
        $this->assertSame($wizard->get_data(), wizard::load('tool_mulib_test', $wizard->get_id())->get_data());

        $wizard->set_data([]);
        $this->assertSame([], $wizard->get_data());

        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '[1, 2', ['id' => $wizard->get_id()]);
        $this->assertSame([], wizard::load('tool_mulib_test', $wizard->get_id())->get_data());
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '"text"', ['id' => $wizard->get_id()]);
        $this->assertSame([], wizard::load('tool_mulib_test', $wizard->get_id())->get_data());

        $wizard->delete();
        $this->assertFalse($DB->record_exists('tool_mulib_muform_wizard', ['id' => $wizard->get_id()]));
        $this->assertNull(wizard::load('tool_mulib_test', $wizard->get_id()));
    }

    public function test_get_url(): void {
        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test');
        $pageurl = new url('/auth/musaml/management/user_upload.php', ['id' => 5, 'stage' => 'old', 'wizard' => 99]);

        $url = $wizard->get_url($pageurl);
        $this->assertStringEndsWith('/auth/musaml/management/user_upload.php', $url->get_path());
        $this->assertSame(['id' => '5', 'wizard' => (string)$wizard->get_id()], $url->params());

        $url = $wizard->get_url($pageurl, 'columns');
        $this->assertSame(['id' => '5', 'wizard' => (string)$wizard->get_id(), 'stage' => 'columns'], $url->params());

        // The page URL is not modified.
        $this->assertSame('99', $pageurl->param('wizard'));
    }

    public function test_resolve_stages(): void {
        $names = ['source', 'columns', 'options'];
        $valid = ['source' => true, 'columns' => true, 'options' => true];
        $isvalid = function (string $name) use (&$valid, &$calls): bool {
            $calls[] = $name;
            return $valid[$name];
        };

        // Everything valid, the last stage is current.
        $calls = [];
        $stages = wizard::resolve_stages($names, $isvalid, null);
        $this->assertSame([
            ['name' => 'source', 'label' => 'source', 'valid' => true, 'current' => false],
            ['name' => 'columns', 'label' => 'columns', 'valid' => true, 'current' => false],
            ['name' => 'options', 'label' => 'options', 'valid' => true, 'current' => true],
        ], $stages);
        $this->assertSame('options', wizard::current_stage($stages));
        $this->assertSame($names, $calls);

        // Requested earlier stage wins when valid.
        $stages = wizard::resolve_stages($names, $isvalid, 'source');
        $this->assertSame('source', wizard::current_stage($stages));
        $this->assertTrue($stages[2]['valid']);
        $this->assertFalse($stages[2]['current']);

        // Unknown stage is ignored.
        $stages = wizard::resolve_stages($names, $isvalid, 'xyz');
        $this->assertSame('options', wizard::current_stage($stages));

        // First invalid stage is current and later stages are not validated.
        $valid['columns'] = false;
        $calls = [];
        $stages = wizard::resolve_stages($names, $isvalid, null);
        $this->assertSame([
            ['name' => 'source', 'label' => 'source', 'valid' => true, 'current' => false],
            ['name' => 'columns', 'label' => 'columns', 'valid' => false, 'current' => true],
            ['name' => 'options', 'label' => 'options', 'valid' => false, 'current' => false],
        ], $stages);
        $this->assertSame(['source', 'columns'], $calls);

        // Requested later stage is clamped, requested current is fine, requested earlier moves back.
        $this->assertSame('columns', wizard::current_stage(wizard::resolve_stages($names, $isvalid, 'options')));
        $this->assertSame('columns', wizard::current_stage(wizard::resolve_stages($names, $isvalid, 'columns')));
        $this->assertSame('source', wizard::current_stage(wizard::resolve_stages($names, $isvalid, 'source')));

        // Nothing valid.
        $valid['source'] = false;
        $stages = wizard::resolve_stages($names, $isvalid, 'options');
        $this->assertSame('source', wizard::current_stage($stages));
        $this->assertSame([false, false, false], array_column($stages, 'valid'));

        // Labels come from string keys.
        $stages = wizard::resolve_stages(['source' => 'Source data', 'columns' => 'Columns'], fn() => true, null);
        $this->assertSame('Source data', $stages[0]['label']);
        $this->assertSame('columns', $stages[1]['name']);
        $this->assertSame('columns', wizard::current_stage($stages));
    }

    public function test_resolve_stages_requires_names(): void {
        $this->expectException(coding_exception::class);
        wizard::resolve_stages([], fn() => true, null);
    }

    public function test_current_stage_requires_current(): void {
        $this->expectException(coding_exception::class);
        wizard::current_stage([['name' => 'x', 'label' => 'x', 'valid' => true, 'current' => false]]);
    }

    public function test_render(): void {
        global $PAGE;

        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/phpunit/muform/fixtures/simple_form.php');

        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test');
        $pageurl = new url('/auth/musaml/management/user_upload.php', ['id' => 5]);
        $form = new \tool_mulib\phpunit\muform\fixtures\simple_form($wizard->get_url($pageurl, 'columns'), []);
        $valid = ['source' => true, 'columns' => true, 'options' => false];
        $stages = wizard::resolve_stages(
            ['source' => 'Source <b>data</b>', 'columns' => 'Columns', 'options' => 'Options'],
            fn(string $name) => $valid[$name],
            'columns'
        );
        $html = $wizard->render($PAGE->get_renderer('core'), $stages, $pageurl, $form);

        $this->assertStringContainsString('<nav aria-label="' . get_string('muform_steps', 'tool_mulib') . '"', $html);
        $sourceurl = $wizard->get_url($pageurl, 'source')->out(false);
        $this->assertStringContainsString('<a href="' . s($sourceurl) . '">Source &#60;b&#62;data&#60;/b&#62;</a>', $html);
        $this->assertStringContainsString('fw-bold" aria-current="step"', $html);
        $this->assertStringContainsString('<span class="badge rounded-pill text-bg-primary">2</span>', $html);
        $this->assertStringContainsString('<span>Columns</span>', $html);
        // The first invalid stage is reachable, resolve_stages() would accept it as requested.
        $optionsurl = $wizard->get_url($pageurl, 'options')->out(false);
        $this->assertStringContainsString('<a href="' . s($optionsurl) . '">Options</a>', $html);
        $this->assertSame(2, substr_count($html, '<a href='));
        $this->assertStringNotContainsString('text-muted', $html);

        // Later invalid stages are muted text.
        $stages = wizard::resolve_stages(['source', 'columns', 'options'], fn() => false, null);
        $html = $wizard->render($PAGE->get_renderer('core'), $stages, $pageurl, $form);
        $this->assertSame(0, substr_count($html, '<a href='));
        $this->assertSame(2, substr_count($html, 'text-muted'));
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertStringContainsString('class="muform-wizard-form col-md-9"', $html);
        $this->assertStringContainsString('<div class="card-body">', $html);
        $this->assertStringContainsString('<form id="tool_mulib-phpunit-muform-fixtures-simple_form', $html);
        $this->assertStringContainsString('action="' . s($wizard->get_url($pageurl, 'columns')->out(false)) . '"', $html);
    }
}
