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
    /** @var array stages of the tested wizard */
    private const array STAGES = ['source' => 'Source', 'columns' => 'Columns', 'options' => 'Options'];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        unset($_GET[wizard::PARAM], $_GET[wizard::STAGE_PARAM]);
    }

    public function test_start_and_load(): void {
        global $DB, $USER;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $this->assertGreaterThan(0, $wizard->get_id());
        $this->assertSame([], $wizard->get_data());
        $record = $DB->get_record('tool_mulib_muform_wizard', ['id' => $wizard->get_id()], '*', MUST_EXIST);
        $this->assertSame((string)$user1->id, $record->userid);
        $this->assertSame('{"data":{},"completed":[]}', $record->jsondata);
        $expected = sha1('tool_mulib_test/source,columns,options/' . $user1->id . '/' . sesskey() . '/' . $record->timecreated);
        $this->assertSame($expected, $record->sessionhash);
        $this->assertEqualsWithDelta(time(), (int)$record->timecreated, 5);

        $loaded = self::load('tool_mulib_test', $wizard->get_id());
        $this->assertInstanceOf(wizard::class, $loaded);
        $this->assertSame($wizard->get_id(), $loaded->get_id());
        // Stage names must match, labels may differ, for example in another language.
        $this->assertNull(self::load('tool_mulib_test', $wizard->get_id(), ['source' => 'Source', 'options' => 'Options']));
        $relabelled = ['source' => 'A', 'columns' => 'B', 'options' => 'C'];
        $this->assertNotNull(self::load('tool_mulib_test', $wizard->get_id(), $relabelled));

        $this->assertNull(self::load('tool_mulib_test', 0));
        $this->assertNull(self::load('tool_mulib_test', -1));
        $this->assertNull(self::load('tool_mulib_test', $wizard->get_id() + 1000));

        // Other user cannot load it.
        $this->setUser($user2);
        $this->assertNull(self::load('tool_mulib_test', $wizard->get_id()));

        // Another wizard or other stages cannot load it.
        $this->assertNull(self::load('tool_mulib_other', $wizard->get_id()));

        $this->assertNull(self::load('', $wizard->get_id()));

        // New login session of the owner cannot load it either.
        $this->setUser($user1);
        $USER->sesskey = 'anothersession';
        $this->assertNull(self::load('tool_mulib_test', $wizard->get_id()));
        $this->assertTrue($DB->record_exists('tool_mulib_muform_wizard', ['id' => $wizard->get_id()]));
    }

    public function test_start_requires_user(): void {
        $this->setUser(0);
        $this->expectException(coding_exception::class);
        wizard::start('tool_mulib_test', self::STAGES);
    }

    public function test_start_requires_name(): void {
        $this->setAdminUser();
        $this->expectException(coding_exception::class);
        wizard::start(' ', self::STAGES);
    }

    public function test_start_refuses_guest(): void {
        $this->setGuestUser();
        $this->expectException(coding_exception::class);
        wizard::start('tool_mulib_test', self::STAGES);
    }

    public function test_load_refuses_guest(): void {
        $this->setGuestUser();
        $this->assertNull(self::load('tool_mulib_test', 1));
    }

    public function test_purge_on_start(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $fresh = wizard::start('tool_mulib_test', self::STAGES);
        $this->setUser($user2);
        $old = wizard::start('tool_mulib_test', self::STAGES);
        $DB->set_field('tool_mulib_muform_wizard', 'timecreated', time() - wizard::TTL - 10, ['id' => $old->get_id()]);
        $recent = wizard::start('tool_mulib_test', self::STAGES);
        $DB->set_field('tool_mulib_muform_wizard', 'timecreated', time() - wizard::TTL + 60, ['id' => $recent->get_id()]);

        $this->setUser($user1);
        wizard::start('tool_mulib_test', self::STAGES);
        $this->assertTrue($DB->record_exists('tool_mulib_muform_wizard', ['id' => $fresh->get_id()]));
        $this->assertTrue($DB->record_exists('tool_mulib_muform_wizard', ['id' => $recent->get_id()]));
        $this->assertFalse($DB->record_exists('tool_mulib_muform_wizard', ['id' => $old->get_id()]));
    }

    public function test_data_and_delete(): void {
        global $DB;

        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $wizard->set_data(['rows' => [['a', 'ž']], 'options' => ['skip' => 1], 'url' => 'https://x/y']);
        $this->assertSame(
            '{"data":{"rows":[["a","ž"]],"options":{"skip":1},"url":"https://x/y"},"completed":[]}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );
        $this->assertSame(['rows' => [['a', 'ž']], 'options' => ['skip' => 1], 'url' => 'https://x/y'], $wizard->get_data());
        $this->assertSame($wizard->get_data(), self::load('tool_mulib_test', $wizard->get_id())->get_data());

        $wizard->set_data([]);
        $this->assertSame([], $wizard->get_data());
        $this->assertSame(
            '{"data":{},"completed":[]}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );

        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '[1, 2', ['id' => $wizard->get_id()]);
        $this->assertSame([], self::load('tool_mulib_test', $wizard->get_id())->get_data());
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '"text"', ['id' => $wizard->get_id()]);
        $this->assertSame([], self::load('tool_mulib_test', $wizard->get_id())->get_data());
        // Rows stored before stages were tracked start again.
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', '{"rows":[]}', ['id' => $wizard->get_id()]);
        $this->assertSame([], self::load('tool_mulib_test', $wizard->get_id())->get_data());

        $wizard->delete();
        $this->assertFalse($DB->record_exists('tool_mulib_muform_wizard', ['id' => $wizard->get_id()]));
        $this->assertNull(self::load('tool_mulib_test', $wizard->get_id()));
    }

    public function test_get_url(): void {
        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $pageurl = new url('/auth/musaml/management/user_upload.php', ['id' => 5, 'stage' => 'old', 'wizard' => 99]);

        $url = $wizard->get_url($pageurl);
        $this->assertStringEndsWith('/auth/musaml/management/user_upload.php', $url->get_path());
        $this->assertSame(['id' => '5', 'wizard' => (string)$wizard->get_id()], $url->params());

        $url = $wizard->get_url($pageurl, 'columns');
        $this->assertSame(['id' => '5', 'wizard' => (string)$wizard->get_id(), 'stage' => 'columns'], $url->params());

        // The page URL is not modified.
        $this->assertSame('99', $pageurl->param('wizard'));
    }

    /**
     * Load a wizard with the id in the page URL.
     *
     * @param string $name
     * @param int $id
     * @param array $stages
     * @return wizard|null
     */
    private static function load(string $name, int $id, array $stages = self::STAGES): ?wizard {
        $_GET[wizard::PARAM] = $id;
        return wizard::load($name, $stages);
    }

    /**
     * Resolve stages with the requested stage in the page URL.
     *
     * @param wizard $wizard
     * @param callable $isvalid
     * @param string|null $requested
     * @param array $skipped
     * @return string current stage
     */
    private function resolve(wizard $wizard, callable $isvalid, ?string $requested, array $skipped = []): string {
        unset($_GET[wizard::STAGE_PARAM]);
        if ($requested !== null) {
            $_GET[wizard::STAGE_PARAM] = $requested;
        }
        return $wizard->resolve_stages($isvalid, $skipped);
    }

    public function test_invalid_stages(): void {
        $this->setAdminUser();
        $invalid = [[], ['source', 'columns'], ['source' => 1], ['Source' => 'Source'], ['source_1' => 'Source']];
        foreach ($invalid as $stages) {
            try {
                wizard::start('tool_mulib_test', $stages);
                $this->fail('Exception expected');
            } catch (coding_exception $e) {
                $this->assertStringContainsString('stage', $e->getMessage());
            }
            try {
                self::load('tool_mulib_test', 1, $stages);
                $this->fail('Exception expected');
            } catch (coding_exception $e) {
                $this->assertStringContainsString('stage', $e->getMessage());
            }
        }
    }

    public function test_resolve_stages(): void {
        global $DB;

        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $valid = ['source' => true, 'columns' => true, 'options' => true];
        $calls = [];
        $isvalid = function (string $name) use (&$valid, &$calls): bool {
            $calls[] = $name;
            return $valid[$name];
        };

        // Valid data does not complete stages, the user must submit each of them.
        $this->assertSame('source', $this->resolve($wizard, $isvalid, null));
        $this->assertFalse($wizard->is_finished());
        $this->assertNull($wizard->get_previous_stage());
        $this->assertSame([], $calls);
        $this->assertSame('source', $this->resolve($wizard, $isvalid, 'columns'));

        $wizard->complete_stage('source', ['x' => 1]);
        $this->assertSame(['x' => 1], $wizard->get_data());
        $this->assertSame(
            '{"data":{"x":1},"completed":["source"]}',
            $DB->get_field('tool_mulib_muform_wizard', 'jsondata', ['id' => $wizard->get_id()])
        );
        $calls = [];
        $this->assertSame('columns', $this->resolve($wizard, $isvalid, null));
        $this->assertSame('source', $wizard->get_previous_stage());
        $this->assertSame(['source'], $calls);

        // Requested later stage is clamped, requested current is fine, requested earlier moves back.
        $this->assertSame('columns', $this->resolve($wizard, $isvalid, 'options'));
        $this->assertSame('columns', $this->resolve($wizard, $isvalid, 'columns'));
        $this->assertSame('source', $this->resolve($wizard, $isvalid, 'source'));
        $this->assertSame('columns', $this->resolve($wizard, $isvalid, 'xyz'));

        $wizard->complete_stage('columns', ['x' => 2]);
        $wizard->complete_stage('options', ['x' => 3]);
        $this->assertSame('options', $this->resolve($wizard, $isvalid, null));
        $this->assertTrue($wizard->is_finished());
        // Users went back to look at a stage, the wizard must not finish.
        $this->assertSame('options', $this->resolve($wizard, $isvalid, 'options'));
        $this->assertFalse($wizard->is_finished());
        $this->assertSame('source', $this->resolve($wizard, $isvalid, 'source'));

        // Completed stage with invalid data is current again, later stages are not validated.
        $valid['columns'] = false;
        $calls = [];
        $this->assertSame('columns', $this->resolve($wizard, $isvalid, null));
        $this->assertSame(['source', 'columns'], $calls);
        $valid['columns'] = true;

        // Submitting an earlier stage again means later stages must be completed again.
        $this->resolve($wizard, $isvalid, 'source');
        $wizard->complete_stage('source', ['x' => 4]);
        $this->assertSame('columns', $this->resolve($wizard, $isvalid, null));

        // Only true means valid, sloppy callbacks returning other values do not complete stages.
        foreach ([1, 'yes', [1], null] as $result) {
            $this->assertSame('source', $this->resolve($wizard, fn() => $result, null));
        }

        // Defaults stored when starting do not complete anything.
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $wizard->set_data(['default' => 'x']);
        $this->assertSame('source', $this->resolve($wizard, fn() => true, null));
    }

    public function test_skipped_stages(): void {
        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $skip = ['columns'];

        $this->resolve($wizard, fn() => true, null, $skip);
        $wizard->complete_stage('source', []);
        // Skipped stages count as completed and cannot be requested.
        $this->assertSame('options', $this->resolve($wizard, fn() => true, null, $skip));
        $this->assertSame('source', $wizard->get_previous_stage());
        $this->assertSame('options', $this->resolve($wizard, fn() => true, 'columns', $skip));
        $this->assertFalse($wizard->is_finished());

        $wizard->complete_stage('options', []);
        $this->assertSame('options', $this->resolve($wizard, fn() => true, null, $skip));
        $this->assertTrue($wizard->is_finished());

        // Without skipping the stage must be completed.
        $this->assertSame('columns', $this->resolve($wizard, fn() => true, null));

        // Skipped last stage, the last stage that is not skipped is current when all are completed.
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $this->resolve($wizard, fn() => true, null, ['options']);
        $wizard->complete_stage('columns', []);
        $this->assertSame('columns', $this->resolve($wizard, fn() => true, null, ['options']));
        $this->assertTrue($wizard->is_finished());

        try {
            $this->resolve($wizard, fn() => true, null, ['xyz']);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Unknown skipped wizard stage', $e->getMessage());
        }
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('All wizard stages are skipped');
        $this->resolve($wizard, fn() => true, null, array_keys(self::STAGES));
    }

    public function test_stages_must_be_resolved(): void {
        global $PAGE;

        $this->setAdminUser();
        $wizard = wizard::start('tool_mulib_test', self::STAGES);
        $calls = [
            fn() => $wizard->complete_stage('source', []),
            fn() => $wizard->is_finished(),
            fn() => $wizard->get_previous_stage(),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Exception expected');
            } catch (coding_exception $e) {
                $this->assertStringContainsString('Wizard stages are not resolved yet', $e->getMessage());
            }
        }
        $this->resolve($wizard, fn() => true, null);
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Unknown wizard stage: xyz');
        $wizard->complete_stage('xyz', []);
    }

    public function test_render(): void {
        global $PAGE, $CFG;
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/phpunit/muform/fixtures/simple_form.php');

        $this->setAdminUser();
        $stages = ['source' => 'Source <b>data</b>', 'columns' => 'Columns', 'options' => 'Options'];
        $wizard = wizard::start('tool_mulib_test', $stages);
        $pageurl = new url('/auth/musaml/management/user_upload.php', ['id' => 5]);
        $form = new \tool_mulib\phpunit\muform\fixtures\simple_form($wizard->get_url($pageurl, 'columns'), []);
        $this->resolve($wizard, fn() => true, null);
        $wizard->complete_stage('columns', []);
        $valid = ['source' => true, 'columns' => true, 'options' => false];
        $this->resolve($wizard, fn(string $name) => $valid[$name], 'columns');
        $html = $wizard->render($PAGE->get_renderer('core'), $pageurl, $form);

        $this->assertStringContainsString('<nav aria-label="' . get_string('muform_steps', 'tool_mulib') . '"', $html);
        $sourceurl = $wizard->get_url($pageurl, 'source')->out(false);
        $link = '<a class="muform-stepper-label stretched-link" href="';
        $this->assertStringContainsString($link . s($sourceurl) . '">Source &#60;b&#62;data&#60;/b&#62;</a>', $html);
        $this->assertStringContainsString('<li class="muform-stepper-item active current" aria-current="step">', $html);
        $this->assertStringContainsString('<span class="muform-stepper-label">Columns</span>', $html);
        // The first stage that is not completed is reachable.
        $optionsurl = $wizard->get_url($pageurl, 'options')->out(false);
        $this->assertStringContainsString($link . s($optionsurl) . '">Options</a>', $html);
        $this->assertSame(2, substr_count($html, $link));
        $this->assertSame(2, substr_count($html, 'muform-stepper-item active'));
        $this->assertStringNotContainsString('disabled', $html);
        $this->assertStringContainsString('data-muform-wizard-nav', $html);
        // Completed stages would be lost when leaving the page.
        $this->assertTrue($form->is_dirty());
        $this->assertStringContainsString('data-muform-dirty="1"', $html);

        // Later stages are disabled text, nothing is lost on the first stage.
        $wizard = wizard::start('tool_mulib_test', $stages);
        $form = new \tool_mulib\phpunit\muform\fixtures\simple_form($wizard->get_url($pageurl, 'source'), []);
        $this->resolve($wizard, fn() => true, null);
        $html = $wizard->render($PAGE->get_renderer('core'), $pageurl, $form);
        $this->assertFalse($form->is_dirty());
        $this->assertStringNotContainsString('data-muform-dirty', $html);
        $this->assertSame(0, substr_count($html, $link));
        $this->assertSame(2, substr_count($html, '<li class="muform-stepper-item disabled">'));
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertStringContainsString('class="muform-wizard-form card"', $html);
        $this->assertStringContainsString('<div class="card-body">', $html);
        $this->assertStringContainsString('<form id="tool_mulib-phpunit-muform-fixtures-simple_form', $html);

        // Skipped stages are muted, never links.
        $this->resolve($wizard, fn() => true, null, ['columns']);
        $wizard->complete_stage('source', []);
        $this->resolve($wizard, fn() => true, null, ['columns']);
        $html = $wizard->render($PAGE->get_renderer('core'), $pageurl, $form);
        $this->assertStringContainsString('<li class="muform-stepper-item skipped disabled">', $html);
        $this->assertStringContainsString(
            '<span class="muform-stepper-label">Columns<span class="visually-hidden"> ('
            . get_string('muform_stepskipped', 'tool_mulib') . ')</span></span>',
            $html
        );
        $this->assertSame(1, substr_count($html, $link));
    }
}
