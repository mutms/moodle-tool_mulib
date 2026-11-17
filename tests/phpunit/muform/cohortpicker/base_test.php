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

namespace tool_mulib\phpunit\muform\cohortpicker;

use tool_mulib\muform\cohortpicker\base;
use tool_mulib\muform\cohortpicker\context_cohorts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cohorts picker family tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base::class)]
#[CoversClass(context_cohorts::class)]
#[Group('MuTMS')]
final class base_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Source with current and excluded cohorts.
     *
     * @param int[] $current
     * @param int[] $excluded
     * @return base
     */
    private function create_source(array $current, array $excluded): base {
        return new class ($current, $excluded) extends base {
            /**
             * Constructor.
             *
             * @param int[] $current
             * @param int[] $excluded
             */
            public function __construct(
                /** @var int[] current cohort ids */
                private array $current,
                /** @var int[] excluded cohort ids */
                private array $excluded
            ) {
            }

            #[\Override]
            public function get_args(): array {
                return [];
            }

            #[\Override]
            protected function get_context(): \core\context {
                return \core\context\system::instance();
            }

            #[\Override]
            protected function get_current_cohortids(): array {
                return $this->current;
            }

            #[\Override]
            protected function get_excluded_cohortids(): array {
                return $this->excluded;
            }
        };
    }

    public function test_search(): void {
        $generator = $this->getDataGenerator();
        $syscontext = \core\context\system::instance();
        $cat1 = $generator->create_category(['name' => 'Faculty <b>A</b>']);
        $cat2 = $generator->create_category(['name' => 'Department', 'parent' => $cat1->id]);
        $cat3 = $generator->create_category(['name' => 'Other faculty']);
        $generator->create_category(['name' => 'Empty']);
        $ctx1 = \core\context\coursecat::instance($cat1->id);
        $ctx2 = \core\context\coursecat::instance($cat2->id);
        $ctx3 = \core\context\coursecat::instance($cat3->id);

        $site = $generator->create_cohort(['name' => 'Site <i>students</i>', 'idnumber' => 'S1']);
        $dept2 = $generator->create_cohort(['name' => 'Dept teachers', 'contextid' => $ctx2->id]);
        $dept1 = $generator->create_cohort(['name' => 'Dept students', 'contextid' => $ctx2->id, 'description' => 'Some staff']);
        $other = $generator->create_cohort(['name' => 'Other staff', 'contextid' => $ctx3->id]);
        $hidden = $generator->create_cohort(['name' => 'Hidden staff', 'contextid' => $ctx1->id, 'visible' => 0]);

        $this->setAdminUser();
        $source = new context_cohorts($syscontext->id);
        $this->assertSame([$syscontext->id], $source->get_args());

        $result = $source->search('', []);
        $this->assertSame(5, $result['total']);
        $this->assertFalse($result['overflow']);
        $this->assertSame([
            ['id' => (int)$syscontext->id, 'name' => 'System', 'depth' => 0, 'parentid' => null],
            ['id' => (int)$ctx1->id, 'name' => 'Faculty A', 'depth' => 1, 'parentid' => (int)$syscontext->id],
            ['id' => (int)$ctx2->id, 'name' => 'Department', 'depth' => 2, 'parentid' => (int)$ctx1->id],
            ['id' => (int)$ctx3->id, 'name' => 'Other faculty', 'depth' => 1, 'parentid' => (int)$syscontext->id],
        ], $result['nodes']);
        $this->assertSame(
            ['Dept students', 'Dept teachers', 'Hidden staff', 'Other staff', 'Site students'],
            array_column($result['cohorts'], 'name')
        );
        $this->assertSame([
            'id' => (int)$site->id,
            'name' => 'Site students',
            'idnumber' => 'S1',
            'nodeid' => (int)$syscontext->id,
            'label' => 'Site students',
        ], $result['cohorts'][4]);

        // Only branches with found cohorts are returned, parents included.
        $result = $source->search('staff', [(string)$other->id]);
        $this->assertSame(2, $result['total']);
        $this->assertSame([(int)$dept1->id, (int)$hidden->id], array_column($result['cohorts'], 'id'));
        $this->assertSame([(int)$syscontext->id, (int)$ctx1->id, (int)$ctx2->id], array_column($result['nodes'], 'id'));
        $result = $source->search('s1', []);
        $this->assertSame([(int)$site->id], array_column($result['cohorts'], 'id'));
        $this->assertSame([(int)$syscontext->id], array_column($result['nodes'], 'id'));
        $result = $source->search('nothing', []);
        $this->assertSame(['nodes' => [], 'cohorts' => [], 'total' => 0, 'overflow' => false], $result);

        // Cohorts that may not be used are not offered.
        $user = $generator->create_user();
        $this->setUser($user);
        $source = new context_cohorts($syscontext->id);
        $result = $source->search('staff', []);
        $this->assertSame([(int)$dept1->id, (int)$other->id], array_column($result['cohorts'], 'id'));
        $roleid = $generator->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $ctx1);
        role_assign($roleid, $user->id, $ctx1);
        $result = $source->search('staff', []);
        $this->assertSame([(int)$dept1->id, (int)$hidden->id, (int)$other->id], array_column($result['cohorts'], 'id'));

        // Excluded cohorts of the source.
        $source = $this->create_source([], [(int)$dept1->id]);
        $result = $source->search('staff', [(string)$other->id, 'x', '0']);
        $this->assertSame([(int)$hidden->id], array_column($result['cohorts'], 'id'));

        $this->setGuestUser();
        try {
            new context_cohorts($syscontext->id);
            $this->fail('Exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\core\exception\require_login_exception::class, $ex);
        }
    }

    public function test_overflow(): void {
        global $DB;

        $records = [];
        for ($i = 1; $i <= base::MAXCOHORTS + 5; $i++) {
            $records[] = [
                'contextid' => \core\context\system::instance()->id,
                'name' => sprintf('Cohort %04d', $i),
                'descriptionformat' => FORMAT_HTML,
                'visible' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
        }
        $DB->insert_records('cohort', $records);

        $this->setAdminUser();
        $source = new context_cohorts(\core\context\system::instance()->id);
        $result = $source->search('', []);
        $this->assertSame(base::MAXCOHORTS + 5, $result['total']);
        $this->assertTrue($result['overflow']);
        $this->assertCount(base::MAXCOHORTS, $result['cohorts']);
        $this->assertSame('Cohort 0001', $result['cohorts'][0]['name']);
        $result = $source->search('Cohort 020', []);
        $this->assertSame(6, $result['total']);
        $this->assertFalse($result['overflow']);
    }

    public function test_labels(): void {
        $generator = $this->getDataGenerator();
        $cohort1 = $generator->create_cohort(['name' => 'Cohort <b>1</b>']);
        $cohort2 = $generator->create_cohort(['name' => 'Cohort 2', 'visible' => 0]);
        $cohort3 = $generator->create_cohort(['name' => 'Cohort 3']);
        $id1 = (string)$cohort1->id;
        $id2 = (string)$cohort2->id;
        $id3 = (string)$cohort3->id;

        $this->setUser($generator->create_user());
        $source = $this->create_source([], []);
        $this->assertSame([$id3 => 'Cohort 3', $id1 => 'Cohort 1'], $source->labels([$id3, $id2, 'x', '0', $id1, $id1 . 'x']));
        $this->assertSame([], $source->labels([]));
        $this->assertSame([], $source->validate([$id1]));

        // Current cohorts stay valid, excluded cohorts never are.
        $source = $this->create_source([(int)$cohort2->id, (int)$cohort3->id], [(int)$cohort3->id]);
        $this->assertSame([$id1 => 'Cohort 1', $id2 => 'Cohort 2'], $source->labels([$id1, $id2, $id3]));
        $this->assertTrue($source->are_allowed([$id1, $id2]));
        $this->assertTrue($source->are_allowed([]));
        $this->assertFalse($source->are_allowed([$id1, $id3]));
        $this->assertFalse($source->are_allowed([$id1, 'x']));

        $panel = $source->export_panel(true);
        $this->assertSame('tool_mulib/muform/cohortpicker/panel', $panel['panel']);
        $this->assertSame('Select cohorts', $panel['strings']['title']);
        $this->assertSame('More than 200 cohorts found, search to narrow them down.', $panel['strings']['overflow']);
        $this->assertSame('Select cohort', $source->export_panel(false)['strings']['title']);
    }

    public function test_restriction(): void {
        $generator = $this->getDataGenerator();
        $cohort1 = $generator->create_cohort(['name' => 'Cohort 1', 'idnumber' => 'pick']);
        $cohort2 = $generator->create_cohort(['name' => 'Cohort 2']);
        $cohort3 = $generator->create_cohort(['name' => 'Cohort 3']);
        $all = [(string)$cohort1->id, (string)$cohort2->id, (string)$cohort3->id];

        $this->setAdminUser();
        $source = new class ((int)$cohort3->id) extends base {
            /**
             * Constructor.
             *
             * @param int $currentid
             */
            public function __construct(
                /** @var int current cohort id */
                private int $currentid
            ) {
            }

            #[\Override]
            public function get_args(): array {
                return [$this->currentid];
            }

            #[\Override]
            protected function get_context(): \core\context {
                return \core\context\system::instance();
            }

            #[\Override]
            protected function get_current_cohortids(): array {
                return [$this->currentid];
            }

            #[\Override]
            protected function get_restriction_sql(): ?\tool_mulib\local\sql {
                return new \tool_mulib\local\sql("ch.idnumber = ? OR ch.idnumber = ?", ['pick', 'other']);
            }
        };

        $result = $source->search('', []);
        $this->assertSame([(int)$cohort1->id], array_column($result['cohorts'], 'id'));
        $this->assertSame(1, $result['total']);
        $this->assertSame([], $source->search('Cohort 2', [])['cohorts']);
        $labels = $source->labels($all);
        $this->assertSame([(string)$cohort1->id, (string)$cohort3->id], array_map('strval', array_keys($labels)));
    }

    public function test_tenants(): void {
        if (!\tool_mulib\local\mulib::is_mutenancy_available()) {
            $this->markTestSkipped('multi-tenancy not available');
        }
        \tool_mutenancy\local\tenancy::activate();
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();
        $ctx1 = \core\context\coursecat::instance($tenant1->categoryid);
        $ctx2 = \core\context\coursecat::instance($tenant2->categoryid);
        $global = $this->getDataGenerator()->create_cohort(['name' => 'Test global']);
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Test one', 'contextid' => $ctx1->id]);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Test two', 'contextid' => $ctx2->id]);

        $this->setAdminUser();
        $source = new context_cohorts($ctx1->id);
        $result = $source->search('Test', []);
        $this->assertSame([(int)$global->id, (int)$cohort1->id], array_column($result['cohorts'], 'id'));
        $labels = $source->labels([(string)$global->id, (string)$cohort1->id, (string)$cohort2->id]);
        $this->assertSame([(string)$global->id, (string)$cohort1->id], array_map('strval', array_keys($labels)));

        $source = new context_cohorts(\core\context\system::instance()->id);
        $result = $source->search('Test', []);
        $this->assertSame([(int)$global->id, (int)$cohort1->id, (int)$cohort2->id], array_column($result['cohorts'], 'id'));
    }
}
