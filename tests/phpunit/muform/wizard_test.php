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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Wizard helper tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\muform\wizard::class)]
#[Group('MuTMS')]
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
        $this->assertSame('{"data":{},"completed":[]}', $record->jsondata);
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
            '{"data":{"rows":[["a","ž"]],"options":{"skip":1},"url":"https://x/y"},"completed":[]}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );
        $this->assertSame(['rows' => [['a', 'ž']], 'options' => ['skip' => 1], 'url' => 'https://x/y'], $wizard->get_data());
        $this->assertSame($wizard->get_data(), wizard::load('tool_mulib_test', $wizard->get_id())->get_data());

        $wizard->set_data([]);
        $this->assertSame([], $wizard->get_data());
        $this->assertSame(
            '{"data":{},"completed":[]}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );

        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '[1, 2', ['id' => $wizard->get_id()]);
        $this->assertSame([], wizard::load('tool_mulib_test', $wizard->get_id())->get_data());
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '"text"', ['id' => $wizard->get_id()]);
        $this->assertSame([], wizard::load('tool_mulib_test', $wizard->get_id())->get_data());
        // Rows stored before stages were tracked start again.
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '{"rows":[]}', ['id' => $wizard->get_id()]);
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
        global $DB;

        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test');
        $names = ['source' => 'source', 'columns' => 'columns', 'options' => 'options'];
        $valid = ['source' => true, 'columns' => true, 'options' => true];
        $calls = [];
        $isvalid = function (string $name) use (&$valid, &$calls): bool {
            $calls[] = $name;
            return $valid[$name];
        };

        // Valid data does not complete stages, the user must submit each of them.
        $stages = $wizard->resolve_stages($names, $isvalid, null);
        $this->assertSame([
            ['name' => 'source', 'label' => 'source', 'completed' => false, 'current' => true],
            ['name' => 'columns', 'label' => 'columns', 'completed' => false, 'current' => false],
            ['name' => 'options', 'label' => 'options', 'completed' => false, 'current' => false],
        ], $stages);
        $this->assertFalse(wizard::is_finished($stages));
        $this->assertSame([], $calls);
        $this->assertSame('source', wizard::current_stage($wizard->resolve_stages($names, $isvalid, 'columns')));

        $wizard->complete_stage('source', ['x' => 1]);
        $this->assertSame(['x' => 1], $wizard->get_data());
        $this->assertSame(
            '{"data":{"x":1},"completed":["source"]}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );
        $calls = [];
        $stages = $wizard->resolve_stages($names, $isvalid, null);
        $this->assertSame('columns', wizard::current_stage($stages));
        $this->assertSame([true, false, false], array_column($stages, 'completed'));
        $this->assertSame(['source'], $calls);

        // Requested later stage is clamped, requested current is fine, requested earlier moves back.
        $this->assertSame('columns', wizard::current_stage($wizard->resolve_stages($names, $isvalid, 'options')));
        $this->assertSame('columns', wizard::current_stage($wizard->resolve_stages($names, $isvalid, 'columns')));
        $this->assertSame('source', wizard::current_stage($wizard->resolve_stages($names, $isvalid, 'source')));
        $this->assertSame('columns', wizard::current_stage($wizard->resolve_stages($names, $isvalid, 'xyz')));

        $wizard->complete_stage('columns', ['x' => 2]);
        $wizard->complete_stage('options', ['x' => 3]);
        $stages = $wizard->resolve_stages($names, $isvalid, null);
        $this->assertSame('options', wizard::current_stage($stages));
        $this->assertTrue(wizard::is_finished($stages));
        $stages = $wizard->resolve_stages($names, $isvalid, 'source');
        $this->assertSame('source', wizard::current_stage($stages));
        $this->assertTrue($stages[2]['completed']);

        // Completed stage with invalid data is current again, later stages are not validated.
        $valid['columns'] = false;
        $calls = [];
        $stages = $wizard->resolve_stages($names, $isvalid, null);
        $this->assertSame([true, false, false], array_column($stages, 'completed'));
        $this->assertSame('columns', wizard::current_stage($stages));
        $this->assertSame(['source', 'columns'], $calls);
        $valid['columns'] = true;

        // Submitting an earlier stage again means later stages must be confirmed again.
        $wizard->resolve_stages($names, $isvalid, 'source');
        $wizard->complete_stage('source', ['x' => 4]);
        $stages = $wizard->resolve_stages($names, $isvalid, null);
        $this->assertSame('columns', wizard::current_stage($stages));
        $this->assertSame([true, false, false], array_column($stages, 'completed'));

        // Stages that do not apply are simply left out.
        $short = ['source' => 'source', 'options' => 'options'];
        $stages = $wizard->resolve_stages($short, $isvalid, null);
        $this->assertSame('options', wizard::current_stage($stages));
        $wizard->complete_stage('options', []);
        $this->assertTrue(wizard::is_finished($wizard->resolve_stages($short, $isvalid, null)));

        // Only true means valid, sloppy callbacks returning other values do not complete stages.
        foreach ([1, 'yes', [1], null] as $result) {
            $stages = $wizard->resolve_stages($short, fn() => $result, null);
            $this->assertSame('source', wizard::current_stage($stages));
        }

        // Labels are indexed by stage names.
        $stages = $wizard->resolve_stages(['source' => 'Source data', 'columns' => 'Columns'], fn() => true, null);
        $this->assertSame('source', $stages[0]['name']);
        $this->assertSame('Source data', $stages[0]['label']);
        $this->assertSame('columns', $stages[1]['name']);
        $this->assertSame('columns', wizard::current_stage($stages));
        foreach ([['source', 'columns'], ['source' => 1]] as $labels) {
            try {
                $wizard->resolve_stages($labels, fn() => true, null);
                $this->fail('Exception expected');
            } catch (coding_exception $e) {
                $this->assertStringContainsString('Wizard stages must be labels indexed by stage names', $e->getMessage());
            }
        }

        // Defaults stored when starting do not complete anything.
        $wizard = wizard::start('tool_mulib_test');
        $wizard->set_data(['default' => 'x']);
        $this->assertSame('source', wizard::current_stage($wizard->resolve_stages($names, fn() => true, null)));
    }

    public function test_resolve_stages_requires_names(): void {
        $this->setAdminUser();
        $this->expectException(coding_exception::class);
        wizard::start('tool_mulib_test')->resolve_stages([], fn() => true, null);
    }

    public function test_complete_stage_requires_resolved_stages(): void {
        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test');
        try {
            $wizard->complete_stage('source', []);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Stages must be resolved before completing a stage', $e->getMessage());
        }
        $wizard->resolve_stages(['source' => 'Source'], fn() => true, null);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Unknown wizard stage: xyz');
        $wizard->complete_stage('xyz', []);
    }

    public function test_current_stage_requires_current(): void {
        $this->expectException(coding_exception::class);
        wizard::current_stage([['name' => 'x', 'label' => 'x', 'completed' => true, 'current' => false]]);
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
        $wizard->resolve_stages(['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'], fn() => true, null);
        $wizard->complete_stage('columns', []);
        $stages = $wizard->resolve_stages(
            ['source' => 'Source <b>data</b>', 'columns' => 'Columns', 'options' => 'Options'],
            fn(string $name) => $valid[$name],
            'columns'
        );
        $html = $wizard->render($PAGE->get_renderer('core'), $stages, $pageurl, $form);

        $this->assertStringContainsString('<nav aria-label="' . get_string('muform_steps', 'tool_mulib') . '"', $html);
        $sourceurl = $wizard->get_url($pageurl, 'source')->out(false);
        $link = '<a class="muform-stepper-label stretched-link" href="';
        $this->assertStringContainsString($link . s($sourceurl) . '">Source &#60;b&#62;data&#60;/b&#62;</a>', $html);
        $this->assertStringContainsString('<li class="muform-stepper-item active current" aria-current="step">', $html);
        $this->assertStringContainsString('<span class="muform-stepper-label">Columns</span>', $html);
        // The first invalid stage is reachable, resolve_stages() would accept it as requested.
        $optionsurl = $wizard->get_url($pageurl, 'options')->out(false);
        $this->assertStringContainsString($link . s($optionsurl) . '">Options</a>', $html);
        $this->assertSame(2, substr_count($html, $link));
        $this->assertSame(2, substr_count($html, 'muform-stepper-item active'));
        $this->assertStringNotContainsString('disabled', $html);
        $this->assertStringContainsString('data-muform-wizard-nav', $html);
        // Completed stages would be lost when leaving the page.
        $this->assertTrue($form->is_dirty());
        $this->assertStringContainsString('data-muform-dirty="1"', $html);

        // Later invalid stages are disabled text, nothing is lost on the first stage.
        $form = new \tool_mulib\phpunit\muform\fixtures\simple_form($wizard->get_url($pageurl, 'columns'), []);
        $labels = ['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'];
        $stages = $wizard->resolve_stages($labels, fn() => false, null);
        $html = $wizard->render($PAGE->get_renderer('core'), $stages, $pageurl, $form);
        $this->assertFalse($form->is_dirty());
        $this->assertStringNotContainsString('data-muform-dirty', $html);
        $this->assertSame(0, substr_count($html, $link));
        $this->assertSame(2, substr_count($html, '<li class="muform-stepper-item disabled">'));
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertStringContainsString('class="muform-wizard-form card"', $html);
        $this->assertStringContainsString('<div class="card-body">', $html);
        $this->assertStringContainsString('<form id="tool_mulib-phpunit-muform-fixtures-simple_form', $html);
        $this->assertStringContainsString('action="' . s($wizard->get_url($pageurl, 'columns')->out(false)) . '"', $html);
    }
}
